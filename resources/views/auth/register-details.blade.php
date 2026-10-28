@extends('layouts.auth')

@section('title', 'Account Details')

@section('content')

<div class="register-page">

    <div class="register-container">

        <!-- Header -->

        <div class="register-header">

            <a href="{{ route('register.index') }}" class="register-logo">

                <span class="logo-icon">
                    <img
                        src="{{ asset('images/CareerNext.png') }}"
                        alt="Career Next Logo"
                        width="42"
                        height="42">
                </span>

                <span>Career Next</span>

            </a>

        </div>

        <!-- Progress -->

        <div class="registration-progress">

            <div class="progress-step completed">

                <div class="step-number">
                    <i class="fas fa-check"></i>
                </div>

                <span>Account</span>

            </div>


            <div class="progress-line active"></div>


            <div class="progress-step active">

                <div class="step-number">
                    2
                </div>

                <span>Details</span>

            </div>


            <div class="progress-line"></div>


            <div class="progress-step">

                <div class="step-number">
                    3
                </div>

                <span>Profile</span>

            </div>


            <div class="progress-line"></div>


            <div class="progress-step">

                <div class="step-number">
                    4
                </div>

                <span>Review</span>

            </div>


            <div class="progress-line"></div>


            <div class="progress-step">

                <div class="step-number">
                    5
                </div>

                <span>Verify</span>

            </div>

        </div>


        <!-- Main Card -->

        <div class="registration-card">

            <div class="card-header">

                <div class="step-badge">

                    <i class="fas fa-user-plus"></i>

                </div>

                <div>

                    <h1>
                        Create your account
                    </h1>

                    <p>
                        Enter your basic account information to get started.
                    </p>

                </div>

            </div>


            <!-- Account Type -->

            <div class="account-type-display">

                @if($accountType === 'JobSeeker')

                <i class="fas fa-user-tie"></i>

                <div>

                    <strong>Job Seeker Account</strong>

                    <span>
                        Looking for jobs and career opportunities
                    </span>

                </div>

                @else

                <i class="fas fa-building"></i>

                <div>

                    <strong>Employer Account</strong>

                    <span>
                        Hiring and looking for talented candidates
                    </span>

                </div>

                @endif


                <a href="{{ route('register.index') }}">
                    Change
                </a>

            </div>


            <!-- Validation Errors -->

            @if($errors->any())

            <div class="registration-alert">

                <i class="fas fa-circle-exclamation"></i>

                <div>

                    <strong>
                        Please check the following:
                    </strong>

                    <ul>

                        @foreach($errors->all() as $error)

                        <li>
                            {{ $error }}
                        </li>

                        @endforeach

                    </ul>

                </div>

            </div>

            @endif


            <!-- Form -->

            <form
                method="POST"
                action="{{ route('register.details.store') }}"
                id="account-details-form">

                @csrf


                <!-- Name -->

                <div class="form-group">

                    <label for="name">

                        @if($accountType === 'JobSeeker')
                        Full Name
                        @else
                        Company Name
                        @endif

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-user"></i>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="{{ $accountType === 'JobSeeker' ? 'Enter your full name' : 'Enter your company name' }}"
                            autocomplete="name"
                            required>

                    </div>

                    @error('name')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Username -->

                <div class="form-group">

                    <label for="username">

                        Username

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-at"></i>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="{{ old('username') }}"
                            placeholder="Choose a username"
                            autocomplete="username"
                            required>

                    </div>


                    <small class="field-help">

                        Use letters, numbers, dots, underscores or hyphens.

                    </small>


                    @error('username')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Email -->

                <div class="form-group">

                    <label for="email">

                        Email Address

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-envelope"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required>

                    </div>


                    <small class="field-help">

                        We'll verify this email address before creating your account.

                    </small>


                    @error('email')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Mobile -->

                <div class="form-group">

                    <label for="mobile">

                        Mobile Number

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-phone"></i>

                        <input
                            type="tel"
                            id="mobile"
                            name="mobile"
                            value="{{ old('mobile') }}"
                            placeholder="Enter your mobile number"
                            autocomplete="tel"
                            required>

                    </div>


                    @error('mobile')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Password -->

                <div class="form-group">

                    <label for="password">

                        Password

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a strong password"
                            autocomplete="new-password"
                            required>


                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword('password', this)"
                            aria-label="Show password">

                            <i class="fas fa-eye"></i>

                        </button>

                    </div>


                    <!-- Password Strength -->

                    <div class="password-strength">

                        <div class="strength-bar">

                            <span id="strength-bar"></span>

                        </div>

                        <span id="strength-text">
                            Enter a password
                        </span>

                    </div>


                    <small class="field-help">

                        Minimum 8 characters. Use a combination of letters,
                        numbers and symbols.

                    </small>


                    @error('password')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Confirm Password -->

                <div class="form-group">

                    <label for="password_confirmation">

                        Confirm Password

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-lock"></i>

                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Re-enter your password"
                            autocomplete="new-password"
                            required>


                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword('password_confirmation', this)"
                            aria-label="Show password">

                            <i class="fas fa-eye"></i>

                        </button>

                    </div>


                    <small
                        class="password-match"
                        id="password-match"></small>

                </div>


                <!-- Actions -->

                <div class="form-actions">

                    <a
                        href="{{ route('register.index') }}"
                        class="btn-secondary">

                        <i class="fas fa-arrow-left"></i>

                        Back

                    </a>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="continue-button">

                        Continue

                        <i class="fas fa-arrow-right"></i>

                    </button>

                </div>

            </form>


            <!-- Login -->

            <div class="login-link">

                Already have an account?

                <a href="{{ route('login') }}">
                    Sign in
                </a>

            </div>

        </div>


        <!-- Footer -->

        <div class="registration-footer">

            <span>
                <i class="fas fa-lock"></i>
                Your information is securely protected
            </span>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Password Strength
    |--------------------------------------------------------------------------
    */

    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    passwordInput.addEventListener('input', function() {

        const password = this.value;

        let score = 0;


        if (password.length >= 8) {
            score++;
        }

        if (/[a-z]/.test(password)) {
            score++;
        }

        if (/[A-Z]/.test(password)) {
            score++;
        }

        if (/[0-9]/.test(password)) {
            score++;
        }

        if (/[^A-Za-z0-9]/.test(password)) {
            score++;
        }


        if (password.length === 0) {

            strengthBar.style.width = '0%';
            strengthText.textContent = 'Enter a password';

        } else if (score <= 2) {

            strengthBar.style.width = '30%';
            strengthText.textContent = 'Weak';

        } else if (score === 3) {

            strengthBar.style.width = '55%';
            strengthText.textContent = 'Fair';

        } else if (score === 4) {

            strengthBar.style.width = '80%';
            strengthText.textContent = 'Good';

        } else {

            strengthBar.style.width = '100%';
            strengthText.textContent = 'Strong';

        }

    });


    /*
    |--------------------------------------------------------------------------
    | Password Match
    |--------------------------------------------------------------------------
    */

    const confirmPassword =
        document.getElementById('password_confirmation');

    const passwordMatch =
        document.getElementById('password-match');


    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmation = confirmPassword.value;


        if (!confirmation) {

            passwordMatch.textContent = '';

            return;
        }


        if (password === confirmation) {

            passwordMatch.textContent = 'Passwords match';
            passwordMatch.className = 'password-match valid';

        } else {

            passwordMatch.textContent = 'Passwords do not match';
            passwordMatch.className = 'password-match invalid';

        }
    }


    confirmPassword.addEventListener(
        'input',
        checkPasswordMatch
    );

    passwordInput.addEventListener(
        'input',
        checkPasswordMatch
    );


    /*
    |--------------------------------------------------------------------------
    | Prevent accidental double submission
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('account-details-form')
        .addEventListener('submit', function() {

            const button =
                document.getElementById('continue-button');

            window.CareerNextButtonLoading?.start(button, 'Saving...');

        });
</script>

@endsection
