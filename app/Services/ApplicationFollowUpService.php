<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationFollowUpMessage;
use App\Models\JobSeekerApplicationFollowUp;
use App\Models\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Application Follow-up Coach.
 *
 * Everything here is derived from records that already exist - the application
 * row, the activity log written by ActivityLogger and the follow-up entries the
 * seeker recorded themselves. Nothing is persisted, no employer behaviour is
 * predicted, and an application whose history was never recorded is reported as
 * exactly that instead of being given an invented timeline.
 *
 * The age bands are the brief's defaults with a 7-day interval: 0-2 days needs no
 * follow-up, 3-6 days means keep waiting, 7-13 days is worth considering one,
 * 14-29 days is recommended and 30+ days is the final check-in. A seeker who
 * picks a different interval scales those boundaries from it.
 */
class ApplicationFollowUpService
{
    /** Kinds of activity a seeker may record about their own application. */
    public const ACTIVITY_FOLLOW_UP_SENT = JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT;

    public const ACTIVITY_RESPONSE_RECEIVED = JobSeekerApplicationFollowUp::TYPE_RESPONSE_RECEIVED;

    public const ACTIVITY_EXPECTED_RESPONSE = JobSeekerApplicationFollowUp::TYPE_EXPECTED_RESPONSE;

    public const ACTIVITY_NOTE = JobSeekerApplicationFollowUp::TYPE_NOTE;

    public const ACTIVITY_TYPES = JobSeekerApplicationFollowUp::TYPES;

    public const DEFAULT_INTERVAL_DAYS = 7;

    public const INTERVAL_OPTIONS = [5, 7, 10, 14, 21, 30];

    /** Message drafts the generator can produce (§5). */
    public const TEMPLATES = [
        'status_inquiry' => 'Application status inquiry',
        'post_interview' => 'Post-interview follow-up',
        'thank_you' => 'Thank-you message after an interview',
        'feedback_request' => 'Request for feedback after rejection',
        'final_follow_up' => 'Final follow-up after no response',
    ];

    /** Recommendations that ask the seeker to send another follow-up. */
    public const SUGGESTED_KEYS = ['consider', 'recommended', 'final'];

    /** How a blank that the draft had to write around is named to the seeker. */
    private const MISSING_LABELS = [
        'candidate_name' => 'your name',
        'job_title' => 'the job title',
        'company' => 'the company name',
        'applied_on' => 'the application date',
        'interview_on' => 'a recorded interview date',
    ];

    /** How recently a status must have changed for "review it first" to win. */
    private const REVIEW_WINDOW_DAYS = 3;

    /** Display groupings for a normalised status. */
    private const STATUS_GROUPS = [
        'withdrawn' => ['withdrawn', 'cancelled', 'canceled', 'closed'],
        'rejected' => ['rejected', 'declined', 'not selected', 'notshortlisted', 'not shortlisted'],
        'accepted' => ['accepted', 'approved', 'selected', 'offer', 'hired'],
        'interview' => ['interview', 'interviewing', 'interview scheduled', 'shortlisted', 'shortlist', 'screening'],
        'awaiting' => ['pending', 'submitted', 'applied', 'under review', 'reviewing'],
    ];

    /**
     * Assess a set of applications that already carry their job, status events and
     * recorded activity, so the caller can load all three in bulk.
     *
     * @param  iterable<Application>  $applications
     * @param  array<int, array<int, Log>>  $statusEvents  keyed by ApplicationId
     * @param  array<int, array<int, JobSeekerApplicationFollowUp>>  $activities  keyed by ApplicationId
     * @param  array<int, array<int, ApplicationFollowUpMessage>>  $messages  delivered drafts, keyed by ApplicationId
     * @return array<int, array>  assessments keyed by ApplicationId
     */
    public function assessMany(iterable $applications, array $statusEvents = [], array $activities = [], ?int $intervalDays = null, ?Carbon $now = null, array $messages = []): array
    {
        $interval = $this->normaliseInterval($intervalDays);
        $now = $now ?? Carbon::now();
        $assessments = [];

        foreach ($applications as $application) {
            $id = (int) $application->ApplicationId;
            $assessments[$id] = $this->assess($application, $statusEvents[$id] ?? [], $activities[$id] ?? [], $interval, $now, $messages[$id] ?? []);
        }

        return $assessments;
    }

