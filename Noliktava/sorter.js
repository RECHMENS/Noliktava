const LOW_STOCK = 5;

function sEl(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function sCell(content, className) {
    const c = document.createElement("td");
    if (className) c.className = className;
    if (content instanceof Node) c.appendChild(content);
    else c.textContent = content;
    return c;
}

function sTable(headers) {
    const wrap = sEl("div", "table-wrap");
    const table = sEl("table");
    const thead = sEl("thead");
    const row = sEl("tr");
    headers.forEach(function (h) { row.appendChild(sEl("th", "", h)); });
    thead.appendChild(row);
    const tbody = sEl("tbody");
    table.appendChild(thead);
    table.appendChild(tbody);
    wrap.appendChild(table);
    return { wrap: wrap, tbody: tbody, columns: headers.length };
}

function sMessageRow(t, text) {
    t.tbody.innerHTML = "";
    const tr = sEl("tr");
    const c = sCell(text);
    c.colSpan = t.columns;
    tr.appendChild(c);
    t.tbody.appendChild(tr);
}

function sSearchBox(placeholder, onChange) {
    const input = sEl("input", "search-box");
    input.type = "text";
    input.placeholder = placeholder;
    input.addEventListener("input", onChange);
    return input;
}

function sButton(label, className, onClick) {
    const b = sEl("button", className, label);
    b.type = "button";
    b.addEventListener("click", onClick);
    return b;
}

function stockStatus(p) {
    const qty = parseInt(p.quantity, 10);
    if (qty <= 0) return { label: "Out of stock", css: "out" };
    if (qty <= LOW_STOCK) return { label: "Low stock", css: "low" };
    return { label: "In stock", css: "ok" };
}

function statusBadge(p) {
    const s = stockStatus(p);
    return sEl("span", "badge badge-" + s.css, s.label);
}

function productMatches(p, text) {
    const t = text.trim().toLowerCase();
    if (!t) return true;
    return (p.name + " " + p.sku + " " + (p.location || "")).toLowerCase().indexOf(t) !== -1;
}

function initProductView(container) {
    let products = [];
    container.innerHTML = "";

    const search = sSearchBox("Search by name, SKU or location", render);
    const t = sTable(["Name", "SKU", "Quantity", "Location", "Status"]);
    container.appendChild(search);
    container.appendChild(t.wrap);

    function render() {
        const list = products.filter(function (p) { return productMatches(p, search.value); });
        if (list.length === 0) {
            sMessageRow(t, products.length === 0 ? "No products yet." : "No products match your search.");
            return;
        }
        t.tbody.innerHTML = "";
        list.forEach(function (p) {
            const tr = sEl("tr");
            tr.appendChild(sCell(p.name));
            tr.appendChild(sCell(p.sku));
            tr.appendChild(sCell(p.quantity));
            tr.appendChild(sCell(p.location || "—"));
            tr.appendChild(sCell(statusBadge(p)));
            t.tbody.appendChild(tr);
        });
    }

    async function reload() {
        try {
            const data = await api("list-products");
            products = data.products;
            render();
        } catch (e) {
            sMessageRow(t, e.message);
        }
    }

    return { reload: reload };
}

function initPlacement(container) {
    let products = [];
    container.innerHTML = "";

    const search = sSearchBox("Search by name, SKU or location", render);
    const t = sTable(["Product", "Quantity", "Location", ""]);
    container.appendChild(search);
    container.appendChild(t.wrap);

    function render() {
        const list = products.filter(function (p) { return productMatches(p, search.value); });
        if (list.length === 0) {
            sMessageRow(t, products.length === 0 ? "No products yet." : "No products match your search.");
            return;
        }
        t.tbody.innerHTML = "";
        list.forEach(function (p) {
            const tr = sEl("tr");

            const name = sEl("div", "", p.name);
            name.appendChild(sEl("small", "", "SKU " + p.sku));

            const loc = sEl("input");
            loc.type = "text";
            loc.value = p.location || "";
            loc.maxLength = 100;
            loc.placeholder = "e.g. A-12";

            const save = sButton("Save", "btn btn-small", async function () {
                save.disabled = true;
                try {
                    await api("set-product-location", {
                        productId: parseInt(p.id, 10),
                        location: loc.value.trim()
                    });
                    p.location = loc.value.trim();
                    save.textContent = "Saved ✓";
                    setTimeout(function () { save.textContent = "Save"; }, 1500);
                } catch (e) {
                    showMessage(e.message, "error");
                }
                save.disabled = false;
            });

            tr.appendChild(sCell(name));
            tr.appendChild(sCell(p.quantity));
            tr.appendChild(sCell(loc));
            tr.appendChild(sCell(save));
            t.tbody.appendChild(tr);
        });
    }

    async function reload() {
        try {
            const data = await api("list-products");
            products = data.products;
            render();
        } catch (e) {
            sMessageRow(t, e.message);
        }
    }

    return { reload: reload };
}

function initPlacementHistory(container) {
    container.innerHTML = "";

    const toolbar = sEl("div", "shipment-toolbar");
    const refresh = sButton("Refresh", "btn btn-small", function () { reload(); });
    toolbar.appendChild(refresh);
    const t = sTable(["Time", "User", "Change"]);
    container.appendChild(toolbar);
    container.appendChild(t.wrap);

    async function reload() {
        try {
            const data = await api("list-placement-history");
            if (data.history.length === 0) {
                sMessageRow(t, "No placement changes yet.");
                return;
            }
            t.tbody.innerHTML = "";
            data.history.forEach(function (h) {
                const tr = sEl("tr");
                tr.appendChild(sCell(h.created, "nowrap"));
                tr.appendChild(sCell(h.username || "—"));
                tr.appendChild(sCell(h.details || "", "log-details"));
                t.tbody.appendChild(tr);
            });
        } catch (e) {
            sMessageRow(t, e.message);
        }
    }

    return { reload: reload };
}

function initReports(container) {
    let products = [];
    container.innerHTML = "";

    const toolbar = sEl("div", "shipment-toolbar");
    const refresh = sButton("Refresh", "btn btn-small", function () { reload(); });
    const exportBtn = sButton("Export report (CSV)", "btn btn-small", exportCsv);
    toolbar.appendChild(refresh);
    toolbar.appendChild(exportBtn);
    container.appendChild(toolbar);

    const summaryBox = sEl("div", "stat-grid");
    container.appendChild(summaryBox);

    container.appendChild(sEl("h3", "", "Stock by location"));
    const locTable = sTable(["Location", "Products", "Units"]);
    container.appendChild(locTable.wrap);

    container.appendChild(sEl("h3", "", "Needs attention (no location, low or no stock)"));
    const attnTable = sTable(["Name", "SKU", "Quantity", "Location", "Status"]);
    container.appendChild(attnTable.wrap);

    function stat(label, value) {
        const box = sEl("div", "stat");
        box.appendChild(sEl("strong", "", String(value)));
        box.appendChild(sEl("span", "", label));
        return box;
    }

    function render() {
        let units = 0, noLocation = 0, outOfStock = 0, lowStock = 0;
        const byLocation = {};

        products.forEach(function (p) {
            const qty = parseInt(p.quantity, 10);
            units += qty;
            if (!p.location) noLocation++;
            if (qty <= 0) outOfStock++;
            else if (qty <= LOW_STOCK) lowStock++;

            const key = p.location || "";
            if (!byLocation[key]) byLocation[key] = { products: 0, units: 0 };
            byLocation[key].products++;
            byLocation[key].units += qty;
        });

        summaryBox.innerHTML = "";
        summaryBox.appendChild(stat("Products", products.length));
        summaryBox.appendChild(stat("Units in stock", units));
        summaryBox.appendChild(stat("Without location", noLocation));
        summaryBox.appendChild(stat("Out of stock", outOfStock));
        summaryBox.appendChild(stat("Low stock (" + LOW_STOCK + " or less)", lowStock));

        const keys = Object.keys(byLocation).sort(function (a, b) {
            if (a === "") return 1;
            if (b === "") return -1;
            return a.localeCompare(b);
        });
        if (keys.length === 0) {
            sMessageRow(locTable, "No products yet.");
        } else {
            locTable.tbody.innerHTML = "";
            keys.forEach(function (key) {
                const tr = sEl("tr");
                tr.appendChild(sCell(key === "" ? "No location" : key));
                tr.appendChild(sCell(byLocation[key].products));
                tr.appendChild(sCell(byLocation[key].units));
                locTable.tbody.appendChild(tr);
            });
        }

        const attention = products.filter(function (p) {
            return !p.location || parseInt(p.quantity, 10) <= LOW_STOCK;
        });
        if (attention.length === 0) {
            sMessageRow(attnTable, "Nothing needs attention.");
        } else {
            attnTable.tbody.innerHTML = "";
            attention.forEach(function (p) {
                const tr = sEl("tr");
                tr.appendChild(sCell(p.name));
                tr.appendChild(sCell(p.sku));
                tr.appendChild(sCell(p.quantity));
                tr.appendChild(sCell(p.location || "—"));
                tr.appendChild(sCell(statusBadge(p)));
                attnTable.tbody.appendChild(tr);
            });
        }
    }

    async function reload() {
        try {
            const data = await api("list-products");
            products = data.products;
            render();
        } catch (e) {
            showMessage(e.message, "error");
        }
    }

    function csvCell(value) {
        let text = String(value === null || value === undefined ? "" : value);
        if (text !== "" && "=+-@\t\r".indexOf(text.charAt(0)) !== -1) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    async function exportCsv() {
        await reload();
        const rows = [["Name", "SKU", "Quantity", "Location", "Status"]];
        products.forEach(function (p) {
            rows.push([p.name, p.sku, p.quantity, p.location || "", stockStatus(p).label]);
        });
        const text = "\uFEFF" + rows.map(function (r) { return r.map(csvCell).join(","); }).join("\r\n");

        const blob = new Blob([text], { type: "text/csv;charset=utf-8" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "placement-report-" + new Date().toISOString().slice(0, 10) + ".csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
        showMessage("Report exported.", "success");
    }

    return { reload: reload };
}
