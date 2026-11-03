@extends('layouts.admin')

@section('title', 'Activity Logs')

@section('content')
<div class="main-content activity-logs-page">

    {{-- Page Header --}}
    <div class="page-header activity-header">
        <div class="page-header-text">
            <h1><i class="bi bi-clock-history"></i> Activity Logs</h1>
            <p>Monitor system activity, review user actions, and track recorded events.</p>
        </div>

        <div class="activity-header-badge">
            <i class="bi bi-shield-check"></i>
            <span>Audit Trail</span>
        </div>
    </div>

    {{-- Information Card --}}
    <div class="activity-info-card">
        <div class="activity-info-icon">
            <i class="bi bi-info-circle"></i>
        </div>
        <div>
            <h6>About activity records</h6>
            <p>
                Historical records may not include actor or target details.
                Newer events include safe identifiers and metadata.
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="activity-card filter-card">
        <div class="activity-card-heading">
            <div class="activity-heading-icon">
                <i class="bi bi-funnel"></i>
            </div>
            <div>
                <h5>Filter Activity</h5>
                <p>Narrow down records using the available filters.</p>
            </div>
        </div>

        <form class="activity-filter-form row g-3"
            method="GET"
            data-ajax-html>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="action">Action</label>
                <input
                    class="form-control"
                    id="action"
                    name="action"
                    value="{{ request('action') }}"
                    maxlength="80"
                    placeholder="Search by action">
            </div>

            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="actor_type">Actor Role</label>
                <select class="form-select" name="actor_type" id="actor_type">
                    <option value="">All roles</option>
                    @foreach(['admin', 'employer', 'jobseeker'] as $role)
                    <option value="{{ $role }}" @selected(request('actor_type')===$role)>
                        {{ ucfirst($role) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="actor_id">Actor ID</label>
                <input
                    class="form-control"
                    id="actor_id"
                    name="actor_id"
                    type="number"
                    min="1"
                    value="{{ request('actor_id') }}"
                    placeholder="Enter ID">
            </div>

            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="from">From Date</label>
                <input
                    class="form-control"
                    id="from"
                    name="from"
                    type="date"
                    value="{{ request('from') }}">
            </div>

            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="to">To Date</label>
                <input
                    class="form-control"
                    id="to"
                    name="to"
                    type="date"
                    value="{{ request('to') }}">
            </div>

            <div class="col-12 col-md-6 col-xl-1 activity-filter-actions">
                <button type="submit" class="btn btn-primary activity-filter-btn">
                    <i class="bi bi-search"></i>
                    <span>Filter</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Activity Table --}}
    <div class="activity-card activity-table-card">

        <div class="activity-card-heading activity-table-heading">
            <div class="activity-heading-icon">
                <i class="bi bi-list-ul"></i>
            </div>
            <div>
                <h5>Activity Records</h5>
                <p>Recorded events and associated details.</p>
            </div>
        </div>

        <div class="table-responsive activity-table-wrapper">
            <table class="table activity-table align-middle">
                <thead>
                    <tr>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Job ID</th>
                        <th>Safe Metadata</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <div class="activity-actor">
                                <div class="activity-avatar">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div>
                                    <span class="activity-primary-text">
                                        {{ $log->actor_type ? ucfirst($log->actor_type) : 'System' }}
                                    </span>
                                    <small>
                                        {{ $log->actor_type ? '#'.$log->actor_id : 'Historical record' }}
                                    </small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="activity-action-badge">
                                {{ $log->action }}
                            </span>
                        </td>

                        <td>
                            @if($log->target_type)
                            <span class="activity-primary-text">
                                {{ $log->target_type }}
                            </span>
                            <small class="activity-subtext">#{{ $log->target_id }}</small>
                            @else
                            <span class="activity-muted">—</span>
                            @endif
                        </td>

                        <td>
                            @if($log->JobId)
                            <span class="activity-id-badge">
                                #{{ $log->JobId }}
                            </span>
                            @else
                            <span class="activity-muted">—</span>
                            @endif
                        </td>

                        <td>
                            @if($log->metadata)
                            <details class="activity-metadata">
                                <summary>
                                    <i class="bi bi-code-slash"></i>
                                    View metadata
                                </summary>
                                <pre>{{ json_encode(json_decode($log->metadata, true), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
                            </details>
                            @else
                            <span class="activity-muted">No metadata</span>
                            @endif
                        </td>

                        <td>
                            <div class="activity-date">
                                <i class="bi bi-calendar3"></i>
                                <span>{{ $log->cdate }}</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="activity-empty-state">
                                <div class="activity-empty-icon">
                                    <i class="bi bi-journal-x"></i>
                                </div>
                                <h6>No activity records found</h6>
                                <p>Try changing your filters to find matching records.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="activity-pagination">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>

    </div>
    @endsection