<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationInterview;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What an interview invitation may be asked to do, and what it looks like when it
 * has done it.
 *
 * The rules live here so the two role controllers, the two cards and the sidebar
 * badge all agree on one state machine instead of each inventing their own. Every
 * eligibility question is answered from the live application row, so an approval
 * revoked on the application page stops new invitations on the very next request -
 * the same way ApplicationChatService suspends a thread.
 *
 * Nothing in this class writes to application_master. An interview is an event on
 * top of an accepted application, never a stage of it, so scheduling, confirming or
 * completing one leaves Status exactly as the employer set it.
 *
 * scheduled_at is stored as an absolute instant rendered in the application
 * timezone (UTC), with the name of the zone the person typed it in kept beside it.
 * Because Eloquent writes the wall clock a Carbon object is carrying, every instant
 * is converted before it is assigned - otherwise 10:00 IST would be stored as 10:00
 * and read back six hours late.
 */
class ApplicationInterviewService
{
    /** Invitations per page on both list screens. */
    public const PER_PAGE = 15;

    /** The shortest reschedule explanation that is still useful to an employer. */
    public const MIN_REASON = 10;

    /** Zones offered in the form. Validation accepts any real zone, this is only the short list. */
    public const COMMON_TIMEZONES = [
        'UTC',
        'Asia/Kolkata',
        'Asia/Dubai',
        'Asia/Singapore',
        'Asia/Tokyo',
        'Australia/Sydney',
        'Europe/London',
        'Europe/Berlin',
        'Europe/Moscow',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Sao_Paulo',
        'Africa/Johannesburg',
    ];

    public function __construct(
        private readonly ApplicationFollowUpService $coach = new ApplicationFollowUpService(),
    ) {}

    /**
     * Whether this application may be offered a new interview right now. Only an
     * accepted application can, because an invitation promises a conversation the
     * employer has not agreed to have yet.
     *
     * @return array{can_schedule:bool,reason:?string}
     */
    public function eligibility(Application $application): array
    {
        $key = $this->coach->statusKey($application->Status);

        if ($key === 'accepted') {
            return ['can_schedule' => true, 'reason' => null];
        }

        $reason = match ($key) {
            'rejected' => 'This applicant was rejected, so no interview can be scheduled.',
            'withdrawn' => 'This application was withdrawn, so no interview can be scheduled.',
            'interview' => 'Interviews are scheduled once the applicant is accepted.',
            default => 'Interviews can only be scheduled after you accept this applicant.',
        };

        return ['can_schedule' => false, 'reason' => $reason];
    }

    /**
     * What the interview panel on an application page may offer: whether a new
     * invitation can be raised at all, the sentence that explains why not, and the
     * round the next one would take. Read from the live application row each time, so
     * a decision changed on this page is reflected on the very next request.
     *
     * @return array{canSchedule:bool, scheduleBlocked:?string, nextRound:int, ready:bool}
     */
    public function scheduleAccess(Application $application): array
    {
        $ready = ApplicationInterview::tableIsReady();
        $eligibility = $this->eligibility($application);

        return [
            'canSchedule' => $ready && $eligibility['can_schedule'],
            'scheduleBlocked' => $ready
                ? $eligibility['reason']
                : 'Interviews need their database table first. Run: php artisan migrate',
            'nextRound' => $ready ? $this->nextRound((int) $application->ApplicationId) : 1,
            'ready' => $ready,
        ];
    }

