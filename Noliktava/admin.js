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
