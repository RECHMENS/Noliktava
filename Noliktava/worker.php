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
            <h2>Products</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Name</th><th>SKU</th><th>Quantity</th><th>Location</th><th></th></tr>
                    </thead>
                    <tbody id="productRows">
                        <tr><td colspan="5">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="error.js"></script>
    <script src="products.js"></script>
    <script>initProducts(document.getElementById("productRows"), false);</script>
</body>
</html>