    /**
     * Which actions each participant may take on this invitation right now, with the
     * wording a blocked person is given. Derived from the stored status alone, so a
     * stale page cannot offer a button the database no longer supports.
     *
     * @return array{seeker:array<string, mixed>, employer:array<string, mixed>}
     */
    public function gate(ApplicationInterview $interview): array
    {
        $status = (string) $interview->status;

        $seekerReason = match ($status) {
            ApplicationInterview::STATUS_PROPOSED => null,
            ApplicationInterview::STATUS_RESCHEDULE_REQUESTED => 'You have asked for another time and the invitation is waiting for the employer to answer.',
            ApplicationInterview::STATUS_CONFIRMED => 'You already confirmed this interview, so there is nothing left to answer. Only the employer can change or cancel a confirmed round.',
            ApplicationInterview::STATUS_DECLINED => 'You declined this invitation.',
            ApplicationInterview::STATUS_CANCELLED => 'The employer cancelled this interview.',
            ApplicationInterview::STATUS_COMPLETED => 'This interview is over.',
            default => 'This invitation needs no answer from you.',
        };

        $employerReason = match ($status) {
            ApplicationInterview::STATUS_RESCHEDULE_REQUESTED => null,
            ApplicationInterview::STATUS_PROPOSED => 'Wait for the applicant to answer before changing the date.',
            ApplicationInterview::STATUS_CONFIRMED => 'The interview is confirmed. Cancel it and raise a new round to change it.',
            ApplicationInterview::STATUS_DECLINED => 'The applicant declined this invitation.',
            ApplicationInterview::STATUS_CANCELLED => 'This interview is already cancelled.',
            ApplicationInterview::STATUS_COMPLETED => 'This interview is already completed.',
            default => 'There is nothing to revise on this invitation.',
        };

        return [
            'seeker' => [
                'can_respond' => $status === ApplicationInterview::STATUS_PROPOSED,
                'can_confirm' => $status === ApplicationInterview::STATUS_PROPOSED,
                'can_decline' => $status === ApplicationInterview::STATUS_PROPOSED,
                'can_request' => $status === ApplicationInterview::STATUS_PROPOSED,
                'reason' => $seekerReason,
            ],
            'employer' => [
                'can_revise' => $status === ApplicationInterview::STATUS_RESCHEDULE_REQUESTED,
                'can_cancel' => in_array($status, ApplicationInterview::OPEN_STATUSES, true),
                'can_complete' => $status === ApplicationInterview::STATUS_CONFIRMED,
                'reason' => $employerReason,
            ],
        ];
    }

    /**
     * Rules for the schedule and revise forms. The type decides which contact field
     * is required, so an online interview cannot be saved without a link and an
     * in-person one cannot be saved without an address.
     *
     * @return array<string, array<int, string>>
     */
    public function scheduleRules(?string $type = null): array
    {
        $rules = [
            'scheduled_at' => ['required', 'date'],
            'timezone' => ['required', 'string', 'max:64'],
            'interview_type' => ['required', 'in:'.implode(',', ApplicationInterview::TYPES)],
            'instructions' => ['nullable', 'string', 'max:'.ApplicationInterview::MAX_INSTRUCTIONS],
        ];

        // The optional field is nullable so an empty value never reaches url: or
        // max:, which would fail a form that correctly left it out.
        $rules['meeting_link'] = ['nullable', 'string', 'max:500'];
        $rules['location'] = ['nullable', 'string', 'max:255'];

        if ($type === ApplicationInterview::TYPE_ONLINE) {
            $rules['meeting_link'] = ['required', 'url:http,https', 'max:500'];
        }

        if ($type === ApplicationInterview::TYPE_IN_PERSON) {
            $rules['location'] = ['required', 'string', 'min:3', 'max:255'];
        }

        return $rules;
    }

