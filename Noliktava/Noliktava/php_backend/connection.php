<?php
$servername = "127.0.0.1";
$port       = "3306";
$username   = "root";
$password   = "";
$dbname     = "lietotaji";

try {
    $conn = new PDO(
        "mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo "Neizdevās savienoties ar datubāzi.";
    exit;
}