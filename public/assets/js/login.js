function switchTab(name) {
    const order = ["login", "register", "forgot"];
    document.querySelectorAll(".tab-btn").forEach((btn, i) => {
        btn.classList.toggle("active", order[i] === name);
    });
    document
        .querySelectorAll(".panel")
        .forEach((p) => p.classList.remove("active"));
    document.getElementById("panel-" + name).classList.add("active");
}

/* ── Show / hide password ───────────────────────────────────────────────── */
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const hidden = inp.type === "password";
    inp.type = hidden ? "text" : "password";
    btn.innerHTML = hidden
        ? '<i class="fas fa-eye-slash"></i>'
        : '<i class="fas fa-eye"></i>';
}

/* ── Password strength meter ────────────────────────────────────────────── */
function checkStrength(val) {
    const wrap = document.getElementById("pw-strength");
    const label = document.getElementById("pw-label");
    const bars = [1, 2, 3, 4].map((n) => document.getElementById("bar" + n));
    if (!val) {
        wrap.style.display = "none";
        return;
    }
    wrap.style.display = "block";
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const cls = ["weak", "fair", "good", "strong"];
    const names = ["Weak", "Fair", "Good", "Strong"];
    const colors = ["#f87171", "#fbbf24", "#34d399", "#10b981"];
    bars.forEach((b, i) => {
        b.className = "pw-bar" + (i < score ? " " + cls[score - 1] : "");
    });
    label.textContent = score ? names[score - 1] : "Too short";
    label.style.color = score ? colors[score - 1] : "#94a3b8";
}

/* ── Register label toggle ──────────────────────────────────────────────── */
function toggleRegFields() {
    const type = document.getElementById("reg-type").value;
    const lbl = document.getElementById("fname-label");
    const inp = document.getElementById("reg-name");
    if (type === "Employer") {
        lbl.textContent = "Company Name";
        inp.placeholder = "Your company name";
    } else {
        lbl.textContent = "Full Name";
        inp.placeholder = "Your full name";
    }
}

/* ── Inline alert helpers ───────────────────────────────────────────────── */
function showError(id, msg) {
    const el = document.getElementById(id);
    el.querySelector("span").textContent = msg;
    el.classList.add("show");
    setTimeout(() => el.classList.remove("show"), 6000);
}

function showSuccess(id, msg) {
    const el = document.getElementById(id);
    el.querySelector("span").textContent = msg;
    el.classList.add("show");
}

/* ── Toast helpers ──────────────────────────────────────────────────────── */
let toastTimer = null;

function showToast(type, msg) {
    const toast = document.getElementById("toast");
    const iconEl = document.getElementById("toast-icon");
    const msgEl = document.getElementById("toast-msg");
    const progress = toast.querySelector(".toast-progress");

    // Reset animation
    progress.style.animation = "none";
    void progress.offsetWidth; // reflow

    toast.className = "toast " + type;
    iconEl.className =
        "fas toast-icon " +
        (type === "success" ? "fa-circle-check" : "fa-circle-exclamation");
    msgEl.textContent = msg;

    // Show
    requestAnimationFrame(() => {
        toast.classList.add("show");
        progress.style.animation = "shrink 4s linear forwards";
    });

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 4200);
}

function hideToast() {
    document.getElementById("toast").classList.remove("show");
}

/* ── Form validation ────────────────────────────────────────────────────── */
function validateLogin() {
    const type = document.getElementById("login-type").value;
    const user = document.getElementById("login-user").value.trim();
    const pass = document.getElementById("login-pass").value;
    if (!type) {
        showError("login-error", "Please select your account type.");
        return false;
    }
    if (!user) {
        showError("login-error", "Please enter your username or email.");
        return false;
    }
    if (!pass) {
        showError("login-error", "Please enter your password.");
        return false;
    }
    return true;
}

function handleLoginSubmit(event) {
    event.preventDefault();
    if (!validateLogin()) return false;

    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    submitBtn.disabled = true;
}

function validateRegister() {
    const type = document.getElementById("reg-type").value;
    const name = document.getElementById("reg-name").value.trim();
    const user = document.getElementById("reg-user").value.trim();
    const email = document.getElementById("reg-email").value.trim();
    const pass = document.getElementById("reg-pass").value;
    const conf = document.getElementById("reg-confirm").value;
    const terms = document.getElementById("terms").checked;

    if (!type) {
        showError("reg-error", "Please select an account type.");
        return false;
    }
    if (!name) {
        showError("reg-error", "Please enter your name.");
        return false;
    }
    if (!user) {
        showError("reg-error", "Please choose a username.");
        return false;
    }
    if (!email || !/\S+@\S+\.\S+/.test(email)) {
        showError("reg-error", "Please enter a valid email address.");
        return false;
    }
    if (pass.length < 6) {
        showError("reg-error", "Password must be at least 6 characters.");
        return false;
    }
    if (pass !== conf) {
        showError("reg-error", "Passwords do not match.");
        return false;
    }
    if (!terms) {
        showError("reg-error", "Please accept the Terms of Service.");
        return false;
    }
    return true;
}

