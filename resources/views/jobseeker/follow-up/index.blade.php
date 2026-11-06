@extends('layouts.jobseeker')

@section('title', 'Follow-up Coach')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/follow-up-coach.css') }}?v={{ @filemtime(public_path('assets/css/follow-up-coach.css')) ?: 1 }}">
@endpushOnce

@section('content')
@php
    $filter = (string) ($filters['filter'] ?? 'all');
    $search = trim((string) ($filters['search'] ?? ''));
    $today = now()->toDateString();

    $tabs = [
        'all' => 'All applications',
        'awaiting' => 'Awaiting response',
        'suggested' => 'Follow-up suggested',
        'interview' => 'Interview scheduled',
        'rejected' => 'Rejected',
        'accepted' => 'Accepted',
        'attention' => 'Needs attention',
    ];

    $stats = [
        ['label' => 'Total applications', 'value' => $statistics['total'], 'detail' => $search !== '' ? 'Matching your search' : 'Recorded on your account'],
        ['label' => 'Awaiting response', 'value' => $statistics['awaiting'], 'detail' => 'Still pending with the employer'],
        ['label' => 'Follow-up suggested', 'value' => $statistics['suggested'], 'detail' => 'Past the window you chose ('.$interval.' days)'],
        ['label' => 'Interviews scheduled', 'value' => $statistics['interview'], 'detail' => $statistics['interview'] === 0 ? 'No interview stage is recorded yet' : 'Prepare for these next'],
        ['label' => 'Needs attention', 'value' => $statistics['attention'], 'detail' => 'No recorded update for a while'],
    ];
@endphp

<div class="cn-fu-page" data-fu-page>

    <section class="cn-fu-hero">
        <div class="cn-fu-hero-icon"><i class="fas fa-envelope-circle-check" aria-hidden="true"></i></div>
        <div class="cn-fu-hero-copy">
            <h1>Application Follow-up Coach</h1>
            <p>Stay organized, follow up at the right time and keep your job search moving.</p>
        </div>
    </section>

    <section class="cn-fu-stats" aria-label="Application statistics">
        @foreach($stats as $stat)
        <article class="cn-fu-stat">
            <span class="cn-fu-stat-label">{{ $stat['label'] }}</span>
            <span class="cn-fu-stat-value">{{ $stat['value'] }}</span>
            <span class="cn-fu-stat-detail">{{ $stat['detail'] }}</span>
        </article>
        @endforeach
    </section>

    <section class="cn-fu-card cn-fu-controls" aria-label="Filter and settings">
        <form class="cn-fu-search" method="GET" action="{{ route('jobseeker.followup.index') }}" data-ajax-html data-no-loader>
            <div class="cn-fu-field cn-fu-field-search">
                <label for="cnFuSearch"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Job title or company</label>
                <input type="search" id="cnFuSearch" name="search" maxlength="100" value="{{ $search }}"
                    placeholder="Search your applications">
            </div>
            <div class="cn-fu-field">
                <label for="cnFuStatus">Group</label>
                <select id="cnFuStatus" name="filter">
                    @foreach($tabs as $key => $label)
                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cn-fu-field cn-fu-field-submit">
                <button type="submit" class="cn-fu-btn cn-fu-btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
            </div>
        </form>

        <nav class="cn-fu-tabs" aria-label="Application groups">
            @foreach($tabs as $key => $label)
            <a class="cn-fu-tab cn-fu-btn-tab{{ $filter === $key ? ' is-active' : '' }}"
                href="{{ route('jobseeker.followup.index', array_filter(['filter' => $key === 'all' ? null : $key, 'search' => $search !== '' ? $search : null])) }}"
                @if($filter === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <form class="cn-fu-interval" method="POST" action="{{ route('jobseeker.followup.preferences') }}"
            data-ajax data-ajax-refresh-target="[data-ajax-region]" data-no-loader>
            @csrf
            <label for="cnFuInterval">Suggest a follow-up once an application has been waiting</label>
            <span class="cn-fu-interval-row">
                <select id="cnFuInterval" name="days">
                    @foreach($intervalOptions as $option)
                    <option value="{{ $option }}" @selected($interval === $option)>{{ $option }} days</option>
                    @endforeach
                </select>
                <button type="submit" class="cn-fu-btn cn-fu-btn-quiet">Save</button>
            </span>
            <span class="cn-fu-interval-note">These are suggestions about your own records, not claims about how fast employers reply.</span>
        </form>
    </section>

    @if($cards->isEmpty())
    <section class="cn-fu-card cn-fu-empty">
        <div class="cn-fu-empty-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
        <h2>{{ $search !== '' || $filter !== 'all' ? 'Nothing in this group' : 'No applications yet' }}</h2>
        <p>
            @if($search !== '' || $filter !== 'all')
                Clear the search or pick another group to see the rest of your applications.
            @else
                Applications you submit appear here with a follow-up recommendation, a timeline of what was actually recorded and drafts you can copy.
            @endif
        </p>
        <div class="cn-fu-empty-actions">
            @if($search !== '' || $filter !== 'all')
            <a class="cn-fu-btn cn-fu-btn-quiet" href="{{ route('jobseeker.followup.index') }}">
                <i class="fas fa-rotate-left" aria-hidden="true"></i> Reset filters
            </a>
            @endif
            <a class="cn-fu-btn" href="{{ route('jobseeker.jobs') }}">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i> Browse jobs
            </a>
        </div>
    </section>
    @else
    <p class="cn-fu-count" role="status">{{ $cards->total() }} application{{ $cards->total() === 1 ? '' : 's' }} in this group.</p>
    @foreach($cards as $row)
        @include('jobseeker.follow-up.card', ['row' => $row, 'today' => $today, 'templates' => $templates, 'trackerReady' => $trackerReady, 'prepDone' => $prepState[$row['application_id']] ?? []])
    @endforeach

    @if($cards->hasPages())
    <div class="cn-fu-card cn-fu-pagination">{{ $cards->links('pagination::bootstrap-5') }}</div>
    @endif
    @endif
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/follow-up-coach.js') }}?v={{ @filemtime(public_path('assets/js/follow-up-coach.js')) ?: 1 }}"></script>
@endsection
