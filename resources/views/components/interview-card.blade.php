@props([
    'interview',
    'role',
    'partner' => null,
    'goto' => null,
    'showJob' => false,
    'busy' => false,
])
@php
    use App\Models\ApplicationInterview;
    use App\Services\ApplicationInterviewService;

    $service = app(ApplicationInterviewService::class);
    $update = $service->updateFor($interview, $role);
    $status = (string) $interview->status;
    $open = $interview->isOpen();
    $link = (string) $interview->meeting_link;
    $isEmployer = $role === 'employer';

    // The gate is a pure read of the stored status, so each card answers for itself
    // instead of the page having to remember to hand one over.
    $gate = $service->gate($interview)[$isEmployer ? 'employer' : 'seeker'];

    // Only a real absolute URL is ever turned into a clickable anchor.
    $safeLink = filter_var($link, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['http', 'https'], true);

    $zones = ApplicationInterviewService::COMMON_TIMEZONES;
    $currentZone = (string) $interview->timezone;
    $zones = $currentZone !== '' && !in_array($currentZone, $zones, true) ? array_merge([$currentZone], $zones) : $zones;

    // A rejected submission comes back to a closed <details>, which reads as though the
    // form simply vanished. Each form carries its own cn_form marker so the one that
    // actually failed is the one that reopens, and not every card on the page.
    $reviseWasOpen = old('cn_form') === 'edit-'.$interview->id;
    $rescheduleWasOpen = old('cn_form') === 'reschedule-'.$interview->id;
@endphp

