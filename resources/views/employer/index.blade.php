@extends('layouts.employer')

@section('title', 'Employer Dashboard')

@section('content')
<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome to Control Panel 👋</h1>
            <p>Manage your job portal activity from one place.<br>
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
        <a href="{{ route('employer.jobs.index') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-list-check"></i></div>
            <div>
                <div class="card-title">Manage Jobs</div>
                <div class="card-desc">Post, edit, or remove job listings from the portal.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Manage</div>
        </a>

        <a href="{{ route('employer.walkins.index') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div>
                <div class="card-title">Walk-in</div>
                <div class="card-desc">Manage walk-in interview schedules and candidates.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> View</div>
        </a>
        <a href="{{ route('employer.applications.index') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-file-lines"></i></div>
            <div>
                <div class="card-title">Applications</div>
                <div class="card-desc">Review and track all submitted job applications.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Review</div>
        </a>
    </div>
</div><!-- /main-content -->
@endsection