    /**
     * The whole picture for one application.
     *
     * @param  array<int, Log>  $statusEvents
     * @param  array<int, JobSeekerApplicationFollowUp>  $activities
     * @param  array<int, ApplicationFollowUpMessage>  $messages
     */
    public function assess(Application $application, array $statusEvents, array $activities, ?int $intervalDays = null, ?Carbon $now = null, array $messages = []): array
    {
        $interval = $this->normaliseInterval($intervalDays);
        $now = $now ?? Carbon::now();

        $status = trim((string) ($application->Status ?? ''));
        $statusKey = $this->statusKey($status);
        $submittedAt = $this->toCarbon($application->ApplicationDate ?? null);

        $events = $this->officialEvents($statusEvents, $submittedAt);
        $lastStatusChange = null;
        foreach ($events as $event) {
            if ($event['type'] === 'status_changed') {
                $lastStatusChange = $event;
            }
        }

        $recorded = $this->recordedActivity($activities, $now);
        $clock = $this->waitingSince($submittedAt, $lastStatusChange, $recorded, $now);

        return [
            'application_id' => (int) $application->ApplicationId,
            'job_id' => (int) ($application->JobId ?? 0),
            'job_title' => $application->job?->JobTitle ? (string) $application->job->JobTitle : null,
            'company' => $application->job?->CompanyName ? (string) $application->job->CompanyName : null,
            'status' => $status !== '' ? $status : 'Pending',
            'status_key' => $statusKey,
            'is_terminal' => in_array($statusKey, ['accepted', 'rejected', 'withdrawn'], true),
            'is_interview' => $statusKey === 'interview',
            'submitted_on' => $submittedAt,
            'age_days' => $submittedAt === null ? null : $this->daysSince($submittedAt, $now),
            'waiting_days' => $clock['days'],
            'waiting_source' => $clock['source'],
            'last_update' => $lastStatusChange ? [
                'label' => $lastStatusChange['label'],
                'at' => $lastStatusChange['at'],
                'days_ago' => $this->daysSince($lastStatusChange['at'], $now),
            ] : null,
            'events' => $events,
            'activity' => $recorded['rows'],
            'messages' => $this->delivered($messages, $now),
            'last_follow_up_days_ago' => $recorded['last_follow_up_days_ago'],
            'expected_response_on' => $recorded['expected_response_on'],
            'recommendation' => $this->recommendation($statusKey, $submittedAt, $clock['days'], $recorded, $lastStatusChange, $interval, $now),
            'category' => $this->category($statusKey, $clock['days'], $interval),
            'interview' => $this->interviewPreparation($application, $statusKey),
            'filters' => $this->filterFlags($statusKey, $clock['days'], $interval),
        ];
    }

    /** What the seeker can actually do next, stated in the same terms each time. */
    private const NEXT_ACTIONS = [
        'unknown_date' => 'Confirm the submission date on this application.',
        'closed_withdrawn' => 'No action needed.',
        'closed_rejected' => 'Optionally ask the employer for feedback.',
        'closed_accepted' => 'Reply to the employer and confirm your next steps.',
        'interview_prep' => 'Work through the interview preparation checklist.',
        'review_update' => 'Read the latest recorded update before writing again.',
        'awaiting_reply' => 'Wait for the employer to reply.',
        'no_followup' => 'No action yet - keep the application on your list.',
        'waiting' => 'No action yet - check back when the window you chose is up.',
        'consider' => 'Draft a short status-inquiry message.',
        'recommended' => 'Draft and send a follow-up message.',
        'final' => 'Decide whether one final message is worth sending.',
    ];

