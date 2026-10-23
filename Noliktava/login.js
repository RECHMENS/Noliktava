const loginForm = document.getElementById("loginForm");

const registerForm = document.getElementById("registerForm");

const registerBtn = document.getElementById("registerBtn");

const backBtn = document.getElementById("backBtn");

registerBtn.addEventListener("click", function () {

    loginForm.style.display = "none";

    registerForm.style.display = "block";

});


backBtn.addEventListener("click", function () {

    registerForm.style.display = "none";

    loginForm.style.display = "block";

});

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

    const imageURL = URL.createObjectURL(file);

    profilePreview.src = imageURL;

    profilePreview.style.display = "block";

    profilePreview.parentElement.style.background =
        "transparent";

    uploadBtn.style.display = "none";

    changePictureBtn.style.display = "flex";

});

changePictureBtn.addEventListener("click", function () {

    profilePicture.click();

});

const shapes =
    document.querySelectorAll(".bg-shape");

let mouseX = 0;
let mouseY = 0;

let targetX = 0;
let targetY = 0;

let animationRunning = false;

document.addEventListener("mousemove", function (event) {

    targetX =
        (event.clientX / window.innerWidth - 0.5) * 2;


    targetY =
        (event.clientY / window.innerHeight - 0.5) * 2;

    if (!animationRunning) {

        animationRunning = true;

        requestAnimationFrame(updateBackground);
    }

});

function updateBackground() {

    mouseX +=
        (targetX - mouseX) * 0.05;


    mouseY +=
        (targetY - mouseY) * 0.05;

    shapes.forEach(function (shape, index) {

        const depth = 8 + (index * 5);


        const moveX = mouseX * depth;


        const moveY = mouseY * depth;


        const rotation =
            (index % 2 === 0 ? 1 : -1)
            * mouseX
            * 3;


        shape.style.transform =
            `translate(${moveX}px, ${moveY}px) rotate(${rotation}deg)`;

    });

    if (
        Math.abs(targetX - mouseX) > 0.001 ||
        Math.abs(targetY - mouseY) > 0.001
    ) {

        requestAnimationFrame(updateBackground);

    } else {

        animationRunning = false;

    }

}

document.getElementById("loginBtn").addEventListener("click", async function () {

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

        const message = await response.text();

        if (message.trim() === "OK") {
            window.location.href = "main.html";
        } else {
            alert(message);
        }

    } catch (error) {
        alert("Could not reach the server.");
    }

});

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

        const message = await response.text();
        alert(message);
    } catch (error) {
        alert("Could not reach the server.");
    }

});