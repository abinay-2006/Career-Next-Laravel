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
function showToast(type, msg) {
    if (typeof window.showAlert === "function") {
        window.showAlert(type === "success" ? "success" : "error", msg);
    }
}

function hideToast() {
    // Retained as a compatibility hook for older registration scripts.
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
    if (pass.length < 8) {
        showError("reg-error", "Password must be at least 8 characters.");
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

    if (!validateRegister()) {
        return false;
    }

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
            Accept: "application/json",
        },
    })
        .then(async (response) => {
            const contentType = response.headers.get("content-type") || "";

            if (contentType.includes("application/json")) {
                const data = await response.json(); // Define data here

                // Check response.ok after parsing JSON
                if (!response.ok) {
                    throw {
                        validation: true,
                        data: data,
                    };
                }

                return data;
            }

            const text = await response.text();

            console.error("Server returned non-JSON response:", text);

            throw {
                serverError: true,
                status: response.status,
                message:
                    "Server error (" +
                    response.status +
                    "). Check Laravel logs.",
            };
        })
        .then((data) => {
            if (data.success) {
                form.reset();

                switchTab("login");

                showToast(
                    "success",
                    data.message || "Account created successfully.",
                );

            } else {
                showError("reg-error", data.message || "Registration failed.");
            }

            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        })
        .catch((error) => {
            console.error("Registration error:", error);

            let message = "Registration failed. Please try again.";

            if (error.validation && error.data) {
                if (error.data.message) {
                    message = error.data.message;
                }

                if (error.data.errors) {
                    const firstField = Object.keys(error.data.errors)[0];

                    if (firstField) {
                        message = error.data.errors[firstField][0];
                    }
                }
            }

            showError("reg-error", message);

            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });

    return false;
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

    /* ── Auto-select account type from URL ───────────────────────────────────── */

    const type = p.get("type");

    if (type) {
        const normalizedType = type.toLowerCase();

        if (normalizedType === "jobseeker") {
            selectAccountType("JobSeeker");
        } else if (normalizedType === "employer") {
            selectAccountType("Employer");
        }
    }
});

/* ═══════════════════════════════════════════════════════════════════════════
   PHASE 1A — ACCOUNT TYPE SELECTION
   ═══════════════════════════════════════════════════════════════════════════ */

let selectedAccountType = null;

/**
 * Select Job Seeker / Employer
 */
function selectAccountType(type) {
    selectedAccountType = type;

    const jobSeekerCard = document.getElementById("account-jobseeker");
    const employerCard = document.getElementById("account-employer");

    const message = document.getElementById("selected-account-message");
    const continueButton = document.getElementById("account-continue");

    // Remove previous selection
    jobSeekerCard.classList.remove("selected");
    employerCard.classList.remove("selected");

    // Select current card
    if (type === "JobSeeker") {
        jobSeekerCard.classList.add("selected");

        message.innerHTML =
            '<i class="fas fa-user-tie"></i>' +
            "<span>Great! Let's create your Job Seeker account.</span>";
    } else if (type === "Employer") {
        employerCard.classList.add("selected");

        message.innerHTML =
            '<i class="fas fa-building"></i>' +
            "<span>Great! Let's create your Employer account.</span>";
    }

    // Style message as selected
    message.classList.add("selected");

    // Enable continue
    continueButton.disabled = false;
}

/**
 * Continue to registration form
 */
function continueRegistration() {
    if (!selectedAccountType) {
        return;
    }

    const typeScreen = document.getElementById("register-type-screen");

    const formScreen = document.getElementById("registration-form-screen");

    const typeInput = document.getElementById("reg-type");

    const title = document.getElementById("registration-form-title");

    const subtitle = document.getElementById("registration-form-subtitle");

    const nameLabel = document.getElementById("fname-label");

    const nameInput = document.getElementById("reg-name");

    // Store selected account type
    typeInput.value = selectedAccountType;

    // Customize form based on account type
    if (selectedAccountType === "JobSeeker") {
        title.textContent = "Create your Job Seeker account";

        subtitle.textContent = "Let's get you started with the basics";

        nameLabel.textContent = "Full Name";

        nameInput.placeholder = "Enter your full name";
    } else if (selectedAccountType === "Employer") {
        title.textContent = "Create your Employer account";

        subtitle.textContent = "Let's get your company started";

        nameLabel.textContent = "Company Name";

        nameInput.placeholder = "Enter your company name";
    }

    // Hide account selection
    typeScreen.style.display = "none";

    // Show registration form
    formScreen.style.display = "block";
}

/**
 * Go back to account selection
 */
function backToAccountType() {
    const typeScreen = document.getElementById("register-type-screen");

    const formScreen = document.getElementById("registration-form-screen");

    formScreen.style.display = "none";
    typeScreen.style.display = "block";
}
