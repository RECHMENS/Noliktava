<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/logger.php';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    echo "Lūdzu aizpildi visus laukus!";
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT lietotaji_id, `lietotajvārds` AS username, parole, loma, aktivs
         FROM lietotaju_registrs WHERE Epasts = :email"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['parole'])) {
        if (!(int)$user['aktivs']) {
            logAction($conn, (int)$user['lietotaji_id'], 'login_blocked', 'Deactivated account tried to log in');
            echo "Konts ir deaktivizēts. Sazinies ar administratoru.";
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['lietotaji_id'];
        $_SESSION['role']    = $user['loma'];

        logAction($conn, (int)$user['lietotaji_id'], 'login', 'Role: ' . $user['loma']);
        echo "OK:" . $user['loma'];
        exit;
    }

    if ($user) {
        logAction($conn, (int)$user['lietotaji_id'], 'login_failed', 'Wrong password');
    } else {
        logAction($conn, null, 'login_failed', 'Unknown e-mail: ' . $email);
    }
    echo "Nepareizs E-pasts vai parole!";

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Sistēmas kļūda. Mēģini vēlreiz.";
}