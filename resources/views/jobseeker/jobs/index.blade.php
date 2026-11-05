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
    <!-- Saved Jobs Shortcut -->
    <div class="saved-jobs-shortcut glass-card">
        <div class="saved-jobs-shortcut-info">
            <div class="saved-jobs-shortcut-icon">
                <i class="fas fa-bookmark"></i>
            </div>
            <div>
                <h3>Your Saved Jobs</h3>
                <p>View the jobs you've bookmarked and revisit them anytime.</p>
            </div>
        </div>

        <a href="{{ route('jobseeker.saved-jobs.index') }}" class="btn-view-saved">
            View Saved Jobs
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <div class="section-title"><i class="fas fa-sliders"></i> Filter Jobs</div>
    <div class="glass-card filter-card  ">
        <form method="GET"
            action="{{ route('jobseeker.jobs') }}" data-ajax-html>
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
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>

            </div>

            <div class="match-controls">
                <div class="form-group">
                    <label for="cmbMatch"><i class="fas fa-wand-magic-sparkles"></i> Smart Match</label>
                    <select name="cmbMatch" id="cmbMatch">
                        <option value="">— All Jobs —</option>
                        @foreach([80, 60, 40] as $threshold)
                        <option value="{{ $threshold }}" @selected((int) request('cmbMatch') === $threshold)>
                            {{ $threshold }}% and Above
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="sort"><i class="fas fa-arrow-down-1-9"></i> Sort By</label>
                    <select name="sort" id="sort">
                        <option value="latest" @selected(request('sort') !== 'best_match')>Latest Jobs</option>
                        <option value="best_match" @selected(request('sort') === 'best_match')>Best Match</option>
                    </select>
                </div>

                <p class="match-controls-note">
                    Smart Match is an indicative overlap between the skills on your profile and each listing,
                    not a hiring or ATS score.
                </p>
            </div>
        </form>
    </div>

    @if($candidateSkills === [])
    <div class="glass-card match-notice" role="note">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <div>
            <strong>{{ \App\Services\JobMatchService::NO_RESUME_MESSAGE }}</strong>
            <span>Once your skills are saved, each job shows how many of them a listing mentions.</span>
        </div>
        <a href="{{ route('jobseeker.resumes.index') }}" class="btn-match-resume">
            <i class="fas fa-file-arrow-up"></i>
            Resume Builder
        </a>
    </div>
    @endif

    <!-- ── Search Results ── -->

    <span style="font-size:.8rem;font-weight:400;color:var(--theme-text-secondary);margin-left:4px">
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
                    <div class="job-card-id">ID: {{ $job->JobId }}</div>
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

                @if($job->ExperienceLevel)
                <span class="meta-pill">
                    {{ \App\Models\Job::experienceLevelLabel($job->ExperienceLevel) }}
                </span>
                @endif

                @if($job->WorkArrangement)
                <span class="meta-pill">
                    {{ $job->WorkArrangement }}
                </span>
                @endif

                @if($job->EmploymentType)
                <span class="meta-pill">
                    {{ $job->EmploymentType }}
                </span>
                @endif
            </div>

            @php
            $cardSkills = array_values(array_filter(
                $job->RequiredSkills ?? [],
                fn ($entry): bool => is_array($entry)
                    && ($entry['type'] ?? 'mandatory') === 'mandatory'
                    && !empty($entry['name'])
            ));
            @endphp

            @if($cardSkills !== [])
            <div class="job-req-card-summary">
                @foreach(array_slice($cardSkills, 0, 3) as $entry)
                <span class="job-req-badge">{{ $entry['name'] }}</span>
                @endforeach
                @if(count($cardSkills) > 3)
                <span class="job-req-badge">+{{ count($cardSkills) - 3 }} more</span>
                @endif
            </div>
            @endif

            @if($job->Description)
            <div class="job-desc">
                {{ $job->Description }}
            </div>
            @endif

            @php
            $match = $matches[$job->JobId] ?? null;
            @endphp

            @if($match && $match['status'] === \App\Services\JobMatchService::STATUS_SCORED)
            @php
            $matchedPreview = array_slice($match['matched'], 0, 3);
            $missingPreview = array_slice($match['missing'], 0, 3);
            $matchedExtra = count($match['matched']) - count($matchedPreview);
            $missingExtra = count($match['missing']) - count($missingPreview);
            @endphp

            <div class="job-match job-match-{{ $match['tone'] }}">
                <div class="job-match-head">
                    <span class="job-match-value">{{ $match['percentage'] }}%</span>
                    <span class="job-match-label">{{ $match['label'] }}</span>
                </div>

                <div class="job-match-track" aria-hidden="true">
                    <span style="width: {{ $match['percentage'] }}%"></span>
                </div>

                @if($matchedPreview !== [])
                <p class="job-match-line job-match-line-matched">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <span>Matching: {{ implode(', ', $matchedPreview) }}@if($matchedExtra > 0) +{{ $matchedExtra }} more @endif</span>
                </p>
                @endif

                @if($missingPreview !== [])
                <p class="job-match-line job-match-line-missing">
                    <i class="fas fa-circle-minus" aria-hidden="true"></i>
                    <span>Not in this job: {{ implode(', ', $missingPreview) }}@if($missingExtra > 0) +{{ $missingExtra }} more @endif</span>
                </p>
                @endif

                <p class="job-match-explanation">{{ $match['explanation'] }}</p>
            </div>

            @elseif($match && $match['status'] === \App\Services\JobMatchService::STATUS_INSUFFICIENT_JOB)

            <p class="job-match job-match-muted">
                <i class="fas fa-circle-question" aria-hidden="true"></i>
                <span>{{ $match['explanation'] }}</span>
            </p>

            @endif

            @php
            $alreadyApplied = $applications->contains('JobId', $job->JobId);
            @endphp

            @php
            $isSaved = collect($savedJobIds ?? [])
            ->contains(fn($id) => (int) $id === (int) $job->JobId);
            @endphp

            <div class="job-card-actions">

                <a href="{{ route('jobseeker.jobs.show', $job->JobId) }}"
                    class="btn-details">
                    <i class="fas fa-eye"></i>
                    View Details
                </a>

                {{-- Save / Remove Saved Job --}}
                @if($isSaved)

                <form action="{{ route('jobseeker.saved-jobs.destroy', $job->JobId) }}"
                    method="POST"
                    data-ajax>
                    @csrf

                    <button type="submit" class="btn-save-job saved">
                        <i class="fas fa-bookmark"></i>
                        Saved
                    </button>
                </form>

                @else

                <form action="{{ route('jobseeker.saved-jobs.store', $job->JobId) }}"
                    method="POST"
                    data-ajax>
                    @csrf

                    <button type="submit" class="btn-save-job">
                        <i class="far fa-bookmark"></i>
                        Save Job
                    </button>
                </form>

                @endif

                {{-- Existing Apply Functionality --}}
                @if($alreadyApplied)

                <button class="btn-apply" disabled aria-disabled="true">
                    <i class="fas fa-check" aria-hidden="true"></i>
                    Applied
                </button>

                @else

                <form action="{{ route('jobseeker.jobs.apply', $job->JobId) }}"
                    method="POST"
                    data-ajax
                    data-ajax-mark-applied="Applied">
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
                                $tone = 'accepted';
                                $icon = 'fa-circle-check';
                                } elseif (str_contains($status, 'reject') || str_contains($status, 'declin')) {
                                $tone = 'rejected';
                                $icon = 'fa-circle-xmark';
                                } else {
                                $tone = 'pending';
                                $icon = 'fa-clock';
                                }
                                @endphp

                                <span class="status-badge {{ $tone }}">
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
