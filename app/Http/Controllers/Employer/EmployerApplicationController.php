<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\Job;

class EmployerApplicationController extends Controller
{
    /**
     * Display applications for jobs posted by the logged-in employer.
     */
    public function index(Request $request)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        // Get only jobs posted by this employer.
        $jobs = Job::where('CompanyName', $employer->CompanyName)
            ->orderByDesc('JobId')
            ->get();

        $selectedJobId = $request->cmbTitle;

        $selectedJob = null;
        $applications = collect();

        if ($selectedJobId) {

            // Find the selected job only from this employer's jobs.
            $selectedJob = $jobs->firstWhere('JobId', $selectedJobId);

            if ($selectedJob) {
                $applications = Application::with('jobSeeker')
                    ->where('JobId', $selectedJob->JobId)
                    ->get();
            }
        }

        return view('employer.applications.index', compact(
            'jobs',
            'applications',
            'selectedJob',
            'selectedJobId'
        ));
    }

    /**
     * Display a single application.
     */
    public function show(Application $application)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $application->load(['job', 'jobSeeker']);

        // Make sure the application belongs to a job
        // posted by this employer.
        if (
            !$application->job ||
            $application->job->CompanyName !== $employer->CompanyName
        ) {
            abort(403, 'Unauthorized');
        }

        return view('employer.applications.show', compact('application'));
    }

    /**
     * Update an application status.
     */
    public function update(Request $request, Application $application)
    {
        $request->validate([
            'Status' => 'required|in:Pending,Accepted,Rejected',
            'Description' => 'nullable|string|max:1000',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $application->load('job');

        // Ensure this application belongs to the logged-in employer.
        if (
            !$application->job ||
            $application->job->CompanyName !== $employer->CompanyName
        ) {
            abort(403, 'Unauthorized');
        }

        $application->update([
            'Status' => $request->Status,
            'Description' => $request->Description,
        ]);

        return redirect()
            ->route(
                'employer.applications.show',
                $application->ApplicationId
            )
            ->with('success', 'Application updated successfully.');
    }
}
