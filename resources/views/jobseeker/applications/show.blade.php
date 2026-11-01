@extends('layouts.jobseeker')

@section('title', 'Application Details')

@section('content')
<div class="main-content application-detail-page">
    <div class="page-header">
        <div class="page-header-icon"><i class="fas fa-briefcase"></i></div>
        <div class="page-header-text">
            <h1>Application Details</h1>
            <p>Application #{{ $application->ApplicationId }}</p>
        </div>
        <a class="back-btn" href="{{ route('jobseeker.applications.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to applications</a>
    </div>

    @if($application->job)
        @php($job = $application->job)
        @php($statusClass = strtolower($application->Status ?? 'pending'))
        <section class="glass-card application-job-hero">
            <div class="application-company-icon"><i class="fas fa-building" aria-hidden="true"></i></div>
            <div class="application-job-heading">
                <span class="application-company-name">{{ $job->CompanyName }}</span>
                <h2>{{ $job->JobTitle }}</h2>
                <span class="application-status-badge {{ in_array($statusClass, ['accepted', 'rejected'], true) ? $statusClass : 'pending' }}"><span class="status-indicator" aria-hidden="true"></span>{{ $application->Status ?: 'Pending' }}</span>
            </div>
        </section>

        <div class="application-details-grid">
            <section class="glass-card application-info-card">
                <div class="application-section-title"><span><i class="fas fa-circle-info" aria-hidden="true"></i></span><div><h2>Job overview</h2><p>Key information about this opportunity</p></div></div>
                <dl class="application-facts">
                    <div><dt><i class="fas fa-users" aria-hidden="true"></i> Vacancies</dt><dd>{{ $job->Vacancy ?? 'Not specified' }}</dd></div>
                    <div><dt><i class="fas fa-graduation-cap" aria-hidden="true"></i> Minimum qualification</dt><dd>{{ $job->MinQualification ?: 'Not specified' }}</dd></div>
                    <div><dt><i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Salary range</dt><dd>
                        @if($job->SalaryFrom && $job->SalaryTo)
                            ₹{{ is_numeric($job->SalaryFrom) ? number_format((float) $job->SalaryFrom) : $job->SalaryFrom }} – ₹{{ is_numeric($job->SalaryTo) ? number_format((float) $job->SalaryTo) : $job->SalaryTo }}
                        @elseif($job->SalaryFrom)
                            ₹{{ is_numeric($job->SalaryFrom) ? number_format((float) $job->SalaryFrom) : $job->SalaryFrom }}+
                        @elseif($job->SalaryTo)
                            Up to ₹{{ is_numeric($job->SalaryTo) ? number_format((float) $job->SalaryTo) : $job->SalaryTo }}
                        @else
                            Not specified
                        @endif
                    </dd></div>
                    <div><dt><i class="fas fa-hashtag" aria-hidden="true"></i> Job ID</dt><dd>{{ $job->JobId }}</dd></div>
                </dl>
            </section>

            <section class="glass-card application-info-card application-timeline-card">
                <div class="application-section-title"><span><i class="fas fa-paper-plane" aria-hidden="true"></i></span><div><h2>Your application</h2><p>Submission and employer update</p></div></div>
                <dl class="application-facts">
                    <div><dt><i class="fas fa-calendar-days" aria-hidden="true"></i> Applied on</dt><dd>{{ $application->ApplicationDate ? \Illuminate\Support\Carbon::parse($application->ApplicationDate)->format('M j, Y') : 'Date unavailable' }}</dd></div>
                    <div><dt><i class="fas fa-clipboard-check" aria-hidden="true"></i> Current status</dt><dd>{{ $application->Status ?: 'Pending' }}</dd></div>
                    <div class="application-note"><dt><i class="fas fa-comment-dots" aria-hidden="true"></i> Employer note</dt><dd>{{ $application->Description ?: 'No note provided.' }}</dd></div>
                </dl>
            </section>

            <section class="glass-card application-info-card application-description-card">
                <div class="application-section-title"><span><i class="fas fa-file-lines" aria-hidden="true"></i></span><div><h2>About the job</h2><p>Full description provided by the employer</p></div></div>
                <div class="application-job-description">@if($job->Description) {!! nl2br(e($job->Description)) !!} @else <span class="application-muted">No additional job description has been provided.</span> @endif</div>
            </section>
        </div>
    @else
        <section class="glass-card application-missing-job">
            <span class="application-company-icon"><i class="fas fa-briefcase" aria-hidden="true"></i></span>
            <div><h2>Job no longer available</h2><p>The job listing has been removed, but your application record is still available.</p></div>
            <dl class="application-facts">
                <div><dt>Application date</dt><dd>{{ $application->ApplicationDate ? \Illuminate\Support\Carbon::parse($application->ApplicationDate)->format('M j, Y') : 'Date unavailable' }}</dd></div>
                <div><dt>Current status</dt><dd>{{ $application->Status ?: 'Pending' }}</dd></div>
                <div><dt>Employer note</dt><dd>{{ $application->Description ?: 'No note provided.' }}</dd></div>
            </dl>
        </section>
    @endif
