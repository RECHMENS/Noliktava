<?php

session_start();
require_once __DIR__ . '/connection.php';

function guardPage(array $allowedRoles): array {
    global $conn;

    header('Cache-Control: no-store');

    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }

    $stmt = $conn->prepare(
        'SELECT lietotaji_id, `lietotajvārds` AS username, loma, aktivs
         FROM lietotaju_registrs WHERE lietotaji_id = ?'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !(int)$user['aktivs']) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.html');
        exit;
    }

    $_SESSION['role'] = $user['loma'];

    if (!in_array($user['loma'], $allowedRoles, true)) {
        $home = ['admin' => 'admin.php', 'worker' => 'worker.php', 'sorter' => 'sorter.php'];
        if (isset($home[$user['loma']])) {
            header('Location: ' . $home[$user['loma']]);
            exit;
        }
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    return $user;
}

function renderHeader(array $user, string $title): void {
    $name = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
    $role = htmlspecialchars($user['loma'], ENT_QUOTES, 'UTF-8');
    $t    = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<header class="topbar">
    <h1>{$t}</h1>
    <div class="who">
        <img src="php_backend/profile_picture.php" alt="" class="avatar" onerror="this.style.visibility='hidden'">
        <span>{$name} <small>({$role})</small></span>
        <button type="button" id="logoutBtn" class="btn">Log out</button>
    </div>
</header>
HTML;
}
