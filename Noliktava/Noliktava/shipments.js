function initShipments(container) {
    const STATUS_LABELS = {
        pending: "Pending",
        packed: "Packed",
        shipped: "Shipped",
        delivered: "Delivered",
        cancelled: "Cancelled"
    };
    const NEXT = {
        pending: { status: "packed", label: "Mark packed" },
        packed: { status: "shipped", label: "Mark shipped" },
        shipped: { status: "delivered", label: "Mark delivered" }
    };
    const CAN_CANCEL = { pending: true, packed: true };

    let products = [];
    let itemRows = [];

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function td(content, className) {
        const c = document.createElement("td");
        if (className) c.className = className;
        if (content instanceof Node) c.appendChild(content);
        else c.textContent = content;
        return c;
    }

    function button(label, className, onClick) {
        const b = el("button", className, label);
        b.type = "button";
        b.addEventListener("click", onClick);
        return b;
    }

    container.innerHTML = "";
    container.appendChild(el("h3", "", "New shipment"));

    const form = el("div", "shipment-form");
    const top = el("div", "add-form");

    const destination = el("input");
    destination.type = "text";
    destination.placeholder = "Destination (customer / address)";
    destination.maxLength = 150;

    const notes = el("input");
    notes.type = "text";
    notes.placeholder = "Notes (optional)";
    notes.maxLength = 500;

    top.appendChild(destination);
    top.appendChild(notes);

    const itemsBox = el("div", "shipment-items");

    function fillSelect(select) {
        const current = select.value;
        select.innerHTML = "";
        const placeholder = el("option", "", "Select product…");
        placeholder.value = "";
        select.appendChild(placeholder);

        products.forEach(function (p) {
            const stock = parseInt(p.quantity, 10);
            const o = el("option", "", p.name + " (" + p.sku + ") — " + stock + " in stock");
            o.value = p.id;
            o.disabled = stock < 1;
            select.appendChild(o);
        });
        select.value = current;
    }

    function updateRemoveButtons() {
        itemRows.forEach(function (r) {
            r.remove.style.display = itemRows.length <= 1 ? "none" : "";
        });
    }

    function addItemRow() {
        const row = el("div", "item-row");

        const select = document.createElement("select");
        fillSelect(select);

        const qty = el("input");
        qty.type = "number";
        qty.min = "1";
        qty.value = "1";
        qty.placeholder = "Qty";

        const entry = { row: row, select: select, qty: qty, remove: null };
        const remove = button("Remove", "btn btn-small btn-danger", function () {
            if (itemRows.length <= 1) return;
            row.remove();
            itemRows = itemRows.filter(function (r) { return r !== entry; });
            updateRemoveButtons();
        });
        entry.remove = remove;

        row.appendChild(select);
        row.appendChild(qty);
        row.appendChild(remove);
        itemsBox.appendChild(row);
        itemRows.push(entry);
        updateRemoveButtons();
    }

    function resetForm() {
        destination.value = "";
        notes.value = "";
        itemsBox.innerHTML = "";
        itemRows = [];
        addItemRow();
    }

    const addItemBtn = button("+ Add product", "btn btn-small", addItemRow);
    const createBtn = button("Create shipment", "btn", async function () {
        createBtn.disabled = true;
        try {
            await api("create-shipment", {
                destination: destination.value.trim(),
                notes: notes.value.trim(),
                items: itemRows.map(function (r) {
                    return { productId: parseInt(r.select.value, 10), quantity: parseInt(r.qty.value, 10) };
                })
            });
            showMessage("Shipment created.", "success");
            resetForm();
            await loadProducts();
            await loadShipments();
        } catch (e) {
            showMessage(e.message, "error");
        }
        createBtn.disabled = false;
    });

    const formActions = el("div", "row-actions");
    formActions.appendChild(addItemBtn);
    formActions.appendChild(createBtn);

    form.appendChild(top);
    form.appendChild(itemsBox);
    form.appendChild(formActions);
    container.appendChild(form);

    container.appendChild(el("h3", "", "All shipments"));

    const toolbar = el("div", "shipment-toolbar");
    const statusFilter = document.createElement("select");
    [["", "All statuses"]].concat(Object.keys(STATUS_LABELS).map(function (k) {
        return [k, STATUS_LABELS[k]];
    })).forEach(function (pair) {
        const o = el("option", "", pair[1]);
        o.value = pair[0];
        statusFilter.appendChild(o);
    });
    statusFilter.addEventListener("change", loadShipments);

    const refreshBtn = button("Refresh", "btn btn-small", function () {
        loadProducts();
        loadShipments();
    });
    toolbar.appendChild(statusFilter);
    toolbar.appendChild(refreshBtn);
    container.appendChild(toolbar);

    const wrap = el("div", "table-wrap");
    const table = el("table");
    const thead = el("thead");
    const headRow = el("tr");
    ["Ref", "Destination", "Items", "Status", "Created", "Actions"].forEach(function (h) {
        headRow.appendChild(el("th", "", h));
    });
    thead.appendChild(headRow);
    const tbody = el("tbody");
    table.appendChild(thead);
    table.appendChild(tbody);
    wrap.appendChild(table);
    container.appendChild(wrap);

    function messageRow(text) {
        tbody.innerHTML = "";
        const tr = el("tr");
        const c = td(text);
        c.colSpan = 6;
        tr.appendChild(c);
        tbody.appendChild(tr);
    }

    async function setStatus(s, status, btn) {
        btn.disabled = true;
        try {
            await api("set-shipment-status", { shipmentId: parseInt(s.id, 10), status: status });
            await loadShipments();
            if (status === "cancelled") loadProducts();
        } catch (e) {
            showMessage(e.message, "error");
            btn.disabled = false;
        }
    }

    async function loadProducts() {
        try {
            const data = await api("list-products");
            products = data.products;
            itemRows.forEach(function (r) { fillSelect(r.select); });
        } catch (e) {
            showMessage(e.message, "error");
        }
    }

    async function loadShipments() {
        try {
            const data = await api("list-shipments", { status: statusFilter.value });
            tbody.innerHTML = "";

            if (data.shipments.length === 0) {
                messageRow("No shipments found.");
                return;
            }

            data.shipments.forEach(function (s) {
                const tr = el("tr");

                const dest = el("div", "", s.destination);
                if (s.notes) dest.appendChild(el("small", "", s.notes));

                const lines = s.items.map(function (i) {
                    return i.name + " (" + i.sku + ") × " + i.quantity;
                }).join("\n");

                const status = el("span", "badge badge-" + s.status, STATUS_LABELS[s.status] || s.status);

                const created = el("div", "", s.created);
                created.appendChild(el("small", "", "by " + (s.created_by || "—")));

                const actions = el("div", "row-actions");
                const next = NEXT[s.status];
                if (next) {
                    const nextBtn = button(next.label, "btn btn-small", function () {
                        setStatus(s, next.status, nextBtn);
                    });
                    actions.appendChild(nextBtn);
                }
                if (CAN_CANCEL[s.status]) {
                    const cancelBtn = button("Cancel", "btn btn-small btn-danger", async function () {
                        const ok = await showConfirm(
                            "Cancel " + s.reference + "? The reserved stock will be returned to the warehouse.",
                            "Cancel shipment", true, "Keep shipment"
                        );
                        if (ok) setStatus(s, "cancelled", cancelBtn);
                    });
                    actions.appendChild(cancelBtn);
                }
                if (!next && !CAN_CANCEL[s.status]) actions.textContent = "—";

                tr.appendChild(td(s.reference, "nowrap"));
                tr.appendChild(td(dest));
                tr.appendChild(td(lines, "pre-line"));
                tr.appendChild(td(status));
                tr.appendChild(td(created));
                tr.appendChild(td(actions));
                tbody.appendChild(tr);
            });
        } catch (e) {
            messageRow(e.message);
        }
    }

    addItemRow();
    loadProducts();
    loadShipments();
}