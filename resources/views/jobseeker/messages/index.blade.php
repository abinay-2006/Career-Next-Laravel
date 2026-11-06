@extends('layouts.jobseeker')

@section('title', 'Messages')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/application-chat.css') }}?v={{ @filemtime(public_path('assets/css/application-chat.css')) ?: 1 }}">
@endpushOnce

@section('content')

<div class="main-content">

    <div class="page-header">
        <div class="page-header-icon"><i class="fas fa-comments"></i></div>
        <div class="page-header-text">
            <h1>Messages</h1>
            <p>Chats with the employers who approved your applications.</p>
        </div>
    </div>

    @if(!$ready)

    <div class="glass-card">
        <div class="empty-state">
            <i class="fas fa-database"></i>
            <p>
                Messaging needs its database tables before conversations can be opened here.
                Ask an administrator to run <code class="cn-chat-code">php artisan migrate</code>;
                every other page on your account works without it.
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
            Chatting here never changes your application status, and it is separate from the Follow-up Coach.
            Only you and this employer can read the conversation.
        </span>
    </div>

    @endif

</div><!-- /main-content -->
@endsection

@section('scripts')
<script src="{{ asset('assets/js/application-chat.js') }}?v={{ @filemtime(public_path('assets/js/application-chat.js')) ?: 1 }}"></script>
@endsection
