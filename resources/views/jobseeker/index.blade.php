@extends('layouts.jobseeker')

@section('title','Jobseeker Portal')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome to Control Panel 👋</h1>
            <p>Manage your job search and career activity from one place.<br>
                Use the quick actions below to navigate.</p>
            <div class="role-badge">
                <i class="fas "></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        <div class="welcome-illustration">🗂️</div>
    </div>

    <!-- Quick Actions -->
    <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>

    <div class="cards-grid">

        <a href="{{ route('jobseeker.education') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-graduation-cap"></i></div>
            <div>
                <div class="card-title">Education</div>
                <div class="card-desc">Add or update your educational qualifications and certificates.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Update</div>
        </a>

        <a href="{{ route('jobseeker.jobs') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-magnifying-glass"></i></div>
            <div>
                <div class="card-title">Search Job</div>
                <div class="card-desc">Browse and apply for available job openings on the portal.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Explore</div>
        </a>

        <a href="{{ route('jobseeker.walkins') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div>
                <div class="card-title">Walk-in</div>
                <div class="card-desc">View and register for upcoming walk-in interview drives.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> View</div>
        </a>
    </div>
</div><!-- /main-content -->
@endsection