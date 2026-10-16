@extends('layouts.employer')

@section('title','Walkins')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Page header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class="fas fa-inbox"></i></div>
            <div class="page-header-text">
                <h1>Applications</h1>
                <p>Review candidates who applied to your job listings</p>
            </div>
        </div>
        <a href="{{route('employer.dashboard')}}" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Panel</a>
    </div>

    @if($jobs->isEmpty())
    <!-- No jobs posted yet -->
    <div class="no-jobs-card">
        <i class="fas fa-briefcase"></i>
        <p>You haven't posted any jobs yet. <a href="{{ route('employer.jobs.index') }}" style="color:rgba(255,255,255,0.8);text-decoration:underline;">Post a job</a> to start receiving applications.</p>
    </div>

    @else

    <!-- ── Filter card ── -->
    <div class="section-title"><i class="fas fa-filter"></i> Filter by Job</div>

    <div class="glass-card">
        <form method="GET" action="{{ route('employer.applications.index') }}">
            <div class="filter-bar">
                <div class="filter-group">
                    <label class="filter-label" for="cmbTitle"><i class="fas fa-list-check"></i> Select Job Title</label>
                    <select class="form-select" name="cmbTitle" id="cmbTitle">
                        <option value="" disabled {{ !$selectedJobId ? 'selected' : '' }}>— Choose a job listing —</option>
                        @foreach($jobs as $job)
                        <option value="{{ $job->JobId }}"
                            {{ $selectedJobId == $job->JobId ? 'selected' : '' }}>
                            {{ $job->JobTitle }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-view">
                    <i class="fas fa-magnifying-glass"></i> View Applications
                </button>
            </div>
        </form>

        @if ($selectedJobId !== null)
        <!-- Stats strip -->
        @php
        $total = $applications->count();

        $accepted = $applications
        ->whereIn('Status', ['Accepted', 'Approved'])
        ->count();

        $rejected = $applications
        ->whereIn('Status', ['Rejected', 'Declined'])
        ->count();

        $pending = $total - $accepted - $rejected;
        @endphp
        <div class="stats-strip">
            <span class="stat-pill">
                <i class="fas fa-users"></i>
                {{ $total }} Total
            </span>
            @if ($pending > 0)<span class="stat-pill" style="border-color:rgba(251,191,36,0.3);color:#fde68a;"><i class="fas fa-clock"></i>{{ $pending }} Pending</span>@endif
            @if ($accepted > 0)<span class="stat-pill" style="border-color:rgba(74,222,128,0.3);color:#86efac;"><i class="fas fa-circle-check"></i>{{ $accepted }} Accepted</span>@endif
            @if ($rejected > 0)<span class="stat-pill" style="border-color:rgba(239,68,68,0.3);color:#fca5a5;"><i class="fas fa-circle-xmark"></i>{{ $rejected }} Rejected</span>@endif
        </div>
        @endif
    </div>

    <!-- ── Results table ── -->
    @if ($selectedJobId !== null)

    <div class="section-title"><i class="fas fa-users"></i> Applicants</div>

    <div class="glass-card delay">
        <div class="table-header">
            <div class="table-header-left">
                <div class="table-header-icon"><i class="fas fa-user-group"></i></div>
                <div>
                    <div class="table-title">{{ optional($selectedJob)->JobTitle }}</div>
                    <div class="table-subtitle">Applicants for this listing</div>
                </div>
            </div>
            <span class="record-count">{{ $applications->count() }}
                Applicant{{ $applications->count() != 1 ? 's' : '' }}</span>
        </div>

        @if($applications->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>App ID</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $app)

                    @php
                    $applicantName = $app->jobSeeker?->JobSeekerName;
                    $initials = $applicantName ? strtoupper(substr($applicantName, 0, 1)) : '?';
                    @endphp

                    <tr>
                        <td><span class="id-badge">{{ $app->ApplicationId }}</span></td>
                        <td>
                            <div class="applicant-cell">
                                <div class="applicant-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="applicant-name">{{ $applicantName ?: 'Applicant unavailable' }}</div>
                                    <div class="applicant-city"><i class="fas fa-location-dot" style="font-size:0.65rem;opacity:0.6;"></i>{{ $app->jobSeeker?->City }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="email-cell">{{ $app->jobSeeker?->Email }}</td>
                        <td>{{ $app->Status }}</td>
                        <td>
                            <a href="{{ route('employer.applications.show', $app->ApplicationId) }}"
                                class="btn-view-profile">
                                <i class="fas fa-eye"></i> View &amp; Send
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @else
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No applications received for this job listing yet.</p>
        </div>
        @endif
    </div>

    @endif

    @endif

</div><!-- /main-content -->
@endsection
@section('scripts')
<script>
    // Auto-submit the form when the select changes
    document.getElementById('cmbTitle')?.addEventListener('change', function() {
        if (this.value) this.closest('form').submit();
    });
</script>
@endsection
