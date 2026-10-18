@extends('layouts.auth')

@section('title', 'Login')

@section('content')

<!-- ── Floating Toast ─────────────────────────────────────────────────────── -->

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
            <button
                type="button"
                class="tab-btn"
                onclick="window.location.href='{{ route('register.index') }}'">
                Register
            </button>
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
                        <select name="cmbUser" id="login-type" required>
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
                Don't have an account? <a
                    href="{{ route('register.index') }}"
                    style="color:purple">
                    Create one
                </a>
            </div>
            <div class="back-home">
                <a href="{{ url('/') }}"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>

        <!-- ══ FORGOT PASSWORD ══ -->
        <div class="panel" id="panel-forgot">
            <div class="panel-title">Reset password 🔑</div>
            <div class="panel-subtitle">We'll send a reset link to your email</div>

            <div class="alert error" id="forgot-error"> <i class="fas fa-circle-exclamation"></i><span></span></div>
            <div class="alert success" id="forgot-success"><i class="fas fa-circle-check"></i><span></span></div>

            <form method="POST" action="{{ route('forgot.send') }}">
                @csrf
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope lead-icon"></i>
                        <input type="email" name="email" id="forgot-email" placeholder="your@email.com" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
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
        if (name === 'register') {
            window.location.href = "{{ route('register.index') }}";
            return;
        }

        const order = ['login', 'forgot'];

        document.querySelectorAll('.tab-btn').forEach((btn, i) => {
            btn.classList.toggle(
                'active',
                order[i] === name
            );
        });

        document
            .querySelectorAll('.panel')
            .forEach(panel => {
                panel.classList.remove('active');
            });

        const target =
            document.getElementById(
                'panel-' + name
            );

        if (target) {
            target.classList.add('active');
        }
    }

</script>
@endsection
