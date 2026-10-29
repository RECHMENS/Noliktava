<?php
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/logger.php';

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $email === '' || $password === '') {
    echo "Lūdzu aizpildi visus laukus!";
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Nederīgs e-pasts!";
    exit;
}

if (strlen($password) < 8) {
    echo "Parolei jābūt vismaz 8 simbolus garai!";
    exit;
}

$imageData = null;
if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['profilePicture'];

    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
        echo "Attēlam jābūt mazākam par 3 MB!";
        exit;
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        echo "Izvēlies JPEG, PNG vai WebP attēlu!";
        exit;
    }
    $imageData = file_get_contents($file['tmp_name']);
}

try {
    $check = $conn->prepare("SELECT Epasts FROM lietotaju_registrs WHERE Epasts = :email");
    $check->execute([':email' => $email]);

    if ($check->fetch()) {
        echo "E-pasts jau ir reģistrēts sistēmā!";
        exit;
    }

    $insert = $conn->prepare(
        "INSERT INTO lietotaju_registrs (`lietotajvārds`, Epasts, parole, profila_bilde, loma, aktivs)
         VALUES (:username, :email, :password, :image, 'worker', 1)"
    );
    $insert->bindValue(':username', $username);
    $insert->bindValue(':email', $email);
    $insert->bindValue(':password', password_hash($password, PASSWORD_DEFAULT));
    $insert->bindValue(':image', $imageData, $imageData === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $insert->execute();

    logAction($conn, (int)$conn->lastInsertId(), 'register', "$username ($email) registered themselves");

    echo "Konts veiksmīgi izveidots!";

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Sistēmas kļūda. Mēģini vēlreiz.";
}