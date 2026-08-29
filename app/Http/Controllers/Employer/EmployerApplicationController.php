<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\Job;

class EmployerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $employer = Auth::guard('employer')->user();

        // Get all jobs posted by this employer
        $jobs = Job::where('CompanyName', $employer->CompanyName)
            ->orderByDesc('JobId')
            ->get();

        // Selected Job ID from dropdown
        $selectedJobId = $request->cmbTitle;

        $selectedJob = null;
        $applications = collect();

        if ($selectedJobId) {

            // Find the selected job
            $selectedJob = $jobs->firstWhere('JobId', $selectedJobId);

            // Load applications for the selected job
            $applications = Application::with('jobSeeker')
                ->where('JobId', $selectedJobId)
                ->get();
        }

        return view('employer.applications.index', compact(
            'jobs',
            'applications',
            'selectedJob',
            'selectedJobId'
        ));
    }
    public function show(Application $application)
    {
        $application->load(['job', 'jobSeeker']);

        return view('employer.applications.show', compact('application'));
    }

    public function update(Request $request, Application $application)
    {
        $request->validate([
            'Status' => 'required|in:Pending,Accepted,Rejected',
            'Description' => 'nullable|string|max:1000',
        ]);

        $application->update([
            'Status' => $request->Status,
            'Description' => $request->Description,
        ]);

        return redirect()
            ->route('employer.applications.show', $application->ApplicationId)
            ->with('success', 'Application updated successfully.');
    }
}
