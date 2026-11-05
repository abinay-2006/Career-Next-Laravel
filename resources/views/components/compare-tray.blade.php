@php
    /*
     * The "comparison is full" dialog. Slot rows are rendered from the session so
     * the dialog works without JavaScript, and the inert template below carries
     * the same markup for when the list changes without a reload.
     */
    $trayEntries = \App\Support\JobComparisonState::entries();
@endphp

<div class="cn-cmp-dialog" data-cmp-dialog hidden>
    <div class="cn-cmp-dialog-box" role="dialog" aria-modal="true" aria-labelledby="cnCmpDialogTitle">
        <h3 id="cnCmpDialogTitle">Your comparison is full</h3>

        <p class="cn-cmp-dialog-lead">
            Compare up to four jobs at a time. Replace one of these with
            <b data-cmp-pending>that job</b>?
        </p>

        <ul class="cn-cmp-dialog-list" data-cmp-dialog-list>
            @foreach($trayEntries as $entry)
            <li>
                <span class="cn-cmp-dialog-job">
                    <b>{{ $entry['title'] !== '' ? $entry['title'] : 'Job ' . $entry['id'] }}</b>
                    @if($entry['company'] !== '')
                    <small>{{ $entry['company'] }}</small>
                    @endif
                </span>

                <form method="POST" action="{{ route('jobseeker.compare.replace', $entry['id']) }}"
                    data-cmp-replace data-no-loader>
                    @csrf
                    <input type="hidden" name="with" value="" data-cmp-replace-with>
                    <button type="submit" class="cn-cmp-btn cn-cmp-btn-sm">Replace</button>
                </form>
            </li>
            @endforeach
        </ul>

        <button type="button" class="cn-cmp-btn cn-cmp-btn-quiet" data-cmp-dialog-close>
            Keep my comparison
        </button>
    </div>
</div>

<template data-cmp-row-template>
    <li>
        <span class="cn-cmp-dialog-job">
            <b data-cmp-row-title></b>
            <small data-cmp-row-company></small>
        </span>

        <form method="POST" action="{{ route('jobseeker.compare.replace', ['job' => '__JOB__']) }}"
            data-cmp-replace data-no-loader>
            @csrf
            <input type="hidden" name="with" value="" data-cmp-replace-with>
            <button type="submit" class="cn-cmp-btn cn-cmp-btn-sm">Replace</button>
        </form>
    </li>
</template>
