(function () {
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function topOverlay() {
        const all = document.querySelectorAll(".popup-overlay");
        return all[all.length - 1];
    }

    function openPopup(opts) {
        return new Promise(function (resolve) {
            const overlay = el("div", "popup-overlay");
            const box = el("div", "popup popup-" + opts.type);
            box.setAttribute("role", opts.role);
            box.setAttribute("aria-modal", "true");

            const actions = el("div", "popup-actions");
            let timer = null;

            function finish(value) {
                clearTimeout(timer);
                document.removeEventListener("keydown", onKey);
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                resolve(value);
            }

            function onKey(e) {
                if (e.key === "Escape" && overlay === topOverlay()) {
                    e.preventDefault();
                    finish(opts.escapeValue);
                }
            }

            let focusBtn = null;
            opts.buttons.forEach(function (b, i) {
                const btn = el("button", "popup-btn" + (b.danger ? " popup-btn-danger" : ""), b.label);
                btn.type = "button";
                btn.addEventListener("click", function () { finish(b.value); });
                actions.appendChild(btn);
                if (i === opts.focusIndex) focusBtn = btn;
            });

            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) finish(opts.escapeValue);
            });
            document.addEventListener("keydown", onKey);

            box.appendChild(el("div", "popup-title", opts.title));
            box.appendChild(el("div", "popup-text", opts.text));
            box.appendChild(actions);
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            if (focusBtn) focusBtn.focus();
            if (opts.autoClose) timer = setTimeout(function () { finish(true); }, opts.autoClose);
        });
    }

    const TITLES = { error: "Error", success: "Success", info: "Notice" };

    window.showMessage = function (text, type) {
        type = TITLES[type] ? type : "info";
        return openPopup({
            type: type,
            role: type === "error" ? "alertdialog" : "dialog",
            title: TITLES[type],
            text: String(text),
            buttons: [{ label: "OK", value: true }],
            focusIndex: 0,
            escapeValue: true,
            autoClose: type === "success" ? 3000 : 0
        });
    };

    window.alert = function (text) {
        window.showMessage(text, "error");
    };

    window.showConfirm = function (text, okLabel, danger, cancelLabel) {
        return openPopup({
            type: danger ? "error" : "info",
            role: "alertdialog",
            title: "Please confirm",
            text: String(text),
            buttons: [
                { label: cancelLabel || "Cancel", value: false },
                { label: okLabel || "OK", value: true, danger: !!danger }
            ],
            focusIndex: 0,
            escapeValue: false,
            autoClose: 0
        });
    };
})();