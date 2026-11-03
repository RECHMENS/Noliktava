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
    <link rel="stylesheet" href="dashboard.css?v=12">
    <link rel="stylesheet" href="popup.css?v=12">
</head>
<body>
    <?php renderHeader($user, 'Sorter'); ?>

    <div class="layout">
        <nav class="menu" aria-label="Sorter sections">
            <button type="button" class="menu-item" data-section="placement">Placement</button>
            <button type="button" class="menu-item active" data-section="products">Products</button>
            <button type="button" class="menu-item" data-section="history">Placement history</button>
            <button type="button" class="menu-item" data-section="reports">Reports</button>
        </nav>

        <main class="page">
            <section class="panel section active" id="section-products">
                <h2>Products</h2>
                <div id="productView"></div>
            </section>

            <section class="panel section" id="section-placement">
                <h2>Placement</h2>
                <div id="placement"></div>
            </section>

            <section class="panel section" id="section-history">
                <h2>Placement history</h2>
                <div id="placementHistory"></div>
            </section>

            <section class="panel section" id="section-reports">
                <h2>Reports</h2>
                <div id="reports"></div>
            </section>
        </main>
    </div>

    <script src="popup.js"></script>
    <script src="error.js"></script>
    <script src="sorter.js"></script>
    <script>
        const pages = {
            "products": initProductView(document.getElementById("productView")),
            "placement": initPlacement(document.getElementById("placement")),
            "history": initPlacementHistory(document.getElementById("placementHistory")),
            "reports": initReports(document.getElementById("reports"))
        };

        document.addEventListener("sectionchange", function (e) {
            if (pages[e.detail]) pages[e.detail].reload();
        });
    </script>
    <script src="menu.js"></script>
</body>
</html>