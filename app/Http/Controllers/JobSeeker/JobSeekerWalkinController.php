<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Walkin;
use Carbon\Carbon;

class JobSeekerWalkinController extends Controller
{
    public function index()
    {
        // Get all walk-ins ordered by latest interview date
        $walkins = Walkin::orderBy('InterviewDate', 'desc')
            ->orderBy('InterviewTime', 'desc')
            ->get();

        // Upcoming interviews
        $upcoming = $walkins->filter(function ($walkin) {
            return Carbon::parse($walkin->InterviewDate)->greaterThanOrEqualTo(Carbon::today());
        });

        // Past interviews
        $past = $walkins->filter(function ($walkin) {
            return Carbon::parse($walkin->InterviewDate)->lt(Carbon::today());
        });

        return view('jobseeker.walkins.index', [
            'walkins'   => $walkins,
            'upcoming'  => $upcoming,
            'past'      => $past,
            'total'     => $walkins->count(),
        ]);
    }
}