@extends('layouts.admin')

@section('title', 'Admin Home')

@section('content')


<!-- ══════════ MAIN ══════════ -->
<div class="main-content">

    <!-- Welcome hero -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1>Welcome back, {{ session('Name') }}! 👋</h1>
            <p>You're signed in to the Career Next portal.<br>
                Head to your dashboard to manage your activity.</p>
            <div class="role-badge">
                <i class="fas fa-user-circle"></i>
                {{ session('UserType') }} Account
            </div>
        </div>
        
    </div>

    <!-- ── Quick actions ── -->
    <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>

    <!-- ADMIN: 2-column equal grid, no inline sizes -->
    <div class="cards-grid admin-grid">

        <a href="{{ url('/admin/dashboard') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-gauge"></i></div>
            <div>
                <div class="card-title">My Dashboard</div>
                <div class="card-desc">View your personalised control panel and manage everything in one place.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Open</div>
        </a>

        <a href="{{ url('/admin/manage-users') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-users-gear"></i></div>
            <div>
                <div class="card-title">Manage Users</div>
                <div class="card-desc">Approve, suspend, or remove job seeker and employer accounts.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Manage</div>
        </a>

        <a href="{{ url('/admin/manage-jobs') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-briefcase"></i></div>
            <div>
                <div class="card-title">Manage Jobs</div>
                <div class="card-desc">Oversee all job listings and remove any that violate guidelines.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Manage</div>
        </a>

        <a href="{{ url('/admin/manage-documents') }}" class="action-card">
            <div class="card-icon"><i class="fas fa-file-shield"></i></div>
            <div>
                <div class="card-title">Manage Documents</div>
                <div class="card-desc">Verify or reject uploaded documents from job seekers.</div>
            </div>
            <div class="card-arrow"><i class="fas fa-arrow-right"></i> Verify</div>
        </a>

    </div>

</div><!-- /main-content -->

@endsection