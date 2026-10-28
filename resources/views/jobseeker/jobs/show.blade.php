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
