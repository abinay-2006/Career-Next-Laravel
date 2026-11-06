@php
    $id = (int) $row['application_id'];
    $rec = $row['recommendation'];
    $category = $row['category'];
    $prep = $row['interview'];
    $draftHere = ($draft['application_id'] ?? null) === $id;
    $doneList = is_array($prepDone) ? $prepDone : [];
@endphp

<article class="cn-fu-card cn-fu-app" data-fu-app="{{ $id }}"
    data-fu-prep-endpoint="{{ route('jobseeker.followup.prep', $id) }}"
    data-fu-token="{{ csrf_token() }}">

    <header class="cn-fu-app-head">
        <div class="cn-fu-app-heading">
            <h2>{{ $row['job_title'] ?? 'Job no longer available' }}</h2>
            <p class="cn-fu-app-company">
                <i class="fas fa-building" aria-hidden="true"></i>
                {{ $row['company'] ?? 'Company not recorded' }}
            </p>
        </div>
        <div class="cn-fu-app-badges">
            <span class="cn-fu-badge cn-fu-badge--{{ $row['status_key'] }}">{{ $row['status'] }}</span>
            <span class="cn-fu-chip cn-fu-chip--{{ $category['key'] }}">{{ $category['label'] }}</span>
        </div>
    </header>

    <dl class="cn-fu-app-meta">
        <div>
            <dt><i class="fas fa-calendar-days" aria-hidden="true"></i> Applied</dt>
            <dd>{{ $row['submitted_on'] ? $row['submitted_on']->format('M j, Y') : 'Date unavailable' }}</dd>
        </div>
        <div>
            <dt><i class="fas fa-hourglass-half" aria-hidden="true"></i> {{ $row['is_terminal'] ? 'Last wait recorded' : 'Awaiting a response for' }}</dt>
            <dd>{{ $row['waiting_days'] !== null ? $row['waiting_days'].' day'.($row['waiting_days'] === 1 ? '' : 's') : 'Not measurable' }}</dd>
        </div>
        <div>
            <dt><i class="fas fa-clipboard-check" aria-hidden="true"></i> Last recorded update</dt>
            <dd>
                @if($row['last_update'])
                    {{ $row['last_update']['label'] }} &middot; {{ $row['last_update']['at']->format('M j, Y') }}
                @else
                    <span class="cn-fu-quiet">None recorded</span>
                @endif
            </dd>
        </div>
    </dl>

    <section class="cn-fu-callout cn-fu-callout--{{ $rec['tone'] }}">
        <p class="cn-fu-callout-head"><i class="fas fa-lightbulb" aria-hidden="true"></i> {{ $rec['headline'] }}</p>
        <p class="cn-fu-callout-reason">{{ $rec['reason'] }}</p>
        @if($row['waiting_source'])
        <p class="cn-fu-callout-clock">Measured from {{ $row['waiting_source'] }}.</p>
        @endif
        <p class="cn-fu-callout-next"><strong>Next suggested action:</strong> {{ $rec['next_action'] }}</p>
        @if($rec['template'])
        <button type="button" class="cn-fu-btn cn-fu-btn-primary"
            data-fu-open="generator" data-fu-template="{{ $rec['template'] }}">
            <i class="fas fa-pen" aria-hidden="true"></i> Open the message draft
        </button>
        @endif
        <a class="cn-fu-btn cn-fu-btn-quiet" href="{{ route('jobseeker.applications.show', $id) }}">
            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Application details
        </a>
    </section>

    <div class="cn-fu-folds">

        <details class="cn-fu-fold" data-fu-fold="timeline" open>
            <summary><i class="fas fa-timeline" aria-hidden="true"></i> Application timeline</summary>
            <ol class="cn-fu-timeline">
                @foreach($row['events'] as $event)
                <li>
                    <span class="cn-fu-timeline-dot" aria-hidden="true"></span>
                    <div>
                        <span class="cn-fu-timeline-label">{{ $event['label'] }}</span>
                        <time datetime="{{ $event['at']->toAtomString() }}">{{ $event['at']->format('M j, Y') }}</time>
                        <span class="cn-fu-timeline-source">{{ $event['source'] }}</span>
                    </div>
                </li>
                @endforeach
            </ol>
            @if(count($row['events']) <= 1)
            <p class="cn-fu-quiet">
                Only what your account actually records is listed here. Career Next does not add stages such as
                reviewed or shortlisted unless they were recorded.
            </p>
            @endif
        </details>

        <details class="cn-fu-fold" data-fu-fold="activity">
            <summary>
                <i class="fas fa-list-check" aria-hidden="true"></i> My follow-up activity
                <span class="cn-fu-fold-count">{{ count($row['activity']) }}</span>
            </summary>

            @if(!$trackerReady)
            <p class="cn-fu-quiet">
                Follow-up history needs its database table. Ask an administrator to run
                <code class="cn-fu-code">php artisan migrate</code>; the rest of this page works without it.
            </p>
            @else
            <ul class="cn-fu-history">
                @forelse($row['activity'] as $item)
                <li>
                    <span class="cn-fu-history-label">{{ $item['label'] }}</span>
                    <time>{{ $item['at'] ? $item['at']->format('M j, Y') : 'Date not recorded' }}</time>
                    @if($item['note'])<p class="cn-fu-history-note">{{ $item['note'] }}</p>@endif
                </li>
                @empty
                <li class="cn-fu-quiet">Nothing recorded yet. Your entries stay private to your account.</li>
                @endforelse
            </ul>

            <form class="cn-fu-activity" method="POST" action="{{ route('jobseeker.followup.activity', $id) }}"
                data-ajax data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
                @csrf
                <div class="cn-fu-field">
                    <label for="cnFuType_{{ $id }}">What did you do?</label>
                    <select id="cnFuType_{{ $id }}" name="activity_type">
                        @foreach(\App\Models\JobSeekerApplicationFollowUp::TYPES as $type)
                        <option value="{{ $type }}">{{ \App\Models\JobSeekerApplicationFollowUp::typeLabel($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="cn-fu-field">
                    <label for="cnFuDate_{{ $id }}">Date</label>
                    <input type="date" id="cnFuDate_{{ $id }}" name="activity_date" value="{{ $today }}" required>
                </div>
                <div class="cn-fu-field cn-fu-field-note">
                    <label for="cnFuNote_{{ $id }}">Private note <span>(optional)</span></label>
                    <input type="text" id="cnFuNote_{{ $id }}" name="note" maxlength="500" placeholder="Who you spoke to, what they said">
                </div>
                <button type="submit" class="cn-fu-btn cn-fu-btn-primary"><i class="fas fa-plus" aria-hidden="true"></i> Record activity</button>
            </form>
            @endif
        </details>

        <details class="cn-fu-fold" data-fu-fold="generator">
            <summary><i class="fas fa-pen-to-square" aria-hidden="true"></i> Write a message</summary>

            <form class="cn-fu-generator" method="POST" action="{{ route('jobseeker.followup.message', $id) }}" data-fu-message>
                @csrf
                <div class="cn-fu-field">
                    <label for="cnFuTpl_{{ $id }}">Scenario</label>
                    <select id="cnFuTpl_{{ $id }}" name="template" data-fu-template>
                        @foreach($templates as $key => $label)
                        <option value="{{ $key }}" @selected($draftHere && ($draft['template'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="cn-fu-btn cn-fu-btn-primary" data-fu-generate>
                    <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Generate draft
                </button>

                <p class="cn-fu-quiet" data-fu-note @if(!$draftHere) hidden @endif>{{ $draftHere ? ($draft['note'] ?? '') : '' }}</p>

                <div class="cn-fu-field">
                    <label for="cnFuSubject_{{ $id }}">Subject</label>
                    <div class="cn-fu-copy-row">
                        <input type="text" id="cnFuSubject_{{ $id }}" data-fu-subject maxlength="200"
                            value="{{ $draftHere ? ($draft['subject'] ?? '') : '' }}"
                            placeholder="Generated when you choose a scenario">
                        <button type="button" class="cn-fu-btn cn-fu-btn-quiet" data-fu-copy="cnFuSubject_{{ $id }}">
                            <i class="far fa-copy" aria-hidden="true"></i> Copy
                        </button>
                    </div>
                </div>

                <div class="cn-fu-field">
                    <label for="cnFuBody_{{ $id }}">Message</label>
                    <textarea id="cnFuBody_{{ $id }}" rows="12" data-fu-body
                        placeholder="The draft appears here, ready for you to edit.">{{ $draftHere ? ($draft['body'] ?? '') : '' }}</textarea>
                </div>

                <div class="cn-fu-generator-foot">
                    <button type="button" class="cn-fu-btn cn-fu-btn-quiet" data-fu-copy="cnFuBody_{{ $id }}">
                        <i class="far fa-copy" aria-hidden="true"></i> Copy message
                    </button>
                    <span class="cn-fu-hint">Career Next never sends this. Edit it, copy it, and send it yourself.</span>
                </div>
            </form>
        </details>

        @if($prep['available'])
        <details class="cn-fu-fold" data-fu-fold="prep">
            <summary><i class="fas fa-clipboard-list" aria-hidden="true"></i> Interview preparation</summary>

            <p class="cn-fu-quiet">
                Career Next does not know what this employer will ask. These are general things worth preparing,
                built from the requirements on the listing you applied to.
            </p>

            <p class="cn-fu-progress"><span data-fu-progress>{{ count($doneList) }}</span> of {{ count($prep['checklist']) }} checked</p>
            <ul class="cn-fu-checklist" data-fu-checklist>
                @foreach($prep['checklist'] as $item)
                @php($isDone = in_array($item['key'], $doneList, true))
                <li>
                    <button type="button" class="cn-fu-check{{ $isDone ? ' is-done' : '' }}"
                        data-fu-prep="{{ $item['key'] }}" aria-pressed="{{ $isDone ? 'true' : 'false' }}">
                        <i class="{{ $isDone ? 'far fa-square-check' : 'far fa-square' }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </button>
                </li>
                @endforeach
            </ul>

            @if($prep['skills']['mandatory'] !== [] || $prep['skills']['preferred'] !== [] || $prep['software'] !== [])
            <div class="cn-fu-skills">
                @if($prep['skills']['mandatory'] !== [])
                <div>
                    <h3>Must-have skills to revisit</h3>
                    <ul>@foreach($prep['skills']['mandatory'] as $skill)<li>{{ $skill }}</li>@endforeach</ul>
                </div>
                @endif
                @if($prep['skills']['preferred'] !== [])
                <div>
                    <h3>Nice-to-have skills</h3>
                    <ul>@foreach($prep['skills']['preferred'] as $skill)<li>{{ $skill }}</li>@endforeach</ul>
                </div>
                @endif
                @if($prep['software'] !== [])
                <div>
                    <h3>Tools named in the listing</h3>
                    <ul>@foreach($prep['software'] as $tool)<li>{{ $tool }}</li>@endforeach</ul>
                </div>
                @endif
            </div>
            @endif

            @if($prep['description'])
            <details class="cn-fu-subfold">
                <summary><i class="fas fa-file-lines" aria-hidden="true"></i> Re-read the job description</summary>
                <div class="cn-fu-description">{!! nl2br(e($prep['description'])) !!}</div>
            </details>
            @endif
        </details>
        @endif
    </div>
</article>
