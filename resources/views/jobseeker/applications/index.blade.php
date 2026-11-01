@extends('layouts.jobseeker')

@section('title', 'My Applications')

@section('content')
<div class="main-content">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-icon">
            <i class="fas fa-clock-rotate-left"></i>
        </div>
        <div class="page-header-text">
            <h1>My Applications</h1>
            <p>Track the status of jobs you have applied for.</p>
        </div>
    </div>

    {{-- Filter Applications --}}
    <div class="section-title">
        <i class="fas fa-sliders"></i> Filter Applications
    </div>

    <div class="glass-card">
        <form method="GET"
            action="{{ route('jobseeker.applications.index') }}"
            data-ajax-html>

            <div class="search-grid">

                <div class="form-group">
                    <label for="search">
                        <i class="fas fa-magnifying-glass"></i>
                        Job Title or Company
                    </label>

                    <input type="text"
                        name="search"
                        id="search"
                        maxlength="100"
                        value="{{ request('search') }}"
                        placeholder="Search your applications">
                </div>

                <div class="form-group">
                    <label for="status">
                        <i class="fas fa-filter"></i>
                        Application Status
                    </label>

                    <select name="status" id="status">
                        <option value="">— All Statuses —</option>

                        @foreach(['Pending', 'Accepted', 'Rejected'] as $status)
                        <option value="{{ $status }}"
                            @selected(request('status')===$status)>
                            {{ $status }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label style="visibility:hidden">Filter</label>

                    <button type="submit" class="btn-search">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                </div>

            </div>
        </form>
    </div>


    {{-- Application Results --}}
    @if($applications->isEmpty())

    <div class="glass-card mt-3 text-center py-5">
        <i class="fas fa-folder-open fa-2x mb-3" aria-hidden="true"></i>

        <h2 class="h5">No applications found</h2>

        <p class="mb-0">
            Applications you submit will appear here.
        </p>
    </div>

    @else

    <div class="glass-card mt-3 p-0 overflow-hidden">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0 applications-table">

                <thead>
                    <tr>
                        <th width="30%">Job</th>
                        <th width="20%">Company</th>
                        <th width="25%">Applied</th>
                        <th width="15%">Status</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($applications as $application)

                    <tr>

                        <td>
                            {{ $application->job?->JobTitle ?? 'Job no longer available' }}
                        </td>

                        <td>
                            {{ $application->job?->CompanyName ?? '—' }}
                        </td>

                        <td>
                            {{ $application->ApplicationDate
                                        ? \Illuminate\Support\Carbon::parse($application->ApplicationDate)->format('M j, Y')
                                        : 'Date unavailable' }}
                        </td>

                        @php
                        $statusClass = strtolower($application->Status ?? 'pending');
                        @endphp

                        <td>
                            <span class="application-status-badge {{ in_array($statusClass, ['accepted', 'rejected'], true) ? $statusClass : 'pending' }}">
                                <span class="status-indicator" aria-hidden="true"></span>
                                {{ $application->Status ?: 'Pending' }}
                            </span>
                        </td>

                        <td>
                            <a class="application-details-button"
                                href="{{ route('jobseeker.applications.show', $application) }}">
                                <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                <span>View full details</span>
                            </a>
                        </td>

                    </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

        <div class="p-3">
            {{ $applications->links() }}
        </div>

    </div>

    @endif

</div>
@endsection