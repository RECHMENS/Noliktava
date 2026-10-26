const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const registerBtn = document.getElementById("registerBtn");
const backBtn = document.getElementById("backBtn");

function showLogin() {
    registerForm.style.display = "none";
    loginForm.style.display = "block";
}

registerBtn.addEventListener("click", function () {
    loginForm.style.display = "none";
    registerForm.style.display = "block";
});

backBtn.addEventListener("click", showLogin);

/* ---------- Profile picture preview ---------- */

const profilePicture = document.getElementById("profilePicture");
const profilePreview = document.getElementById("profilePreview");
const changePictureBtn = document.getElementById("changePictureBtn");
const uploadBtn = document.querySelector(".upload-btn");

profilePicture.addEventListener("change", function () {
    const file = this.files[0];

    if (!file) {
        return;
    }

    if (!file.type.startsWith("image/")) {
        alert("Please select an image.");
        this.value = "";
        return;
    }

    profilePreview.src = URL.createObjectURL(file);
    profilePreview.style.display = "block";
    profilePreview.parentElement.style.background = "transparent";

    uploadBtn.style.display = "none";
    changePictureBtn.style.display = "flex";
});

changePictureBtn.addEventListener("click", function () {
    profilePicture.click();
});

/* ---------- Login ---------- */

// Where each role lands after logging in
const ROLE_PAGES = {
    admin: "admin.php",
    worker: "worker.php",
    sorter: "sorter.php"
};

async function doLogin() {
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    if (!email || !password) {
        alert("Please fill in all fields.");
        return;
    }

    const formData = new FormData();
    formData.append("email", email);
    formData.append("password", password);

    try {
        const response = await fetch("php_backend/login.php", {
            method: "POST",
            body: formData
        });

        const result = (await response.text()).trim();

        if (result.startsWith("OK:")) {
            const role = result.split(":")[1];
            const page = ROLE_PAGES[role];

            if (page) {
                window.location.href = page;
            } else {
                alert("Your account has no valid role. Contact an administrator.");
            }
        } else {
            alert(result);
        }
    } catch (error) {
        alert("Could not reach the server.");
    }
}

document.getElementById("loginBtn").addEventListener("click", doLogin);

// Press Enter in the login fields to sign in
["email", "password"].forEach(function (id) {
    document.getElementById(id).addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            doLogin();
        }
    });
});

/* ---------- Register ---------- */

const createAccountBtn = document.getElementById("createAccountBtn");
const usernameInput = document.getElementById("usernameID");
const registerEmail = document.getElementById("registerEmail");
const registerPassword = document.getElementById("registerPassword");
const confirmPassword = document.getElementById("confirmPassword");

createAccountBtn.addEventListener("click", async function () {
    const username = usernameInput.value.trim();
    const email = registerEmail.value.trim();
    const password = registerPassword.value;
    const confirm = confirmPassword.value;

    if (!username || !email || !password || !confirm) {
        alert("Please fill in all fields.");
        return;
    }

    if (password !== confirm) {
        alert("Passwords do not match.");
        return;
    }

    const formData = new FormData();
    formData.append("username", username);
    formData.append("email", email);
    formData.append("password", password);

    if (profilePicture.files[0]) {
        formData.append("profilePicture", profilePicture.files[0]);
    }

    try {
        const response = await fetch("php_backend/registrs.php", {
            method: "POST",
            body: formData
        });

        const message = (await response.text()).trim();
        alert(message);

        // On success, go back to the login form
        if (message === "Konts veiksmīgi izveidots!") {
            document.getElementById("email").value = email;
            showLogin();
        }
    } catch (error) {
        alert("Could not reach the server.");
    }
});
