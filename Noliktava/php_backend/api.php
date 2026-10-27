<?php
session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/logger.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

const ROLES = ['admin', 'worker', 'sorter'];

function respond($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireUser(): int {
    global $conn;
    if (empty($_SESSION['user_id'])) {
        respond(['error' => 'Please log in again.'], 401);
    }
    $stmt = $conn->prepare('SELECT loma, aktivs FROM lietotaju_registrs WHERE lietotaji_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !(int)$row['aktivs']) {
        $_SESSION = [];
        session_destroy();
        respond(['error' => 'Please log in again.'], 401);
    }
    $_SESSION['role'] = $row['loma'];
    return (int)$_SESSION['user_id'];
}

function requireRole(array $roles): int {
    $uid = requireUser();
    if (!in_array($_SESSION['role'], $roles, true)) {
        respond(['error' => 'Access denied.'], 403);
    }
    return $uid;
}

function input(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

function getUser(PDO $conn, int $id): array {
    $stmt = $conn->prepare(
        'SELECT lietotaji_id, `lietotajvārds`, Epasts, loma, profila_bilde
         FROM lietotaju_registrs WHERE lietotaji_id = ?'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(['error' => 'Account not found.'], 404);
    }

    $image = $user['profila_bilde'];
    unset($user['profila_bilde']);

    $user['role'] = $user['loma'];
    $user['picture'] = null;
    if ($image) {
        $info = @getimagesizefromstring($image);
        $mime = $info ? $info['mime'] : 'image/jpeg';
        $user['picture'] = 'data:' . $mime . ';base64,' . base64_encode($image);
    }
    return $user;
}

function countActiveAdmins(PDO $conn): int {
    return (int)$conn->query(
        "SELECT COUNT(*) FROM lietotaju_registrs WHERE loma = 'admin' AND aktivs = 1"
    )->fetchColumn();
}

try {
    $action = $_GET['action'] ?? '';
    $data   = input();

    if ($action === 'me') {
        $uid = requireUser();
        respond(['user' => getUser($conn, $uid)]);
    }

    if ($action === 'logout') {
        if (!empty($_SESSION['user_id'])) {
            logAction($conn, (int)$_SESSION['user_id'], 'logout');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        respond(['ok' => true, 'redirect' => '../login.html']);
    }

    if ($action === 'csrf') {
        requireUser();
        if (empty($_SESSION['delete_csrf'])) {
            $_SESSION['delete_csrf'] = bin2hex(random_bytes(32));
        }
        respond(['csrf' => $_SESSION['delete_csrf']]);
    }

    if ($action === 'profile') {
        $uid = requireUser();
        $username = trim($data['username'] ?? '');

        if ($username === '' || mb_strlen($username) > 100) {
            respond(['error' => 'Enter a username up to 100 characters.'], 400);
        }

        $stmt = $conn->prepare(
            'SELECT lietotaji_id FROM lietotaju_registrs
             WHERE `lietotajvārds` = ? AND lietotaji_id <> ?'
        );
        $stmt->execute([$username, $uid]);
        if ($stmt->fetch()) {
            respond(['error' => 'That username is already taken.'], 409);
        }

        $image = null;
        if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profilePicture'];

            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
                respond(['error' => 'Image must be smaller than 3 MB.'], 400);
            }
            $info = getimagesize($file['tmp_name']);
            if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                respond(['error' => 'Choose a JPEG, PNG, or WebP image.'], 400);
            }
            $image = file_get_contents($file['tmp_name']);
        }

        $stmt = $conn->prepare('UPDATE lietotaju_registrs SET `lietotajvārds` = ? WHERE lietotaji_id = ?');
        $stmt->execute([$username, $uid]);

        if ($image !== null) {
            $stmt = $conn->prepare('UPDATE lietotaju_registrs SET profila_bilde = ? WHERE lietotaji_id = ?');
            $stmt->bindValue(1, $image, PDO::PARAM_LOB);
            $stmt->bindValue(2, $uid, PDO::PARAM_INT);
            $stmt->execute();
        }

        $what = 'username: ' . $username;
        if ($image !== null) {
            $what .= '; profile picture changed';
        }
        logAction($conn, $uid, 'profile_update', $what);
        respond(['ok' => true, 'user' => getUser($conn, $uid)]);
    }

    if ($action === 'list-users') {
        requireRole(['admin']);
        $stmt = $conn->query(
            'SELECT lietotaji_id, `lietotajvārds` AS username, Epasts AS email, loma AS role, aktivs AS active
             FROM lietotaju_registrs ORDER BY `lietotajvārds`'
        );
        respond(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'add-user') {
        $uid = requireRole(['admin']);
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role     = $data['role'] ?? '';

        if ($username === '' || mb_strlen($username) > 100
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 8
            || !in_array($role, ROLES, true)) {
            respond(['error' => 'Invalid data. Password needs at least 8 characters.'], 400);
        }

        $stmt = $conn->prepare('SELECT 1 FROM lietotaju_registrs WHERE Epasts = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            respond(['error' => 'E-mail already registered.'], 409);
        }

        $stmt = $conn->prepare(
            'INSERT INTO lietotaju_registrs (`lietotajvārds`, Epasts, parole, loma, aktivs)
             VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        logAction($conn, $uid, 'user_add', "$username ($email), role: $role");
        respond(['ok' => true, 'message' => 'User created.']);
    }

    if ($action === 'set-role') {
        $uid = requireRole(['admin']);
        $target = filter_var($data['userId'] ?? null, FILTER_VALIDATE_INT);
        $role   = $data['role'] ?? '';

        if (!$target || !in_array($role, ROLES, true)) {
            respond(['error' => 'Invalid data.'], 400);
        }
        if ($target === $uid) {
            respond(['error' => 'You cannot change your own role.'], 400);
        }
        $stmt = $conn->prepare('SELECT `lietotajvārds` AS username, loma FROM lietotaju_registrs WHERE lietotaji_id = ?');
        $stmt->execute([$target]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            respond(['error' => 'User not found.'], 404);
        }

        $stmt = $conn->prepare('UPDATE lietotaju_registrs SET loma = ? WHERE lietotaji_id = ?');
        $stmt->execute([$role, $target]);
        logAction($conn, $uid, 'role_change', $old['username'] . ': ' . $old['loma'] . ' → ' . $role);
        respond(['ok' => true]);
    }

    if ($action === 'set-active') {
        $uid = requireRole(['admin']);
        $target = filter_var($data['userId'] ?? null, FILTER_VALIDATE_INT);
        $active = !empty($data['active']) ? 1 : 0;

        if (!$target) {
            respond(['error' => 'Invalid data.'], 400);
        }
        if ($target === $uid) {
            respond(['error' => 'You cannot deactivate yourself.'], 400);
        }
        $stmt = $conn->prepare('SELECT `lietotajvārds` AS username FROM lietotaju_registrs WHERE lietotaji_id = ?');
        $stmt->execute([$target]);
        $targetName = $stmt->fetchColumn();
        if ($targetName === false) {
            respond(['error' => 'User not found.'], 404);
        }

        $stmt = $conn->prepare('UPDATE lietotaju_registrs SET aktivs = ? WHERE lietotaji_id = ?');
        $stmt->execute([$active, $target]);
        logAction($conn, $uid, 'active_change', $targetName . ': ' . ($active ? 'activated' : 'deactivated'));
        respond(['ok' => true]);
    }

    if ($action === 'delete-account') {
        $uid = requireUser();
        if (empty($_SESSION['delete_csrf']) ||
            !hash_equals($_SESSION['delete_csrf'], (string)($data['csrf'] ?? ''))) {
            respond(['error' => 'Security check failed. Refresh and try again.'], 403);
        }
        if ($_SESSION['role'] === 'admin' && countActiveAdmins($conn) <= 1) {
            respond(['error' => 'You are the last admin and cannot delete this account.'], 400);
        }

        logAction($conn, $uid, 'account_delete', 'Account deleted by the user');
        $stmt = $conn->prepare('DELETE FROM lietotaju_registrs WHERE lietotaji_id = ?');
        $stmt->execute([$uid]);

        $_SESSION = [];
        session_destroy();
        respond(['ok' => true, 'redirect' => '../login.html']);
    }

    if ($action === 'list-products') {
        requireRole(['admin', 'worker', 'sorter']);
        $stmt = $conn->query(
            'SELECT produkta_id AS id, nosaukums AS name, sku, daudzums AS quantity, vieta AS location
             FROM produkti ORDER BY nosaukums'
        );
        respond(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'add-product') {
        $uid = requireRole(['admin']);
        $name     = trim($data['name'] ?? '');
        $sku      = trim($data['sku'] ?? '');
        $quantity = filter_var($data['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $location = trim($data['location'] ?? '');

        if ($name === '' || mb_strlen($name) > 150
            || $sku === '' || mb_strlen($sku) > 50
            || $quantity === false || $quantity < 0
            || mb_strlen($location) > 100) {
            respond(['error' => 'Invalid data. Name and SKU are required, quantity must be 0 or more.'], 400);
        }

        $stmt = $conn->prepare('SELECT 1 FROM produkti WHERE sku = ?');
        $stmt->execute([$sku]);
        if ($stmt->fetch()) {
            respond(['error' => 'SKU already exists.'], 409);
        }

        $stmt = $conn->prepare(
            'INSERT INTO produkti (nosaukums, sku, daudzums, vieta) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $sku, $quantity, $location === '' ? null : $location]);
        logAction($conn, $uid, 'product_add', "$name (SKU $sku), quantity: $quantity" . ($location !== '' ? ", location: $location" : ''));
        respond(['ok' => true, 'message' => 'Product added.']);
    }

    if ($action === 'update-product') {
        $uid = requireRole(['admin', 'worker']);
        $id       = filter_var($data['productId'] ?? null, FILTER_VALIDATE_INT);
        $name     = trim($data['name'] ?? '');
        $sku      = trim($data['sku'] ?? '');
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
        $location = trim($data['location'] ?? '');

        if (!$id || $name === '' || mb_strlen($name) > 150
            || $sku === '' || mb_strlen($sku) > 50
            || $quantity === false || $quantity === null || $quantity < 0
            || mb_strlen($location) > 100) {
            respond(['error' => 'Invalid data. Name and SKU are required, quantity must be 0 or more.'], 400);
        }

        $stmt = $conn->prepare('SELECT 1 FROM produkti WHERE sku = ? AND produkta_id <> ?');
        $stmt->execute([$sku, $id]);
        if ($stmt->fetch()) {
            respond(['error' => 'SKU already used by another product.'], 409);
        }

        $stmt = $conn->prepare('SELECT nosaukums, sku, daudzums, vieta FROM produkti WHERE produkta_id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) {
            respond(['error' => 'Product not found.'], 404);
        }

        $stmt = $conn->prepare(
            'UPDATE produkti SET nosaukums = ?, sku = ?, daudzums = ?, vieta = ? WHERE produkta_id = ?'
        );
        $stmt->execute([$name, $sku, $quantity, $location === '' ? null : $location, $id]);

        $changes = [];
        if ($old['nosaukums'] !== $name)        { $changes[] = 'name: ' . $old['nosaukums'] . ' → ' . $name; }
        if ($old['sku'] !== $sku)               { $changes[] = 'SKU: ' . $old['sku'] . ' → ' . $sku; }
        if ((int)$old['daudzums'] !== $quantity) { $changes[] = 'quantity: ' . $old['daudzums'] . ' → ' . $quantity; }
        if (($old['vieta'] ?? '') !== $location) { $changes[] = 'location: ' . (($old['vieta'] ?? '') === '' ? '—' : $old['vieta']) . ' → ' . ($location === '' ? '—' : $location); }
        if ($changes) {
            logAction($conn, $uid, 'product_update', $old['nosaukums'] . ' (SKU ' . $old['sku'] . '): ' . implode('; ', $changes));
        }
        respond(['ok' => true]);
    }

    if ($action === 'delete-product') {
        $uid = requireRole(['admin']);
        $id = filter_var($data['productId'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            respond(['error' => 'Invalid data.'], 400);
        }
        $stmt = $conn->prepare('SELECT nosaukums, sku FROM produkti WHERE produkta_id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare('DELETE FROM produkti WHERE produkta_id = ?');
        $stmt->execute([$id]);
        if ($old) {
            logAction($conn, $uid, 'product_delete', $old['nosaukums'] . ' (SKU ' . $old['sku'] . ')');
        }
        respond(['ok' => true]);
    }

    if ($action === 'list-logs') {
        requireRole(['admin']);
        $where  = [];
        $params = [];

        $filter = trim((string)($data['filter'] ?? ''));
        $search = trim((string)($data['search'] ?? ''));

        if ($filter !== '') {
            $where[]  = 'darbiba = ?';
            $params[] = $filter;
        }
        if ($search !== '') {
            $where[]  = '(`lietotajvards` LIKE ? OR detalas LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $limit = min(500, max(1, (int)($data['limit'] ?? 200)));

        $sql = 'SELECT zurnala_id AS id, `lietotajvards` AS username, darbiba AS action,
                       detalas AS details, ip, izveidots AS created
                FROM zurnals'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY zurnala_id DESC LIMIT ' . $limit;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        respond(['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    respond(['error' => 'Unknown action.'], 404);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log($e->getMessage());
    respond(['error' => 'A server error occurred. Check the PHP error log.'], 500);
}