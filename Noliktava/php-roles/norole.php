<?php
session_start();
require_once __DIR__ . '/../php_backend/connection.php';

header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$stmt = $conn->prepare('SELECT loma, aktivs FROM lietotaju_registrs WHERE lietotaji_id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !(int)$user['aktivs']) {
    $_SESSION = [];
    session_destroy();
    header('Location: ../login.html');
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
    <title>Warehouse</title>
    <style>
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            padding: 20px;
            background: #5da1ce;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 22px;
            text-align: center;
        }
        button {
            height: 44px;
            padding: 0 24px;
            border: none;
            border-radius: 12px;
            background: #3c6a96;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #4c86bd; }
    </style>
</head>
<body>
    <p>Please contact an administrator.</p>
    <button type="button" id="backBtn">Back to login</button>

    <script>
        document.getElementById("backBtn").addEventListener("click", async function () {
            try {
                await fetch("../php_backend/api.php?action=logout");
            } catch (e) {}
            window.location.href = "../login.html";
        });
    </script>
</body>
</html>