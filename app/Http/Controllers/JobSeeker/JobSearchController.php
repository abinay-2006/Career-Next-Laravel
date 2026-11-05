<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;
use App\Models\Application;
use App\Services\JobMatchService;

class JobSearchController extends Controller
{
    /** Match thresholds offered by the Smart Match filter. */
    private const MATCH_THRESHOLDS = [40, 60, 80];

    public function __construct(private readonly JobMatchService $jobMatch)
    {
    }

    /**
     * Display approved jobs and application history.
     */
    public function index(Request $request)
    {
        // Dropdown values
        $qualifications = Job::whereRaw('LOWER(Status) = ?', ['approved'])
            ->distinct()
            ->orderBy('MinQualification')
            ->pluck('MinQualification');

        $companies = Job::whereRaw('LOWER(Status) = ?', ['approved'])
            ->distinct()
            ->orderBy('CompanyName')
            ->pluck('CompanyName');

        $titles = Job::whereRaw('LOWER(Status) = ?', ['approved'])
            ->distinct()
            ->orderBy('JobTitle')
            ->pluck('JobTitle');

        // Search query
        $query = Job::whereRaw('LOWER(Status) = ?', ['approved']);

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

        // The seeker's saved skills and resume are read once and reused for every card.
        $candidate = $this->jobMatch->candidateLists($jobSeeker->JobSeekId);
        $candidateSkills = $candidate['skills'];

        $matches = $jobs->mapWithKeys(fn (Job $job) => [
            $job->JobId => $this->jobMatch->calculate($candidateSkills, $job, $candidate['software']),
        ]);

        $minimumMatch = $this->minimumMatch($request);
        $sort = $request->input('sort') === 'best_match' ? 'best_match' : 'latest';

        if ($candidateSkills !== []) {
            if ($minimumMatch > 0) {
                $jobs = $jobs->filter(function (Job $job) use ($matches, $minimumMatch) {
                    $match = $matches[$job->JobId];

                    return $match['status'] === JobMatchService::STATUS_SCORED
                        && $match['percentage'] >= $minimumMatch;
                })->values();
            }

        }

        if ($sort === 'best_match') {
            $jobs = $jobs->sort(function (Job $left, Job $right) use ($matches): int {
                $leftScore = $matches[$left->JobId]['percentage'] ?? -1;
                $rightScore = $matches[$right->JobId]['percentage'] ?? -1;

                return ($rightScore <=> $leftScore) ?: ((int) $right->JobId <=> (int) $left->JobId);
            })->values();
        }

        // Application history
        $applications = Application::with('job')
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->orderByDesc('ApplicationId')
            ->get();

        return view('jobseeker.jobs.index', [
            'jobs'             => $jobs,
            'applications'     => $applications,
            'qualifications'   => $qualifications,
            'companies'        => $companies,
            'titles'           => $titles,
            'matches'          => $matches,
            'candidateSkills'  => $candidateSkills,
            'minimumMatch'     => $minimumMatch,
            'sort'             => $sort,
        ]);
    }

    /**
     * Display details of a single approved job.
     */
    public function show(Job $job)
    {
        // Only approved jobs can be viewed by Job Seekers.
        if (strtolower(trim((string) $job->Status)) !== 'approved') {
            abort(404);
        }

        // The EnsureUserRole middleware guarantees an authenticated job seeker.
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Determine if the current Job Seeker has already applied for this job.
        $alreadyApplied = false;
        if ($jobSeeker) {
            $alreadyApplied = Application::where('JobId', $job->JobId)
                ->where('JobSeekId', $jobSeeker->JobSeekId)
                ->exists();
        }

        $candidate = $this->jobMatch->candidateLists($jobSeeker->JobSeekId);

        $match = $this->jobMatch->calculate(
            $candidate['skills'],
            $job,
            $candidate['software']
        );

        return view('jobseeker.jobs.show', compact(
            'job',
            'alreadyApplied',
            'match'
        ));
    }

    /**
     * Only the published thresholds are accepted; anything else is ignored.
     */
    private function minimumMatch(Request $request): int
    {
        $value = (int) $request->input('cmbMatch');

        return in_array($value, self::MATCH_THRESHOLDS, true) ? $value : 0;
    }
}
