function pCell(content) {
    const td = document.createElement("td");
    td.appendChild(content);
    return td;
}

function pMessageRow(tbody, text) {
    tbody.innerHTML = "";
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = 5;
    td.textContent = text;
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function pInput(type, value, extra) {
    const input = document.createElement("input");
    input.type = type;
    input.value = value;
    Object.assign(input, extra || {});
    return input;
}

function initProducts(tbody, canDelete) {
    async function load() {
        try {
            const data = await api("list-products");
            tbody.innerHTML = "";

            if (data.products.length === 0) {
                pMessageRow(tbody, "No products yet.");
                return;
            }

            data.products.forEach(function (p) {
                const id = parseInt(p.id, 10);
                const tr = document.createElement("tr");

                const name = pInput("text", p.name, { maxLength: 150 });
                const sku = pInput("text", p.sku, { maxLength: 50 });
                const qty = pInput("number", p.quantity, { min: "0", className: "qty-input" });
                const loc = pInput("text", p.location || "", { maxLength: 100 });

                const save = document.createElement("button");
                save.type = "button";
                save.className = "btn btn-small";
                save.textContent = "Save";
                save.addEventListener("click", async function () {
                    save.disabled = true;
                    try {
                        await api("update-product", {
                            productId: id,
                            name: name.value.trim(),
                            sku: sku.value.trim(),
                            quantity: parseInt(qty.value, 10),
                            location: loc.value.trim()
                        });
                        save.textContent = "Saved ✓";
                        setTimeout(function () { save.textContent = "Save"; }, 1500);
                    } catch (e) {
                        alert(e.message);
                    }
                    save.disabled = false;
                });

                const actions = document.createElement("div");
                actions.className = "row-actions";
                actions.appendChild(save);

                if (canDelete) {
                    const del = document.createElement("button");
                    del.type = "button";
                    del.className = "btn btn-small btn-danger";
                    del.textContent = "Delete";
                    del.addEventListener("click", async function () {
                        if (!confirm('Delete "' + p.name + '"?')) return;
                        try {
                            await api("delete-product", { productId: id });
                            load();
                        } catch (e) {
                            alert(e.message);
                        }
                    });
                    actions.appendChild(del);
                }

                tr.appendChild(pCell(name));
                tr.appendChild(pCell(sku));
                tr.appendChild(pCell(qty));
                tr.appendChild(pCell(loc));
                tr.appendChild(pCell(actions));
                tbody.appendChild(tr);
            });
        } catch (e) {
            pMessageRow(tbody, e.message);
        }
    }

    load();
    return load;
}