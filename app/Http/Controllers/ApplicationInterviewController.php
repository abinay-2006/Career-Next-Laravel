<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Services\ActivityLogger;
use App\Services\ApplicationInterviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shared half of interview scheduling: who is asking, what they own, and what
 * the state machine currently allows.
 *
 * Every entry point resolves the actor from its own guard and then looks the
 * invitation up through that actor's ownership path - the job for an employer, the
 * application for a seeker - so an id typed into the URL cannot reach someone else's
 * interview no matter which action it names. The identity written into a row always
 * comes from the guard, never from the body.
 *
 * The actions themselves deliberately do NOT live here. Scheduling, revising,
 * cancelling and completing are only defined on the employer controller, and
 * confirming, declining and asking for another time only on the seeker controller, so
 * a seeker route cannot be pointed at an employer power by accident. What is shared
 * is the plumbing, not the permission.
 *
 * A seeker's request to move an interview records what they would prefer and changes
 * nothing else; only the employer writes a date, a link, a location or instructions.
 * ApplicationInterviewService owns the state machine, so a page that has fallen behind
 * the database cannot complete a transition the row no longer supports.
 *
 * Nothing here writes to application_master.Status. That column belongs to the
 * employer's recruitment decision, made on the application page.
 */
abstract class ApplicationInterviewController extends Controller
{
    public function __construct(protected readonly ApplicationInterviewService $interviews) {}

    /** 'employer' or 'jobseeker': the guard, the route prefix and the ownership path. */
    abstract protected function role(): string;

    /**
     * The interview list for this actor, soonest interview first. Renders an empty
     * state rather than an error when the table has not been migrated yet.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', $this->statusTokens())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $actorId = $this->actorId();
        $ready = ApplicationInterview::tableIsReady();
        $interviews = collect();

        if ($ready) {
            $interviews = $this->listQuery($actorId)
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('application_interviews.status', $status))
                ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                    $search = '%'.trim($filters['search']).'%';
                    $query->where(fn ($inner) => $inner->where('job_master.JobTitle', 'like', $search)
                        ->orWhere('job_master.CompanyName', 'like', $search));
                })
                ->orderBy('application_interviews.scheduled_at')
                ->orderByDesc('application_interviews.id')
                ->paginate(ApplicationInterviewService::PER_PAGE)
                ->withQueryString();
        }

        return view($this->role().'.interviews.index', [
            'interviews' => $interviews,
            'ready' => $ready,
            'role' => $this->role(),
            'filters' => $filters,
            'statusTokens' => $this->statusTokens(),
            'awaiting' => $ready ? $this->awaitingCount($actorId) : 0,
        ]);
    }

    /** @return array<int, string> */
    protected function statusTokens(): array
    {
        return array_values(array_merge(ApplicationInterview::OPEN_STATUSES, ApplicationInterview::CLOSED_STATUSES));
    }

    /**
     * The rules for whichever type was just picked, so an online form demands a link and
     * an in-person form demands an address without the other field failing.
     *
     * @return array<string, array<int, string>>
     */
    protected function scheduleRulesFor(Request $request): array
    {
        $type = $request->input('interview_type');

        return $this->interviews->scheduleRules(is_string($type) ? $type : null);
    }

    /**
     * Time and zone, checked after the shape rules, because "in the future" can only be
     * answered once the wall clock has been read in the zone it was typed in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, string>>
     */
    protected function timeErrors(array $data, string $field = 'scheduled_at'): array
    {
        $zone = filled($data['timezone'] ?? null) ? (string) $data['timezone'] : $this->interviews->storageZone();
        $errors = [];

        if (! $this->interviews->isValidTimezone($zone)) {
            $errors['timezone'][] = 'Pick a real timezone from the list.';
        }

        $at = $this->interviews->instant($data['scheduled_at'] ?? null, $zone);

        if (! $at instanceof Carbon) {
            $errors[$field][] = 'That date and time could not be read. Use a format like 2026-12-01 10:00.';
        } elseif (! $this->interviews->isFuture($at)) {
            $errors[$field][] = 'Choose an interview date and time in the future.';
        }

        return $errors;
    }

