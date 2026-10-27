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
    <link rel="stylesheet" href="dashboard.css">
</head>
<body data-user-id="<?= $myId ?>">
    <?php renderHeader($user, 'Admin panel'); ?>

    <main class="page">
        <section class="panel">
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

        <section class="panel">
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

        <section class="panel">
            <h2>Warehouse</h2>

            <div class="tabs" role="tablist">
                <button type="button" class="tab active" data-tab="products" role="tab">Products</button>
                <button type="button" class="tab" data-tab="log" role="tab">Activity log</button>
            </div>

            <div class="tab-content active" id="tab-products">
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

            <div class="tab-content" id="tab-log">
                <div class="add-form">
                    <input type="text" id="logSearch" placeholder="Search user or details">
                    <select id="logFilter">
                        <option value="">All actions</option>
                        <option value="login">Login</option>
                        <option value="login_failed">Failed login</option>
                        <option value="login_blocked">Blocked login</option>
                        <option value="logout">Logout</option>
                        <option value="register">Registration</option>
                        <option value="profile_update">Profile update</option>
                        <option value="account_delete">Account deleted</option>
                        <option value="user_add">User added</option>
                        <option value="role_change">Role changed</option>
                        <option value="active_change">Active changed</option>
                        <option value="product_add">Product added</option>
                        <option value="product_update">Product updated</option>
                        <option value="product_delete">Product deleted</option>
                    </select>
                    <button type="button" id="logRefreshBtn" class="btn">Refresh</button>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
                        </thead>
                        <tbody id="logRows">
                            <tr><td colspan="5">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="muted">Showing the latest 200 matching entries.</p>
            </div>
        </section>

        <div class="popup"></div>
        <div class="popup-text"></div>
    </main>

    <script src="error.js"></script>
    <script src="products.js"></script>
    <script src="admin.js"></script>
</body>
</html>