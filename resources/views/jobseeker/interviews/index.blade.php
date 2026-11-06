@extends('layouts.jobseeker')

@section('title', 'My Interviews')

@pushOnce('styles')
<link rel="stylesheet" href="{{ asset('assets/css/application-interviews.css') }}?v={{ @filemtime(public_path('assets/css/application-interviews.css')) ?: 1 }}">
@endpushOnce

@section('content')
<div class="main-content application-detail-page cn-int-page">

    <div class="page-header">
        <div class="page-header-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="page-header-text">
            <h1>My interviews</h1>
            <p>
                @if($awaiting > 0)
                {{ $awaiting }} invitation{{ $awaiting === 1 ? '' : 's' }} waiting for your answer.
                @else
                You have answered every invitation so far.
                @endif
            </p>
        </div>
        <a class="back-btn" href="{{ route('jobseeker.applications.index') }}">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to applications
        </a>
    </div>

    @unless($ready)
    <section class="glass-card cn-int-empty is-warn">
        <i class="fas fa-database" aria-hidden="true"></i>
        <h2>Interviews are not set up on this database yet</h2>
        <p>Run <code>php artisan migrate</code> once, and invitations from employers appear here.</p>
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
        <a href="{{ route('jobseeker.interviews.index') }}" class="cn-int-btn-quiet">Clear</a>
        @endif
    </form>

    @if($interviews->isEmpty())
    <section class="glass-card cn-int-empty">
        <i class="fas fa-mug-hot" aria-hidden="true"></i>
        <h2>No interview invitations yet</h2>
        <p>
            When an employer accepts your application and offers an interview, the invitation lands here with
            the time, the type and the instructions. You can then confirm, ask for another time or decline.
        </p>
        <a href="{{ route('jobseeker.applications.index') }}" class="cn-int-btn-primary">
            <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>See my applications
        </a>
    </section>
    @else
    <div class="cn-int-list">
        @foreach($interviews as $interview)
        <x-interview-card
            :interview="$interview"
            role="jobseeker"
            :show-job="true"
            :partner="$interview->CompanyName ?? $interview->application?->job?->CompanyName"
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
