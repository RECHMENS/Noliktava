<?php
session_start();
require_once __DIR__ . '/connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT profila_bilde FROM lietotaju_registrs WHERE lietotaji_id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $image = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit;
}

if ($image === false || $image === null || $image === '') {
    http_response_code(404);
    exit;
}

$info = @getimagesizefromstring($image);
if ($info === false || strpos($info['mime'], 'image/') !== 0) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . strlen($image));
header('Cache-Control: no-store');
echo $image;