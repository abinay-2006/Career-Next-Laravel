<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index');
    }

    public function dashboard()
    {
        // Counts
        $totalJobSeekers = DB::table('jobseeker_reg')->count();
        $totalEmployers  = DB::table('employer_reg')->count();
        $totalJobs       = DB::table('job_master')->count();
        $totalWalkins    = DB::table('walkin_master')->count();
        $totalApplications = DB::table('application_master')->count();

        $pendingJobSeekers = DB::table('jobseeker_reg')
            ->where('Status', 'Pending')
            ->count();

        $pendingEmployers = DB::table('employer_reg')
            ->where('Status', 'Pending')
            ->count();

        $confirmedJobSeekers = DB::table('jobseeker_reg')
            ->whereIn('Status', ['Confirm', 'Confirmed', 'Approved'])
            ->count();

        $confirmedEmployers = DB::table('employer_reg')
            ->whereIn('Status', ['Confirm', 'Confirmed', 'Approved'])
            ->count();

        $totalUsers = $totalJobSeekers + $totalEmployers;
        $totalPending = $pendingJobSeekers + $pendingEmployers;

        // Recent Job Seekers
        $recentSeekers = DB::table('jobseeker_reg')
            ->select(
                'JobSeekId',
                'JobSeekerName',
                'City',
                'Email',
                'Qualification',
                'Status'
            )
            ->orderByDesc('JobSeekId')
            ->limit(5)
            ->get();

        // Recent Employers
        $recentEmployers = DB::table('employer_reg')
            ->select(
                'EmployerId',
                'CompanyName',
                'City',
                'Email',
                'Area_Work',
                'Status'
            )
            ->orderByDesc('EmployerId')
            ->limit(5)
            ->get();

        // Recent Jobs
        $recentJobs = DB::table('job_master')
            ->select(
                'JobId',
                'CompanyName',
                'JobTitle',
                'Vacancy',
                'MinQualification'
            )
            ->orderByDesc('JobId')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalJobSeekers',
            'totalEmployers',
            'totalJobs',
            'totalWalkins',
            'totalApplications',
            'pendingJobSeekers',
            'pendingEmployers',
            'confirmedJobSeekers',
            'confirmedEmployers',
            'totalUsers',
            'totalPending',
            'recentSeekers',
            'recentEmployers',
            'recentJobs'
        ));
    }

    public function manageUsers()
    {
        return view('admin.manage_users');
    }

    public function manageJobs()
    {
        return view('admin.manage_jobs');
    }

    public function manageDocuments()
    {
        return view('admin.manage_documents');
    }
}
