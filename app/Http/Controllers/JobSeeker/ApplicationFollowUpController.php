<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationFollowUpMessage;
use App\Models\JobSeeker;
use App\Models\JobSeekerApplicationFollowUp;
use App\Models\Log;
use App\Services\ActivityLogger;
use App\Services\ApplicationFollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Application Follow-up Coach.
 *
 * Read-mostly: the rows written here are the seeker's own follow-up entries and the
 * messages they deliberately delivered to an employer. Application statuses stay
 * owned by the employer workflow, every lookup is scoped to the authenticated
 * seeker, and the reminders, drafts and timelines come from
 * ApplicationFollowUpService working off records that already exist. Nothing is
 * emailed anywhere by this controller.
 */
class ApplicationFollowUpController extends Controller
{
    /** Cards per page, matching the applications list. */
    private const PER_PAGE = 10;

    private const INTERVAL_KEY = 'jobseeker_followup_interval';

    private const PREP_KEY = 'jobseeker_followup_prep';

    public function __construct(private readonly ApplicationFollowUpService $coach)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'awaiting', 'suggested', 'interview', 'rejected', 'accepted', 'attention'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $interval = $this->interval($request);
        $all = $this->assessments($this->seeker(), trim((string) ($filters['search'] ?? '')), $interval);
        $filter = (string) ($filters['filter'] ?? 'all');
        $rows = $filter === 'all' ? $all : array_filter($all, fn (array $row): bool => !empty($row['filters'][$filter]));

        $lastPage = max(1, (int) ceil(count($rows) / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $lastPage);
        $cards = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($rows),
            self::PER_PAGE,
            $page,
            [
                'path' => route('jobseeker.followup.index'),
                'query' => array_filter([
                    'filter' => $filter === 'all' ? null : $filter,
                    'search' => $filters['search'] ?? null,
                ]),
            ]
        );

