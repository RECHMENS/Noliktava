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
                    showMessage(e.message, "error");
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
                    showMessage(e.message, "error");
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
        showMessage("User created.", "success");
        loadUsers();
    } catch (e) {
        showMessage(e.message, "error");
    }
});

loadUsers();

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
        showMessage("Product added.", "success");
        loadProducts();
    } catch (e) {
        showMessage(e.message, "error");
    }
});

const activityLog = initActivityLog(document.getElementById("activityLog"));

initShipments(document.getElementById("shipments"));

document.addEventListener("sectionchange", function (e) {
    if (e.detail === "activity-log") activityLog.reload();
});