@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<div class="page-wrapper">
    <div class="left-panel">
        <div class="brand"><h1 class="brand-logo"><img src="{{ asset('assets/images/CareerNext.png') }}" alt="Career Next"><span>Career Next</span></h1></div>
        <div class="left-features">
            <h2>Get back to your next opportunity</h2>
            <div class="feature-item"><div class="dot"><i class="fas fa-envelope"></i></div><span>Verify your identity with a one-time code</span></div>
            <div class="feature-item"><div class="dot"><i class="fas fa-lock"></i></div><span>Choose a new password for your account</span></div>
        </div>
    </div>
    <div class="right-panel">
        <div class="panel active">
            @if(isset($token))
                <div class="panel-title">Choose a new password</div>
                <div class="panel-subtitle">Your code is verified. Set a new password to finish.</div>
                <form method="POST" action="{{ route('forgot.reset', $token) }}">
                    @csrf
                    <div class="form-group"><label for="password">New Password</label><div class="input-wrap"><i class="fas fa-lock lead-icon"></i><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div></div>
                    <div class="form-group"><label for="password_confirmation">Confirm New Password</label><div class="input-wrap"><i class="fas fa-lock lead-icon"></i><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div></div>
                    <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Reset Password</button>
                </form>
            @elseif(($step ?? null) === 'otp')
                <div class="panel-title">Enter verification code</div>
                <div class="panel-subtitle">Enter the six-digit code we sent to your email. It expires in 10 minutes.</div>
                <form method="POST" action="{{ route('forgot.verify.submit') }}">
                    @csrf
                    <div class="form-group"><label for="otp">Verification Code</label><div class="input-wrap"><i class="fas fa-key lead-icon"></i><input id="otp" type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></div></div>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Verify Code</button>
                </form>
            @else
                <div class="panel-title">Reset your password</div>
                <div class="panel-subtitle">Enter the email address for your account and we’ll send a one-time verification code.</div>
                <form method="POST" action="{{ route('forgot.send') }}">
                    @csrf
                    <div class="form-group"><label for="email">Email Address</label><div class="input-wrap"><i class="fas fa-envelope lead-icon"></i><input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" required autocomplete="email" autofocus></div></div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Verification Code</button>
                </form>
            @endif
            @if(session('status'))<div class="alert success mt-3" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert error mt-3" role="alert">{{ $errors->first() }}</div>@endif
            <div class="switch-text">Remember your password? <a href="{{ route('login') }}">Sign in</a></div>
            <div class="back-home"><a href="{{ url('/') }}"><i class="fas fa-arrow-left"></i> Back to Home</a></div>
        </div>
    </div>
</div>
@endsection
