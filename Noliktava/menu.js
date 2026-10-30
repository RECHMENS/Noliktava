(function () {
    const items = Array.prototype.slice.call(document.querySelectorAll(".menu-item"));
    if (!items.length) return;
    const names = items.map(function (b) { return b.dataset.section; });

    function show(name) {
        if (names.indexOf(name) === -1) name = names[0];
        items.forEach(function (b) {
            b.classList.toggle("active", b.dataset.section === name);
        });
        document.querySelectorAll(".section").forEach(function (sec) {
            sec.classList.toggle("active", sec.id === "section-" + name);
        });
        if (location.hash !== "#" + name) history.replaceState(null, "", "#" + name);
        document.dispatchEvent(new CustomEvent("sectionchange", { detail: name }));
    }

    items.forEach(function (b) {
        b.addEventListener("click", function () { show(b.dataset.section); });
    });
    window.addEventListener("hashchange", function () { show(location.hash.slice(1)); });
    show(location.hash.slice(1));
})();