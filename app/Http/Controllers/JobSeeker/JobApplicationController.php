<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\Job;

class JobApplicationController extends Controller
{
    public function apply(Job $job)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Only approved jobs can receive applications.
        if ($job->Status !== 'Approved') {
            abort(404);
        }

        // Prevent duplicate applications
        $exists = Application::where('JobId', $job->JobId)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();

        if ($exists) {
            return back()->with('error', 'You have already applied for this job.');
        }

        Application::create([
            'JobSeekId'   => $jobSeeker->JobSeekId,
            'JobId'       => $job->JobId,
            'Status'      => 'Pending',
            'Description' => 'Application Submitted',
        ]);

        return back()->with('success', 'Application submitted successfully.');
    }
}
