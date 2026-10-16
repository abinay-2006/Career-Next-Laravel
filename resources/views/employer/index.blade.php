@extends('layouts.employer')

@section('title', 'Employer Dashboard')

@section('content')
<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome to Control Panel 👋</h1>
            <p>Manage your job portal activity from one place.</p>
            <div class="role-badge">
                <i class="fas "></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration">🗂️</div>
    </div>
</div><!-- /main-content -->
@endsection
