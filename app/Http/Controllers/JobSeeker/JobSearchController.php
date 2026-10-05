<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;
use App\Models\Application;

class JobSearchController extends Controller
{
    /**
     * Display approved jobs and application history.
     */
    public function index(Request $request)
    {
        // Dropdown values
        $qualifications = Job::where('Status', 'Approved')
            ->distinct()
            ->orderBy('MinQualification')
            ->pluck('MinQualification');

        $companies = Job::where('Status', 'Approved')
            ->distinct()
            ->orderBy('CompanyName')
            ->pluck('CompanyName');

        $titles = Job::where('Status', 'Approved')
            ->distinct()
            ->orderBy('JobTitle')
            ->pluck('JobTitle');

        // Search query
        $query = Job::where('Status', 'Approved');

        if ($request->filled('cmbQual')) {
            $query->where('MinQualification', $request->cmbQual);
        }

        if ($request->filled('cmbCompany')) {
            $query->where('CompanyName', $request->cmbCompany);
        }

        if ($request->filled('cmbTitle')) {
            $query->where('JobTitle', $request->cmbTitle);
        }

        $jobs = $query
            ->orderByDesc('JobId')
            ->get();

        // Logged-in Job Seeker
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Application history
        $applications = Application::with('job')
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->orderByDesc('ApplicationId')
            ->get();

        return view('jobseeker.jobs.index', [
            'jobs'           => $jobs,
            'applications'   => $applications,
            'qualifications' => $qualifications,
            'companies'      => $companies,
            'titles'         => $titles,
        ]);
    }

    /**
     * Display details of a single approved job.
     */
    public function show(Job $job)
    {
        // Only approved jobs can be viewed by Job Seekers.
        if ($job->Status !== 'Approved') {
            abort(404);
        }

        $jobSeeker = Auth::guard('jobseeker')->user();

        // Check whether the current Job Seeker has already applied.
        $alreadyApplied = Application::where('JobId', $job->JobId)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();

        return view('jobseeker.jobs.show', compact(
            'job',
            'alreadyApplied'
        ));
    }
}
