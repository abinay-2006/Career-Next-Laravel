@extends('layouts.auth')

@section('title', 'Reset password')

@section('content')
<div class="page-wrapper">
    <div class="left-panel">
        <div class="brand">
            <h1 class="brand-logo">
                <img src="{{ asset('assets/images/CareerNext.png') }}" alt="Career Next">
                <span>Career Next</span>
            </h1>
        </div>

        <div class="left-features">
            <h2>Get back to your next opportunity</h2>
            <div class="feature-item">
                <div class="dot"><i class="fas fa-envelope"></i></div>
                <span>Verify your email with a one-time code</span>
            </div>
            <div class="feature-item">
                <div class="dot"><i class="fas fa-lock"></i></div>
                <span>Choose a new password to secure your account</span>
            </div>
        </div>

        <div class="left-stats">
            <h2>Your Gateway to Professional Success</h2>
        </div>
    </div>

    <div class="right-panel">
        <div class="panel active">
            <div class="panel-title">Reset your password</div>
            @php($reset = session('password_reset'))

            @if (!$reset)
                <div class="panel-subtitle">Enter your account email and we’ll send you a verification code.</div>
            @elseif (!$reset['verified'])
                <div class="panel-subtitle">Enter the 6-digit code sent to {{ $reset['email'] }}.</div>
            @else
                <div class="panel-subtitle">Your email is verified. Choose a new password.</div>
            @endif

            @if (session('status'))
                <div class="alert success" role="status"><i class="fas fa-circle-check"></i><span>{{ session('status') }}</span></div>
            @endif
            @if ($errors->any())
                <div class="alert error" role="alert"><i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
            @endif

            @if (!$reset)
                <form method="POST" action="{{ route('forgot.send') }}">
                    @csrf
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope lead-icon"></i>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="your@email.com" required autocomplete="email" autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Verification Code</button>
                </form>
            @elseif (!$reset['verified'])
                <form method="POST" action="{{ route('forgot.verify') }}">
                    @csrf
                    <div class="form-group">
                        <label for="otp">Verification Code</label>
                        <div class="input-wrap">
                            <i class="fas fa-key lead-icon"></i>
                            <input id="otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" required autocomplete="one-time-code" autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Verify Code</button>
                </form>
                <form method="POST" action="{{ route('forgot.send') }}" class="forgot-resend-form">
                    @csrf
                    <input type="hidden" name="email" value="{{ $reset['email'] }}">
                    <button type="submit" class="forgot-resend-button">Didn’t receive a code? Send a new one</button>
                </form>
            @else
                <form method="POST" action="{{ route('forgot.reset') }}">
                    @csrf
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock lead-icon"></i>
                            <input id="password" type="password" name="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password" autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock lead-icon"></i>
                            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Re-enter your password" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Reset Password</button>
                </form>
            @endif

            <div class="switch-text">Remember your password? <a href="{{ route('login') }}">Sign in</a></div>
            <div class="back-home"><a href="{{ url('/') }}"><i class="fas fa-arrow-left"></i> Back to Home</a></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .forgot-resend-form { margin-top: 14px; text-align: center; }
    .forgot-resend-button { border: 0; background: none; color: #667eea; font: inherit; font-size: .85rem; cursor: pointer; }
    .forgot-resend-button:hover { text-decoration: underline; }
</style>
@endpush