function handleRegisterSubmit(event) {
    event.preventDefault();
    if (!validateRegister()) return false;

    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    submitBtn.disabled = true;

    fetch(form.action, {
        method: "POST",
        body: formData,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                switchTab("login");
                showToast(
                    "success",
                    "Approval requested! Your account will be activated soon.",
                );
                showSuccess(
                    "reg-success",
                    data.message || "Account created! You can now sign in.",
                );
                form.reset();
            } else {
                showError("reg-error", data.message || "Registration failed.");
            }
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        })
        .catch((error) => {
            console.error("Error:", error);
            showError(
                "reg-error",
                "A network error occurred. Please try again.",
            );
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
}

function handleForgotSubmit(event) {
    event.preventDefault();
    if (!validateForgot()) return false;

    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    submitBtn.disabled = true;

    fetch(form.action, {
        method: "POST",
        body: formData,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    })
        .then((response) =>
            response.json().catch(() => ({
                success: true,
                message: "Reset link sent.",
            })),
        )
        .then((data) => {
            if (data.success) {
                showSuccess(
                    "forgot-success",
                    data.message || "Reset link sent — check your inbox.",
                );
                form.reset();
            } else {
                showError("forgot-error", data.message || "An error occurred.");
            }
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        })
        .catch((error) => {
            console.error("Error:", error);
            showError("forgot-error", "A network error occurred.");
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
}

function validateForgot() {
    const user = document.getElementById("forgot-user").value.trim();
    const email = document.getElementById("forgot-email").value.trim();
    if (!user) {
        showError("forgot-error", "Please enter your username.");
        return false;
    }
    if (!email || !/\S+@\S+\.\S+/.test(email)) {
        showError("forgot-error", "Please enter a valid email address.");
        return false;
    }
    return true;
}

/* ── On load — read URL params and show appropriate messages ────────────── */
window.addEventListener("DOMContentLoaded", function () {
    const p = new URLSearchParams(window.location.search);

    // ── PHP login error (wrong password) ──
    if (p.get("login") === "error") {
        showError(
            "login-error",
            "Wrong username or password. Please try again.",
        );
    }

    // ── Registration SUCCESS ──
    if (p.get("reg") === "ok") {
        switchTab("login");
        showToast(
            "success",
            "Approval requested! Your account will be activated within 24-48 hours.",
        );
        showSuccess("reg-success", "Account created! You can now sign in.");
    }

    // ── Registration ERRORS (duplicate username / email / other) ──
    const regError = p.get("reg_error");
    if (regError) {
        switchTab("register");
        const msg = decodeURIComponent(regError);

        // Map server messages to friendly ones with icons
        let friendly = msg;
        if (msg.toLowerCase().includes("username is already taken")) {
            friendly =
                "⚠️ That username is already taken. Please choose a different one.";
        } else if (
            msg.toLowerCase().includes("email already exists") ||
            msg.toLowerCase().includes("email")
        ) {
            friendly =
                "⚠️ That email address is already registered. Try signing in instead.";
        }

        showError("reg-error", friendly);
        showToast("error", friendly);
    }

    // ── Other URL-based states ──
    if (p.get("error") === "1")
        showError(
            "login-error",
            "Wrong username or password. Please try again.",
        );
    if (p.get("error") === "db")
        showError("login-error", "Database error. Please try again later.");
    if (p.get("reset") === "1") {
        switchTab("forgot");
        showSuccess("forgot-success", "Reset link sent — check your inbox.");
    }
    if (p.get("tab") === "register") switchTab("register");
    if (p.get("tab") === "forgot") switchTab("forgot");

    // ── Auto-select user type from URL ──
    const type = p.get("type");
    if (type) {
        document
            .querySelectorAll('select[name="cmbUser"]')
            .forEach((select) => {
                for (let i = 0; i < select.options.length; i++) {
                    if (
                        select.options[i].value.toLowerCase() ===
                        type.toLowerCase()
                    ) {
                        select.selectedIndex = i;
                        if (select.id === "reg-type") toggleRegFields();
                        break;
                    }
                }
            });
    }
});
