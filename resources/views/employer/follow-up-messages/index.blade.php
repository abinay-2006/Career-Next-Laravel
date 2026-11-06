@extends('layouts.employer')

@section('title', 'Follow-up Messages')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/follow-up-messages.css') }}?v={{ @filemtime(public_path('assets/css/follow-up-messages.css')) ?: 1 }}">
@endpushOnce

@section('content')

<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class="fas fa-envelope-open-text"></i></div>
            <div class="page-header-text">
                <h1>Follow-up Messages</h1>
                <p>Short follow-ups applicants sent about their applications to your listings.</p>
            </div>
        </div>

        <a href="{{ route('employer.applications.index') }}" class="btn-back">
            <i class="fas fa-user-check"></i>
            Applications
        </a>
    </div>

    @if(!$ready)

    <div class="glass-card">
        <div class="empty-state">
            <i class="fas fa-database"></i>
            <p>
                Applicant follow-ups need their database table before they can be delivered here.
                Ask an administrator to run <code class="cn-fum-code">php artisan migrate</code>;
                every other employer page works without it.
            </p>
        </div>
    </div>

    @else

    <div class="glass-card">
        <div class="stats-strip">
            <span class="stat-pill"><i class="fas fa-inbox"></i> {{ $total }} Message{{ $total === 1 ? '' : 's' }}</span>
            @if($unread > 0)
            <span class="stat-pill pending cn-fum-stat-new"><i class="fas fa-circle-dot"></i> {{ $unread }} New</span>
            @endif
        </div>

        <div class="cn-fum-tabs" role="group" aria-label="Filter messages">
            <a class="cn-fum-tab cn-fum-btn-tab{{ $view === 'all' ? ' is-active' : '' }}"
                href="{{ route('employer.followupmessages.index') }}"
                @if($view === 'all') aria-current="page" @endif>All messages</a>
            <a class="cn-fum-tab cn-fum-btn-tab{{ $view === 'new' ? ' is-active' : '' }}"
                href="{{ route('employer.followupmessages.index', ['view' => 'new']) }}"
                @if($view === 'new') aria-current="page" @endif>Needs a look @if($unread > 0)({{ $unread }})@endif</a>
        </div>
    </div>

    @if($messages->isEmpty())

    <div class="glass-card delay">
        <div class="empty-state">
            <i class="fas fa-envelope-circle-check"></i>
            <p>
                @if($view === 'new')
                    Nothing left to read. Every follow-up on your listings has been marked as seen.
                @else
                    No applicant has delivered a follow-up message yet. Messages appear here when an
                    applicant presses Send to employer in their Follow-up Coach.
                @endif
            </p>
        </div>
    </div>

    @else

    @foreach($messages as $message)
    @php
        $application = $message->application;
        $seeker = $message->jobSeeker;
        $applicantName = $seeker?->JobSeekerName ?: 'Applicant unavailable';
        $isNew = $message->read_at === null;
    @endphp

    <article class="glass-card delay cn-fum-message{{ $isNew ? ' is-new' : '' }}">
        <div class="cn-fum-head">
            <div class="applicant-cell">
                <div class="applicant-avatar">{{ strtoupper(substr($applicantName, 0, 1)) }}</div>
                <div>
                    <div class="applicant-name">{{ $applicantName }}</div>
                    <div class="applicant-city">
                        <i class="fas fa-briefcase" style="font-size:0.65rem;opacity:0.75;"></i>
                        {{ $application?->job?->JobTitle ?? 'Job no longer listed' }}
                        @if($seeker?->City)
                        &middot; {{ $seeker->City }}
                        @endif
                    </div>
                </div>
            </div>

            <span class="cn-fum-state {{ $isNew ? 'is-unread' : 'is-read' }}">
                {{ $isNew ? 'New' : 'Viewed '.$message->read_at->format('M j, Y') }}
            </span>
        </div>

        <h2 class="cn-fum-subject">{{ $message->subject }}</h2>
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
                &middot; App ID {{ $message->ApplicationId }}
            </span>

            <div class="cn-fum-actions">
                @if($application)
                <a href="{{ route('employer.applications.show', $message->ApplicationId) }}" class="btn-view">
                    <i class="fas fa-eye"></i> Open application
                </a>
                @endif

                @if($isNew)
                <form method="POST" action="{{ route('employer.followupmessages.seen', $message->id) }}">
                    @csrf
                    <button type="submit" class="btn-accept cn-fum-btn-seen">
                        <i class="fas fa-check"></i> Mark as seen
                    </button>
                </form>
                @endif
            </div>
        </div>
    </article>
    @endforeach

    <div class="glass-card cn-fum-note">
        @if($messages->hasPages())
            <div class="cn-fum-pagination">{{ $messages->links('pagination::bootstrap-5') }}</div>
        @endif

        <p class="cn-fum-hint">
            Marking a message as seen only tells the applicant that you read it. It does not change their
            application status, and Career Next never replies on your behalf.
        </p>
    </div>

    @endif

    @endif

</div><!-- /main-content -->
@endsection