    /**
     * Validate through the validator rather than the exception handler, because under the
     * web guard a failed validate() redirects even when the caller asked for JSON, and an
     * AJAX caller needs to be told which field was wrong at a real status code.
     *
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, mixed>|Response
     */
    protected function validated(Request $request, array $rules): array|Response
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = 'Please correct the highlighted fields.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422);
            }

            return back()->withInput()->with('error', $message)->withErrors($errors);
        }

        return $validator->validated();
    }

    /**
     * Refusal for a request that is well formed but not allowed right now - a past date,
     * an interview that is already over, an invitation that was already answered.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    protected function failed(Request $request, string $message, int $status = 422, array $errors = []): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
        }

        // The message promises highlighted fields, so the field errors have to travel with
        // it; a plain redirect would leave the page saying so with nothing marked.
        $redirect = back()->withInput()->with('error', $message);

        return $errors === [] ? $redirect : $redirect->withErrors($errors);
    }

    /**
     * Without the table there is nothing to interview on. Reads fall back to an empty
     * state and writes refuse, because the web guard never answers JSON on its own.
     */
    protected function ready(Request $request): ?Response
    {
        if (ApplicationInterview::tableIsReady()) {
            return null;
        }

        return $this->failed($request, 'Interviews need their database table first. Run: php artisan migrate', 503);
    }

    /** The actor id from this role's own guard, or a refusal for a visitor without it. */
    protected function actorId(): int
    {
        $user = Auth::guard($this->role())->user();

        if (! $user) {
            $this->deny();
        }

        return (int) ($this->isEmployer() ? $user->EmployerId : $user->JobSeekId);
    }

    /**
     * Interviews waiting on this actor. Employers are counted through the job, seekers
     * through their own application, so a badge can never borrow another account.
     */
    protected function awaitingCount(int $actorId): int
    {
        return $this->isEmployer()
            ? ApplicationInterview::awaitingEmployerCount($actorId)
            : ApplicationInterview::awaitingSeekerCount($actorId);
    }

    /**
     * The application side of the ownership check: an employer only reaches applications
     * on their own jobs, a seeker only their own applications.
     */
    protected function ownApplication(Application $application): void
    {
        $application->loadMissing('job');

        if ($this->isEmployer()) {
            abort_unless(
                $application->job !== null && (int) $application->job->EmployerId === $this->actorId(),
                403,
                'This application belongs to another employer.'
            );

            return;
        }

        abort_if((int) $application->JobSeekId !== $this->actorId(), 404);
    }

    /**
     * The interview side of the same check, read through the application and its job so
     * no column a request could supply is ever trusted. A row whose application is gone
     * is treated as foreign rather than as unowned.
     *
     * @return ApplicationInterview|Response
     */
    protected function ownInterview(Request $request, int $interviewId): ApplicationInterview|Response
    {
        $row = ApplicationInterview::query()->with('application.job')->find($interviewId);

        if ($row === null || $row->application === null) {
            $this->deny();
        }

        // Ownership is settled before the gate, because the state wording of another
        // company's interview would itself be a leak.
        if ($this->isEmployer()) {
            if ((int) $row->application->job?->EmployerId !== $this->actorId()) {
                abort(403, 'This interview belongs to another employer.');
            }
        } elseif ((int) $row->application->JobSeekId !== $this->actorId()) {
            abort(404);
        }

        return $row;
    }

    /**
     * A transition the row no longer supports. Not an ownership failure, so it answers
     * 422 with the wording the person needs; null means the caller may continue.
     */
    protected function employerMay(Request $request, ApplicationInterview $interview, string $capability): ?Response
    {
        $gate = $this->interviews->gate($interview)['employer'];

        return $gate[$capability] === true
            ? null
            : $this->failed($request, (string) ($gate['reason'] ?? 'This interview cannot be changed any further.'));
    }

    protected function seekerMay(Request $request, ApplicationInterview $interview, string $capability): ?Response
    {
        $gate = $this->interviews->gate($interview)['seeker'];

        return $gate[$capability] === true
            ? null
            : $this->failed($request, (string) ($gate['reason'] ?? 'This invitation needs no answer from you.'));
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    protected function listQuery(int $actorId)
    {
        return ApplicationInterview::query()
            ->with(['application.job', 'application.jobSeeker', 'creator'])
            ->join('application_master', 'application_master.ApplicationId', '=', 'application_interviews.ApplicationId')
            ->join('job_master', 'job_master.JobId', '=', 'application_master.JobId')
            ->where($this->isEmployer() ? 'job_master.EmployerId' : 'application_master.JobSeekId', $actorId)
            ->select(
                'application_interviews.*',
                'application_master.JobSeekId',
                'application_master.JobId',
                'application_master.Status as application_status',
                'job_master.JobTitle',
                'job_master.CompanyName',
                'job_master.EmployerId'
            );
    }

    protected function isEmployer(): bool
    {
        return $this->role() === 'employer';
    }

    /**
     * Record one of the seeker's answers and take them back to the card. The activity
     * row and the redirect are the same step in the story, so they are written together.
     */
    protected function answered(
        Request $request,
        ApplicationInterview $interview,
        string $action,
        string $message,
    ): Response {
        app(ActivityLogger::class)->record(
            $action,
            'application_interview',
            (int) $interview->id,
            $interview->jobId(),
            [
                'application_id' => (int) $interview->ApplicationId,
                'round' => (int) $interview->round,
                'status' => (string) $interview->status,
            ],
        );

        return $this->done($request, $interview, $message);
    }

    /** Land the person on the page they acted from; both render the interview card. */
    protected function done(Request $request, ApplicationInterview $interview, string $message): Response
    {
        return $this->redirectAfter($request, $interview)->with('success', $message);
    }

    /**
     * The application page when the form said so, otherwise this role's interview list.
     */
    protected function redirectAfter(Request $request, ApplicationInterview $interview): Response
    {
        $applicationId = (int) $interview->ApplicationId;

        if ($request->input('goto') === 'application' && $applicationId > 0) {
            return redirect()->route($this->role().'.applications.show', $applicationId);
        }

        return redirect()->route($this->role().'.interviews.index');
    }

    /** Whether two instants are the same absolute moment, to the second. */
    protected function sameInstant(?Carbon $before, ?Carbon $after): bool
    {
        if ($before === null || $after === null) {
            return $before === null && $after === null;
        }

        return $before->getTimestamp() === $after->getTimestamp();
    }

    /** Foreign rows are refused the way this project already refuses them. */
    private function deny(): never
    {
        if ($this->isEmployer()) {
            abort(403, 'This application belongs to another employer.');
        }

        abort(404);
    }
}
