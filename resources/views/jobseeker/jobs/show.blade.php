@extends('layouts.jobseeker')

@section('title', 'Job Details')

@section('content')

<div class="main-content">

    <!-- ═══════════════════════════════════════════════════════════════
         PAGE HEADER
         ═══════════════════════════════════════════════════════════════ -->

    <div class="page-header">

        <div class="page-header-icon">
            <i class="fas fa-briefcase"></i>
        </div>

        <div class="page-header-text">
            <h1>Job Details</h1>
            <p>Explore this opportunity and take the next step in your career.</p>
        </div>

        <a href="{{ route('jobseeker.jobs') }}" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Jobs
        </a>

    </div>


    <!-- ═══════════════════════════════════════════════════════════════
         JOB HERO
         ═══════════════════════════════════════════════════════════════ -->

    <div class="job-details-hero">

        <div class="job-hero-left">

            <div class="company-logo-box">
                <i class="fas fa-building"></i>
            </div>

            <div class="job-hero-content">

                <div class="job-status-label">
                    <span class="status-dot"></span>
                    Currently Hiring
                </div>

                <h2>{{ $job->JobTitle }}</h2>

                <div class="company-name-large">
                    <i class="fas fa-building"></i>
                    {{ $job->CompanyName }}
                </div>

                <div class="job-id">
                    Job ID: {{ $job->JobId }}
                </div>

            </div>

        </div>

        <div class="job-hero-right">

            @if($alreadyApplied)

            <div class="applied-label">
                <i class="fas fa-circle-check"></i>
                Application Submitted
            </div>

            @else

            <form
                action="{{ route('jobseeker.jobs.apply', $job->JobId) }}"
                method="POST" data-ajax data-ajax-mark-applied="Application Submitted">

                @csrf

                <button type="submit" class="job-apply-button">
                    <i class="fas fa-paper-plane"></i>
                    Apply Now
                </button>

            </form>

            @endif

        </div>

    </div>


    <!-- ═══════════════════════════════════════════════════════════════
         QUICK INFORMATION
         ═══════════════════════════════════════════════════════════════ -->

    <div class="job-info-grid">

        <!-- Vacancies -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-users"></i>
            </div>

            <div class="info-card-content">
                <span>Vacancies</span>
                <strong>{{ $job->Vacancy }}</strong>
            </div>

        </div>


        <!-- Qualification -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>

            <div class="info-card-content">
                <span>Minimum Qualification</span>
                <strong>{{ $job->MinQualification }}</strong>
            </div>

        </div>


        @if($job->ExperienceLevel)
        <!-- Experience level -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-layer-group"></i>
            </div>

            <div class="info-card-content">
                <span>Experience Level</span>
                <strong>{{ \App\Models\Job::experienceLevelLabel($job->ExperienceLevel) }}</strong>
            </div>

        </div>
        @endif


        @if($job->WorkArrangement)
        <!-- Work arrangement -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-house-laptop"></i>
            </div>

            <div class="info-card-content">
                <span>Work Arrangement</span>
                <strong>{{ $job->WorkArrangement }}</strong>
            </div>

        </div>
        @endif


        @if($job->EmploymentType)
        <!-- Employment type -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-file-signature"></i>
            </div>

            <div class="info-card-content">
                <span>Employment Type</span>
                <strong>{{ $job->EmploymentType }}</strong>
            </div>

        </div>
        @endif


        <!-- Salary -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-indian-rupee-sign"></i>
            </div>

            <div class="info-card-content">
                <span>Salary</span>

                <strong>

                    @if($job->SalaryFrom && $job->SalaryTo)

                    ₹{{ number_format($job->SalaryFrom) }}
                    -
                    ₹{{ number_format($job->SalaryTo) }}

                    @elseif($job->SalaryFrom)

                    ₹{{ number_format($job->SalaryFrom) }}+

                    @elseif($job->SalaryTo)

                    Up to ₹{{ number_format($job->SalaryTo) }}

                    @else

                    Not specified

                    @endif

                </strong>

            </div>

        </div>


        <!-- Company -->
        <div class="job-info-card">

            <div class="info-card-icon">
                <i class="fas fa-building"></i>
            </div>

            <div class="info-card-content">
                <span>Company</span>
                <strong>{{ $job->CompanyName }}</strong>
            </div>

        </div>

    </div>


    <!-- ═══════════════════════════════════════════════════════════════
         MAIN JOB CONTENT
         ═══════════════════════════════════════════════════════════════ -->

    <div class="job-details-layout">


        <!-- ── LEFT CONTENT ── -->

        <div class="job-details-main">

            <div class="details-section">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-file-lines"></i>
                    </div>

                    <div>
                        <h3>About the Job</h3>
                        <p>Details provided by the employer</p>
                    </div>

                </div>


                <div class="job-description">

                    @if($job->Description)

                    {!! nl2br(e($job->Description)) !!}

                    @else

                    <div class="no-description">
                        <i class="fas fa-circle-info"></i>
                        <span>No additional job description has been provided.</span>
                    </div>

                    @endif

                </div>

            </div>


            <!-- Structured requirements set by the employer -->

            @php
            $requiredSkills = $job->RequiredSkills ?? [];
            $requiredSoftware = $job->RequiredSoftware ?? [];

            $byType = function (array $entries, string $type): array {
                return array_values(array_filter(
                    $entries,
                    fn ($entry): bool => is_array($entry)
                        && ($entry['type'] ?? 'mandatory') === $type
                        && !empty($entry['name'])
                ));
            };

            $requirementGroups = [
                ['title' => 'Mandatory skills', 'icon' => 'fa-asterisk', 'items' => $byType($requiredSkills, 'mandatory')],
                ['title' => 'Preferred skills', 'icon' => 'fa-star', 'items' => $byType($requiredSkills, 'preferred')],
                ['title' => 'Mandatory software', 'icon' => 'fa-toolbox', 'items' => $byType($requiredSoftware, 'mandatory')],
                ['title' => 'Preferred software', 'icon' => 'fa-toolbox', 'items' => $byType($requiredSoftware, 'preferred')],
            ];
            $requirementGroups = array_filter($requirementGroups, fn (array $group): bool => $group['items'] !== []);
            @endphp

            @if($requirementGroups !== [])

            <div class="details-section">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-list-check"></i>
                    </div>

                    <div>
                        <h3>Required Skills &amp; Software</h3>
                        <p>Listed by the employer. Any level shown is what this job asks for, not your own rating.</p>
                    </div>

                </div>

                @foreach($requirementGroups as $group)

                <div class="job-req-section">
                    <h4><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i> {{ $group['title'] }}</h4>
                    <div class="job-req-badges">
                        @foreach($group['items'] as $item)
                        <span class="job-req-badge {{ ($item['type'] ?? 'mandatory') === 'mandatory' ? 'job-req-badge-mandatory' : '' }}">
                            {{ $item['name'] }}
                            @if(!empty($item['proficiency']))
                            <span class="job-req-prof">{{ ucfirst($item['proficiency']) }}</span>
                            @endif
                        </span>
                        @endforeach
                    </div>
                </div>

                @endforeach

            </div>

            @endif


            <!-- Smart Match breakdown -->

            <div class="details-section job-match-panel">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </div>

                    <div>
                        <h3>Smart Job Match</h3>
                        <p>An indicative overlap between the skills on your profile and this listing, not a hiring or ATS score</p>
                    </div>

                </div>

                @if($match['status'] === \App\Services\JobMatchService::STATUS_SCORED)

                <div class="job-match job-match-{{ $match['tone'] }} job-match-detailed">

                    <div class="job-match-head">
                        <span class="job-match-value">{{ $match['percentage'] }}%</span>
                        <span class="job-match-label">{{ $match['label'] }}</span>
                    </div>

                    <div class="job-match-track" aria-hidden="true">
                        <span style="width: {{ $match['percentage'] }}%"></span>
                    </div>

                    <p class="job-match-explanation">{{ $match['explanation'] }}</p>

                    @if(!empty($match['structured']))

                    <div class="job-match-columns">

                        <div class="job-match-column">
                            <h4>
                                <i class="fas fa-circle-check" aria-hidden="true"></i>
                                Requirements your profile covers
                            </h4>
                            @if($match['matched_mandatory'] !== [])
                            <p class="job-match-group"><strong>Mandatory:</strong> {{ implode(', ', $match['matched_mandatory']) }}</p>
                            @endif
                            @if($match['matched_preferred'] !== [])
                            <p class="job-match-group"><strong>Preferred:</strong> {{ implode(', ', $match['matched_preferred']) }}</p>
                            @endif
                            @if($match['matched'] === [])
                            <p class="job-match-empty">None of this listing's required skills appear in your profile.</p>
                            @endif
                        </div>

                        <div class="job-match-column">
                            <h4>
                                <i class="fas fa-circle-minus" aria-hidden="true"></i>
                                Requirements not in your profile
                            </h4>
                            @if($match['missing_mandatory'] !== [])
                            <p class="job-match-group"><strong>Mandatory:</strong> {{ implode(', ', $match['missing_mandatory']) }}</p>
                            @endif
                            @if($match['missing_preferred'] !== [])
                            <p class="job-match-group"><strong>Preferred:</strong> {{ implode(', ', $match['missing_preferred']) }}</p>
                            @endif
                            @if($match['missing'] === [])
                            <p class="job-match-empty">Your profile covers every skill this listing asks for.</p>
                            @endif
                        </div>

                    </div>

                    @if($match['software'])
                    <p class="job-match-group">
                        <strong>Software:</strong>
                        @if($match['software']['matched'] !== [])
                        in your profile: {{ implode(', ', $match['software']['matched']) }}.
                        @else
                        none of the {{ $match['software']['total'] }} named tool{{ $match['software']['total'] !== 1 ? 's' : '' }} appear in your profile.
                        @endif
                        @if($match['software']['missing'] !== [])
                        Not listed: {{ implode(', ', $match['software']['missing']) }}.
                        @endif
                        Tool matches are not included in the percentage.
                    </p>
                    @endif

                    @else

                    <div class="job-match-columns">

                        <div class="job-match-column">
                            <h4>
                                <i class="fas fa-circle-check" aria-hidden="true"></i>
                                Matching skills
                            </h4>
                            @if($match['matched'] !== [])
                            <ul class="job-match-chips">
                                @foreach($match['matched'] as $skill)
                                <li>
                                    {{ $skill }}
                                    <small>{{ implode(', ', $match['matched_in'][$skill]) }}</small>
                                </li>
                                @endforeach
                            </ul>
                            @else
                            <p class="job-match-empty">None of the skills you listed are mentioned in this listing.</p>
                            @endif
                        </div>

                        <div class="job-match-column">
                            <h4>
                                <i class="fas fa-circle-minus" aria-hidden="true"></i>
                                Skills not found in this job
                            </h4>
                            @if($match['missing'] !== [])
                            <ul class="job-match-chips job-match-chips-muted">
                                @foreach($match['missing'] as $skill)
                                <li>{{ $skill }}</li>
                                @endforeach
                            </ul>
                            @else
                            <p class="job-match-empty">Every skill you listed appears in this listing.</p>
                            @endif
                        </div>

                    </div>

                    @endif

                    @if($match['suggestion'])
                    <p class="job-match-suggestion">
                        <i class="fas fa-lightbulb" aria-hidden="true"></i>
                        {{ $match['suggestion'] }}
                    </p>
                    @endif

                </div>

                @else

                <p class="job-match job-match-muted">
                    <i class="fas fa-circle-{{ $match['status'] === \App\Services\JobMatchService::STATUS_NEEDS_RESUME ? 'info' : 'question' }}" aria-hidden="true"></i>
                    <span>{{ $match['explanation'] }}</span>
                    @if($match['status'] === \App\Services\JobMatchService::STATUS_NEEDS_RESUME)
                    <a href="{{ route('jobseeker.resumes.index') }}" class="btn-match-resume">
                        Open Resume Builder
                    </a>
                    @endif
                </p>

                @endif

            </div>


            <!-- Qualification Section -->

            <div class="details-section">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>

                    <div>
                        <h3>Eligibility</h3>
                        <p>Minimum qualification required</p>
                    </div>

                </div>


                <div class="eligibility-box">

                    <div class="eligibility-icon">
                        <i class="fas fa-award"></i>
                    </div>

                    <div>
                        <span>Minimum Qualification</span>
                        <strong>{{ $job->MinQualification }}</strong>
                    </div>

                </div>

            </div>

        </div>


        <!-- ── RIGHT SIDEBAR ── -->

        <aside class="job-details-sidebar">


            <!-- Application Card -->

            <div class="application-card">

                <div class="application-card-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>

                <h3>
                    @if($alreadyApplied)
                    Application Submitted
                    @else
                    Interested in this job?
                    @endif
                </h3>

                <p>

                    @if($alreadyApplied)

                    Your application has already been submitted for this position.

                    @else

                    Take the next step and submit your application to
                    {{ $job->CompanyName }}.

                    @endif

                </p>


                @if($alreadyApplied)

                <div class="application-complete">
                    <i class="fas fa-circle-check"></i>
                    Already Applied
                </div>

                @else

                <form
                    action="{{ route('jobseeker.jobs.apply', $job->JobId) }}"
                    method="POST" data-ajax data-ajax-mark-applied="Application Submitted">

                    @csrf

                    <button type="submit" class="sidebar-apply-button">
                        <i class="fas fa-paper-plane"></i>
                        Apply for this Job
                    </button>

                </form>

                @endif

            </div>


            <!-- Job Summary -->

            <div class="summary-card">

                <h3>Job Summary</h3>

                <div class="summary-item">

                    <i class="fas fa-briefcase"></i>

                    <div>
                        <span>Job Title</span>
                        <strong>{{ $job->JobTitle }}</strong>
                    </div>

                </div>


                <div class="summary-item">

                    <i class="fas fa-building"></i>

                    <div>
                        <span>Company</span>
                        <strong>{{ $job->CompanyName }}</strong>
                    </div>

                </div>


                <div class="summary-item">

                    <i class="fas fa-users"></i>

                    <div>
                        <span>Open Positions</span>
                        <strong>{{ $job->Vacancy }}</strong>
                    </div>

                </div>


                <div class="summary-item">

                    <i class="fas fa-hashtag"></i>

                    <div>
                        <span>Job ID</span>
                        <strong>{{ $job->JobId }}</strong>
                    </div>

                </div>

            </div>

        </aside>

    </div>




</div>

@endsection
