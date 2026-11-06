@php
    $selectedId = $selected !== null ? (int) $selected->id : 0;
@endphp

<aside class="cn-chat-list glass-card" aria-label="Conversations">

    <div class="cn-chat-list-head">
        <span class="cn-chat-list-title">Conversations</span>
        @if($unreadTotal > 0)
            <span class="cn-chat-list-count">{{ $unreadTotal }} new</span>
        @endif
    </div>

    @if($threads->isEmpty())

        <div class="cn-chat-empty cn-chat-list-empty">
            <i class="fas fa-inbox" aria-hidden="true"></i>
            <span>No conversations yet.</span>
            <span class="cn-chat-empty-note">
                {{ $role === 'employer'
                    ? 'A chat opens automatically for each applicant you approve, and it appears here.'
                    : 'Career Next opens a chat with the employer once your application is approved.' }}
            </span>
        </div>

    @else

        <ul class="cn-chat-rows">
            @foreach($threads as $thread)
                @php
                    $tid = (int) $thread->id;
                    $waiting = $unread[$tid] ?? 0;
                    $label = $role === 'employer' ? $thread->applicantName() : $thread->companyName();
                    $snippet = $thread->lastMessage?->preview();
                @endphp

                <li class="cn-chat-row{{ $tid === $selectedId ? ' is-active' : '' }}">
                    <a class="cn-chat-row-link cn-chat-btn-open" href="{{ route($role.'.messages.show', $tid) }}">
                        <span class="cn-chat-avatar" aria-hidden="true">
                            {{ strtoupper(substr($label, 0, 1)) }}
                        </span>

                        <span class="cn-chat-row-main">
                            <span class="cn-chat-row-name">{{ $label }}</span>
                            <span class="cn-chat-row-job">
                                <i class="fas fa-briefcase" aria-hidden="true"></i> {{ $thread->jobTitle() }}
                            </span>
                            <span class="cn-chat-row-preview">
                                {{ $snippet !== null && $snippet !== '' ? $snippet : 'No messages yet' }}
                            </span>
                        </span>

                        <span class="cn-chat-row-side">
                            @if($thread->last_message_at)
                                <time class="cn-chat-row-time" datetime="{{ $thread->last_message_at->toIso8601String() }}">
                                    {{ $thread->last_message_at->diffForHumans(['short' => true]) }}
                                </time>
                            @endif

                            @if($waiting > 0)
                                <span class="cn-chat-row-unread">{{ $waiting }}</span>
                                <span class="sr-only">unread message{{ $waiting === 1 ? '' : 's' }} in this conversation</span>
                            @elseif($tid === $selectedId)
                                <span class="cn-chat-row-open">Open</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if($threads->hasPages())
            <div class="cn-chat-pagination">{{ $threads->links('pagination::bootstrap-5') }}</div>
        @endif

    @endif

</aside>
