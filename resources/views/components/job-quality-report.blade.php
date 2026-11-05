@props([
    'formId' => 'postJobForm',
])

{{--
    Job Quality Checker shell. It carries no numbers of its own: job-quality.js
    posts the fields the employer has typed to the employer endpoint and paints
    whatever JobQualityService returned, so the score on screen is always the
    server's. The markup is inert until that reply arrives.
--}}
<div class="cn-quality"
    id="cnQuality-{{ $formId }}"
    data-cn-quality-shell
    data-cn-quality-endpoint="{{ route('employer.jobs.qualityCheck') }}"
    tabindex="-1">
    <div class="cn-quality-head">
        <span class="cn-quality-head-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
        <span class="cn-quality-head-text">
            <span class="cn-quality-title">Job Quality Checker</span>
            <span class="cn-quality-sub">Improve your job posting to give candidates clear and complete information.</span>
        </span>
        <span class="cn-quality-readout">
            <span class="cn-quality-score" data-cn-quality-score>&ndash;</span>
            <span class="cn-quality-of">/100</span>
        </span>
    </div>

    <div class="cn-quality-meter" aria-live="polite">
        <span class="cn-quality-track"><i class="cn-quality-fill" data-cn-quality-fill></i></span>
        <span class="cn-quality-band" data-cn-quality-band>Waiting for your first edit</span>
    </div>

    <ul class="cn-quality-parts" data-cn-quality-categories hidden></ul>

    <div class="cn-quality-busy" data-cn-quality-busy hidden>
        <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Checking the posting&hellip;
    </div>

    <div class="cn-quality-groups">
        <div class="cn-quality-group" data-cn-quality-completed-wrap hidden>
            <span class="cn-quality-group-title"><i class="fas fa-circle-check" aria-hidden="true"></i> Completed checks</span>
            <ul class="cn-quality-list" data-cn-quality-completed></ul>
        </div>

        <div class="cn-quality-group" data-cn-quality-warnings-wrap hidden>
            <span class="cn-quality-group-title"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Warnings</span>
            <ul class="cn-quality-list" data-cn-quality-warnings></ul>
        </div>

        <div class="cn-quality-group" data-cn-quality-suggestions-wrap hidden>
            <span class="cn-quality-group-title"><i class="fas fa-lightbulb" aria-hidden="true"></i> Improvement suggestions</span>
            <ul class="cn-quality-list" data-cn-quality-suggestions></ul>
        </div>

        <div class="cn-quality-group" data-cn-quality-missing-wrap hidden>
            <span class="cn-quality-group-title"><i class="fas fa-asterisk" aria-hidden="true"></i> Missing information</span>
            <ul class="cn-quality-list cn-quality-chips" data-cn-quality-missing></ul>
        </div>
    </div>

    <div class="cn-quality-empty" data-cn-quality-empty>
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <span>Nothing scored yet. Fill a few fields and the report updates as you type.</span>
    </div>

    <span class="cn-quality-note">
        The score measures how complete and clear this advertisement is. It does not verify an employer and it is no proof that a job is genuine.
    </span>
</div>
