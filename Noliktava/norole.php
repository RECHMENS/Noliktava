<?php
session_start();
require_once __DIR__ . '/php_backend/connection.php';

header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$stmt = $conn->prepare('SELECT loma, aktivs FROM lietotaju_registrs WHERE lietotaji_id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !(int)$user['aktivs']) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.html');
    exit;
}

$home = ['admin' => 'admin.php', 'worker' => 'worker.php', 'sorter' => 'sorter.php'];
$role = (string)$user['loma'];
if (isset($home[$role])) {
    header('Location: ' . $home[$role]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse – No role</title>
    <link rel="stylesheet" href="dashboard.css?v=13">
</head>
<body class="center-page">
    <p>Please contact an administrator.</p>
    <button type="button" id="logoutBtn" class="btn">Log out</button>

    <script src="error.js"></script>
</body>
</html>