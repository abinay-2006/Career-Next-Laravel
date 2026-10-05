<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Walkin;
use Carbon\Carbon;

class JobSeekerWalkinController extends Controller
{
    public function index()
    {
        $walkins = Walkin::orderBy('InterviewDate', 'asc')
            ->orderBy('InterviewTime', 'asc')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Separate upcoming and past interviews
        |--------------------------------------------------------------------------
        | Interviews scheduled for today are considered upcoming.
        */

        $today = Carbon::today();

        $upcoming = $walkins->filter(function ($walkin) use ($today) {
            return Carbon::parse($walkin->InterviewDate)->gte($today);
        })->values();

        $past = $walkins->filter(function ($walkin) use ($today) {
            return Carbon::parse($walkin->InterviewDate)->lt($today);
        })->sortByDesc(function ($walkin) {
            return Carbon::parse($walkin->InterviewDate);
        })->values();

        return view('jobseeker.walkins.index', [
            'walkins'  => $walkins,
            'upcoming' => $upcoming,
            'past'     => $past,
            'total'    => $walkins->count(),
        ]);
    }
}
