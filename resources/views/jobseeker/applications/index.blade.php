@extends('layouts.jobseeker')

@section('title', 'My Applications')

@section('content')
<div class="main-content">
    <div class="page-header"><div class="page-header-icon"><i class="fas fa-clock-rotate-left"></i></div><div class="page-header-text"><h1>My Applications</h1><p>Track the status of jobs you have applied for.</p></div></div>
    <div class="glass-card">
        <form method="GET" action="{{ route('jobseeker.applications.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-6"><label class="form-label" for="search">Job title or company</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" maxlength="100"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option>@foreach(['Pending','Accepted','Rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
        </form>
    </div>
    @if($applications->isEmpty())
        <div class="glass-card mt-3 text-center py-5"><i class="fas fa-folder-open fa-2x mb-3" aria-hidden="true"></i><h2 class="h5">No applications found</h2><p class="mb-0">Applications you submit will appear here.</p></div>
    @else
        <div class="glass-card mt-3 p-0 overflow-hidden">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Job</th><th>Company</th><th>Applied</th><th>Status</th><th>Details</th></tr></thead><tbody>
            @foreach($applications as $application)
                <tr><td>{{ $application->job?->JobTitle ?? 'Job no longer available' }}</td><td>{{ $application->job?->CompanyName ?? '—' }}</td><td>{{ $application->ApplicationDate ? \Illuminate\Support\Carbon::parse($application->ApplicationDate)->format('M j, Y') : 'Date unavailable' }}</td><td><span class="badge text-bg-{{ $application->Status === 'Accepted' ? 'success' : ($application->Status === 'Rejected' ? 'danger' : 'warning') }}">{{ $application->Status }}</span></td><td><a href="{{ route('jobseeker.applications.show', $application) }}">View details</a></td></tr>
            @endforeach
            </tbody></table></div>
            <div class="p-3">{{ $applications->links() }}</div>
        </div>
    @endif
</div>
@endsection