    /**
     * The recommendation wording, plus why it was chosen.
     *
     * @param  array{type: string, label: string, at: Carbon, from: string|null, to: string|null, source: string}|null  $lastStatusChange
     * @param  array{last_follow_up_days_ago: int|null, last_response_days_ago: int|null, rows: array, expected_response_on: Carbon|null}  $recorded
     * @return array{key: string, headline: string, reason: string, tone: string, suggested: bool, template: string|null, next_action: string}
     */
    public function recommendation(string $statusKey, ?Carbon $submittedAt, ?int $waitingDays, array $recorded, ?array $lastStatusChange, ?int $intervalDays = null, ?Carbon $now = null): array
    {
        $interval = $this->normaliseInterval($intervalDays);
        $now = $now ?? Carbon::now();

        $build = function (string $key, string $headline, string $reason, string $tone, ?string $template): array {
            return [
                'key' => $key,
                'headline' => $headline,
                'reason' => $reason,
                'tone' => $tone,
                'suggested' => in_array($key, self::SUGGESTED_KEYS, true),
                'template' => $template,
                'next_action' => self::NEXT_ACTIONS[$key] ?? 'No action suggested.',
            ];
        };

        if ($submittedAt === null && $waitingDays === null) {
            return $build(
                'unknown_date',
                'No submission date recorded',
                'This application has no stored submission date, so the coach cannot estimate follow-up timing. Use the application details to confirm your record.',
                'muted',
                null,
            );
        }

        if ($statusKey === 'withdrawn') {
            return $build('closed_withdrawn', 'You withdrew this application', 'No follow-up is recommended for a withdrawn application.', 'muted', null);
        }

        if ($statusKey === 'rejected') {
            return $build(
                'closed_rejected',
                'This application was not successful',
                'No follow-up is recommended. You can still ask the employer for feedback, which is optional and never counted as a follow-up.',
                'muted',
                'feedback_request',
            );
        }

        if ($statusKey === 'accepted') {
            return $build(
                'closed_accepted',
                'Your application was accepted',
                'No follow-up is recommended while the decision is already in your favour. Reply to the employer directly if they asked you to confirm something.',
                'positive',
                null,
            );
        }

        if ($statusKey === 'interview') {
            return $build(
                'interview_prep',
                'An interview has been recorded. Focus on preparing for the interview.',
                'Your status is an interview stage, so time is better spent on the preparation checklist below than on another message.',
                'info',
                'post_interview',
            );
        }

        // A status the employer moved after the seeker last wrote to them is worth
        // reading before anything else is drafted.
        if ($lastStatusChange !== null) {
            $changedDaysAgo = $this->daysSince($lastStatusChange['at'], $now);
            $afterLastFollowUp = $recorded['last_follow_up_days_ago'] === null
                || $changedDaysAgo < $recorded['last_follow_up_days_ago'];

            if ($changedDaysAgo <= self::REVIEW_WINDOW_DAYS && $afterLastFollowUp) {
                return $build(
                    'review_update',
                    'Your application status has changed',
                    'The status moved to '.$lastStatusChange['to'].' '.$changedDaysAgo.' day'.($changedDaysAgo === 1 ? '' : 's').' ago. Review the latest update before taking further action.',
                    'info',
                    null,
                );
            }
        }

        $lastFollowUp = $recorded['last_follow_up_days_ago'];
        if ($lastFollowUp !== null && $lastFollowUp < $interval) {
            return $build(
                'awaiting_reply',
                'You followed up '.$lastFollowUp.' day'.($lastFollowUp === 1 ? '' : 's').' ago',
                'Another message is not suggested yet. Your next follow-up becomes available in '.max(1, $interval - $lastFollowUp).' day(s), or sooner if the employer replies.',
                'neutral',
                null,
            );
        }

        if ($recorded['expected_response_on'] !== null && $recorded['expected_response_on']->greaterThan($now)) {
            $days = (int) ceil($now->diffInDays($recorded['expected_response_on'], false));

            return $build(
                'awaiting_reply',
                'You are expecting a response by '.$recorded['expected_response_on']->format('M j, Y'),
                'You recorded an expected response date, so no follow-up is suggested until it has passed.',
                'neutral',
                null,
            );
        }

        $days = $waitingDays ?? 0;
        $waitingStart = intdiv(3 * $interval, 7);
        $recommendedAt = 2 * $interval;
        $finalAt = (4 * $interval) + 2;
        $for = fn (int $threshold): int => max(1, $threshold - $days);

        if ($days < $waitingStart) {
            return $build(
                'no_followup',
                'No follow-up is necessary yet',
                'Give the employer more time to review your application. A follow-up first becomes worth considering in '.$for($interval).' day(s).',
                'neutral',
                null,
            );
        }

        if ($days < $interval) {
            return $build(
                'waiting',
                'Continue waiting',
                'Your application has been awaiting a response for '.$days.' day(s). It is still within the normal window you chose, so nothing is suggested yet.',
                'neutral',
                null,
            );
        }

        if ($days < $recommendedAt) {
            return $build(
                'consider',
                'Consider following up',
                'Your application has been awaiting a response for '.$days.' days. You may consider sending a polite follow-up.',
                'warning',
                'status_inquiry',
            );
        }

        if ($days < $finalAt) {
            return $build(
                'recommended',
                'Follow-up recommended',
                'There has been no recorded update for '.$days.' days, which is past the point where a short, polite message is usually worthwhile.',
                'warning',
                'status_inquiry',
            );
        }

        return $build(
            'final',
            'Consider a final follow-up',
            'This application has been waiting '.$days.' days without a recorded status update. Review it and decide whether one last message is worth sending, then move your focus elsewhere.',
            'danger',
            'final_follow_up',
        );
    }

