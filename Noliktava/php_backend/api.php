<?php
session_start();
require_once __DIR__ . '/connection.php';

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
        requireRole(['admin']);
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
        $stmt = $conn->prepare('UPDATE lietotaju_registrs SET loma = ? WHERE lietotaji_id = ?');
        $stmt->execute([$role, $target]);
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
        $stmt = $conn->prepare('UPDATE lietotaju_registrs SET aktivs = ? WHERE lietotaji_id = ?');
        $stmt->execute([$active, $target]);
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

        $stmt = $conn->prepare('DELETE FROM lietotaju_registrs WHERE lietotaji_id = ?');
        $stmt->execute([$uid]);

        $_SESSION = [];
        session_destroy();
        respond(['ok' => true, 'redirect' => '../login.html']);
    }

    respond(['error' => 'Unknown action.'], 404);

} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log($e->getMessage());
    respond(['error' => 'A server error occurred. Check the PHP error log.'], 500);
}