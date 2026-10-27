const myId = parseInt(document.body.dataset.userId, 10);
const rows = document.getElementById("userRows");
const ROLES = ["admin", "worker", "sorter"];

function cell(content) {
    const td = document.createElement("td");
    if (content instanceof Node) {
        td.appendChild(content);
    } else {
        td.textContent = content;
    }
    return td;
}

async function loadUsers() {
    try {
        const data = await api("list-users");
        rows.innerHTML = "";

        data.users.forEach(function (u) {
            const id = parseInt(u.lietotaji_id, 10);
            const isMe = id === myId;
            const tr = document.createElement("tr");

            const select = document.createElement("select");
            ROLES.forEach(function (r) {
                const opt = document.createElement("option");
                opt.value = r;
                opt.textContent = r;
                opt.selected = r === u.role;
                select.appendChild(opt);
            });
            select.disabled = isMe;
            select.addEventListener("change", async function () {
                try {
                    await api("set-role", { userId: id, role: select.value });
                } catch (e) {
                    alert(e.message);
                    loadUsers();
                }
            });

            const check = document.createElement("input");
            check.type = "checkbox";
            check.checked = parseInt(u.active, 10) === 1;
            check.disabled = isMe;
            check.addEventListener("change", async function () {
                try {
                    await api("set-active", { userId: id, active: check.checked });
                } catch (e) {
                    alert(e.message);
                    loadUsers();
                }
            });

            tr.appendChild(cell(u.username + (isMe ? " (you)" : "")));
            tr.appendChild(cell(u.email));
            tr.appendChild(cell(select));
            tr.appendChild(cell(check));
            rows.appendChild(tr);
        });
    } catch (e) {
        rows.innerHTML = "";
        const tr = document.createElement("tr");
        const td = cell(e.message);
        td.colSpan = 4;
        tr.appendChild(td);
        rows.appendChild(tr);
    }
}

document.getElementById("addUserBtn").addEventListener("click", async function () {
    const username = document.getElementById("newUsername");
    const email = document.getElementById("newEmail");
    const password = document.getElementById("newPassword");
    const role = document.getElementById("newRole");

    try {
        await api("add-user", {
            username: username.value.trim(),
            email: email.value.trim(),
            password: password.value,
            role: role.value
        });
        username.value = email.value = password.value = "";
        loadUsers();
    } catch (e) {
        alert(e.message);
    }
});

loadUsers();

document.querySelectorAll(".tab").forEach(function (tab) {
    tab.addEventListener("click", function () {
        document.querySelectorAll(".tab").forEach(function (t) { t.classList.remove("active"); });
        document.querySelectorAll(".tab-content").forEach(function (c) { c.classList.remove("active"); });
        tab.classList.add("active");
        document.getElementById("tab-" + tab.dataset.tab).classList.add("active");
        if (tab.dataset.tab === "log") loadLogs();
    });
});

const loadProducts = initProducts(document.getElementById("productRows"), true);

document.getElementById("addProductBtn").addEventListener("click", async function () {
    const name = document.getElementById("newProductName");
    const sku = document.getElementById("newProductSku");
    const qty = document.getElementById("newProductQty");
    const loc = document.getElementById("newProductLocation");

    try {
        await api("add-product", {
            name: name.value.trim(),
            sku: sku.value.trim(),
            quantity: parseInt(qty.value, 10) || 0,
            location: loc.value.trim()
        });
        name.value = sku.value = loc.value = "";
        qty.value = 0;
        loadProducts();
    } catch (e) {
        alert(e.message);
    }
});

const logRows = document.getElementById("logRows");
const logSearch = document.getElementById("logSearch");
const logFilter = document.getElementById("logFilter");

const ACTION_LABELS = {
    login: "Login",
    login_failed: "Failed login",
    login_blocked: "Blocked login",
    logout: "Logout",
    register: "Registration",
    profile_update: "Profile update",
    account_delete: "Account deleted",
    user_add: "User added",
    role_change: "Role changed",
    active_change: "Active changed",
    product_add: "Product added",
    product_update: "Product updated",
    product_delete: "Product deleted"
};

function logMessage(text) {
    logRows.innerHTML = "";
    const tr = document.createElement("tr");
    const td = cell(text);
    td.colSpan = 5;
    tr.appendChild(td);
    logRows.appendChild(tr);
}

async function loadLogs() {
    try {
        const data = await api("list-logs", {
            filter: logFilter.value,
            search: logSearch.value.trim()
        });
        logRows.innerHTML = "";

        if (data.logs.length === 0) {
            logMessage("No log entries found.");
            return;
        }

        data.logs.forEach(function (l) {
            const tr = document.createElement("tr");
            const time = cell(l.created);
            time.className = "nowrap";
            const details = cell(l.details || "");
            details.className = "log-details";

            tr.appendChild(time);
            tr.appendChild(cell(l.username || "—"));
            tr.appendChild(cell(ACTION_LABELS[l.action] || l.action));
            tr.appendChild(details);
            tr.appendChild(cell(l.ip || ""));
            logRows.appendChild(tr);
        });
    } catch (e) {
        logMessage(e.message);
    }
}

document.getElementById("logRefreshBtn").addEventListener("click", loadLogs);
logFilter.addEventListener("change", loadLogs);
logSearch.addEventListener("keydown", function (e) {
    if (e.key === "Enter") loadLogs();
});