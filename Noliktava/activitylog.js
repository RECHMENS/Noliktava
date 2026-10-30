function initActivityLog(container) {
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
        product_delete: "Product deleted",
        shipment_create: "Shipment created",
        shipment_status: "Shipment status",
        log_export: "Log exported"
    };

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function cell(text, className) {
        const c = document.createElement("td");
        if (className) c.className = className;
        c.textContent = text;
        return c;
    }

    container.innerHTML = "";
    const toolbar = el("div", "add-form");

    const search = el("input");
    search.type = "text";
    search.placeholder = "Search user or details";

    const filter = document.createElement("select");
    const all = el("option", "", "All actions");
    all.value = "";
    filter.appendChild(all);
    Object.keys(ACTION_LABELS).forEach(function (key) {
        const o = el("option", "", ACTION_LABELS[key]);
        o.value = key;
        filter.appendChild(o);
    });

    const refreshBtn = el("button", "btn", "Refresh");
    refreshBtn.type = "button";

    const exportBtn = el("button", "btn", "Export CSV");
    exportBtn.type = "button";

    toolbar.appendChild(search);
    toolbar.appendChild(filter);
    toolbar.appendChild(refreshBtn);
    toolbar.appendChild(exportBtn);
    container.appendChild(toolbar);

    const wrap = el("div", "table-wrap");
    const table = el("table");
    const thead = el("thead");
    const headRow = el("tr");
    ["Time", "User", "Action", "Details", "IP"].forEach(function (h) {
        headRow.appendChild(el("th", "", h));
    });
    thead.appendChild(headRow);
    const tbody = el("tbody");
    table.appendChild(thead);
    table.appendChild(tbody);
    wrap.appendChild(table);
    container.appendChild(wrap);

    container.appendChild(el("p", "muted", "The table shows the latest 200 matching entries. Export CSV includes all matching entries."));

    function message(text) {
        tbody.innerHTML = "";
        const tr = el("tr");
        const c = cell(text);
        c.colSpan = 5;
        tr.appendChild(c);
        tbody.appendChild(tr);
    }

    async function load() {
        try {
            const data = await api("list-logs", { filter: filter.value, search: search.value.trim() });
            tbody.innerHTML = "";

            if (data.logs.length === 0) {
                message("No log entries found.");
                return;
            }

            data.logs.forEach(function (l) {
                const tr = el("tr");
                tr.appendChild(cell(l.created, "nowrap"));
                tr.appendChild(cell(l.username || "—"));
                tr.appendChild(cell(ACTION_LABELS[l.action] || l.action));
                tr.appendChild(cell(l.details || "", "log-details"));
                tr.appendChild(cell(l.ip || ""));
                tbody.appendChild(tr);
            });
        } catch (e) {
            message(e.message);
        }
    }

    async function exportCsv() {
        exportBtn.disabled = true;
        try {
            const url = "php_backend/api.php?action=export-logs"
                + "&filter=" + encodeURIComponent(filter.value)
                + "&search=" + encodeURIComponent(search.value.trim());
            const response = await fetch(url);

            if (response.status === 401) {
                window.location.href = "login.html";
                return;
            }
            if (!response.ok) {
                let text = "Export failed.";
                try {
                    const data = await response.json();
                    text = data.error || text;
                } catch (e) {}
                throw new Error(text);
            }

            const blob = await response.blob();
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "activity-log.csv";
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);

            showMessage("Activity log exported.", "success");
            load();
        } catch (e) {
            showMessage(e.message, "error");
        }
        exportBtn.disabled = false;
    }

    refreshBtn.addEventListener("click", load);
    exportBtn.addEventListener("click", exportCsv);
    filter.addEventListener("change", load);
    search.addEventListener("keydown", function (e) {
        if (e.key === "Enter") load();
    });

    load();
    return { reload: load };
}