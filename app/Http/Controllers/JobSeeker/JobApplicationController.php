<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\Job;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function apply(Request $request, Job $job)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Only approved jobs can receive applications.
        if (strtolower(trim((string) $job->Status)) !== 'approved') {
            abort(404);
        }

        // Prevent duplicate applications
        $exists = Application::where('JobId', $job->JobId)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();

        if ($exists) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'You have already applied for this job.'], 409);
            }
            return back()->with('error', 'You have already applied for this job.');
        }

        $application = Application::create([
            'JobSeekId'   => $jobSeeker->JobSeekId,
            'JobId'       => $job->JobId,
            'Status'      => 'Pending',
            'Description' => 'Application Submitted',
            'ApplicationDate' => now(),
        ]);
        app(ActivityLogger::class)->record('application_submitted', 'application', $application->ApplicationId, $job->JobId);

        $message = 'Application submitted successfully.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
