@extends('layouts.admin')

@section('title', 'Feedback')

@section('content')
<div class="main-content feedback-page">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-icon">
            <i class="fas fa-comments"></i>
        </div>

        <div class="page-header-text">
            <h1>Feedback Management</h1>
            <p>Review feedback submitted by job seekers and employers.</p>

            <span class="feedback-count-badge">
                <i class="fas fa-comment-dots"></i>
                {{ $feedback->total() }} Feedback Entries
            </span>
        </div>
    </div>

    {{-- Filter Feedback --}}
    <div class="section-title">
        <i class="fas fa-sliders"></i>
        Filter Feedback
    </div>

    <div class="glass-card feedback-filter-card">

        <form method="GET"
            action="{{ url()->current() }}"
            data-ajax-html
            data-ajax-submit-on-change>

            <div class="feedback-filter-grid">

                <div class="form-group">
                    <label for="account">
                        <i class="fas fa-users"></i>
                        Account Type
                    </label>

                    <select id="account"
                        name="account"
                        class="form-select">

                        <option value="all"
                            @selected(request('account', 'all' )==='all' )>
                            All Accounts
                        </option>

                        <option value="jobseeker"
                            @selected(request('account')==='jobseeker' )>
                            Job Seeker
                        </option>

                        <option value="employer"
                            @selected(request('account')==='employer' )>
                            Employer
                        </option>

                    </select>
                </div>

            </div>

        </form>

    </div>


    {{-- Submitted Feedback --}}
    <div class="section-title feedback-results-title">
        <i class="fas fa-list"></i>
        Submitted Feedback
    </div>

    <div class="glass-card mt-3 p-0 overflow-hidden feedback-table-card">

        <div class="table-responsive feedback-table-responsive">

            <table class="table table-hover align-middle mb-0 feedback-table"
                style="width: 100%; min-width: 100%;">

                <thead>
                    <tr>
                        <th width="15%">Sender ID</th>
                        <th width="20%">Account Type</th>
                        <th width="40%">Feedback Message</th>
                        <th width="25%">Submitted Date</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($feedback as $item)

                    <tr>

                        {{-- Sender ID --}}
                        <td>
                            <strong class="feedback-sender-id">
                                #{{ $item->JobSeekId ?? $item->EmployerId }}
                            </strong>
                        </td>

                        {{-- Account Type --}}
                        <td>
                            @if($item->JobSeekId)

                            <span class="feedback-role jobseeker-role">
                                <i class="fas fa-user"></i>
                                Job Seeker
                            </span>

                            @else

                            <span class="feedback-role employer-role">
                                <i class="fas fa-building"></i>
                                Employer
                            </span>

                            @endif
                        </td>

                        {{-- Feedback Message --}}
                        <td>
                            <div class="feedback-message">
                                {{ $item->Feedback }}
                            </div>
                        </td>

                        {{-- Submitted Date --}}
                        <td>
                            <span class="feedback-date">
                                <i class="far fa-calendar-alt"></i>

                                {{ $item->FeedbakDate
                                    ? \Illuminate\Support\Carbon::parse($item->FeedbakDate)->format('M j, Y')
                                    : 'Date unavailable' }}
                            </span>
                        </td>

                    </tr>

                    @empty

                    <tr>
                        <td colspan="4" class="text-center py-5">

                            <i class="fas fa-folder-open fa-2x mb-3"
                                aria-hidden="true"></i>

                            <h6 class="mb-2">No feedback found</h6>

                            <p class="mb-0">
                                Feedback submitted by users will appear here.
                            </p>

                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        {{-- Pagination --}}
        @if($feedback->hasPages())
        <div class="p-3 feedback-pagination">
            {{ $feedback->withQueryString()->links() }}
        </div>
        @endif

        {{-- Table Footer --}}
        <div class="feedback-table-footer">

            <span>
                <i class="fas fa-info-circle"></i>
                Showing {{ $feedback->count() }} of {{ $feedback->total() }} feedback entries
            </span>

            <span class="feedback-total">
                <i class="fas fa-database"></i>
                Total {{ $feedback->total() }}
            </span>

        </div>

    </div>

</div>
@endsection
