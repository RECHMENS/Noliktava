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
            <p class="muted"></p>
        </section>
    </main>

    <script src="common.js"></script>
    <script src="admin.js"></script>
</body>
</html>
