// Shared helpers for all logged-in pages

async function api(action, body) {
    const options = { method: body ? "POST" : "GET" };
    if (body) {
        options.headers = { "Content-Type": "application/json" };
        options.body = JSON.stringify(body);
    }

    const response = await fetch("php_backend/api.php?action=" + encodeURIComponent(action), options);
    let data = {};
    try {
        data = await response.json();
    } catch (e) {}

    if (response.status === 401) {
        window.location.href = "login.html";
        throw new Error("Not logged in");
    }
    if (!response.ok) {
        throw new Error(data.error || "Request failed.");
    }
    return data;
}

document.getElementById("logoutBtn").addEventListener("click", async function () {
    try {
        await api("logout");
    } catch (e) {}
    window.location.href = "login.html";
});
