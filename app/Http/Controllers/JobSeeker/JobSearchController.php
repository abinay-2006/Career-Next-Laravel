<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;
use App\Models\Application;

class JobSearchController extends Controller
{
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

        // Search Query
        $query = Job::where('Status', 'Approved');

        if ($request->filled('cmbQual')) {
            $query->where('MinQualification', $request->cmbQual);
        }

        if ($request->filled('cmbCompany')) {
            $query->where('CompanyName', $request->cmbCompany);
        }

        if ($request->filled('cmbArea')) {
            $query->where('JobTitle', $request->cmbArea);
        }

        $jobs = $query->orderByDesc('JobId')->get();

        // Logged in Job Seeker
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Application History
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
}
