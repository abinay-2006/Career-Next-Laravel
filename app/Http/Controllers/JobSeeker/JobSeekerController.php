<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobSeekerController extends Controller
{
    public function index()
    {
        return view('jobseeker.index');
    }

    public function education()
    {
        return view('jobseeker.education.index');
    }

    public function jobs()
    {
        return view('jobseeker.jobs.index');
    }

    public function walkins()
    {
        return view('jobseeker.walkins.index');
    }
}
