@extends('layouts.admin')

@section('title', 'Admin Home')

@section('content')


<!-- ══════════ MAIN ══════════ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome back, {{ session('Name') }}! 👋</h1>
            <p>You're signed in to the Career Next portal.</p>
            <div class="role-badge">
                <i class="fas fa-user-circle"></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        
    </div>

    </div><!-- /main-content -->

@endsection
