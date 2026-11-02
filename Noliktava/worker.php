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
    <link rel="stylesheet" href="dashboard.css?v=12">
    <link rel="stylesheet" href="popup.css?v=12">
</head>
<body>
    <?php renderHeader($user, 'Worker'); ?>

    <div class="layout">
        <nav class="menu" aria-label="Worker sections">
            <button type="button" class="menu-item" data-section="shipments">Shipments</button>
            <button type="button" class="menu-item active" data-section="products">Products</button>
            <button type="button" class="menu-item" data-section="activity-log">Activity log</button>
        </nav>

        <main class="page">
            <section class="panel section active" id="section-products">
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

            <section class="panel section" id="section-shipments">
                <h2>Shipments</h2>
                <div id="shipments"></div>
            </section>

            <section class="panel section" id="section-activity-log">
                <h2>Activity log</h2>
                <div id="activityLog"></div>
            </section>
        </main>
    </div>

    <script src="popup.js"></script>
    <script src="error.js"></script>
    <script src="products.js"></script>
    <script src="shipments.js"></script>
    <script src="activitylog.js"></script>
    <script>
        initProducts(document.getElementById("productRows"), false);
        initShipments(document.getElementById("shipments"));
        const activityLog = initActivityLog(document.getElementById("activityLog"));

        document.addEventListener("sectionchange", function (e) {
            if (e.detail === "activity-log") activityLog.reload();
        });
    </script>
    <script src="menu.js?v=12"></script>
</body>
</html>