<article class="cn-int-card is-{{ str_replace('_', '-', $status) }}{{ $open ? '' : ' is-closed' }}" data-cn-int-card>

    <header class="cn-int-head">
        <span class="cn-int-round"><i class="fas fa-hashtag" aria-hidden="true"></i>Round {{ $interview->round }}</span>

        <span class="cn-int-badge is-{{ str_replace('_', '-', $status) }}">
            <span class="cn-int-dot" aria-hidden="true"></span>{{ ApplicationInterview::statusLabel($status) }}
        </span>

        <span class="cn-int-type">
            <i class="{{ ApplicationInterview::typeIcon($interview->interview_type) }}" aria-hidden="true"></i>
            {{ ApplicationInterview::typeLabel($interview->interview_type) }}
        </span>
    </header>

    @if($showJob)
    <p class="cn-int-job">
        <i class="fas fa-briefcase" aria-hidden="true"></i>
        <strong>{{ $interview->JobTitle ?? $interview->application?->job?->JobTitle ?? 'Job unavailable' }}</strong>
        <span class="cn-int-company">{{ $interview->CompanyName ?? $interview->application?->job?->CompanyName ?? '' }}</span>
        @if($partner !== null)
        <span class="cn-int-partner"><i class="fas fa-user-check" aria-hidden="true"></i>{{ $partner }}</span>
        @endif
    </p>
    @endif

    <div class="cn-int-when">
        <i class="fas fa-calendar-clock" aria-hidden="true"></i>
        <div>
            <strong>{{ $interview->scheduledLabel() }}</strong>
            <span>{{ $interview->timezoneLabel() }}</span>
        </div>
    </div>

    <dl class="cn-int-facts">
        <div>
            <dt><i class="{{ ApplicationInterview::typeIcon($interview->interview_type) }}" aria-hidden="true"></i>{{ ApplicationInterview::typeLabel($interview->interview_type) }}</dt>
            <dd>
                @if($interview->interview_type === ApplicationInterview::TYPE_ONLINE && $safeLink)
                <a class="cn-int-link" href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow">{{ $link }}<i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                @elseif($interview->interview_type === ApplicationInterview::TYPE_IN_PERSON)
                {{ $interview->whereLabel() }}
                @else
                {{ $interview->whereLabel() }}
                @endif
            </dd>
        </div>

        <div>
            <dt><i class="fas fa-circle-info" aria-hidden="true"></i>Application</dt>
            <dd>{{ $interview->application?->Status ?: 'Pending' }}
                @if($busy)<span class="cn-int-waiting">waiting on {{ $isEmployer ? 'the applicant' : 'the employer' }}</span>@endif
            </dd>
        </div>
    </dl>

    @if($interview->instructions)
    <div class="cn-int-instructions">
        <span class="cn-int-instructions-label"><i class="fas fa-list-ol" aria-hidden="true"></i>Instructions from the employer</span>
        <div class="cn-int-instructions-body">{!! nl2br(e($interview->instructions)) !!}</div>
    </div>
    @endif

    <p class="cn-int-update">{{ $update }}</p>

    @if($interview->reschedule_requested_at)
    <div class="cn-int-request">
        <span class="cn-int-request-head">
            <i class="fas fa-rotate-left" aria-hidden="true"></i>
            {{ $isEmployer ? 'The applicant asked for another time' : 'You asked for another time' }}
            <time datetime="{{ $interview->reschedule_requested_at->toAtomString() }}">{{ $interview->reschedule_requested_at->format('M j, Y') }}</time>
        </span>
        <p class="cn-int-request-reason">{{ $interview->reschedule_reason }}</p>
        @if($interview->reschedule_preferred_at)
        <p class="cn-int-request-preferred">
            <i class="fas fa-clock" aria-hidden="true"></i>
            Preferred: {{ \Illuminate\Support\Carbon::parse($interview->reschedule_preferred_at)->timezone($currentZone ?: config('app.timezone'))->format('D, M j, Y · g:i A') }}
        </p>
        @endif
    </div>
    @endif

    <footer class="cn-int-foot">
        @if($interview->revision_count > 0)
        <span class="cn-int-revised"><i class="fas fa-pen" aria-hidden="true"></i>Revised {{ $interview->revision_count }} time{{ $interview->revision_count === 1 ? '' : 's' }}</span>
        @endif
        <span class="cn-int-updated">Updated {{ $interview->updated_at?->diffForHumans() ?? 'recently' }}</span>
    </footer>

    @if($isEmployer)
        {{-- Employer powers: revise after a request, cancel, mark completed. --}}
        <div class="cn-int-actions">

            @if($gate['can_revise'])
            <details class="cn-int-form" data-cn-int-details="revise" @if($reviseWasOpen) open @endif>
                <summary><i class="fas fa-calendar-plus" aria-hidden="true"></i>Propose a new time</summary>

                <form method="POST" action="{{ route('employer.interviews.revise', $interview->id) }}" data-cn-int-form>
                    @csrf
                    @include('interview._fields', ['model' => $interview, 'zones' => $zones, 'goto' => $goto])

                    <div class="cn-int-form-actions">
                        <button type="submit" class="cn-int-btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i>Send revised invitation</button>
                    </div>
                </form>
            </details>
            @endif

            @if($gate['can_complete'])
            <form method="POST" action="{{ route('employer.interviews.complete', $interview->id) }}" data-cn-int-form
                data-cn-confirm="Mark this interview as completed? The applicant will see the round is over. Nothing here changes the application status.">
                @csrf
                @if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif
                <button type="submit" class="cn-int-btn-primary"><i class="fas fa-circle-check" aria-hidden="true"></i>Mark completed</button>
            </form>
            @endif

            @if($gate['can_cancel'])
            <form method="POST" action="{{ route('employer.interviews.cancel', $interview->id) }}" data-cn-int-form
                data-cn-confirm="Cancel this interview invitation? The applicant is told it was cancelled, and the round stays visible on both sides.">
                @csrf
                @if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif
                <button type="submit" class="cn-int-btn-danger"><i class="fas fa-ban" aria-hidden="true"></i>Cancel interview</button>
            </form>
            @endif

            @if($status === ApplicationInterview::STATUS_DECLINED)
            <span class="cn-int-hint">The applicant turned this one down - offer another round from the schedule form.</span>
            @endif

        </div>
    @else
        {{-- Applicant answers, and nothing else. --}}
        <div class="cn-int-actions">

            @if($gate['can_confirm'])
            <form method="POST" action="{{ route('jobseeker.interviews.confirm', $interview->id) }}" data-cn-int-form
                data-cn-confirm="Confirm you will attend this interview at {{ $interview->scheduledLabel() }} ({{ $interview->timezoneLabel() }})?">
                @csrf
                @if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif
                <button type="submit" class="cn-int-btn-primary"><i class="fas fa-circle-check" aria-hidden="true"></i>Confirm attendance</button>
            </form>
            @endif

            @if($gate['can_request'])
            <details class="cn-int-form" data-cn-int-details="reschedule" @if($rescheduleWasOpen) open @endif>
                <summary><i class="fas fa-rotate-left" aria-hidden="true"></i>Request another time</summary>

                <form method="POST" action="{{ route('jobseeker.interviews.reschedule', $interview->id) }}" data-cn-int-form>
                    @csrf
                    <input type="hidden" name="cn_form" value="reschedule-{{ $interview->id }}">

                    @php
                        $reasonError = $rescheduleWasOpen ? trim((string) $errors->first('reason')) : '';
                        $preferredError = $rescheduleWasOpen ? trim((string) $errors->first('preferred_at')) : '';
                        $zoneError = $rescheduleWasOpen ? trim((string) $errors->first('preferred_timezone')) : '';
                    @endphp

                    <div @class(['cn-int-field', 'is-invalid' => $reasonError !== ''])>
                        <label for="cn-int-reason-{{ $interview->id }}">Why would you like another time?</label>
                        <textarea id="cn-int-reason-{{ $interview->id }}" name="reason" rows="3" required
                            minlength="{{ ApplicationInterviewService::MIN_REASON }}" maxlength="500"
                            placeholder="Give the employer enough to propose a better slot.">{{ old('reason', $interview->reschedule_reason) }}</textarea>
                        @if($reasonError)<span class="cn-int-field-error">{{ $reasonError }}</span>@endif
                    </div>

                    <div class="cn-int-field-row">
                        <div @class(['cn-int-field', 'is-invalid' => $preferredError !== ''])>
                            <label for="cn-int-preferred-{{ $interview->id }}">A time that would suit you</label>
                            <div class="cn-int-date">
                                <input id="cn-int-preferred-{{ $interview->id }}" type="datetime-local" required
                                    min="{{ now()->format('Y-m-d\TH:i') }}"
                                    name="preferred_at" value="{{ old('preferred_at') }}">
                                <button type="button" class="cn-int-btn-quiet cn-int-cal"
                                    data-cn-int-picker="cn-int-preferred-{{ $interview->id }}" hidden>
                                    <i class="fas fa-calendar-day" aria-hidden="true"></i>Calendar
                                </button>
                            </div>
                            @if($preferredError)<span class="cn-int-field-error">{{ $preferredError }}</span>@endif
                        </div>

                        <div @class(['cn-int-field', 'is-invalid' => $zoneError !== ''])>
                            <label for="cn-int-preferred-zone-{{ $interview->id }}">Timezone</label>
                            <select id="cn-int-preferred-zone-{{ $interview->id }}" name="preferred_timezone">
                                <option value="">Same as the invitation ({{ $currentZone }})</option>
                                @foreach($zones as $zone)
                                <option value="{{ $zone }}" @selected(old('preferred_timezone') === $zone)>{{ $zone }}</option>
                                @endforeach
                            </select>
                            @if($zoneError)<span class="cn-int-field-error">{{ $zoneError }}</span>@endif
                        </div>
                    </div>

                    @if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif

                    <p class="cn-int-note">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <span>Sending this does not move your interview. It stays at {{ $interview->scheduledLabel() }} until the employer proposes a new time.</span>
                    </p>

                    <div class="cn-int-form-actions">
                        <button type="submit" class="cn-int-btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i>Send request</button>
                    </div>
                </form>
            </details>
            @endif

            @if($gate['can_decline'])
            <form method="POST" action="{{ route('jobseeker.interviews.decline', $interview->id) }}" data-cn-int-form
                data-cn-confirm="Decline this invitation? The employer is told you did not take this round, and can offer another time.">
                @csrf
                @if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif
                <button type="submit" class="cn-int-btn-danger"><i class="fas fa-circle-xmark" aria-hidden="true"></i>Decline</button>
            </form>
            @endif

            @if(!$gate['can_respond'])
            <p class="cn-int-hint">{{ $gate['reason'] }}</p>
            @endif

        </div>
    @endif

</article>
