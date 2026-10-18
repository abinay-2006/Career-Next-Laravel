@extends('layouts.admin')
@section('title', 'Feedback')
@section('content')
<div class="main-content"><div class="page-header"><div class="page-header-text"><h1>Feedback</h1><p>Feedback submitted by job seekers and employers.</p></div></div>
<form method="GET" class="mb-3"><label for="account" class="form-label">Account type</label><select id="account" name="account" class="form-select" onchange="this.form.submit()"><option value="all">All</option><option value="jobseeker" @selected(request('account')==='jobseeker')>Job Seeker</option><option value="employer" @selected(request('account')==='employer')>Employer</option></select></form>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Sender ID</th><th>Role</th><th>Feedback</th><th>Submitted</th></tr></thead><tbody>@forelse($feedback as $item)<tr><td>{{ $item->JobSeekId ?? $item->EmployerId }}</td><td>{{ $item->JobSeekId ? 'Job Seeker' : 'Employer' }}</td><td>{{ $item->Feedback }}</td><td>{{ $item->FeedbakDate }}</td></tr>@empty<tr><td colspan="4">No feedback has been submitted.</td></tr>@endforelse</tbody></table></div>{{ $feedback->links() }}</div>
@endsection
