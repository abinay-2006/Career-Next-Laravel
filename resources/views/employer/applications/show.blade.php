@extends('layouts.employer')

@section('title', 'Applicant Details')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/follow-up-messages.css') }}?v={{ @filemtime(public_path('assets/css/follow-up-messages.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('assets/css/application-chat.css') }}?v={{ @filemtime(public_path('assets/css/application-chat.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('assets/css/application-interviews.css') }}?v={{ @filemtime(public_path('assets/css/application-interviews.css')) ?: 1 }}">
@endpushOnce

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

    @if($followUpMessages->isNotEmpty())

    <!-- Applicant Follow-up Messages -->
    <div class="glass-card cn-fum-card">

        <div class="card-title">
            <i class="fas fa-envelope-open-text"></i>
            Follow-up messages from this applicant
            <span class="cn-fum-count">{{ $followUpMessages->count() }}</span>
        </div>

        @foreach($followUpMessages as $message)
        @php($isNew = $message->read_at === null)

        <article class="cn-fum-message{{ $isNew ? ' is-new' : '' }}">

            <div class="cn-fum-head">
                <h3 class="cn-fum-subject">{{ $message->subject }}</h3>
                <span class="cn-fum-state {{ $isNew ? 'is-unread' : 'is-read' }}">
                    {{ $isNew ? 'New' : 'Viewed '.$message->read_at->format('M j, Y') }}
                </span>
            </div>

            <p class="cn-fum-body">{{ $message->body }}</p>

            <div class="cn-fum-foot">
                <span class="cn-fum-sent">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    Delivered
                    @if($message->sent_at)
                    <time datetime="{{ $message->sent_at->toAtomString() }}">{{ $message->sent_at->format('M j, Y') }}</time>
                    @else
                    (date unavailable)
                    @endif
                </span>

                @if($isNew)
                <form method="POST" action="{{ route('employer.followupmessages.seen', $message->id) }}">
                    @csrf
                    <button type="submit" class="btn-accept cn-fum-btn-seen">
                        <i class="fas fa-check"></i> Mark as seen
                    </button>
                </form>
                @endif
            </div>

        </article>

        @endforeach

        <p class="cn-fum-hint">
            These messages were sent by the applicant from their Follow-up Coach. Marking one as seen only
            tells the applicant you read it - it never changes the application status below.
        </p>

    </div>

    @endif

    @if($chatOpen)

    <!-- Approved: the applicant can now write here, and only here, about this role. -->
    <div class="glass-card cn-chat-cta">

        <div class="cn-chat-cta-text">
            <span class="cn-chat-cta-title">Chat with this applicant</span>
            <span class="cn-chat-cta-note">
                Messaging opened when you accepted this application. It is separate from the follow-up
                messages above and never changes the decision below.
            </span>
        </div>

        <a href="{{ route('employer.messages.show', $chatThread->id) }}" class="cn-chat-btn-thread">
            <i class="fas fa-comments" aria-hidden="true"></i>
            Chat with Applicant
        </a>

    </div>

    @endif

    {{-- Interview scheduling. Read-only alongside the decision form below: nothing here
         touches application_master.Status, and a round that is answered stays on this
         page as the record it is. --}}
    <section class="glass-card cn-int-panel" id="cn-interviews">

        <div class="cn-int-panel-head">
            <h2 class="cn-int-panel-title">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>Interview schedule
            </h2>
            @if($ready && $interviewRounds->isNotEmpty())
            <span class="cn-int-panel-count">{{ $interviewRounds->count() }} round{{ $interviewRounds->count() === 1 ? '' : 's' }}</span>
            @endif
            <a href="{{ route('employer.interviews.index') }}" class="cn-int-btn-quiet">
                <i class="fas fa-list" aria-hidden="true"></i>All interviews
            </a>
        </div>

        @unless($ready)
        <p class="cn-int-blocked">
            <i class="fas fa-database" aria-hidden="true"></i>{{ $scheduleBlocked }}
        </p>
        @else

            @if($canSchedule)
            <details class="cn-int-form" data-cn-int-details="schedule" @if(old('cn_form') === 'new') open @endif>
                <summary>
                    <i class="fas fa-calendar-plus" aria-hidden="true"></i>Schedule round {{ $nextRound }} interview
                </summary>

                <form method="POST" action="{{ route('employer.interviews.store', $application->ApplicationId) }}" data-cn-int-form>
                    @csrf
                    @include('interview._fields', ['model' => null, 'goto' => 'application'])

                    <div class="cn-int-form-actions">
                        <button type="submit" class="cn-int-btn-primary">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>Send invitation
                        </button>
                    </div>
                </form>
            </details>
            @else
            <p class="cn-int-blocked">
                <i class="fas fa-circle-info" aria-hidden="true"></i>{{ $scheduleBlocked }}
            </p>
            @endif

            @if($interviewRounds->isEmpty())
            <p class="cn-int-panel-note">
                No invitation has been sent for this applicant yet. A round can be proposed once
                the application is accepted, and the applicant confirms, declines or asks for
                another time.
            </p>
            @else
            <div class="cn-int-rounds">
                @foreach($interviewRounds as $interview)
                <x-interview-card
                    :interview="$interview"
                    role="employer"
                    goto="application"
                    :busy="$interview->awaitsSeeker()" />
                @endforeach
            </div>
            @endif

        @endunless

    </section>

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

@section('scripts')
<script src="{{ asset('assets/js/application-interviews.js') }}?v={{ @filemtime(public_path('assets/js/application-interviews.js')) ?: 1 }}"></script>
@endsection