    /**
     * Rules for the seeker's reschedule request. A reason is required because an
     * employer cannot propose a better slot against an empty field.
     *
     * @return array<string, array<int, string>>
     */
    public function requestRules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.self::MIN_REASON, 'max:'.ApplicationInterview::MAX_REASON],
            'preferred_at' => ['required', 'date'],
            'preferred_timezone' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Plain text only, cleaned the same way chat messages are: tags are dropped
     * rather than escaped, so nothing stored here can be reinterpreted as markup on
     * the other participant's page.
     */
    public function cleanText(mixed $raw, int $max = ApplicationInterview::MAX_INSTRUCTIONS): string
    {
        if (!is_string($raw)) {
            return '';
        }

        $text = strip_tags($raw);
        // Control characters survive strip_tags and would break the card layout.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = trim($text);

        return mb_substr($text, 0, $max);
    }

    /** Whether a zone name can be used. Accepts any real identifier, not just the short list. */
    public function isValidTimezone(mixed $zone): bool
    {
        return is_string($zone) && in_array($zone, timezone_identifiers_list(), true);
    }

    /**
     * The instant a participant meant. The wall clock they typed is read in the zone
     * they chose, then moved into the application timezone so it is stored as one
     * comparable absolute moment.
     */
    public function instant(mixed $value, ?string $zone): ?Carbon
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $zone = $this->isValidTimezone($zone) ? $zone : $this->storageZone();

        try {
            // Parsed in the source zone first: the string is a wall clock, not an instant.
            $at = Carbon::parse((string) $value, $zone);
        } catch (\Throwable) {
            return null;
        }

        if (!$at instanceof Carbon) {
            return null;
        }

        return $at->setTimezone($this->storageZone());
    }

    /** A date that has already gone by cannot be offered to anyone. */
    public function isFuture(?Carbon $at): bool
    {
        return $at !== null && $at->greaterThan(now()->addMinute());
    }

    /** The zone every instant is written and read in. */
    public function storageZone(): string
    {
        $zone = (string) config('app.timezone');

        return $this->isValidTimezone($zone) ? $zone : 'UTC';
    }

    /**
     * The round a new invitation should take: one past the highest already used, so
     * rounds never repeat and two open invitations for the same round cannot be
     * raised through the form at all.
     */
    public function nextRound(int $applicationId): int
    {
        $highest = (int) ApplicationInterview::query()
            ->where('ApplicationId', $applicationId)
            ->max('round');

        return max(1, $highest + 1);
    }

    /**
     * The invitation still waiting for an answer in a given round, if any. Used to
     * tell the employer which interview they would be touching before they touch it.
     */
    public function openInvitation(int $applicationId, ?int $round = null): ?ApplicationInterview
    {
        $query = ApplicationInterview::query()
            ->where('ApplicationId', $applicationId)
            ->whereIn('status', ApplicationInterview::OPEN_STATUSES);

        if ($round !== null) {
            $query->where('round', $round);
        }

        return $query->orderByDesc('round')->orderByDesc('id')->first();
    }

    /**
     * Raise a new invitation for the next round. The insert and the activity row are
     * one transaction, and the unique open-invitation index is trusted rather than
     * pre-checked: if two requests race, the loser reads back the row that won
     * instead of reporting its own failure.
     *
     * @param  array<string, mixed>  $data
     */
    public function propose(Application $application, int $employerId, array $data): ApplicationInterview
    {
        $round = $this->nextRound((int) $application->ApplicationId);
        $fields = $this->scheduleFields($data, $employerId);

        try {
            return DB::transaction(
                fn (): ApplicationInterview => ApplicationInterview::query()->create($fields + [
                    'ApplicationId' => (int) $application->ApplicationId,
                    'round' => $round,
                    'status' => ApplicationInterview::STATUS_PROPOSED,
                    'active_marker' => 1,
                    'responded_at' => null,
                    'reschedule_requested_at' => null,
                    'reschedule_reason' => null,
                    'reschedule_preferred_at' => null,
                    'revision_count' => 0,
                    'revised_at' => null,
                    'concluded_at' => null,
                ]),
            );
        } catch (QueryException $exception) {
            // Another request raised the same round between the check and the insert.
            $race = $this->openInvitation((int) $application->ApplicationId, $round);

            if ($race instanceof ApplicationInterview) {
                return $race;
            }

            throw $exception;
        }
    }

    /**
     * Answer the seeker's request with a new time. The same row is revised, so the
     * invitation the seeker then confirms is the one they have been answering all
     * along, and what they asked for stays on the record.
     *
     * @param  array<string, mixed>  $data
     */
    public function revise(ApplicationInterview $interview, array $data): ApplicationInterview
    {
        $fields = $this->scheduleFields($data, (int) $interview->created_by_employer_id);

        $interview->update($fields + [
            'status' => ApplicationInterview::STATUS_PROPOSED,
            'active_marker' => 1,
            // The new proposal waits for an answer, so the old one is no longer current.
            'responded_at' => null,
            'revision_count' => (int) $interview->revision_count + 1,
            'revised_at' => now(),
        ]);

        return $interview->refresh();
    }

    /**
     * The seeker's answer. confirm and request leave the invitation open; decline
     * closes it. A request never touches scheduled_at - the interview stays exactly
     * when the employer put it until the employer moves it themselves.
     *
     * @param  array<string, mixed>  $data
     */
    public function respond(ApplicationInterview $interview, string $decision, array $data = []): ApplicationInterview
    {
        $fields = ['responded_at' => now()];

        if ($decision === ApplicationInterview::STATUS_CONFIRMED) {
            $fields['status'] = ApplicationInterview::STATUS_CONFIRMED;
            $fields['active_marker'] = 1;
        } elseif ($decision === ApplicationInterview::STATUS_DECLINED) {
            $fields['status'] = ApplicationInterview::STATUS_DECLINED;
            $fields['active_marker'] = null;
            $fields['concluded_at'] = now();
        } else {
            $preferred = $this->instant(
                $data['preferred_at'] ?? null,
                $data['preferred_timezone'] ?? $interview->timezone
            );

            $fields['status'] = ApplicationInterview::STATUS_RESCHEDULE_REQUESTED;
            $fields['active_marker'] = 1;
            $fields['reschedule_requested_at'] = now();
            $fields['reschedule_reason'] = $this->cleanText($data['reason'] ?? null, ApplicationInterview::MAX_REASON);
            $fields['reschedule_preferred_at'] = $preferred?->copy()->setTimezone($this->storageZone());
        }

        $interview->update($fields);

        return $interview->refresh();
    }

    /**
     * Cancel an invitation. Nothing is deleted: the seeker keeps seeing the round
     * that was cancelled, which is what makes the history readable.
     */
    public function cancel(ApplicationInterview $interview): ApplicationInterview
    {
        $interview->update([
            'status' => ApplicationInterview::STATUS_CANCELLED,
            'active_marker' => null,
            'concluded_at' => now(),
        ]);

        return $interview->refresh();
    }

    /** Mark a confirmed interview as held. */
    public function complete(ApplicationInterview $interview): ApplicationInterview
    {
        $interview->update([
            'status' => ApplicationInterview::STATUS_COMPLETED,
            'active_marker' => null,
            'concluded_at' => now(),
        ]);

        return $interview->refresh();
    }

    /**
     * One sentence describing the newest thing to know, from the point of view of the
     * person reading it. This is the interview update shown in the page - the app has
     * no notification table, so the card and the flash message carry it instead.
     */
    public function updateFor(ApplicationInterview $interview, string $role): string
    {
        $status = (string) $interview->status;
        $to = $role === 'employer' ? 'the employer' : 'you';
        $by = $role === 'employer' ? 'You' : 'The applicant';

        if ($role === 'employer') {
            return match ($status) {
                ApplicationInterview::STATUS_PROPOSED => 'Invitation round '.$interview->round.' is waiting for the applicant to answer.',
                ApplicationInterview::STATUS_CONFIRMED => 'The applicant confirmed round '.$interview->round.' for '.$interview->scheduledLabel().'.',
                ApplicationInterview::STATUS_RESCHEDULE_REQUESTED => 'The applicant asked to move round '.$interview->round.'. Their reason: '.$interview->reschedule_reason.'.',
                ApplicationInterview::STATUS_DECLINED => 'The applicant declined round '.$interview->round.'. You can offer another round instead.',
                ApplicationInterview::STATUS_CANCELLED => 'Round '.$interview->round.' was cancelled.',
                ApplicationInterview::STATUS_COMPLETED => 'Round '.$interview->round.' is recorded as completed.',
                default => 'Interview round '.$interview->round.'.',
            };
        }

        return match ($status) {
            ApplicationInterview::STATUS_PROPOSED => $interview->revision_count > 0
                ? 'The employer proposed a new time for round '.$interview->round.'. Please confirm or ask again.'
                : 'You have a new interview invitation for round '.$interview->round.'. Confirm, ask for another time or decline.',
            ApplicationInterview::STATUS_CONFIRMED => 'Round '.$interview->round.' is confirmed for '.$interview->scheduledLabel().' ('.$interview->timezoneLabel().').',
            ApplicationInterview::STATUS_RESCHEDULE_REQUESTED => 'Your request to move round '.$interview->round.' was sent to '.$to.' on '.($interview->reschedule_requested_at?->format('M j, Y') ?? 'an unknown date').'.',
            ApplicationInterview::STATUS_DECLINED => 'You declined round '.$interview->round.'. '.$by.' can offer another time.',
            ApplicationInterview::STATUS_CANCELLED => 'Round '.$interview->round.' was cancelled by the employer.',
            ApplicationInterview::STATUS_COMPLETED => 'Round '.$interview->round.' is recorded as completed.',
            default => 'Interview round '.$interview->round.'.',
        };
    }

    /** @return array<string, mixed> */
    private function scheduleFields(array $data, int $employerId): array
    {
        $zone = (string) ($data['timezone'] ?? $this->storageZone());
        $type = (string) ($data['interview_type'] ?? ApplicationInterview::TYPE_ONLINE);
        $at = $this->instant($data['scheduled_at'] ?? null, $zone)
            ?->copy()
            ->setTimezone($this->storageZone());

        $link = $type === ApplicationInterview::TYPE_ONLINE
            ? trim((string) ($data['meeting_link'] ?? ''))
            : null;
        $location = $type === ApplicationInterview::TYPE_IN_PERSON
            ? $this->cleanText($data['location'] ?? null, 255)
            : null;

        return [
            'scheduled_at' => $at,
            'timezone' => $this->isValidTimezone($zone) ? $zone : $this->storageZone(),
            'interview_type' => $type,
            'meeting_link' => $link !== null && $link !== '' ? mb_substr($link, 0, 500) : null,
            'location' => $location !== null && $location !== '' ? $location : null,
            'instructions' => $this->cleanText($data['instructions'] ?? null),
            'created_by_employer_id' => $employerId,
        ];
    }
}
