<?php
require_once 'php_backend/auth.php';
$user = guardPage(['sorter', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse – Sorter</title>
    <link rel="stylesheet" href="dashboard.css?v=11">
    <link rel="stylesheet" href="popup.css?v=11">
</head>
<body>
    <?php renderHeader($user, 'Sorter'); ?>

    <main class="page">
        <section class="panel">
            <h2>Sorting</h2>
            <p class="muted"></p>
        </section>
    </main>

    <script src="popup.js?v=11"></script>
    <script src="error.js?v=11"></script>
</body>
</html>