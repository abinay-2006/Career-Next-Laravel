@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')

<div class="register-page">

    <div class="register-container">

        <!-- Header -->

        <div class="register-header">

            <a href="{{ route('register.index') }}" class="register-logo">

                <span class="logo-icon">
                    <i class="fas fa-briefcase"></i>
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


            <div class="progress-step completed">

                <div class="step-number">
                    <i class="fas fa-check"></i>
                </div>

                <span>Details</span>

            </div>


            <div class="progress-line active"></div>


            <div class="progress-step completed">

                <div class="step-number">
                    <i class="fas fa-check"></i>
                </div>

                <span>Profile</span>

            </div>


            <div class="progress-line active"></div>


            <div class="progress-step completed">

                <div class="step-number">
                    <i class="fas fa-check"></i>
                </div>

                <span>Review</span>

            </div>


            <div class="progress-line active"></div>


            <div class="progress-step active">

                <div class="step-number">
                    5
                </div>

                <span>Verify</span>

            </div>

        </div>


        <!-- Main Card -->

        <div class="registration-card">

            <!-- Header -->

            <div class="card-header">

                <div class="step-badge">

                    <i class="fas fa-envelope"></i>

                </div>

                <div>

                    <h1>
                        Verify your email
                    </h1>

                    <p>
                        Enter the verification code sent to your email address.
                    </p>

                </div>

            </div>


            <!-- Success Message -->

            @if(session('success'))

            <div class="registration-info-box">

                <i class="fas fa-check-circle"></i>

                <div>

                    <strong>
                        Verification code sent
                    </strong>

                    <p>
                        {{ session('success') }}
                    </p>

                </div>

            </div>

            @endif


            <!-- Validation Errors -->

            @if($errors->any())

            <div class="registration-alert">

                <i class="fas fa-circle-exclamation"></i>

                <div>

                    <strong>
                        Verification failed
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


            <!-- Email Display -->

            <div class="account-type-display">

                <i class="fas fa-envelope"></i>

                <div>

                    <strong>
                        Verification email sent to
                    </strong>

                    <span>
                        {{ $registration['email'] }}
                    </span>

                </div>

            </div>


            <!-- OTP Form -->

            <form
                method="POST"
                action="{{ route('register.verify.store') }}"
                id="otp-form">

                @csrf


                <!-- OTP -->

                <div class="form-group">

                    <label for="otp">

                        Verification Code

                        <span>*</span>

                    </label>


                    <div class="input-wrapper">

                        <i class="fas fa-key"></i>

                        <input
                            type="text"
                            id="otp"
                            name="otp"
                            value="{{ old('otp') }}"
                            placeholder="Enter 6-digit code"
                            maxlength="6"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required>

                    </div>


                    <small class="field-help">

                        Enter the 6-digit verification code sent to your email.
                        The code is valid for 10 minutes.

                    </small>


                    @error('otp')

                    <small class="field-error">
                        {{ $message }}
                    </small>

                    @enderror

                </div>


                <!-- Security Information -->

                <div class="registration-info-box">

                    <i class="fas fa-shield-alt"></i>

                    <div>

                        <strong>
                            Your account is not created yet
                        </strong>

                        <p>
                            Your Career Next account will only be created
                            after you successfully verify your email address.
                        </p>

                    </div>

                </div>


                <!-- Actions -->

                <div class="form-actions">

                    <a
                        href="{{ route('register.review') }}"
                        class="btn-secondary">

                        <i class="fas fa-arrow-left"></i>

                        Back to Review

                    </a>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="verify-button">

                        <span id="verify-text">

                            Verify Email

                            <i class="fas fa-check-circle"></i>

                        </span>

                    </button>

                </div>

            </form>


            <!-- Resend -->

            <div class="login-link">

                Didn't receive the code?

                <form
                    method="POST"
                    action="{{ route('register.resend-otp') }}"
                    id="resend-form"
                    style="display:inline;">

                    @csrf

                    <button
                        type="submit"
                        id="resend-button"
                        style="
                            border:0;
                            background:none;
                            padding:0;
                            color:#2563eb;
                            font-weight:600;
                            cursor:pointer;
                        ">

                        Resend Code

                    </button>

                </form>

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

</div>

@endsection


@section('scripts')

<script>
    /*
    |--------------------------------------------------------------------------
    | OTP Input
    |--------------------------------------------------------------------------
    */

    const otpInput =
        document.getElementById('otp');


    otpInput.addEventListener('input', function() {

        this.value =
            this.value
            .replace(/\D/g, '')
            .slice(0, 6);

    });


    /*
    |--------------------------------------------------------------------------
    | Verify Form
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('otp-form')
        .addEventListener('submit', function() {

            const button =
                document.getElementById('verify-button');

            const text =
                document.getElementById('verify-text');


            button.disabled = true;

            text.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        });


    /*
    |--------------------------------------------------------------------------
    | Resend Form
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('resend-form')
        .addEventListener('submit', function() {

            const button =
                document.getElementById('resend-button');


            button.disabled = true;

            button.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Sending...';

        });
</script>

@endsection