</div>
@endsection

@push('styles')
<style>
    .application-job-hero { display: flex; align-items: center; gap: 1.2rem; padding: 1.6rem; margin-bottom: 1rem; }
    .application-company-icon { display: grid; place-items: center; flex: 0 0 64px; width: 64px; height: 64px; border-radius: 18px; background: linear-gradient(145deg,#eef1ff,#e4f0ff); color: #4657c8; font-size: 1.5rem; }
    .application-job-heading { display: grid; justify-items: start; gap: .35rem; }
    .application-company-name { color: #667085; font-size: .9rem; font-weight: 600; }
    .application-job-heading h2 { margin: 0; color: #182230; font-size: clamp(1.3rem, 2vw, 1.8rem); font-weight: 750; }
    .application-status-badge { display: inline-flex; align-items: center; gap: .48rem; padding: .42rem .7rem; border: 1px solid transparent; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
    .application-status-badge .status-indicator { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .application-status-badge.pending { color: #9a6700; background: #fff6db; border-color: #f4df9a; }
    .application-status-badge.accepted { color: #087443; background: #e8f7ef; border-color: #b8e7cc; }
    .application-status-badge.rejected { color: #b42318; background: #fff0ee; border-color: #f5c3bd; }
    .application-details-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
    .application-info-card { padding: 1.35rem; }
    .application-section-title { display: flex; align-items: center; gap: .85rem; margin-bottom: 1rem; }
    .application-section-title > span { display: grid; place-items: center; flex: 0 0 42px; height: 42px; border-radius: 13px; background: #f0f2ff; color: #4c5bd4; }
    .application-section-title h2 { margin: 0 0 .2rem; color: #182230; font-size: 1.05rem; font-weight: 750; }
    .application-section-title p { margin: 0; color: #667085; font-size: .83rem; }
    .application-facts { display: grid; gap: .7rem; margin: 0; }
    .application-facts > div { display: grid; grid-template-columns: minmax(140px, .8fr) minmax(0,1.2fr); gap: .8rem; padding: .75rem 0; border-top: 1px solid #edf0f5; }
    .application-facts dt { color: #667085; font-size: .84rem; font-weight: 600; }
    .application-facts dt i { width: 1.15rem; color: #6978d8; }
    .application-facts dd { margin: 0; color: #263244; font-size: .9rem; font-weight: 600; overflow-wrap: anywhere; }
    .application-note, .application-description-card { grid-column: 1 / -1; }
    .application-description-card { padding: 1.5rem; }
    .application-job-description { color: #344054; font-size: .95rem; line-height: 1.8; overflow-wrap: anywhere; }
    .application-muted, .application-missing-job p { color: #667085; }
    .application-missing-job { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 1rem; padding: 1.5rem; }
    .application-missing-job h2 { margin: 0 0 .3rem; color: #182230; font-size: 1.2rem; }
    .application-missing-job p { margin: 0; }
    .application-missing-job .application-facts { grid-column: 1/-1; width: 100%; }
    @media (max-width: 767.98px) {
        .application-details-grid { grid-template-columns: minmax(0,1fr); }
        .application-description-card, .application-note { grid-column: auto; }
        .application-facts > div { grid-template-columns: minmax(0,1fr); gap: .3rem; }
        .application-job-hero { align-items: flex-start; padding: 1.2rem; }
        .application-company-icon { flex-basis: 52px; width: 52px; height: 52px; border-radius: 15px; }
    }
</style>
@endpush