    /**
     * Turn the recorded log rows into the timeline the page may show. Only the two
     * application actions ActivityLogger writes are official events; anything else
     * in the log belongs to another part of the product.
     *
     * @param  array<int, Log>  $statusEvents
     * @return array<int, array{type: string, label: string, at: Carbon, from: string|null, to: string|null, source: string}>
     */
    public function officialEvents(array $statusEvents, ?Carbon $submittedAt = null): array
    {
        $events = [];

        foreach ($statusEvents as $log) {
            $at = $this->toCarbon($log->cdate ?? null);
            if ($at === null) {
                continue;
            }

            $metadata = $this->metadata($log);
            $action = strtolower(trim((string) ($log->action ?? '')));

            if ($action === 'application_status_changed') {
                $to = isset($metadata['to']) ? (string) $metadata['to'] : null;
                $from = isset($metadata['from']) ? (string) $metadata['from'] : null;

                $events[] = [
                    'type' => 'status_changed',
                    'label' => 'Status changed'.($to !== null ? ' to '.$to : ''),
                    'at' => $at,
                    'from' => $from,
                    'to' => $to,
                    'source' => 'Activity log',
                ];

                continue;
            }

            if ($action === 'application_submitted') {
                $events[] = [
                    'type' => 'submitted',
                    'label' => 'Application submitted',
                    'at' => $at,
                    'from' => null,
                    'to' => null,
                    'source' => 'Activity log',
                ];
            }
        }

        // The application row is the record of the submission itself, so the first
        // event is real even when the log was never written for this application.
        if ($submittedAt !== null && !collect($events)->contains('type', 'submitted')) {
            $events[] = [
                'type' => 'submitted',
                'label' => 'Application submitted',
                'at' => $submittedAt,
                'from' => null,
                'to' => null,
                'source' => 'Application record',
            ];
        }

        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return array_values($events);
    }

    /**
     * The four buckets from §7, worded around recorded updates rather than guesses.
     *
     * @return array{key: string, label: string}
     */
    public function category(?string $statusKey, ?int $waitingDays, ?int $intervalDays = null): array
    {
        $interval = $this->normaliseInterval($intervalDays);

        if (in_array($statusKey, ['accepted', 'rejected', 'withdrawn'], true)) {
            return ['key' => 'closed', 'label' => 'Closed'];
        }

        if ($waitingDays === null) {
            return ['key' => 'unknown', 'label' => 'Date unavailable'];
        }

        if ($statusKey === 'interview') {
            return ['key' => 'interview', 'label' => 'Interview stage'];
        }

        if ($waitingDays < intdiv(3 * $interval, 7)) {
            return ['key' => 'recent', 'label' => 'Recently submitted'];
        }

        if ($waitingDays < (4 * $interval) + 2) {
            return $waitingDays < $interval
                ? ['key' => 'waiting', 'label' => 'Waiting for response']
                : ['key' => 'follow_up', 'label' => 'Follow-up recommended'];
        }

        return ['key' => 'inactive', 'label' => 'Potentially inactive'];
    }

