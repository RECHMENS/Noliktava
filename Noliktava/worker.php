<?php
require_once 'php_backend/auth.php';
$user = guardPage(['worker', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse – Worker</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <?php renderHeader($user, 'Worker'); ?>

    <main class="page">
        <section class="panel">
            <h2>Your tasks</h2>
            <p class="muted"></p>
        </section>
    </main>

    <script src="common.js"></script>
</body>
</html>