        return view('jobseeker.follow-up.index', [
            'cards' => $cards,
            'statistics' => $this->coach->statistics($all, $interval),
            'unsearchedTotal' => count($all),
            'filters' => $filters,
            'interval' => $interval,
            'intervalOptions' => ApplicationFollowUpService::INTERVAL_OPTIONS,
            'templates' => ApplicationFollowUpService::TEMPLATES,
            'trackerReady' => JobSeekerApplicationFollowUp::tableIsReady(),
            'prepState' => array_map(
                fn (array $items): array => array_values(array_unique($items)),
                (array) $request->session()->get(self::PREP_KEY, []),
            ),
            'draft' => session('followup_draft'),
        ]);
    }

    /**
     * The §5 drafts. This only produces text - delivery is the separate, deliberate
     * action in send() below, so a seeker can never hand a draft to an employer by
     * accident.
     */
    public function message(Request $request, int $application): JsonResponse|RedirectResponse
    {
        $seeker = $this->seeker();
        $record = $this->ownApplication($application);
        $data = $this->validated($request, [
            'template' => ['required', Rule::in(array_keys(ApplicationFollowUpService::TEMPLATES))],
        ]);

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        $draft = $this->coach->message($data['template'], $this->draftContext($record, $seeker, $this->interval($request)));

        if ($request->expectsJson()) {
            return response()->json(['success' => true] + $draft);
        }

        $request->session()->flash('followup_draft', $draft + ['application_id' => (int) $record->ApplicationId, 'template' => $data['template']]);

        return redirect()->route('jobseeker.followup.index', ['application' => (int) $record->ApplicationId]);
    }

    /**
     * Hand the edited draft to the employer. This is the only place a seeker's text
     * leaves their own account: it happens because they pressed the button, it goes
     * to the employer's Career Next pages, and no email is composed or sent here.
     */
    public function send(Request $request, int $application): JsonResponse|RedirectResponse
    {
        $seeker = $this->seeker();
        $record = $this->ownApplication($application);
        $data = $this->validated($request, [
            'subject' => ['required', 'string', 'max:'.ApplicationFollowUpMessage::MAX_SUBJECT],
            'body' => ['required', 'string', 'max:'.ApplicationFollowUpMessage::MAX_BODY],
            'template' => ['nullable', Rule::in(array_keys(ApplicationFollowUpService::TEMPLATES))],
        ]);

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        if (!ApplicationFollowUpMessage::tableIsReady()) {
            return $this->failed($request, 'Delivery to employers needs its database table. Run the migration for this feature; copying the draft still works.', 503);
        }

        $employerId = is_numeric($record->job?->EmployerId ?? null) ? (int) $record->job->EmployerId : 0;

        if ($employerId <= 0) {
            return $this->failed($request, 'This listing has no employer account that can receive a message, so Career Next cannot deliver it. Copy the draft and send it yourself.');
        }

        $subject = trim(preg_replace('/\s+/u', ' ', $data['subject']) ?? (string) $data['subject']);
        $body = trim($data['body']);

        if ($subject === '' || $body === '') {
            return $this->invalid($request, 'body', 'There is nothing to send yet. Generate a draft or write your message first.');
        }

        $lastSent = ApplicationFollowUpMessage::lastSentAt((int) $record->ApplicationId);

        if ($lastSent !== null && $lastSent->greaterThan(now()->subHours(ApplicationFollowUpMessage::REPEAT_WINDOW_HOURS))) {
            return $this->failed($request, 'A message was already delivered to this employer on '.$lastSent->format('M j, Y').'. One follow-up a day keeps the thread useful - try again after that.');
        }

        $delivered = ApplicationFollowUpMessage::create([
            'ApplicationId' => (int) $record->ApplicationId,
            'JobSeekId' => (int) $seeker->JobSeekId,
            'EmployerId' => $employerId,
            'template' => $data['template'] ?? null,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
        ]);

        // The delivery counts as the follow-up the seeker sent today, which is what
        // holds the coach back from suggesting another message right away.
        if (JobSeekerApplicationFollowUp::tableIsReady()) {
            JobSeekerApplicationFollowUp::create([
                'ApplicationId' => (int) $record->ApplicationId,
                'JobSeekId' => (int) $seeker->JobSeekId,
                'activity_type' => JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT,
                'activity_date' => now(),
                'note' => 'Delivered to the employer inside Career Next.',
            ]);
        }

        app(ActivityLogger::class)->record(
            'follow_up_message_sent',
            'application',
            (int) $record->ApplicationId,
            $record->JobId ? (int) $record->JobId : null,
            ['message_id' => (int) $delivered->id, 'employer_id' => $employerId],
        );

        $notice = 'Delivered to the employer. They see it on your application in Career Next - no email was sent.';

        if ($request->expectsJson()) {
            // The card repaints: the recommendation, the activity list and the
            // delivered-message history all change with a delivery.
            return response()->json(['success' => true, 'message' => $notice, 'refresh' => true]);
        }

        return back()->with('success', $notice);
    }

    /** Record the seeker's own follow-up activity (§8). */
    public function activity(Request $request, int $application): JsonResponse|RedirectResponse
    {
        $seeker = $this->seeker();
        $record = $this->ownApplication($application);
        $data = $this->validated($request, [
            'activity_type' => ['required', Rule::in(ApplicationFollowUpService::ACTIVITY_TYPES)],
            'activity_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        if (!JobSeekerApplicationFollowUp::tableIsReady()) {
            return $this->failed($request, 'Follow-up history is not available yet. Run the database migration for this feature.', 503);
        }

        $date = Carbon::parse($data['activity_date']);

        if (!JobSeekerApplicationFollowUp::allowsFutureDate($data['activity_type']) && $date->greaterThan(Carbon::today()->endOfDay())) {
            return $this->invalid($request, 'activity_date', 'This activity is something that already happened, so it cannot be dated in the future.');
        }

        JobSeekerApplicationFollowUp::create([
            'ApplicationId' => (int) $record->ApplicationId,
            'JobSeekId' => (int) $seeker->JobSeekId,
            'activity_type' => $data['activity_type'],
            'activity_date' => $date,
            'note' => Str::limit(trim((string) ($data['note'] ?? '')), 500, '') ?: null,
        ]);

        app(ActivityLogger::class)->record(
            'follow_up_recorded',
            'application',
            (int) $record->ApplicationId,
            $record->JobId ? (int) $record->JobId : null,
            ['type' => $data['activity_type']],
        );

        $message = JobSeekerApplicationFollowUp::typeLabel($data['activity_type']).' recorded.';

        if ($request->expectsJson()) {
            // The card underneath has to be re-rendered: the recorded entry changes
            // both the recommendation and the history list.
            return response()->json(['success' => true, 'message' => $message, 'refresh' => true]);
        }

        return back()->with('success', $message);
    }

    /** How long the seeker waits before the coach suggests another message. */
    public function intervalSave(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, [
            'days' => ['required', 'integer', 'min:3', 'max:90'],
        ]);

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        $days = $this->coach->normaliseInterval($data['days']);
        $request->session()->put(self::INTERVAL_KEY, $days);
        $message = 'Follow-up interval set to '.$days.' days.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'interval' => $days, 'refresh' => true]);
        }

        return back()->with('success', $message);
    }

    /** Tick the interview preparation checklist for one application. */
    public function prep(Request $request, int $application): JsonResponse|RedirectResponse
    {
        $record = $this->ownApplication($application);
        $data = $this->validated($request, [
            'item' => ['required', 'string', 'max:40'],
            'done' => ['required', 'boolean'],
        ]);

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        $state = (array) $request->session()->get(self::PREP_KEY, []);
        $done = array_values(array_unique($state[(int) $record->ApplicationId] ?? []));

        $done = $data['done']
            ? array_values(array_unique(array_merge($done, [$data['item']])))
            : array_values(array_filter($done, fn (string $item): bool => $item !== $data['item']));

        $state[(int) $record->ApplicationId] = $done;
        $request->session()->put(self::PREP_KEY, $state);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'done' => $done]);
        }

        return back();
    }

    /**
     * Assess every application the seeker owns, in the order the page shows them.
     * Four queries whatever the count: the applications, their logged status events,
     * their recorded follow-up activity and the messages already delivered.
     *
     * @return array<int, array>
     */
    private function assessments(JobSeeker $seeker, string $search, int $interval): array
    {
        $applications = Application::query()
            ->with('job')
            ->where('JobSeekId', (int) $seeker->JobSeekId)
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
                $query->whereHas('job', fn ($jobs) => $jobs->where('JobTitle', 'like', $like)->orWhere('CompanyName', 'like', $like));
            })
            ->orderByDesc('ApplicationDate')
            ->orderByDesc('ApplicationId')
            ->get();

        if ($applications->isEmpty()) {
            return [];
        }

        $ids = $applications->pluck('ApplicationId')->map(fn ($id): int => (int) $id)->all();

        $events = Log::query()
            ->where('target_type', 'application')
            ->whereIn('target_id', $ids)
            ->whereIn('action', ['application_submitted', 'application_status_changed'])
            ->orderBy('cdate')
            ->get()
            ->groupBy(fn (Log $log): int => (int) $log->target_id);

        $activities = JobSeekerApplicationFollowUp::tableIsReady()
            ? JobSeekerApplicationFollowUp::query()
                ->where('JobSeekId', (int) $seeker->JobSeekId)
                ->whereIn('ApplicationId', $ids)
                ->orderBy('activity_date')
                ->get()
                ->groupBy(fn (JobSeekerApplicationFollowUp $row): int => (int) $row->ApplicationId)
            : collect();

        $messages = ApplicationFollowUpMessage::tableIsReady()
            ? ApplicationFollowUpMessage::query()
                ->where('JobSeekId', (int) $seeker->JobSeekId)
                ->whereIn('ApplicationId', $ids)
                ->orderBy('sent_at')
                ->get()
                ->groupBy(fn (ApplicationFollowUpMessage $row): int => (int) $row->ApplicationId)
            : collect();

        return $this->coach->assessMany(
            $applications,
            $events->map(fn ($group): array => $group->all())->all(),
            $activities->map(fn ($group): array => $group->all())->all(),
            $interval,
            null,
            $messages->map(fn ($group): array => $group->all())->all(),
        );
    }

    /**
     * The facts a draft may be written from. An interview date has no source in
     * this schema - applications do not store one - so it stays null rather than
     * being guessed, and employer contact details are never read.
     *
     * @return array<string, string|int|null>
     */
    private function draftContext(Application $application, JobSeeker $seeker, int $interval): array
    {
        $assessment = $this->assessments($seeker, '', $interval)[(int) $application->ApplicationId] ?? [];

        return [
            'candidate_name' => (string) ($seeker->JobSeekerName ?? ''),
            'job_title' => $application->job?->JobTitle ? (string) $application->job->JobTitle : null,
            'company' => $application->job?->CompanyName ? (string) $application->job->CompanyName : null,
            'applied_on' => $application->ApplicationDate
                ? Carbon::parse($application->ApplicationDate)->format('F j, Y')
                : null,
            'interview_on' => null,
            'waiting_days' => $assessment['waiting_days'] ?? null,
        ];
    }

    private function interval(Request $request): int
    {
        return $this->coach->normaliseInterval($request->session()->get(self::INTERVAL_KEY));
    }

    private function seeker(): JobSeeker
    {
        $seeker = Auth::guard('jobseeker')->user();

        abort_unless($seeker instanceof JobSeeker, 403);

        return $seeker;
    }

    /** Look the application up by owner, so another seeker's row is simply not found. */
    private function ownApplication(int $applicationId): Application
    {
        $record = Application::query()
            ->with('job')
            ->where('ApplicationId', $applicationId)
            ->where('JobSeekId', (int) Auth::guard('jobseeker')->id())
            ->first();

        abort_unless($record !== null, 404);

        return $record;
    }

    /**
     * Validate without leaning on the exception handler: under the web guard a
     * failed validate() redirects, and an AJAX caller needs a JSON 422 instead.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>|JsonResponse|RedirectResponse
     */
    private function validated(Request $request, array $rules): array|JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails() && $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return $validator->validate();
    }

    private function invalid(Request $request, string $field, string $message): JsonResponse|RedirectResponse
    {
        return $this->failed($request, $message, 422, [$field => [$message]]);
    }

    private function failed(Request $request, string $message, int $status = 422, array $errors = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
        }

        return back()->withInput()->with('error', $message);
    }
}