    /**
     * Build one of the §5 drafts. Every blank is filled from the application itself;
     * a missing company or an unrecorded interview date is written around rather
     * than invented, and no employer contact detail is ever included.
     *
     * @return array{subject: string, body: string, used: array<string, string|null>, note: string|null}
     */
    public function message(string $template, array $context): array
    {
        if (!isset(self::TEMPLATES[$template])) {
            $template = 'status_inquiry';
        }

        $candidate = trim((string) ($context['candidate_name'] ?? ''));
        $jobTitle = $this->clean($context['job_title'] ?? null);
        $company = $this->clean($context['company'] ?? null);
        $appliedOn = $this->clean($context['applied_on'] ?? null);
        $interviewOn = $this->clean($context['interview_on'] ?? null);
        $waitingDays = $context['waiting_days'] ?? null;

        $role = $jobTitle ?? 'the position';
        $greeting = 'Dear '.($company !== null ? $company.' Hiring Team' : 'Hiring Team').',';
        $signOff = $candidate !== '' ? "\n\nBest regards,\n".$candidate : "\n\nBest regards";

        $subject = match ($template) {
            'post_interview' => 'Following up after my '.$role.' interview',
            'thank_you' => 'Thank you for the '.$role.' interview',
            'feedback_request' => 'Feedback request - '.$role.' application',
            'final_follow_up' => 'Final follow-up on my '.$role.' application',
            default => 'Follow-up on my '.$role.' application',
        };

        $opening = match ($template) {
            'feedback_request' => 'Thank you for letting me know the outcome of my application for the '.$role.' position.',
            'final_follow_up' => 'I hope you are doing well.',
            default => 'I hope you are doing well.',
        };

        $paragraphs = match ($template) {
            'post_interview' => array_values(array_filter([
                'Thank you for the time you spent speaking with me about the '.$role.' position.',
                $interviewOn !== null
                    ? 'Further to our interview on '.$interviewOn.', I remain very interested in the opportunity.'
                    : 'I remain very interested in the opportunity and wanted to ask about the next steps in the process.',
                'If any additional information would help the team reach a decision, I am happy to provide it.',
            ])),
            'thank_you' => array_values(array_filter([
                'Thank you for speaking with me about the '.$role.' position.',
                $interviewOn !== null
                    ? 'Our conversation on '.$interviewOn.' gave me a much clearer picture of the work your team is doing.'
                    : 'Our conversation gave me a much clearer picture of the work your team is doing.',
                'I appreciate the time you took and remain enthusiastic about the opportunity.',
            ])),
            'feedback_request' => array_values(array_filter([
                'If you have a moment, I would be grateful for any feedback on my application or profile.',
                'Any guidance you can share will help me improve for future opportunities. I understand a short reply may not always be possible.',
                'Thank you again for your time and consideration.',
            ])),
            'final_follow_up' => array_values(array_filter([
                'I wanted to follow up one last time on my application for the '.$role.' position.',
                $appliedOn !== null ? 'I applied on '.$appliedOn.' and have not recorded an update since.' : 'I have not recorded an update on my application since submitting it.',
                'I remain interested in the opportunity, but I also want to respect your time, so there is no need to reply if the role has been filled.',
                'Thank you for the consideration you have already given my application.',
            ])),
            default => array_values(array_filter([
                'I am writing to politely follow up on my application for the '.$role.' position.',
                $appliedOn !== null ? 'I submitted my application on '.$appliedOn.'.' : null,
                $waitingDays !== null && is_numeric($waitingDays) && (int) $waitingDays > 0
                    ? 'My application has been awaiting a response for '.(int) $waitingDays.' days.'
                    : null,
                'I remain interested in the opportunity and would appreciate any updates regarding the recruitment process.',
                'Thank you for your time and consideration.',
            ])),
        };

        $body = $greeting."\n\n".$opening."\n\n".implode("\n\n", $paragraphs).$signOff;

        $used = [
            'candidate_name' => $candidate !== '' ? $candidate : null,
            'job_title' => $jobTitle,
            'company' => $company,
            'applied_on' => $appliedOn,
            'interview_on' => $interviewOn,
        ];

        // Only the blanks this scenario would actually have filled are worth
        // mentioning; an inquiry about a pending application does not need an
        // interview date.
        $relevant = match ($template) {
            'post_interview', 'thank_you' => ['candidate_name', 'job_title', 'company', 'interview_on'],
            'final_follow_up' => ['candidate_name', 'job_title', 'company', 'applied_on'],
            default => ['candidate_name', 'job_title', 'company', 'applied_on'],
        };

        $missing = array_values(array_intersect(
            $relevant,
            collect($used)->filter(fn (?string $value): bool => $value === null || $value === '')->keys()->all(),
        ));

        return [
            'subject' => $subject,
            'body' => $body,
            'used' => $used,
            'note' => $missing === [] ? null : 'Written without '.implode(', ', array_map(
                fn (string $key): string => self::MISSING_LABELS[$key] ?? str_replace('_', ' ', $key),
                $missing,
            )).'. Career Next never invents these details.',
        ];
    }

