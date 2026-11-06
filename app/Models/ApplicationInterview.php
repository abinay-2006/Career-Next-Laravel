<?php

namespace App\Models;

use App\Support\SchemaReady;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * One interview invitation for one round of one application.
 *
 * The row belongs to the application, and the participants are read through it, so
 * neither side's id is ever taken from a form. The employer who raised it is stored
 * as created_by_employer_id, which is what the job-ownership check is compared
 * against on every request.
 *
 * A round is revised in place, so the invitation the seeker confirms is the same row
 * that was first offered and reschedule_requested_at / reschedule_reason /
 * reschedule_preferred_at record what was asked for along the way. active_marker is
 * 1 while the invitation still expects an answer and null once it is declined,
 * cancelled or completed; the unique index over (ApplicationId, round, active_marker)
 * is what stops a second open invitation for the same round even when both buttons
 * are pressed in one request.
 *
 * Nothing here writes to application_master. An interview is an event on top of an
 * accepted application, not a stage of it, so the application keeps whatever Status
 * the employer decided on.
 */
class ApplicationInterview extends Model
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_RESCHEDULE_REQUESTED = 'reschedule_requested';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    /** Statuses that still expect something from one of the two people. */
    public const OPEN_STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_CONFIRMED,
        self::STATUS_RESCHEDULE_REQUESTED,
    ];

    /** Statuses that have no further answer to wait for. */
    public const CLOSED_STATUSES = [
        self::STATUS_DECLINED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    public const TYPE_ONLINE = 'online';

    public const TYPE_IN_PERSON = 'in_person';

    public const TYPE_PHONE = 'phone';

    public const TYPES = [self::TYPE_ONLINE, self::TYPE_IN_PERSON, self::TYPE_PHONE];

    public const MAX_INSTRUCTIONS = 2000;

    public const MAX_REASON = 500;

    protected $table = 'application_interviews';

    protected $fillable = [
        'ApplicationId',
        'round',
        'scheduled_at',
        'timezone',
        'interview_type',
        'meeting_link',
        'location',
        'instructions',
        'status',
        'active_marker',
        'responded_at',
        'reschedule_requested_at',
        'reschedule_reason',
        'reschedule_preferred_at',
        'revision_count',
        'revised_at',
        'concluded_at',
        'created_by_employer_id',
    ];

    protected $casts = [
        'ApplicationId' => 'integer',
        'round' => 'integer',
        'scheduled_at' => 'datetime',
        'responded_at' => 'datetime',
        'reschedule_requested_at' => 'datetime',
        'reschedule_preferred_at' => 'datetime',
        'revised_at' => 'datetime',
        'concluded_at' => 'datetime',
        'revision_count' => 'integer',
        'created_by_employer_id' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'ApplicationId', 'ApplicationId');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employer::class, 'created_by_employer_id', 'EmployerId');
    }

    /**
     * The table only exists once the migration has run. Every caller asks instead of
     * catching a query exception, so an un-migrated database still renders the rest
     * of both dashboards.
     */
    public static function tableIsReady(): bool
    {
        return SchemaReady::hasTable('application_interviews');
    }

    public function isOpen(): bool
    {
        return in_array((string) $this->status, self::OPEN_STATUSES, true);
    }

    /** Whether the seeker still owes this invitation an answer. */
    public function awaitsSeeker(): bool
    {
        return (string) $this->status === self::STATUS_PROPOSED;
    }

    /** Whether the employer still owes this invitation a new date. */
    public function awaitsEmployer(): bool
    {
        return (string) $this->status === self::STATUS_RESCHEDULE_REQUESTED;
    }

    public function isCompleted(): bool
    {
        return (string) $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Wording for the status chip. Spelled out here rather than in Blade so the two
     * role pages, the list and the badge cannot drift into three different vocabularies.
     */
    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_PROPOSED => 'Proposed',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_RESCHEDULE_REQUESTED => 'Reschedule requested',
            self::STATUS_DECLINED => 'Declined',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_COMPLETED => 'Completed',
            default => ucfirst((string) $status),
        };
    }

    /** Icon class for the interview type, used by both role pages. */
    public static function typeIcon(?string $type): string
    {
        return match ((string) $type) {
            self::TYPE_ONLINE => 'fas fa-video',
            self::TYPE_IN_PERSON => 'fas fa-location-dot',
            self::TYPE_PHONE => 'fas fa-phone',
            default => 'fas fa-calendar-clock',
        };
    }

    public static function typeLabel(?string $type): string
    {
        return match ((string) $type) {
            self::TYPE_ONLINE => 'Online',
            self::TYPE_IN_PERSON => 'In person',
            self::TYPE_PHONE => 'Phone',
            default => 'Interview',
        };
    }

    /**
     * The stored instant read back in the timezone the invitation was written in.
     * Falls back to the application timezone when a row predates a zone or the zone
     * name is unusable, so a bad value can never break a page.
     */
    public function scheduledInItsTimezone(): ?Carbon
    {
        $scheduled = $this->scheduled_at;

        if (!$scheduled instanceof Carbon) {
            return null;
        }

        $zone = (string) $this->timezone;

        try {
            return $zone !== '' ? $scheduled->copy()->timezone($zone) : $scheduled->copy();
        } catch (\Throwable) {
            return $scheduled->copy();
        }
    }

    /** Date and time as the participant should read them. */
    public function scheduledLabel(): string
    {
        $at = $this->scheduledInItsTimezone();

        return $at === null ? 'Time unavailable' : $at->format('D, M j, Y · g:i A');
    }

    /** The short zone suffix beside the time, e.g. "Asia/Kolkata (IST)". */
    public function timezoneLabel(): string
    {
        $at = $this->scheduledInItsTimezone();
        $zone = (string) $this->timezone;

        if ($zone === '' || $at === null) {
            return $zone;
        }

        return $zone.' ('.$at->format('T').')';
    }

    /** How the seeker is reached, phrased for whichever field the type filled in. */
    public function whereLabel(): string
    {
        return match ((string) $this->interview_type) {
            self::TYPE_IN_PERSON => trim((string) $this->location) !== '' ? $this->location : 'Location to be shared',
            self::TYPE_PHONE => 'By phone',
            default => trim((string) $this->meeting_link) !== '' ? 'Online meeting' : 'Link to be shared',
        };
    }

    /** The job this interview sits under, for activity logging. */
    public function jobId(): ?int
    {
        $jobId = $this->application?->JobId ?? null;

        return is_numeric($jobId) ? (int) $jobId : null;
    }

    /**
     * Open invitations waiting for the seeker's answer across their own applications.
     * The seeker is read through the application rather than a column on this table,
     * so nobody can borrow another person's totals, and it answers zero for a visitor
     * who is not a seeker or for an un-migrated database.
     */
    public static function awaitingSeekerCount(mixed $jobSeekerId): int
    {
        return static::countByStatus(
            is_numeric($jobSeekerId) ? (int) $jobSeekerId : 0,
            'application_master.JobSeekId',
            [self::STATUS_PROPOSED],
        );
    }

    /**
     * Invitations waiting for the employer to act: one the seeker asked to move, and
     * one the seeker declined, which is the signal to offer another time or round.
     */
    public static function awaitingEmployerCount(mixed $employerId): int
    {
        return static::countByStatus(
            is_numeric($employerId) ? (int) $employerId : 0,
            'job_master.EmployerId',
            [self::STATUS_RESCHEDULE_REQUESTED, self::STATUS_DECLINED],
        );
    }

    /** @param array<int, string> $statuses */
    private static function countByStatus(int $id, string $ownerColumn, array $statuses): int
    {
        if ($id <= 0 || !static::tableIsReady()) {
            return 0;
        }

        try {
            return static::query()
                ->join('application_master', 'application_master.ApplicationId', '=', 'application_interviews.ApplicationId')
                ->join('job_master', 'job_master.JobId', '=', 'application_master.JobId')
                ->where($ownerColumn, $id)
                ->whereIn('application_interviews.status', $statuses)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** The guard the current employer id lives behind. */
    public static function currentEmployerId(): int
    {
        $employer = Auth::guard('employer')->user();

        return $employer instanceof Employer ? (int) $employer->EmployerId : 0;
    }

    /** The guard the current job seeker id lives behind. */
    public static function currentJobSeekerId(): int
    {
        $seeker = Auth::guard('jobseeker')->user();

        return $seeker instanceof JobSeeker ? (int) $seeker->JobSeekId : 0;
    }
}
