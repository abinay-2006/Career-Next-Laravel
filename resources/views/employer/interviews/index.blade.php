@extends('layouts.employer')

@section('title', 'Interviews')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/application-interviews.css') }}?v={{ @filemtime(public_path('assets/css/application-interviews.css')) ?: 1 }}">
@endpushOnce

@section('content')
<div class="main-content cn-int-page">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="page-header-text">
                <h1>Interviews</h1>
                <p>
                    @if($awaiting > 0)
                    {{ $awaiting }} invitation{{ $awaiting === 1 ? '' : 's' }} waiting for you to act.
                    @else
                    Nothing is waiting on you right now.
                    @endif
                </p>
            </div>
        </div>
        <a href="{{ route('employer.applications.index') }}" class="btn-back">
            <i class="fas fa-user-check" aria-hidden="true"></i> Applications
        </a>
    </div>

    @unless($ready)
    <section class="glass-card cn-int-empty is-warn">
        <i class="fas fa-database" aria-hidden="true"></i>
        <h2>Interviews are not set up on this database yet</h2>
        <p>Run <code>php artisan migrate</code> once, and this page fills with the invitations you have sent.</p>
    </section>
    @else

    <form method="GET" class="cn-int-filters">
        <div class="cn-int-filter">
            <label for="cn-int-status">Status</label>
            <select id="cn-int-status" name="status">
                <option value="">All interviews</option>
                @foreach($statusTokens as $token)
                <option value="{{ $token }}" @selected(($filters['status'] ?? '') === $token)>{{ \App\Models\ApplicationInterview::statusLabel($token) }}</option>
                @endforeach
            </select>
        </div>
        <div class="cn-int-filter cn-int-filter-grow">
            <label for="cn-int-search">Search</label>
            <input id="cn-int-search" type="search" name="search" maxlength="100"
                placeholder="Job title or company" value="{{ $filters['search'] ?? '' }}">
        </div>
        <button type="submit" class="cn-int-btn-primary"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Filter</button>
        @if(($filters['status'] ?? '') !== '' || ($filters['search'] ?? '') !== '')
        <a href="{{ route('employer.interviews.index') }}" class="cn-int-btn-quiet">Clear</a>
        @endif
    </form>

    @if($interviews->isEmpty())
    <section class="glass-card cn-int-empty">
        <i class="fas fa-calendar-plus" aria-hidden="true"></i>
        <h2>No interviews to show</h2>
        <p>
            Open an applicant you have accepted and use
            <strong>Schedule interview</strong> to send the first invitation. Only accepted applicants can be
            interviewed, and scheduling never changes the application decision.
        </p>
        <a href="{{ route('employer.applications.index') }}" class="cn-int-btn-primary">
            <i class="fas fa-user-check" aria-hidden="true"></i>Go to applications
        </a>
    </section>
    @else
    <div class="cn-int-list">
        @foreach($interviews as $interview)
        <x-interview-card
            :interview="$interview"
            role="employer"
            :show-job="true"
            :partner="$interview->application?->jobSeeker?->JobSeekerName"
            :busy="$interview->awaitsSeeker()" />
        @endforeach
    </div>

    <div class="cn-int-pagination">{{ $interviews->links('pagination::bootstrap-5') }}</div>
    @endif
    @endunless

</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/application-interviews.js') }}?v={{ @filemtime(public_path('assets/js/application-interviews.js')) ?: 1 }}"></script>
@endsection