    /** The interval a seeker chose, or the default when the value is unusable. */
    public function normaliseInterval(mixed $intervalDays): int
    {
        if (!is_numeric($intervalDays)) {
            return self::DEFAULT_INTERVAL_DAYS;
        }

        $intervalDays = (int) $intervalDays;

        return $intervalDays >= 3 && $intervalDays <= 90 ? $intervalDays : self::DEFAULT_INTERVAL_DAYS;
    }

    /** 'Accepted', 'approved' and 'Not Selected' all group as 'accepted'. */
    public function statusKey(?string $status): string
    {
        $normalised = strtolower(trim((string) $status));

        if ($normalised === '') {
            return 'awaiting';
        }

        foreach (self::STATUS_GROUPS as $key => $needles) {
            foreach ($needles as $needle) {
                if ($normalised === $needle || str_contains($normalised, $needle)) {
                    return $key;
                }
            }
        }

        return 'awaiting';
    }

    /**
     * Summary cards for the page header, counted from the assessments so the numbers
     * can never disagree with the cards underneath them.
     *
     * @param  array<int, array>  $assessments
     * @return array<string, int>
     */
    public function statistics(array $assessments, ?int $intervalDays = null): array
    {
        $interval = $this->normaliseInterval($intervalDays);

        $counts = [
            'total' => count($assessments),
            'awaiting' => 0,
            'suggested' => 0,
            'interview' => 0,
            'attention' => 0,
        ];

        foreach ($assessments as $assessment) {
            if (($assessment['status_key'] ?? '') === 'awaiting') {
                $counts['awaiting']++;
            }

            if (!empty($assessment['recommendation']['suggested'])) {
                $counts['suggested']++;
            }

            if (($assessment['status_key'] ?? '') === 'interview') {
                $counts['interview']++;
            }

            $category = $assessment['category'] ?? $this->category($assessment['status_key'] ?? null, $assessment['waiting_days'] ?? null, $interval);
            if (in_array($category['key'] ?? '', ['follow_up', 'inactive'], true)) {
                $counts['attention']++;
            }
        }

        return $counts;
    }

    /**
     * Filter flags used by the tabs in §3, derived from the same assessment.
     *
     * @return array<string, bool>
     */
    private function filterFlags(string $statusKey, ?int $waitingDays, int $interval): array
    {
        $finalAt = (4 * $interval) + 2;

        return [
            'awaiting' => $statusKey === 'awaiting',
            'suggested' => $waitingDays !== null && $waitingDays >= $interval && $waitingDays < $finalAt && $statusKey === 'awaiting',
            'interview' => $statusKey === 'interview',
            'rejected' => $statusKey === 'rejected',
            'accepted' => $statusKey === 'accepted',
            'attention' => $statusKey === 'awaiting' && $waitingDays !== null && $waitingDays >= $interval,
        ];
    }

