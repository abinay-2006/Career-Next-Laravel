@extends('layouts.employer')

@section('title', 'Messages')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/application-chat.css') }}?v={{ @filemtime(public_path('assets/css/application-chat.css')) ?: 1 }}">
@endpushOnce

@section('content')

<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class="fas fa-comments"></i></div>
            <div class="page-header-text">
                <h1>Messages</h1>
                <p>Chats with the applicants you approved. These are separate from the follow-up messages in your coach inbox.</p>
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
                Messaging needs its database tables before conversations can be opened here.
                Ask an administrator to run <code class="cn-chat-code">php artisan migrate</code>;
                every other employer page works without it.
            </p>
        </div>
    </div>

    @else

    <div class="cn-chat-layout {{ $selected ? 'has-selection' : 'is-list-only' }}">
        @include('messages.inbox-list', [
            'threads' => $threads,
            'unread' => $unread,
            'unreadTotal' => $unreadTotal,
            'selected' => $selected,
            'role' => $role,
        ])

        @include('messages.thread-panel', [
            'selected' => $selected,
            'messages' => $messages,
            'gate' => $gate,
            'hasMore' => $hasMore,
            'role' => $role,
            'actorName' => $actorName,
        ])
    </div>

    <div class="glass-card cn-chat-note">
        <span class="cn-chat-note-text">
            Replying here never changes an applicant's status. Use the Application Decision buttons on the
            applicant page for that. Only you and the applicant can read this conversation.
        </span>
    </div>

    @endif

</div><!-- /main-content -->
@endsection

@section('scripts')
<script src="{{ asset('assets/js/application-chat.js') }}?v={{ @filemtime(public_path('assets/js/application-chat.js')) ?: 1 }}"></script>
@endsection
