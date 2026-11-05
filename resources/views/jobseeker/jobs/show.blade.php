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

            <x-compare-toggle :job="$job" button-class="cn-cmp-btn cn-cmp-btn-quiet" />

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
                        <span class="job-match-caption">Skill Match</span>
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


            <!-- Job Suitability Assessment -->

            @if(isset($suitability))
            @php
            $fitIcons = [
                \App\Services\JobSuitabilityService::CRITERION_MET => 'fa-circle-check',
                \App\Services\JobSuitabilityService::CRITERION_PARTIAL => 'fa-circle-half-stroke',
                \App\Services\JobSuitabilityService::CRITERION_UNMET => 'fa-circle-xmark',
                \App\Services\JobSuitabilityService::CRITERION_UNKNOWN => 'fa-circle-question',
                \App\Services\JobSuitabilityService::CRITERION_NOT_STATED => 'fa-circle-minus',
            ];
            @endphp

            <div class="details-section job-fit-panel">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>

                    <div>
                        <h3>Job Suitability Assessment</h3>
                        <p>Whether your profile meets what this employer asked for. Private to you, and nothing here is saved.</p>
                    </div>

                </div>

                <div class="job-fit job-fit-{{ $suitability['tone'] }} job-fit-detailed">

                    <div class="job-fit-head">
                        <span class="job-fit-badge">
                            <i class="fas {{ $suitability['icon'] }}" aria-hidden="true"></i>
                            {{ $suitability['label'] }}
                        </span>
                        @if($suitability['percentage'] !== null)
                        <span class="job-fit-score">
                            <span class="job-fit-value">{{ $suitability['percentage'] }}%</span>
                            <span class="job-fit-score-caption">of essential requirements</span>
                        </span>
                        @endif
                    </div>

                    @if($suitability['percentage'] !== null)
                    <p class="job-fit-score-note">
                        Counts only what this listing makes a condition: {{ $suitability['essential_met'] }} of {{ $suitability['essential_total'] }} essential requirement{{ $suitability['essential_total'] === 1 ? '' : 's' }} confirmed.
                        Preferred skills, salary, employment type and work arrangement are not part of this number.
                    </p>
                    @endif

                    <p class="job-fit-explanation">{{ $suitability['explanation'] }}</p>

                    <div class="job-fit-criteria">
                        @foreach($suitability['criteria'] as $criterion)
                        <div class="job-fit-row job-fit-row-{{ $criterion['status'] }}">

                            <i class="fas {{ $fitIcons[$criterion['status']] ?? 'fa-circle-question' }}" aria-hidden="true"></i>

                            <div class="job-fit-row-body">

                                <div class="job-fit-row-top">
                                    <strong>{{ $criterion['name'] }}</strong>
                                    <span class="job-fit-status job-fit-status-{{ $criterion['status'] }}">
                                        {{ $criterion['label'] }}
                                    </span>
                                    @if($criterion['essential'])
                                    <span class="job-fit-required">Required by this listing</span>
                                    @endif
                                </div>

                                @if($criterion['expected'] !== null || $criterion['candidate'] !== null)
                                <p class="job-fit-values">
                                    @if($criterion['expected'] !== null)
                                    <span><small>Listing</small> {{ $criterion['expected'] }}</span>
                                    @endif
                                    @if($criterion['candidate'] !== null)
                                    <span><small>Your profile</small> {{ $criterion['candidate'] }}</span>
                                    @endif
                                </p>
                                @endif

                                <p class="job-fit-reason">{{ $criterion['explanation'] }}</p>

                            </div>

                        </div>
                        @endforeach
                    </div>

                    @if($suitability['missing_requirements'] !== [])
                    <div class="job-fit-group">
                        <h4><i class="fas fa-circle-minus" aria-hidden="true"></i> What is missing</h4>
                        <ul class="job-fit-list">
                            @foreach($suitability['missing_requirements'] as $line)
                            <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($suitability['unknown_requirements'] !== [])
                    <div class="job-fit-group">
                        <h4><i class="fas fa-circle-question" aria-hidden="true"></i> Could not be checked</h4>
                        <ul class="job-fit-list job-fit-list-muted">
                            @foreach($suitability['unknown_requirements'] as $line)
                            <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($suitability['suggestions'] !== [])
                    <div class="job-fit-suggestions">
                        <h4><i class="fas fa-lightbulb" aria-hidden="true"></i> To improve your suitability</h4>
                        <ul class="job-fit-list">
                            @foreach($suitability['suggestions'] as $suggestion)
                            <li>{{ $suggestion }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                </div>

            </div>

            @endif


            <!-- Career Gap-to-Job Roadmap -->

            @if(isset($roadmap))
            @php
            $roadmapStateIcons = [
                \App\Services\CareerRoadmapService::STATE_MATCHED => 'fa-circle-check',
                \App\Services\CareerRoadmapService::STATE_GAP => 'fa-circle-minus',
                \App\Services\CareerRoadmapService::STATE_UNRECORDED => 'fa-circle-question',
            ];

            $roadmapActions = [
                'skills' => ['label' => 'Update Skills', 'icon' => 'fa-toolbox', 'route' => route('jobseeker.profile.skills.update')],
                'education' => ['label' => 'Update Education', 'icon' => 'fa-graduation-cap', 'route' => route('jobseeker.education')],
                'experience' => ['label' => 'Update Experience', 'icon' => 'fa-layer-group', 'route' => route('jobseeker.preferences.edit')],
                'resume' => ['label' => 'Update Resume', 'icon' => 'fa-file-lines', 'route' => route('jobseeker.resumes.index')],
            ];

            // The default view is the work: a step the profile does not cover yet,
            // whether that is a shortfall or a value nobody has recorded.
            $roadmapOutstanding = array_values(array_filter(
                $roadmap['steps'],
                static fn (array $step): bool => $step['state'] !== \App\Services\CareerRoadmapService::STATE_MATCHED
            ));

            $roadmapUnrecorded = count(array_filter(
                $roadmapOutstanding,
                static fn (array $step): bool => $step['state'] === \App\Services\CareerRoadmapService::STATE_UNRECORDED
            ));

            $roadmapWidth = $roadmap['percentage'] ?? 0;

            // When nothing at all could be weighed, the roadmap's own note already
            // says so; this line is for a partly filled profile, where a few of the
            // counted steps are still unknown.
            $roadmapUnknownLine = '';

            if ($roadmapUnrecorded > 0 && $roadmap['steps_total'] > 0) {
                $roadmapUnknownLine = $roadmapUnrecorded.' requirement'.($roadmapUnrecorded === 1 ? '' : 's')
                    .($roadmapUnrecorded === 1 ? ' is' : ' are').' not recorded in your profile, so '
                    .($roadmapUnrecorded === 1 ? 'it is' : 'they are').' counted as neither matched nor missed.';
            }
            @endphp

            <div class="details-section" id="jobRoadmap">

                <div class="details-section-heading">

                    <div class="heading-icon">
                        <i class="fas fa-route"></i>
                    </div>

                    <div>
                        <h3>Your Job Readiness</h3>
                    </div>

                </div>

                <div class="job-roadmap job-roadmap-detailed job-roadmap-{{ $roadmap['tone'] }}">

                    <p class="job-roadmap-score">
                        Requirements matched:
                        <strong>{{ $roadmap['steps_total'] > 0 ? $roadmap['steps_completed'].'/'.$roadmap['steps_total'] : 'not measured yet' }}</strong>
                    </p>

                    <div class="job-roadmap-track" role="progressbar" aria-label="Requirements matched by your profile"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $roadmapWidth }}">
                        <span style="width: {{ $roadmapWidth }}%"></span>
                    </div>

                    @if($roadmap['steps_total'] === 0)

                    <p class="job-roadmap-quiet">{{ $roadmap['note'] }}</p>

                    @elseif($roadmapOutstanding === [])

                    <p class="job-roadmap-positive">Your profile meets all the requirements listed for this job.</p>

                    @endif

                    @if($roadmapOutstanding !== [])

                    <ul class="job-roadmap-gaps">
                        @foreach($roadmapOutstanding as $step)
                        <li class="job-roadmap-gap">
                            <span class="job-roadmap-gap-copy">
                                <strong>{{ $step['title'] }}</strong>
                                <small>{{ $step['hint'] }}</small>
                            </span>
                            <span class="job-roadmap-cat">{{ $step['category'] }}</span>
                        </li>
                        @endforeach
                    </ul>

                    @endif

                    @if($roadmapUnknownLine !== '')
                    <p class="job-roadmap-unknown">{{ $roadmapUnknownLine }}</p>
                    @endif

                    <a href="{{ route('jobseeker.profile.index') }}" class="btn-roadmap-primary">Improve My Profile</a>

                    @if(count($roadmap['steps']) > 0)

                    <details class="job-roadmap-all">
                        <summary>
                            View all requirements
                            <i class="fas fa-chevron-down job-roadmap-chevron" aria-hidden="true"></i>
                        </summary>

                        <p class="job-roadmap-note">
                            Recalculated from what your profile holds right now - ticking a step here changes nothing.
                        </p>

                        <div class="job-roadmap-groups">

                            @foreach($roadmap['groups'] as $roadmapGroup)

                            <div class="job-roadmap-group job-roadmap-group-{{ $roadmapGroup['key'] }}">

                                <h4>
                                    <i class="fas {{ $roadmapGroup['icon'] }}" aria-hidden="true"></i>
                                    {{ $roadmapGroup['label'] }}
                                    @php
                                    $roadmapGroupPending = $roadmapGroup['total'] - $roadmapGroup['completed'];
                                    @endphp
                                    <span class="job-roadmap-count">
                                        {{ $roadmapGroupPending === 0 ? 'all covered' : $roadmapGroupPending.' of '.$roadmapGroup['total'].' left' }}
                                    </span>
                                </h4>

                                <p class="job-roadmap-group-note">{{ $roadmapGroup['note'] }}</p>

                                <ul class="job-roadmap-steps">
                                    @foreach($roadmapGroup['steps'] as $step)
                                    <li class="job-roadmap-step job-roadmap-step-{{ $step['state'] }}">

                                        <i class="fas {{ $roadmapStateIcons[$step['state']] ?? 'fa-circle-question' }}" aria-hidden="true"></i>

                                        <div class="job-roadmap-step-body">
                                            <span class="job-roadmap-step-label">
                                                <strong>{{ $step['label'] }}</strong>
                                                @if($step['state'] === \App\Services\CareerRoadmapService::STATE_MATCHED)
                                                <em class="job-roadmap-state">Already Matched</em>
                                                @endif
                                            </span>
                                            <p>{{ $step['detail'] }}</p>

                                            @if($step['expected'] !== null || $step['current'] !== null)
                                            <p class="job-roadmap-values">
                                                @if($step['expected'] !== null)
                                                <span><small>Listing</small> {{ $step['expected'] }}</span>
                                                @endif
                                                @if($step['current'] !== null)
                                                <span><small>Your profile</small> {{ $step['current'] }}</span>
                                                @endif
                                            </p>
                                            @endif

                                            @if($step['state'] !== \App\Services\CareerRoadmapService::STATE_MATCHED && isset($roadmapActions[$step['action']]))
                                            <a href="{{ $roadmapActions[$step['action']]['route'] }}" class="job-roadmap-step-link">
                                                <i class="fas {{ $roadmapActions[$step['action']]['icon'] }}" aria-hidden="true"></i>
                                                {{ $roadmapActions[$step['action']]['label'] }}
                                            </a>
                                            @endif
                                        </div>

                                    </li>
                                    @endforeach
                                </ul>

                            </div>

                            @endforeach

                        </div>

                        <div class="job-roadmap-actions">
                            @foreach($roadmapActions as $roadmapAction)
                            <a href="{{ $roadmapAction['route'] }}" class="btn-roadmap-action">
                                <i class="fas {{ $roadmapAction['icon'] }}" aria-hidden="true"></i>
                                {{ $roadmapAction['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </details>

                    @endif

                    <p class="job-roadmap-disclaimer">
                        Completing these steps does not guarantee shortlisting or the job - the employer decides that.
                    </p>

                </div>

            </div>

            @endif


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

    <x-compare-tray />


</div>

@endsection
