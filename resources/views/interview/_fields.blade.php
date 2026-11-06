@php
    use App\Models\ApplicationInterview;
    use App\Services\ApplicationInterviewService;

    // $model is the interview being revised, or null for a brand new invitation.
    $model = $model ?? null;
    $goto = $goto ?? null;
    $zones = $zones ?? ApplicationInterviewService::COMMON_TIMEZONES;
    $idPrefix = $model ? 'edit-'.$model->id : 'new';
    $storedZone = $model ? (string) $model->timezone : config('app.timezone');
    $pickedType = old('interview_type', $model?->interview_type ?: ApplicationInterview::TYPE_ONLINE);

    // Every card on the page posts the same field names, so the errors can only be read
    // as belonging to the form whose cn_form marker came back with the failed input.
    $failed = old('cn_form') === $idPrefix;
    $dateError = $failed ? trim((string) $errors->first('scheduled_at')) : '';
    $zoneError = $failed ? trim((string) $errors->first('timezone')) : '';
    $linkError = $failed ? trim((string) $errors->first('meeting_link')) : '';
    $locationError = $failed ? trim((string) $errors->first('location')) : '';
    $instructionError = $failed ? trim((string) $errors->first('instructions')) : '';

    // datetime-local carries a wall clock, so the stored instant is shown back in the
    // zone the invitation was written in rather than in the server's zone.
    $localValue = null;

    if ($model?->scheduled_at) {
        try {
            $localValue = $model->scheduled_at->copy()->timezone($storedZone ?: config('app.timezone'))->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            $localValue = $model->scheduled_at->format('Y-m-d\TH:i');
        }
    }
@endphp

<div class="cn-int-field-row">
    <div @class(['cn-int-field', 'is-invalid' => $dateError !== ''])>
        <label for="cn-int-date-{{ $idPrefix }}">Date and time</label>
        <div class="cn-int-date">
            <input id="cn-int-date-{{ $idPrefix }}" type="datetime-local" name="scheduled_at" required
                min="{{ now()->format('Y-m-d\TH:i') }}"
                value="{{ old('scheduled_at', $localValue) }}">
            <button type="button" class="cn-int-btn-quiet cn-int-cal" data-cn-int-picker="cn-int-date-{{ $idPrefix }}" hidden>
                <i class="fas fa-calendar-day" aria-hidden="true"></i>Calendar
            </button>
        </div>
        @if($dateError)<span class="cn-int-field-error">{{ $dateError }}</span>@endif
    </div>

    <div @class(['cn-int-field', 'is-invalid' => $zoneError !== ''])>
        <label for="cn-int-zone-{{ $idPrefix }}">Timezone</label>
        <select id="cn-int-zone-{{ $idPrefix }}" name="timezone" required>
            @foreach($zones as $zone)
            <option value="{{ $zone }}" @selected(old('timezone', $storedZone) === $zone)>{{ $zone }}</option>
            @endforeach
        </select>
        @if($zoneError)<span class="cn-int-field-error">{{ $zoneError }}</span>@endif
    </div>
</div>

<div class="cn-int-field">
    <span class="cn-int-field-label">Interview type</span>
    <div class="cn-int-types" role="radiogroup" aria-label="Interview type">
        @foreach([
            ApplicationInterview::TYPE_ONLINE => ['Online', 'fas fa-video'],
            ApplicationInterview::TYPE_IN_PERSON => ['In person', 'fas fa-location-dot'],
            ApplicationInterview::TYPE_PHONE => ['Phone', 'fas fa-phone'],
        ] as $value => [$label, $icon])
        <label class="cn-int-type-chip{{ $pickedType === $value ? ' is-checked' : '' }}">
            <input type="radio" name="interview_type" value="{{ $value }}"
                data-cn-int-type="{{ $value }}" @checked($pickedType === $value)>
            <span><i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}</span>
        </label>
        @endforeach
    </div>
</div>

<div data-cn-int-field="meeting_link" @class(['cn-int-field', 'is-hidden' => $pickedType !== ApplicationInterview::TYPE_ONLINE, 'is-invalid' => $linkError !== ''])>
    <label for="cn-int-link-{{ $idPrefix }}">Online meeting link</label>
    <input id="cn-int-link-{{ $idPrefix }}" type="url" name="meeting_link" maxlength="500"
        placeholder="https://meet.example.com/room"
        value="{{ old('meeting_link', $model?->interview_type === ApplicationInterview::TYPE_ONLINE ? $model?->meeting_link : null) }}"
        data-cn-int-conditional>
    @if($linkError)
    <span class="cn-int-field-error">{{ $linkError }}</span>
    @else
    <span class="cn-int-help">A full address starting with https:// - the applicant clicks it on the day.</span>
    @endif
</div>

<div data-cn-int-field="location" @class(['cn-int-field', 'is-hidden' => $pickedType !== ApplicationInterview::TYPE_IN_PERSON, 'is-invalid' => $locationError !== ''])>
    <label for="cn-int-location-{{ $idPrefix }}">Interview location</label>
    <input id="cn-int-location-{{ $idPrefix }}" type="text" name="location" maxlength="255"
        placeholder="Office address, floor or room"
        value="{{ old('location', $model?->interview_type === ApplicationInterview::TYPE_IN_PERSON ? $model?->location : null) }}">
    @if($locationError)
    <span class="cn-int-field-error">{{ $locationError }}</span>
    @else
    <span class="cn-int-help">Where to report in person.</span>
    @endif
</div>

<div @class(['cn-int-field', 'is-invalid' => $instructionError !== ''])>
    <label for="cn-int-instructions-{{ $idPrefix }}">Instructions <span class="cn-int-optional">optional</span></label>
    <textarea id="cn-int-instructions-{{ $idPrefix }}" name="instructions" rows="3" maxlength="{{ ApplicationInterview::MAX_INSTRUCTIONS }}"
        placeholder="Who to ask for, what to bring, how long it will take...">{{ old('instructions', $model?->instructions) }}</textarea>
    @if($instructionError)<span class="cn-int-field-error">{{ $instructionError }}</span>@endif
</div>

<input type="hidden" name="cn_form" value="{{ $idPrefix }}">
@if($goto)<input type="hidden" name="goto" value="{{ $goto }}">@endif
