@extends('layouts.employer')

@section('title', 'Employer Dashboard')

@section('content')
<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome, {{ session('UserName') }}!</h1>
            <p>Manage your hiring activity, job listings, and candidate applications from one place.</p>
            <div class="role-badge">
                <i class="fas fa-building"></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration"><i class="fas fa-briefcase" aria-hidden="true"></i></div>
    </div>


    @include('components.dashboard-analytics', ['role' => 'employer', 'analytics' => $analytics])
</div><!-- /main-content -->
@endsection
