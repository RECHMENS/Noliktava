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

function shipmentRef(int $id): string {
    return 'SH-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function logQuery(string $filter, string $search, int $limit): array {
    $where  = [];
    $params = [];

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

    $sql = 'SELECT zurnala_id AS id, `lietotajvards` AS username, darbiba AS action,
                   detalas AS details, ip, izveidots AS created
            FROM zurnals'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY zurnala_id DESC LIMIT ' . $limit;

    return [$sql, $params];
}

function csvSafe($value): string {
    $text = (string)$value;
    if ($text !== '' && strpos("=+-@\t\r", $text[0]) !== false) {
        return "'" . $text;
    }
    return $text;
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

    if ($action === 'list-shipments') {
        requireRole(['admin', 'worker']);
        $status = trim((string)($data['status'] ?? ''));

        $sql = 'SELECT sutijuma_id AS id, galamerkis AS destination, piezimes AS notes,
                       statuss AS status, izveidoja_vards AS created_by, izveidots AS created
                FROM sutijumi';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE statuss = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY sutijuma_id DESC LIMIT 200';

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $index = [];
        foreach ($shipments as $i => $row) {
            $shipments[$i]['reference'] = shipmentRef((int)$row['id']);
            $shipments[$i]['items'] = [];
            $index[(int)$row['id']] = $i;
        }

        if ($index) {
            $in = implode(',', array_fill(0, count($index), '?'));
            $stmt = $conn->prepare(
                "SELECT sutijuma_id, nosaukums AS name, sku, daudzums AS quantity
                 FROM sutijumu_rindas WHERE sutijuma_id IN ($in) ORDER BY rinda_id"
            );
            $stmt->execute(array_keys($index));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
                $shipments[$index[(int)$line['sutijuma_id']]]['items'][] = [
                    'name'     => $line['name'],
                    'sku'      => $line['sku'],
                    'quantity' => (int)$line['quantity']
                ];
            }
        }
        respond(['shipments' => $shipments]);
    }

    if ($action === 'create-shipment') {
        $uid = requireRole(['admin', 'worker']);
        $destination = trim((string)($data['destination'] ?? ''));
        $notes       = trim((string)($data['notes'] ?? ''));
        $rawItems    = $data['items'] ?? null;

        if ($destination === '' || mb_strlen($destination) > 150 || mb_strlen($notes) > 500
            || !is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 50) {
            respond(['error' => 'Enter a destination and add at least one product.'], 400);
        }

        $wanted = [];
        foreach ($rawItems as $it) {
            $pid = is_array($it) ? filter_var($it['productId'] ?? null, FILTER_VALIDATE_INT) : false;
            $qty = is_array($it) ? filter_var($it['quantity'] ?? null, FILTER_VALIDATE_INT) : false;
            if (!$pid || $pid < 1 || $qty === false || $qty === null || $qty < 1) {
                respond(['error' => 'Each item needs a product and a quantity of at least 1.'], 400);
            }
            $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
        }
        ksort($wanted); // same lock order every time avoids deadlocks

        $stmt = $conn->prepare('SELECT `lietotajvārds` FROM lietotaju_registrs WHERE lietotaji_id = ?');
        $stmt->execute([$uid]);
        $creatorName = $stmt->fetchColumn() ?: null;

        $conn->beginTransaction();

        $lines = [];
        foreach ($wanted as $pid => $qty) {
            $stmt = $conn->prepare('SELECT nosaukums, sku, daudzums FROM produkti WHERE produkta_id = ? FOR UPDATE');
            $stmt->execute([$pid]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$p) {
                $conn->rollBack();
                respond(['error' => 'A selected product no longer exists.'], 404);
            }
            if ((int)$p['daudzums'] < $qty) {
                $conn->rollBack();
                respond(['error' => 'Not enough stock for ' . $p['nosaukums'] . ' (available: ' . $p['daudzums'] . ').'], 409);
            }
            $lines[] = ['id' => $pid, 'name' => $p['nosaukums'], 'sku' => $p['sku'], 'qty' => $qty];
        }

        $stmt = $conn->prepare(
            'INSERT INTO sutijumi (galamerkis, piezimes, statuss, izveidoja, izveidoja_vards)
             VALUES (?, ?, \'pending\', ?, ?)'
        );
        $stmt->execute([$destination, $notes === '' ? null : $notes, $uid, $creatorName]);
        $shipmentId = (int)$conn->lastInsertId();

        $insertLine = $conn->prepare(
            'INSERT INTO sutijumu_rindas (sutijuma_id, produkta_id, nosaukums, sku, daudzums) VALUES (?, ?, ?, ?, ?)'
        );
        $takeStock = $conn->prepare('UPDATE produkti SET daudzums = daudzums - ? WHERE produkta_id = ?');
        $summary = [];
        foreach ($lines as $l) {
            $insertLine->execute([$shipmentId, $l['id'], $l['name'], $l['sku'], $l['qty']]);
            $takeStock->execute([$l['qty'], $l['id']]);
            $summary[] = $l['name'] . ' ×' . $l['qty'];
        }
        $conn->commit();

        $ref = shipmentRef($shipmentId);
        logAction($conn, $uid, 'shipment_create', $ref . ' → ' . $destination . ': ' . implode(', ', $summary));
        respond(['ok' => true, 'id' => $shipmentId, 'reference' => $ref]);
    }

    if ($action === 'set-shipment-status') {
        $uid = requireRole(['admin', 'worker']);
        $id  = filter_var($data['shipmentId'] ?? null, FILTER_VALIDATE_INT);
        $new = (string)($data['status'] ?? '');

        $allowed = [
            'pending' => ['packed', 'cancelled'],
            'packed'  => ['shipped', 'cancelled'],
            'shipped' => ['delivered']
        ];
        if (!$id || !in_array($new, ['packed', 'shipped', 'delivered', 'cancelled'], true)) {
            respond(['error' => 'Invalid data.'], 400);
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare('SELECT statuss FROM sutijumi WHERE sutijuma_id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            $conn->rollBack();
            respond(['error' => 'Shipment not found.'], 404);
        }
        if (!in_array($new, $allowed[$current] ?? [], true)) {
            $conn->rollBack();
            respond(['error' => 'This shipment cannot be changed to that status.'], 400);
        }

        if ($new === 'cancelled') {
            $stmt = $conn->prepare('SELECT produkta_id, daudzums FROM sutijumu_rindas WHERE sutijuma_id = ?');
            $stmt->execute([$id]);
            $restore = $conn->prepare('UPDATE produkti SET daudzums = daudzums + ? WHERE produkta_id = ?');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
                if ($line['produkta_id'] !== null) {
                    $restore->execute([(int)$line['daudzums'], (int)$line['produkta_id']]);
                }
            }
        }

        $stmt = $conn->prepare('UPDATE sutijumi SET statuss = ? WHERE sutijuma_id = ?');
        $stmt->execute([$new, $id]);
        $conn->commit();

        logAction($conn, $uid, 'shipment_status', shipmentRef($id) . ': ' . $current . ' → ' . $new);
        respond(['ok' => true]);
    }

    if ($action === 'list-logs') {
        requireRole(['admin', 'worker']);
        $filter = trim((string)($data['filter'] ?? ''));
        $search = trim((string)($data['search'] ?? ''));
        $limit  = min(500, max(1, (int)($data['limit'] ?? 200)));

        [$sql, $params] = logQuery($filter, $search, $limit);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        respond(['logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'export-logs') {
        $uid    = requireRole(['admin', 'worker']);
        $filter = trim((string)($_GET['filter'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));

        [$sql, $params] = logQuery($filter, $search, 50000);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $labels = [
            'login' => 'Login', 'login_failed' => 'Failed login', 'login_blocked' => 'Blocked login',
            'logout' => 'Logout', 'register' => 'Registration', 'profile_update' => 'Profile update',
            'account_delete' => 'Account deleted', 'user_add' => 'User added', 'role_change' => 'Role changed',
            'active_change' => 'Active changed', 'product_add' => 'Product added',
            'product_update' => 'Product updated', 'product_delete' => 'Product deleted',
            'shipment_create' => 'Shipment created', 'shipment_status' => 'Shipment status',
            'log_export' => 'Log exported'
        ];

        logAction($conn, $uid, 'log_export', 'Filter: ' . ($filter === '' ? 'all' : $filter)
            . ($search === '' ? '' : ', search: ' . $search));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');

        $outFile = fopen('php://output', 'w');
        fwrite($outFile, "\xEF\xBB\xBF");
        fputcsv($outFile, ['Time', 'User', 'Action', 'Details', 'IP'], ',', '"', '\\');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($outFile, [
                csvSafe($row['created']),
                csvSafe($row['username']),
                csvSafe($labels[$row['action']] ?? $row['action']),
                csvSafe($row['details']),
                csvSafe($row['ip'])
            ], ',', '"', '\\');
        }
        fclose($outFile);
        exit;
    }

    respond(['error' => 'Unknown action.'], 404);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log($e->getMessage());
    respond(['error' => 'A server error occurred. Check the PHP error log.'], 500);
}