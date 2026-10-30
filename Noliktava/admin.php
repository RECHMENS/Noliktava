<?php
require_once 'php_backend/auth.php';
$user = guardPage(['admin']);
$myId = (int)$user['lietotaji_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse – Admin</title>
    <link rel="stylesheet" href="dashboard.css?v=11">
    <link rel="stylesheet" href="popup.css?v=11">
</head>
<body data-user-id="<?= $myId ?>">
    <?php renderHeader($user, 'Admin panel'); ?>

    <div class="layout">
        <nav class="menu" aria-label="Admin sections">
            <button type="button" class="menu-item active" data-section="add-user">Add users</button>
            <button type="button" class="menu-item" data-section="users">Users</button>
            <button type="button" class="menu-item" data-section="products">Products</button>
            <button type="button" class="menu-item" data-section="shipments">Shipments</button>
            <button type="button" class="menu-item" data-section="activity-log">Activity log</button>
        </nav>

        <main class="page">
        <section class="panel section active" id="section-add-user">
            <h2>Add user</h2>
            <div class="add-form">
                <input type="text" id="newUsername" placeholder="Username" maxlength="100">
                <input type="email" id="newEmail" placeholder="E-mail">
                <input type="password" id="newPassword" placeholder="Password (min. 8 characters)">
                <select id="newRole">
                    <option value="worker">worker</option>
                    <option value="sorter">sorter</option>
                    <option value="admin">admin</option>
                </select>
                <button type="button" id="addUserBtn" class="btn">Add user</button>
            </div>
        </section>

        <section class="panel section" id="section-users">
            <h2>Users</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Username</th><th>E-mail</th><th>Role</th><th>Active</th></tr>
                    </thead>
                    <tbody id="userRows">
                        <tr><td colspan="4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel section" id="section-products">
            <h2>Products</h2>

            <div class="stack" id="tab-products">
                <div class="add-form">
                    <input type="text" id="newProductName" placeholder="Product name" maxlength="150">
                    <input type="text" id="newProductSku" placeholder="SKU" maxlength="50">
                    <input type="number" id="newProductQty" placeholder="Quantity" min="0" value="0">
                    <input type="text" id="newProductLocation" placeholder="Location (e.g. A-12)" maxlength="100">
                    <button type="button" id="addProductBtn" class="btn">Add product</button>
                </div>

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
            </div>

        </section>

        <section class="panel section" id="section-activity-log">
            <h2>Activity log</h2>
            <div id="activityLog"></div>
        </section>

        <section class="panel section" id="section-shipments">
            <h2>Shipments</h2>
            <div id="shipments"></div>
        </section>
    </main>
    </div>

    <script src="popup.js?v=11"></script>
    <script src="error.js?v=11"></script>
    <script src="products.js?v=11"></script>
    <script src="shipments.js?v=11"></script>
    <script src="activitylog.js?v=11"></script>
    <script src="admin.js?v=11"></script>
    <script src="menu.js?v=11"></script>
</body>
</html>