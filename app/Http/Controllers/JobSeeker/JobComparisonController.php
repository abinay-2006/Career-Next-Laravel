<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\SavedJob;
use App\Services\JobMatchService;
use App\Services\JobOfferComparisonService;
use App\Services\JobSuitabilityService;
use App\Support\JobComparisonState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class JobComparisonController extends Controller
{
    /** Saved jobs offered as pickers under a full comparison table. */
    private const POOL_LIMIT = 6;

    public function __construct(
        private readonly JobMatchService $jobMatch,
        private readonly JobSuitabilityService $suitability,
        private readonly JobOfferComparisonService $comparison,
    ) {
    }

    public function index(Request $request): View
    {
        $this->adoptSharedSelection($request);

        $jobIds = JobComparisonState::jobIds();
        $built = $this->build($jobIds);
        $comparison = $built['comparison'];

        // A listing deleted or pulled down since it was picked cannot be
        // compared, so it leaves the selection as well.
        if (count($comparison['columns']) !== count($jobIds)) {
            JobComparisonState::retain(array_keys($comparison['columns']));
        }

        return view('jobseeker.comparison.index', [
            'comparison' => $comparison,
            'results' => $built['results'],
            'maxJobs' => JobComparisonState::MAX_JOBS,
            'shareIds' => implode(',', array_keys($comparison['columns'])),
            'savedExtras' => $this->savedExtras(array_map('intval', array_keys($comparison['columns']))),
        ]);
    }

    public function add(Request $request, int $job): JsonResponse|RedirectResponse
    {
        $record = $this->visibleJob($job);

        $state = JobComparisonState::add(
            (int) $record->JobId,
            (string) $record->JobTitle,
            (string) $record->CompanyName
        );

        return $this->respond($request, match ($state) {
            JobComparisonState::ADDED => [
                'success' => true,
                'state' => $state,
                'message' => 'Added to your comparison.',
                'count' => JobComparisonState::count(),
            ],
            JobComparisonState::DUPLICATE => [
                'success' => true,
                'state' => $state,
                'message' => 'This job is already in your comparison.',
                'count' => JobComparisonState::count(),
            ],
            default => [
                'success' => true,
                'state' => $state,
                'message' => 'Your comparison holds four jobs. Replace one to add this one.',
                'count' => JobComparisonState::count(),
                'entries' => JobComparisonState::entries(),
                'added' => ['id' => (int) $record->JobId, 'title' => (string) $record->JobTitle],
            ],
        });
    }

    public function remove(Request $request, int $job): JsonResponse|RedirectResponse
    {
        JobComparisonState::remove($job);

        return $this->respond($request, [
            'success' => true,
            'state' => 'removed',
            'message' => 'Removed from your comparison.',
            'count' => JobComparisonState::count(),
            'refresh' => true,
        ]);
    }

    /**
     * Swap one occupied slot for another listing, keeping its position.
     */
    public function replace(Request $request, int $job): JsonResponse|RedirectResponse
    {
        $incoming = $this->visibleJob((int) $request->input('with'));

        $replaced = JobComparisonState::replace(
            $job,
            (int) $incoming->JobId,
            (string) $incoming->JobTitle,
            (string) $incoming->CompanyName
        );

        if (!$replaced) {
            return $this->respond($request, [
                'success' => false,
                'message' => 'That job is already in your comparison.',
            ]);
        }

        return $this->respond($request, [
            'success' => true,
            'message' => 'Swapped in ' . $incoming->JobTitle . '.',
            'count' => JobComparisonState::count(),
            'refresh' => true,
        ]);
    }

    public function move(Request $request, int $job): JsonResponse|RedirectResponse
    {
        JobComparisonState::move($job, $request->input('direction') === 'right' ? 1 : -1);

        return $this->respond($request, [
            'success' => true,
            'message' => 'Comparison reordered.',
            'refresh' => true,
        ]);
    }

    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        JobComparisonState::clear();

        return $this->respond($request, [
            'success' => true,
            'message' => 'Comparison cleared.',
            'refresh' => true,
        ]);
    }

    /**
     * Only the five weights are read from the request. Scores, percentages and
     * job data are recalculated here from the database, so a hand-edited request
     * cannot push a score up.
     */
    public function weights(Request $request): JsonResponse|RedirectResponse
    {
        $reset = $request->boolean('reset');

        $weights = $reset
            ? JobComparisonState::resetWeights()
            : JobComparisonState::setWeights($request->all());

        $built = $this->build(JobComparisonState::jobIds());

        return $this->respond($request, [
            'success' => true,
            'weights' => $weights,
            'results' => $built['results'],
            'message' => $reset ? 'Priorities reset.' : null,
        ]);
    }

    /**
     * @param  list<int>  $jobIds
     * @return array{comparison: array<string, mixed>, results: array<string, mixed>}
     */
    private function build(array $jobIds): array
    {
        /** @var JobSeeker $seeker */
        $seeker = Auth::guard('jobseeker')->user();

        // Read once for the whole comparison, then handed down: the same contract
        // the job search page uses so the seeker's resume is never re-read per job.
        $candidate = $this->jobMatch->candidateLists($seeker->JobSeekId);
        $context = $this->suitability->candidateContext($seeker, $candidate);

        $comparison = $this->comparison->compare($jobIds, $context);

        return ['comparison' => $comparison, 'results' => $this->payload($comparison)];
    }

    /**
     * The numbers the sliders work against, always produced here rather than in
     * the browser.
     *
     * @param  array<string, mixed>  $comparison
     * @return array<string, mixed>
     */
    private function payload(array $comparison): array
    {
        $jobs = [];

        foreach ($comparison['columns'] as $jobId => $column) {
            $jobs[(int) $jobId] = [
                'personalized' => $column['score']['personalized'],
                'status' => $column['score']['status'],
                'points' => array_map(
                    static fn (array $point): array => [
                        'score' => $point['score'],
                        'weight' => $point['weight'],
                        'share' => $point['share'],
                    ],
                    $column['score']['points']
                ),
            ];
        }

        return [
            'jobs' => $jobs,
            'shares' => $comparison['shares'],
            'highest' => $comparison['highest'],
            'highlights' => $comparison['highlights'],
            'explanation' => $comparison['explanation'],
            'message' => $comparison['message'],
        ];
    }

    /**
     * Saved jobs the seeker has not compared yet, offered as a picker on the
     * comparison page. Withdrawn listings are left out.
     *
     * @param  list<int>  $compared
     * @return Collection<int, Job>
     */
    private function savedExtras(array $compared): Collection
    {
        if (!Schema::hasTable('saved_jobs')) {
            return collect();
        }

        /** @var JobSeeker $seeker */
        $seeker = Auth::guard('jobseeker')->user();

        return SavedJob::query()
            ->with('job')
            ->where('JobSeekId', $seeker->JobSeekId)
            ->latest('created_at')
            ->get()
            ->pluck('job')
            ->filter()
            ->reject(static function (Job $job) use ($compared): bool {
                return in_array((int) $job->JobId, $compared, true)
                    || strtolower(trim((string) $job->Status)) !== 'approved';
            })
            ->take(self::POOL_LIMIT)
            ->values();
    }

    private function visibleJob(int $jobId): Job
    {
        $record = Job::query()->find($jobId);

        // Unapproved and missing listings are invisible to a seeker either way.
        if ($record === null || strtolower(trim((string) $record->Status)) !== 'approved') {
            abort(404);
        }

        return $record;
    }

    /**
     * A comparison link carries listing ids. Each one is checked before it is
     * accepted, so a link cannot put a private or withdrawn listing in front of a
     * seeker; priorities and scores still come from whoever opens it.
     */
    private function adoptSharedSelection(Request $request): void
    {
        $raw = $request->query('jobs');

        if (!is_string($raw) || $raw === '') {
            return;
        }

        $ids = [];

        foreach (preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $id = (int) $part;

            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }

            if (count($ids) >= JobComparisonState::MAX_JOBS) {
                break;
            }
        }

        if ($ids === []) {
            return;
        }

        $records = Job::query()
            ->whereIn('JobId', $ids)
            ->whereRaw('LOWER(Status) = ?', ['approved'])
            ->get()
            ->keyBy('JobId');

        JobComparisonState::clear();

        foreach ($ids as $id) {
            $record = $records->get($id);

            if ($record !== null) {
                JobComparisonState::add((int) $record->JobId, (string) $record->JobTitle, (string) $record->CompanyName);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with(
            $payload['success'] ?? false ? 'success' : 'error',
            (string) ($payload['message'] ?? 'The request could not be completed.')
        );
    }
}
