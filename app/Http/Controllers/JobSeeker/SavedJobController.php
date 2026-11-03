<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\SavedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SavedJobController extends Controller
{
    public function index(): View
    {
        $jobSeekerId = Auth::guard('jobseeker')->user()->JobSeekId;

        $savedJobs = SavedJob::query()
            ->with('job')
            ->where('JobSeekId', $jobSeekerId)
            ->latest('created_at')
            ->paginate(12);

        return view('jobseeker.saved-jobs.index', compact('savedJobs'));
    }

    public function store(\Illuminate\Http\Request $request, int $job): JsonResponse|RedirectResponse
    {
        $jobSeekerId = Auth::guard('jobseeker')->user()->JobSeekId;
        $jobRecord = Job::query()->findOrFail($job);

        if (strtolower(trim((string) $jobRecord->Status)) !== 'approved') {
            abort(404);
        }

        SavedJob::query()->firstOrCreate([
            'JobSeekId' => $jobSeekerId,
            'JobId' => $jobRecord->JobId,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Job added to your saved jobs.',
                'refresh' => true,
            ]);
        }

        return back()->with('success', 'Job added to your saved jobs.');
    }

    public function destroy(\Illuminate\Http\Request $request, int $job): JsonResponse|RedirectResponse
    {
        $jobSeekerId = Auth::guard('jobseeker')->user()->JobSeekId;

        // Scope deletion to the authenticated job seeker; another account's bookmark is untouched.
        SavedJob::query()
            ->where('JobSeekId', $jobSeekerId)
            ->where('JobId', $job)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Job removed from your saved jobs.',
                'refresh' => true,
            ]);
        }

        return back()->with('success', 'Job removed from your saved jobs.');
    }
}
