@php
    $threadId = $selected !== null ? (int) $selected->id : 0;
    $canSend = (bool) ($gate['can_send'] ?? false);
    $blockReason = $gate['reason'] ?? null;
    $counterpart = $role === 'employer'
        ? ($selected?->applicantName() ?? 'Applicant')
        : ($selected?->companyName() ?? 'Employer');
    $threadJobTitle = $selected?->jobTitle() ?? '';
@endphp

<section class="cn-chat-panel glass-card" id="cn-chat-panel"
    @if($selected)
    data-cn-chat="{{ $threadId }}"
    data-chat-poll="{{ route($role.'.messages.poll', $threadId) }}"
    data-chat-history="{{ route($role.'.messages.history', $threadId) }}"
    data-chat-send="{{ route($role.'.messages.send', $threadId) }}"
    data-chat-token="{{ csrf_token() }}"
    data-chat-can-send="{{ $canSend ? 1 : 0 }}"
    data-chat-role="{{ $role }}"
    @endif>

    @if(!$selected)

        <div class="cn-chat-placeholder">
            <i class="fas fa-comments" aria-hidden="true"></i>
            <span class="cn-chat-placeholder-title">Pick a conversation</span>
            <span class="cn-chat-placeholder-note">
                Choose a thread on the left to read it and reply. Career Next only opens a chat once an
                application is approved, and nothing written here changes the application status.
            </span>
        </div>

    @else

        <header class="cn-chat-head">
            <a class="cn-chat-back cn-chat-btn-back" href="{{ route($role.'.messages.index') }}">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span class="sr-only">Back to conversations</span>
            </a>

            <div class="cn-chat-who">
                <span class="cn-chat-who-name">{{ $counterpart }}</span>
                <span class="cn-chat-who-job">
                    <i class="fas fa-briefcase" aria-hidden="true"></i> {{ $threadJobTitle }}
                </span>
            </div>

            <span class="cn-chat-flag {{ $canSend ? 'is-open' : 'is-closed' }}">
                {{ $canSend ? 'Open' : 'Closed' }}
            </span>
        </header>

        @if($blockReason)
            <div class="cn-chat-notice" role="status">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>{{ $blockReason }}</span>
            </div>
        @endif

        <div class="cn-chat-toolbar">
            <button type="button" class="cn-chat-btn-older{{ $hasMore ? '' : ' is-hidden' }}"
                data-chat-older
                @if(!$hasMore) disabled @endif>
                <i class="fas fa-angle-up" aria-hidden="true"></i>
                Show older messages
            </button>

            <span class="cn-chat-status" data-chat-status aria-live="polite"></span>
        </div>

        <div class="cn-chat-scroll" data-chat-scroll tabindex="0" aria-label="Message history">

            @if($messages->isEmpty())
                <div class="cn-chat-empty">
                    <i class="fas fa-envelope-circle-check" aria-hidden="true"></i>
                    <span>No messages yet.</span>
                    <span class="cn-chat-empty-note">
                        {{ $canSend ? 'Say hello — the first reply opens the thread for both of you.' : 'This thread has no messages to read.' }}
                    </span>
                </div>
            @else
                @foreach($messages as $message)
                    @php($mine = $message->SenderRole === $role)
                    <article class="cn-chat-msg{{ $mine ? ' is-mine' : ' is-theirs' }}"
                        data-chat-msg="{{ (int) $message->id }}"
                        data-chat-sender="{{ $message->SenderRole }}">
                        <span class="cn-chat-msg-name">{{ $mine ? 'You' : $message->senderName() }}</span>
                        <div class="cn-chat-msg-bubble">
                            <span class="cn-chat-msg-body">{{ $message->message }}</span>
                        </div>
                        <div class="cn-chat-msg-foot">
                            <time class="cn-chat-msg-time" datetime="{{ $message->created_at?->toIso8601String() }}">
                                {{ $message->created_at?->format('M j, Y g:i A') ?? 'Just now' }}
                            </time>
                            @if($mine)
                                <span class="cn-chat-msg-receipt" data-chat-receipt="{{ (int) $message->id }}">
                                    {{ $message->read_at ? 'Read '.$message->read_at->format('M j') : 'Delivered' }}
                                </span>
                            @endif
                        </div>
                    </article>
                @endforeach
            @endif

        </div>

        <form class="cn-chat-composer" method="POST"
            action="{{ route($role.'.messages.send', $threadId) }}" data-chat-form>
            @csrf

            <label class="cn-chat-composer-label" for="cn-chat-message-{{ $threadId }}">
                Message {{ $counterpart }}
            </label>

            <div class="cn-chat-composer-row">
                <textarea id="cn-chat-message-{{ $threadId }}" name="message" rows="2" maxlength="2000"
                    class="cn-chat-input" data-chat-input
                    placeholder="{{ $canSend ? 'Write a message…' : 'Messaging is closed for this application.' }}"
                    @if(!$canSend) disabled @endif></textarea>

                <button type="submit" class="cn-chat-btn-send"
                    @if(!$canSend) disabled @endif>
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    Send
                </button>
            </div>

            <span class="cn-chat-composer-hint">
                Plain text only, up to 2000 characters. Messages are stored exactly as you write them.
            </span>
        </form>

    @endif

</section>