    /**
     * The clock a recommendation is measured against: the submission date, unless a
     * status update or a reply the seeker recorded is more recent.
     *
     * @param  array{label: string, at: Carbon, to: string|null}|null  $lastStatusChange
     * @param  array{last_follow_up_days_ago: int|null, last_response_days_ago: int|null, rows: array, expected_response_on: Carbon|null}  $recorded
     * @return array{days: int|null, source: string|null, since: Carbon|null}
     */
    private function waitingSince(?Carbon $submittedAt, ?array $lastStatusChange, array $recorded, Carbon $now): array
    {
        $candidates = [];

        if ($submittedAt !== null) {
            $candidates[] = ['since' => $submittedAt, 'source' => 'submitted on '.$submittedAt->format('M j, Y')];
        }

        if ($lastStatusChange !== null) {
            $candidates[] = ['since' => $lastStatusChange['at'], 'source' => 'status update on '.$lastStatusChange['at']->format('M j, Y')];
        }

        if ($recorded['last_response_at'] !== null) {
            $candidates[] = ['since' => $recorded['last_response_at'], 'source' => 'the employer response you recorded on '.$recorded['last_response_at']->format('M j, Y')];
        }

        if ($candidates === []) {
            return ['days' => null, 'source' => null, 'since' => null];
        }

        $latest = collect($candidates)->sortByDesc('since')->first();

        return [
            'days' => $this->daysSince($latest['since'], $now),
            'source' => $latest['source'],
            'since' => $latest['since'],
        ];
    }

    /**
     * Shape the seeker's own entries. The follow-up clock and the expected-response
     * date come from the recorded rows, never from what the browser says today.
     *
     * @param  array<int, JobSeekerApplicationFollowUp>  $activities
     * @return array{rows: array, last_follow_up_days_ago: int|null, last_response_days_ago: int|null, last_response_at: Carbon|null, expected_response_on: Carbon|null}
     */
    private function recordedActivity(array $activities, Carbon $now): array
    {
        $rows = [];
        $lastFollowUpAt = null;
        $lastResponseAt = null;
        $expectedOn = null;

        foreach ($activities as $activity) {
            $at = $this->toCarbon($activity->activity_date ?? null);
            $type = (string) ($activity->activity_type ?? '');

            if ($at !== null && $type === self::ACTIVITY_FOLLOW_UP_SENT) {
                $lastFollowUpAt = $lastFollowUpAt === null || $at->greaterThan($lastFollowUpAt) ? $at : $lastFollowUpAt;
            }

            if ($at !== null && $type === self::ACTIVITY_RESPONSE_RECEIVED) {
                $lastResponseAt = $lastResponseAt === null || $at->greaterThan($lastResponseAt) ? $at : $lastResponseAt;
            }

            if ($at !== null && $type === self::ACTIVITY_EXPECTED_RESPONSE) {
                $expectedOn = $expectedOn === null || $at->greaterThan($expectedOn) ? $at : $expectedOn;
            }

            $rows[] = [
                'id' => (int) ($activity->id ?? 0),
                'type' => $type,
                'label' => JobSeekerApplicationFollowUp::typeLabel($type),
                'at' => $at,
                'note' => $activity->note ?? null,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return ($b['at']?->getTimestamp() ?? PHP_INT_MIN) <=> ($a['at']?->getTimestamp() ?? PHP_INT_MIN);
        });

        return [
            'rows' => $rows,
            'last_follow_up_days_ago' => $lastFollowUpAt === null ? null : $this->daysSince($lastFollowUpAt, $now),
            'last_response_days_ago' => $lastResponseAt === null ? null : $this->daysSince($lastResponseAt, $now),
            'last_response_at' => $lastResponseAt,
            'expected_response_on' => $expectedOn,
        ];
    }

