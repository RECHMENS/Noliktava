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
    <link rel="stylesheet" href="dashboard.css?v=11">
    <link rel="stylesheet" href="popup.css?v=11">
</head>
<body>
    <?php renderHeader($user, 'Worker'); ?>

    <div class="layout">
        <nav class="menu" aria-label="Worker sections">
            <button type="button" class="menu-item active" data-section="products">Products</button>
            <button type="button" class="menu-item" data-section="shipments">Shipments</button>
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

    <script src="popup.js?v=11"></script>
    <script src="error.js?v=11"></script>
    <script src="products.js?v=11"></script>
    <script src="shipments.js?v=11"></script>
    <script src="activitylog.js?v=11"></script>
    <script>
        initProducts(document.getElementById("productRows"), false);
        initShipments(document.getElementById("shipments"));
        const activityLog = initActivityLog(document.getElementById("activityLog"));

        // Reload the log whenever its menu section is opened
        document.addEventListener("sectionchange", function (e) {
            if (e.detail === "activity-log") activityLog.reload();
        });
    </script>
    <script src="menu.js?v=11"></script>
</body>
</html>