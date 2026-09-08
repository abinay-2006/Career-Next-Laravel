@extends('layouts.auth')

@section('title', 'Login')

@section('content')

@if(session('success'))
<div id="successMessage" class="alert alert-success">
    {{ session('success') }}
</div>
@endif
<!-- ── Floating Toast ─────────────────────────────────────────────────────── -->
<div class="toast" id="toast" role="alert">
    <i class="fas toast-icon" id="toast-icon"></i>
    <span id="toast-msg"></span>
    <button class="toast-close" onclick="hideToast()" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <div class="toast-progress"></div>
</div>

<div class="page-wrapper">

    <!-- ════════════ LEFT PANEL ════════════ -->
    <div class="left-panel">
        <div class="brand">
            <h1 class="brand-logo">
                <img src="{{ asset('assets/images/CareerNext.png') }}" alt="Career Next">
                <span>Career Next</span>
            </h1>
        </div>

        <div class="left-features">
            <h2>Find your dream job with us today</h2>
            <div class="feature-item">
                <div class="dot"><i class="fas fa-search"></i></div>
                <span>Browse thousands of curated job listings across all industries</span>
            </div>
            <div class="feature-item">
                <div class="dot"><i class="fas fa-bolt"></i></div>
                <span>Apply in one click and track all your applications in real time</span>
            </div>
            <div class="feature-item">
                <div class="dot"><i class="fas fa-bell"></i></div>
                <span>Get instant alerts for jobs that match your skills and profile</span>
            </div>
        </div>

        <div class="left-stats">
            <h2>Your Gateway to Professional Success</h2>
        </div>
    </div>

    <!-- ════════════ RIGHT PANEL ════════════ -->
    <div class="right-panel">

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Sign In</button>
            <button class="tab-btn" onclick="switchTab('register')">Register</button>
        </div>

        <!-- ══ SIGN IN ══ -->
        <div class="panel active" id="panel-login">
            <div class="panel-title">Welcome back 👋</div>
            <div class="panel-subtitle">Sign in to access your account</div>

            <div class="alert error" id="login-error">
                <i class="fas fa-circle-exclamation"></i><span></span>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label>I am a</label>
                    <div class="input-wrap">
                        <i class="fas fa-users lead-icon"></i>
                        <select name="cmbUser" id="login-type">
                            <option value="">Select User Type</option>
                            <option value="JobSeeker">Job Seeker</option>
                            <option value="Employer">Employer</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Username or Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-user lead-icon"></i>
                        <input type="text" name="txtUser" id="login-user"
                            placeholder="Enter your username or email"
                            autocomplete="username"
                            value="{{ old('txtUser') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock lead-icon"></i>
                        <input type="password" name="txtPass" id="login-pass"
                            placeholder="Enter your password"
                            autocomplete="current-password">
                        <button type="button" class="toggle-pass" onclick="togglePass('login-pass',this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="forgot-link">
                    <a onclick="switchTab('forgot')">Forgot password?</a>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="switch-text">
                Don't have an account? <a style="color:purple" onclick="switchTab('register')">Create one</a>
            </div>
            <div class="back-home">
                <a href="{{ url('/') }}"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>

        <!-- ══ REGISTER ══ -->
        <div class="panel" id="panel-register">

            <!-- ══ ACCOUNT TYPE SELECTION ══ -->
            <div id="register-type-screen">

                <div class="panel-title">Create your account ✨</div>

                <div class="panel-subtitle">
                    Choose how you want to use Career Next
                </div>

                <div class="account-type-options">

                    <!-- JOB SEEKER -->
                    <button
                        type="button"
                        class="account-type-card"
                        id="account-jobseeker"
                        onclick="selectAccountType('JobSeeker')">

                        <div class="account-type-icon jobseeker-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>

                        <div class="account-type-content">
                            <h3>Job Seeker</h3>

                            <p>
                                I'm looking for a job and new career opportunities.
                            </p>

                            <div class="account-type-features">
                                <span>
                                    <i class="fas fa-check"></i>
                                    Find jobs
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Apply easily
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Build your profile
                                </span>
                            </div>
                        </div>

                        <div class="account-type-check">
                            <i class="fas fa-check"></i>
                        </div>

                    </button>


                    <!-- EMPLOYER -->
                    <button
                        type="button"
                        class="account-type-card"
                        id="account-employer"
                        onclick="selectAccountType('Employer')">

                        <div class="account-type-icon employer-icon">
                            <i class="fas fa-building"></i>
                        </div>

                        <div class="account-type-content">
                            <h3>Employer</h3>

                            <p>
                                I'm hiring and looking for talented candidates.
                            </p>

                            <div class="account-type-features">
                                <span>
                                    <i class="fas fa-check"></i>
                                    Post jobs
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Find candidates
                                </span>

                                <span>
                                    <i class="fas fa-check"></i>
                                    Manage applications
                                </span>
                            </div>
                        </div>

                        <div class="account-type-check">
                            <i class="fas fa-check"></i>
                        </div>

                    </button>

                </div>


                <!-- Selected account -->
                <div class="selected-account-message" id="selected-account-message">
                    <i class="fas fa-circle-info"></i>
                    <span>Please select an account type to continue.</span>
                </div>


                <button
                    type="button"
                    class="btn-primary account-continue-btn"
                    id="account-continue"
                    onclick="continueRegistration()"
                    disabled>

                    Continue
                    <i class="fas fa-arrow-right"></i>

                </button>


                <div class="switch-text">
                    Already have an account?
                    <a onclick="switchTab('login')">Sign in</a>
                </div>

                <div class="back-home">
                    <a href="{{ url('/') }}">
                        <i class="fas fa-arrow-left"></i>
                        Back to Home
                    </a>
                </div>

            </div>


            <!-- ══ EXISTING REGISTRATION FORM ══ -->
            <div id="registration-form-screen" style="display:none;">

                <div class="panel-title" id="registration-form-title">
                    Create your account ✨
                </div>

                <div class="panel-subtitle" id="registration-form-subtitle">
                    Enter your account details
                </div>

                <div class="alert error" id="reg-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span></span>
                </div>

                <div class="alert success" id="reg-success">
                    <i class="fas fa-circle-check"></i>
                    <span></span>
                </div>


                <form
                    method="POST"
                    action="{{ route('register') }}"
                    onsubmit="return handleRegisterSubmit(event)">

                    @csrf

                    <!-- Hidden account type -->
                    <input
                        type="hidden"
                        name="cmbUser"
                        id="reg-type"
                        value="">


                    <div class="form-row">

                        <div class="form-group">

                            <label id="fname-label">
                                Full Name
                            </label>

                            <div class="input-wrap">

                                <i class="fas fa-id-card lead-icon"></i>

                                <input
                                    type="text"
                                    name="txtName"
                                    id="reg-name"
                                    placeholder="Your full name">

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Username
                            </label>

                            <div class="input-wrap">

                                <i class="fas fa-user lead-icon"></i>

                                <input
                                    type="text"
                                    name="txtUser"
                                    id="reg-user"
                                    placeholder="Choose a username">

                            </div>

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Email Address
                        </label>

                        <div class="input-wrap">

                            <i class="fas fa-envelope lead-icon"></i>

                            <input
                                type="email"
                                name="txtEmail"
                                id="reg-email"
                                placeholder="your@email.com">

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Password
                        </label>

                        <div class="input-wrap">

                            <i class="fas fa-lock lead-icon"></i>

                            <input
                                type="password"
                                name="txtPass"
                                id="reg-pass"
                                placeholder="Create a password"
                                oninput="checkStrength(this.value)"
                                autocomplete="new-password">

                            <button
                                type="button"
                                class="toggle-pass"
                                onclick="togglePass('reg-pass',this)">

                                <i class="fas fa-eye"></i>

                            </button>

                        </div>


                        <div
                            class="pw-strength"
                            id="pw-strength">

                            <div class="pw-bars">

                                <div class="pw-bar" id="bar1"></div>
                                <div class="pw-bar" id="bar2"></div>
                                <div class="pw-bar" id="bar3"></div>
                                <div class="pw-bar" id="bar4"></div>

                            </div>

                            <span
                                class="pw-label"
                                id="pw-label">
                            </span>

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Confirm Password
                        </label>

                        <div class="input-wrap">

                            <i class="fas fa-lock lead-icon"></i>

                            <input
                                type="password"
                                name="txtConfirm"
                                id="reg-confirm"
                                placeholder="Re-enter password"
                                autocomplete="new-password">

                            <button
                                type="button"
                                class="toggle-pass"
                                onclick="togglePass('reg-confirm',this)">

                                <i class="fas fa-eye"></i>

                            </button>

                        </div>

                    </div>


                    <div class="checkbox-wrap">

                        <input
                            type="checkbox"
                            id="terms"
                            name="terms">

                        <label for="terms">

                            I agree to the
                            <a href="#" target="_blank">
                                Terms of Service
                            </a>

                            and

                            <a href="#" target="_blank">
                                Privacy Policy
                            </a>

                        </label>

                    </div>


                    <button
                        type="submit"
                        class="btn-primary">

                        <i class="fas fa-user-plus"></i>
                        Create Account

                    </button>

                </form>


                <div class="change-account-type">

                    <button
                        type="button"
                        onclick="backToAccountType()">

                        <i class="fas fa-arrow-left"></i>
                        Change account type

                    </button>

                </div>


                <div class="switch-text">

                    Already have an account?
                    <a onclick="switchTab('login')">
                        Sign in
                    </a>

                </div>


                <div class="back-home">

                    <a href="{{ url('/') }}">

                        <i class="fas fa-arrow-left"></i>
                        Back to Home

                    </a>

                </div>

            </div>

        </div>

        <!-- ══ FORGOT PASSWORD ══ -->
        <div class="panel" id="panel-forgot">
            <div class="panel-title">Reset password 🔑</div>
            <div class="panel-subtitle">We'll send a reset link to your email</div>

            <div class="alert error" id="forgot-error"> <i class="fas fa-circle-exclamation"></i><span></span></div>
            <div class="alert success" id="forgot-success"><i class="fas fa-circle-check"></i><span></span></div>

            <form method="POST" action="{{ route('forgot') }}" onsubmit="return handleForgotSubmit(event)">

                <div class="form-group">
                    <label>Account Type</label>
                    <div class="input-wrap">
                        <i class="fas fa-users lead-icon"></i>
                        <select name="cmbUser">
                            <option value="JobSeeker">Job Seeker</option>
                            <option value="Employer">Employer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrap">
                        <i class="fas fa-user lead-icon"></i>
                        <input type="text" name="txtUser" id="forgot-user" placeholder="Your username">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope lead-icon"></i>
                        <input type="email" name="txtEmail" id="forgot-email" placeholder="your@email.com">
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div class="divider">or</div>

            <div class="switch-text">
                Remember it now? <a onclick="switchTab('login')">Sign in</a>
            </div>
            <div class="back-home">
                <a href="{{ url('/') }}"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>

    </div><!-- /right-panel -->
</div><!-- /page-wrapper -->
@endsection
@section('scripts')
<script>
    function switchTab(name) {
        const order = ['login', 'register', 'forgot'];
        document.querySelectorAll('.tab-btn').forEach((btn, i) => {
            btn.classList.toggle('active', order[i] === name);
        });
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + name).classList.add('active');
    }

    setTimeout(function() {
        const message = document.getElementById('successMessage');

        if (message) {
            message.style.transition = 'opacity 0.4s ease';
            message.style.opacity = '0';

            setTimeout(function() {
                message.remove();
            }, 400);
        }
    }, 3000);
</script>
@endsection