    /**
     * The drafts this seeker actually pressed send on, newest first, with whether the
     * employer has opened them. Nothing here is inferred: a message that was only
     * generated and never sent does not appear, and an unopened one is reported as
     * unopened rather than as a reply.
     *
     * @param  array<int, ApplicationFollowUpMessage>  $messages
     * @return array<int, array{id: int, subject: string, body: string, template: string|null, sent_at: Carbon|null, read_at: Carbon|null, days_ago: int|null, viewed: bool, label: string}>
     */
    private function delivered(array $messages, Carbon $now): array
    {
        $rows = [];

        foreach ($messages as $message) {
            $sentAt = $this->toCarbon($message->sent_at ?? null);
            $readAt = $this->toCarbon($message->read_at ?? null);

            $rows[] = [
                'id' => (int) ($message->id ?? 0),
                'subject' => (string) ($message->subject ?? ''),
                'body' => (string) ($message->body ?? ''),
                'template' => isset($message->template) && $message->template !== '' ? (string) $message->template : null,
                'sent_at' => $sentAt,
                'read_at' => $readAt,
                'days_ago' => $sentAt === null ? null : $this->daysSince($sentAt, $now),
                'viewed' => $readAt !== null,
                'label' => ApplicationFollowUpMessage::deliveryLabel($readAt),
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($b['sent_at']?->getTimestamp() ?? PHP_INT_MIN) <=> ($a['sent_at']?->getTimestamp() ?? PHP_INT_MIN));

        return array_values($rows);
    }

    /**
     * Preparation content for §9. It is offered only when the application itself is
     * at an interview stage, and reuses the job's own description and structured
     * requirements instead of claiming to know what the employer will ask.
     *
     * @return array{available: bool, checklist: array<int, array{key: string, label: string}>, skills: array{mandatory: array, preferred: array}, software: array<int, string>, description: string|null}
     */
    private function interviewPreparation(Application $application, string $statusKey): array
    {
        $checklist = [
            ['key' => 're-read', 'label' => 'Re-read the job description and the skills it asks for.'],
            ['key' => 'examples', 'label' => 'Prepare two or three examples of past work that match those skills.'],
            ['key' => 'company', 'label' => 'Look up the company and the role so you can explain why it interests you.'],
            ['key' => 'questions', 'label' => 'Write down two questions you want to ask the interviewer.'],
            ['key' => 'logistics', 'label' => 'Confirm how and where the interview happens, and test the setup.'],
            ['key' => 'summary', 'label' => 'Practise a short summary of your experience out loud.'],
        ];

        $mandatory = [];
        $preferred = [];
        foreach ($this->requirements($application->job?->RequiredSkills ?? null) as $entry) {
            if ($entry['type'] === 'preferred') {
                $preferred[] = $entry['name'];
            } else {
                $mandatory[] = $entry['name'];
            }
        }

        return [
            'available' => $statusKey === 'interview',
            'checklist' => $checklist,
            'skills' => ['mandatory' => $mandatory, 'preferred' => $preferred],
            'software' => array_values(array_map(
                fn (array $entry): string => $entry['name'],
                $this->requirements($application->job?->RequiredSoftware ?? null),
            )),
            'description' => $application->job?->Description ? (string) $application->job->Description : null,
        ];
    }

    /**
     * Structured requirement payloads are stored as JSON objects, but the legacy
     * column may hold a plain string, so both are read defensively.
     *
     * @return array<int, array{name: string, type: string}>
     */
    private function requirements(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', preg_split('/,/', $value) ?: [])));
            $value = array_map(fn (string $name): array => ['name' => $name, 'type' => 'mandatory'], is_array($value) ? $value : []);
        }

        if (!is_array($value)) {
            return [];
        }

        $entries = [];
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $name = trim($entry);
                if ($name !== '') {
                    $entries[] = ['name' => $name, 'type' => 'mandatory'];
                }

                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $entries[] = ['name' => $name, 'type' => strtolower(trim((string) ($entry['type'] ?? ''))) === 'preferred' ? 'preferred' : 'mandatory'];
        }

        return $entries;
    }

    /** @return array<string, mixed> */
    private function metadata(Log $log): array
    {
        $raw = $log->metadata ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Whole days elapsed, so a record from this morning still reads as today. */
    private function daysSince(Carbon $from, Carbon $now): int
    {
        return max(0, (int) floor($from->diffInDays($now, false)));
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse(trim($value));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function clean(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : Str::limit(preg_replace('/\s+/', ' ', $value) ?? $value, 120, '');
    }
}
