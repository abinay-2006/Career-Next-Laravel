@extends('layouts.employer')

@section('title', 'Applicant Details')

@section('content')

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">
                <i class="fas fa-user-tie"></i>
            </div>

            <div class="page-header-text">
                <h1>Applicant Details</h1>
                <p>Review applicant profile and manage application status.</p>
            </div>
        </div>

        <a href="{{ route('employer.applications.index',['job_id'=>$application->JobId]) }}"
            class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
    </div>

    <!-- Applicant Profile -->
    <div class="glass-card profile-card">

        <div class="profile-left">

            <div class="profile-image">

                @if($applicantPhotoUrl = \App\Support\ProfilePhoto::url($application->jobSeeker->Photo))
                <img src="{{ $applicantPhotoUrl }}"
                    alt="Applicant">
                @else
                <i class="fas fa-user"></i>
                @endif

            </div>

        </div>

        <div class="profile-right">

            <h2>{{ $application->jobSeeker->JobSeekerName }}</h2>

            <div class="status-row">

                <span class="status-badge
                    @if($application->Status=='Accepted')
                        accepted
                    @elseif($application->Status=='Rejected')
                        rejected
                    @else
                        pending
                    @endif" data-application-status>

                    {{ $application->Status }}

                </span>

            </div>

            <div class="quick-info">

                <div>
                    <label>Email</label>
                    <span>{{ $application->jobSeeker->Email }}</span>
                </div>

                <div>
                    <label>Mobile</label>
                    <span>{{ $application->jobSeeker->Mobile }}</span>
                </div>

                <div>
                    <label>City</label>
                    <span>{{ $application->jobSeeker->City }}</span>
                </div>

                <div>
                    <label>Qualification</label>
                    <span>{{ $application->jobSeeker->Qualification }}</span>
                </div>

            </div>

        </div>

    </div>
    <!-- Information Grid -->
    <div class="details-grid">

        <!-- Personal Information -->
        <div class="glass-card info-card">

            <div class="card-title">
                <i class="fas fa-address-card"></i>
                Personal Information
            </div>

            <div class="info-list">

                <div class="info-item">
                    <span>Full Name</span>
                    <strong>{{ $application->jobSeeker->JobSeekerName }}</strong>
                </div>

                <div class="info-item">
                    <span>Email Address</span>
                    <strong>{{ $application->jobSeeker->Email }}</strong>
                </div>

                <div class="info-item">
                    <span>Mobile Number</span>
                    <strong>{{ $application->jobSeeker->Mobile }}</strong>
                </div>

                <div class="info-item">
                    <span>Gender</span>
                    <strong>{{ $application->jobSeeker->Gender }}</strong>
                </div>

                <div class="info-item">
                    <span>Birth Date</span>
                    <strong>{{ $application->jobSeeker->BirthDate }}</strong>
                </div>

                <div class="info-item">
                    <span>City</span>
                    <strong>{{ $application->jobSeeker->City }}</strong>
                </div>

                <div class="info-item">
                    <span>Qualification</span>
                    <strong>{{ $application->jobSeeker->Qualification }}</strong>
                </div>

                <div class="info-item">
                    <span>Address</span>
                    <strong>{{ $application->jobSeeker->Address }}</strong>
                </div>

            </div>

        </div>

        <!-- Applied Job -->
        <div class="glass-card info-card">

            <div class="card-title">
                <i class="fas fa-briefcase"></i>
                Applied Job
            </div>

            <div class="info-list">

                <div class="info-item">
                    <span>Company</span>
                    <strong>{{ $application->job->CompanyName }}</strong>
                </div>

                <div class="info-item">
                    <span>Job Title</span>
                    <strong>{{ $application->job->JobTitle }}</strong>
                </div>

                <div class="info-item">
                    <span>Vacancies</span>
                    <strong>{{ $application->job->Vacancy }}</strong>
                </div>

                <div class="info-item">
                    <span>Minimum Qualification</span>
                    <strong>{{ $application->job->MinQualification }}</strong>
                </div>

            </div>

        </div>

    </div>
    <!-- Resume Card -->
    <div class="glass-card resume-card">

        <div class="card-title">
            <i class="fas fa-file-pdf"></i>
            Resume
        </div>

        @if(!empty($application->jobSeeker->Resume))

        <div class="resume-wrapper">

            <div class="resume-icon">
                <i class="fas fa-file-pdf"></i>
            </div>

            <div class="resume-details">

                <h4>Resume Uploaded</h4>

                <p>{{ $resumeDocument?->OriginalFileName ?? basename((string) $application->jobSeeker->Resume) }}</p>

            </div>

            <div class="resume-actions">

                <a href="{{ route('employer.applications.resume', $application->ApplicationId) }}"
                    target="_blank"
                    class="btn-resume btn-view-resume">

                    <i class="fas fa-eye"></i>
                    View

                </a>

                <a href="{{ route('employer.applications.resume', $application->ApplicationId) }}"
                    download
                    class="btn-resume btn-download-resume">

                    <i class="fas fa-download"></i>
                    Download

                </a>

            </div>

        </div>

        @else

        <div class="resume-empty">

            <i class="fas fa-file-circle-xmark"></i>

            <h4>No Resume Uploaded</h4>

            <p>The applicant has not uploaded a resume.</p>

        </div>

        @endif

    </div>

    <!-- Status Update Card -->
    <div class="glass-card status-card">

        <div class="card-title">
            <i class="fas fa-user-check"></i>
            Application Decision
        </div>

        @if($errors->any())
        <div class="alert-danger">
            <i class="fas fa-circle-xmark"></i>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('employer.applications.update', $application->ApplicationId) }}"
            method="POST" data-ajax data-ajax-status-target="[data-application-status]" data-ajax-status-base="status-badge">

            @csrf
            @method('PUT')

            

            <div class="form-group">

                <label>
                    <i class="fas fa-comment-dots"></i>
                    Employer Remarks
                </label>

                <textarea
                    name="Description"
                    rows="5"
                    class="form-control"
                    placeholder="Enter remarks for the applicant...">{{ old('Description',$application->Description) }}</textarea>

            </div>

            <div class="action-buttons">

                <button
                    type="submit"
                    name="Status"
                    value="Accepted"
                    class="btn-accept">

                    <i class="fas fa-circle-check"></i>
                    Accept Application

                </button>

                <button
                    type="submit"
                    name="Status"
                    value="Rejected"
                    class="btn-reject">

                    <i class="fas fa-circle-xmark"></i>
                    Reject Application

                </button>

                <button
                    type="submit"
                    name="Status"
                    value="Pending"
                    class="btn-pending">

                    <i class="fas fa-clock"></i>
                    Keep Pending

                </button>

            </div>

        </form>

    </div>
</div>

@endsection
