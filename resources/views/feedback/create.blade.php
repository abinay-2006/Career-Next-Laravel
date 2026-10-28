@extends(Auth::guard('jobseeker')->check() ? 'layouts.jobseeker' : 'layouts.employer')

@section('title', 'Feedback')
@section('content')
<div class="main-content"><div class="page-header"><div class="page-header-icon"><i class="fas fa-comment"></i></div><div class="page-header-text"><h1>Share Feedback</h1><p>Your feedback helps us improve Career Next.</p></div></div>
<div class="glass-card" style="max-width:760px"><form method="POST" data-ajax data-ajax-reset data-loading-text="Submitting..." action="{{ route(Auth::guard('jobseeker')->check() ? 'jobseeker.feedback.store' : 'employer.feedback.store') }}">@csrf<label class="form-label" for="Feedback">Feedback</label><textarea class="form-control" id="Feedback" name="Feedback" rows="5" maxlength="200" required>{{ old('Feedback') }}</textarea><div class="form-text">Up to 200 characters.</div><button class="btn btn-primary mt-3" type="submit">Submit feedback</button></form></div></div>
@endsection
