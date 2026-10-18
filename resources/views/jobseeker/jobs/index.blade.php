@extends('layouts.jobseeker')

@section('title', 'Find Jobs')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-icon"><i class="fas fa-magnifying-glass"></i></div>
        <div class="page-header-text">
            <h1>Search Job</h1>
            <p>Find and apply for jobs matching your qualifications</p>
        </div>
    </div>

    <!-- ── Search Form ── -->
    <div class="section-title"><i class="fas fa-sliders"></i> Filter Jobs</div>
    <div class="glass-card">
        <form method="GET"
            action="{{ route('jobseeker.jobs') }}">
            <div class="search-grid">

                <div class="form-group">
                    <label><i class="fas fa-graduation-cap"></i> Qualification</label>
                    <select name="cmbQual" id="cmbQual">
                        <option value="">— Any Qualification —</option>
                        @foreach($qualifications as $qualification)
                        <option value="{{ $qualification }}"
                            @selected(request('cmbQual')==$qualification)>
                            {{ $qualification }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-building"></i> Company Name</label>
                    <select name="cmbCompany" id="cmbCompany">
                        <option value="">— Any Company —</option>
                        @foreach($companies as $company)
                        <option
                            value="{{ $company }}"
                            @selected(request('cmbCompany')==$company)>
                            {{ $company }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-briefcase"></i> Job Title</label>
                    <select name="cmbTitle" id="cmbTitle">
                        <option value="">— Any Job Title —</option>
                        @foreach($titles as $title)
                        <option
                            value="{{ $title }}"
                            @selected(request('cmbTitle')==$title)>
                            {{ $title }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label style="visibility:hidden">Search</label>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-magnifying-glass"></i> Search
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- ── Search Results ── -->

    <span style="font-size:.8rem;font-weight:400;color:rgba(255,255,255,.55);margin-left:4px">
        {{ $jobs->count() }} job{{ $jobs->count() !== 1 ? 's' : '' }} found
    </span>

    @if($jobs->count() > 0)
    <div class="results-grid">
        @foreach($jobs as $i => $job)

        <div class="job-card" style="animation-delay:{{ $i * 0.07 }}s">

            <div class="job-card-header">
                <div style="flex:1">
                    <div class="job-title">
                        {{ $job->JobTitle }}
                    </div>

                    <div class="job-company">
                        {{ $job->CompanyName }}
                    </div>
                </div>

                <div class="job-company-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>

            <div class="job-meta">
                <span class="meta-pill">
                    {{ $job->Vacancy }} Vacancies
                </span>

                <span class="meta-pill">
                    {{ $job->MinQualification }}
                </span>

                <span class="meta-pill">
                    ID: {{ $job->JobId }}
                </span>
            </div>

            @if($job->Description)
            <div class="job-desc">
                {{ $job->Description }}
            </div>
            @endif

            @php
            $alreadyApplied = $applications->contains('JobId', $job->JobId);
            @endphp

            <div class="job-card-actions">

                <a href="{{ route('jobseeker.jobs.show', $job->JobId) }}"
                    class="btn-details">
                    <i class="fas fa-eye"></i>
                    View Details
                </a>

                @if($alreadyApplied)

                <button class="btn-apply" disabled
                    style="background:#6c757d;cursor:not-allowed;">
                    <i class="fas fa-check"></i>
                    Applied
                </button>

                @else

                <form action="{{ route('jobseeker.jobs.apply', $job->JobId) }}"
                    method="POST">
                    @csrf

                    <button type="submit" class="btn-apply">
                        <i class="fas fa-paper-plane"></i>
                        Apply Now
                    </button>
                </form>

                @endif

            </div>

        </div>

        @endforeach
    </div>

    @else
    <div class="glass-card" style="padding:0">
        <div class="no-results">
            <i class="fas fa-circle-xmark"></i>
            <p>No jobs found matching your filters.</p>
            <span>Try adjusting your qualification, company, or area of work.</span>
        </div>
    </div>
    @endif


    <!-- ── Application Status ── -->
    <div class="section-title" style="margin-top:8px"><i class="fas fa-clock-rotate-left"></i> My Application Status</div>

    <div class="glass-card" style="padding:0;overflow:hidden">
        @if ($applications->count() > 0)
        <div class="status-table-wrap">
            <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="status-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-building" style="margin-right:6px;opacity:.7"></i>Company</th>
                        <th><i class="fas fa-briefcase" style="margin-right:6px;opacity:.7"></i>Job Title</th>
                        <th><i class="fas fa-circle-dot" style="margin-right:6px;opacity:.7"></i>Status</th>
                        <th><i class="fas fa-comment-dots" style="margin-right:6px;opacity:.7"></i>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $application)
                    <tr>
                        <td>
                            <span class="company-name">
                                {{ $application->job->CompanyName ?? '-' }}
                            </span>
                        </td>

                        <td>
                            <span class="job-title-cell">
                                {{ $application->job->JobTitle ?? '-' }}
                            </span>
                        </td>

                        <td>
                            @php
                            $status = strtolower($application->Status);

                            if (str_contains($status, 'accept') || str_contains($status, 'approv') || $status == 'selected') {
                            $bg = 'rgba(74,222,128,.15)';
                            $border = 'rgba(74,222,128,.3)';
                            $color = '#86efac';
                            $icon = 'fa-circle-check';
                            } elseif (str_contains($status, 'reject') || str_contains($status, 'declin')) {
                            $bg = 'rgba(248,113,113,.15)';
                            $border = 'rgba(248,113,113,.3)';
                            $color = '#fca5a5';
                            $icon = 'fa-circle-xmark';
                            } else {
                            $bg = 'rgba(251,191,36,.15)';
                            $border = 'rgba(251,191,36,.3)';
                            $color = '#fde68a';
                            $icon = 'fa-clock';
                            }
                            @endphp

                            <span class="status-badge"
                                style="background:{{ $bg }}; border-color:{{ $border }}; color:{{ $color }}">
                                <i class="fas {{ $icon }}"></i>
                                {{ $application->Status }}
                            </span>
                        </td>

                        <td>
                            <span class="desc-cell"
                                title="{{ $application->Description }}">
                                {{ $application->Description }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
            <div class="table-footer">
                <i class="fas fa-info-circle"></i> Total {{ $applications->count() }} application{{ $applications->count() !== 1 ? 's' : '' }} found
            </div>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>You haven't applied to any jobs yet.<br>Search and apply above to get started.</p>
        </div>
        @endif
    </div>

</div><!-- /main-content -->
@endsection

@section('scripts')
<script>
    /* ── Navbar dropdown ── */
    function toggleDropdown() {
        document.getElementById('profileDropdown').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('profileDropdown');
        if (!dd.contains(e.target)) dd.classList.remove('open');
    });
</script>
@endsection
