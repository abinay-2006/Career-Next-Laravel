<?php

namespace App\Models;

use App\Support\SchemaReady;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One follow-up message a job seeker delivered to an employer from the
 * Application Follow-up Coach.
 *
 * Only the seeker creates these rows, and only the employer can set read_at. The
 * text is whatever the seeker had in the draft box when they pressed send, so it is
 * stored exactly as written and never regenerated. Private tracker notes live in
 * JobSeekerApplicationFollowUp and are not readable from here.
 */
class ApplicationFollowUpMessage extends Model
{
    public const MAX_SUBJECT = 200;

    public const MAX_BODY = 4000;

    /** How long an employer must wait before the same applicant may send again. */
    public const REPEAT_WINDOW_HOURS = 24;

    protected $table = 'application_follow_up_messages';

    protected $fillable = [
        'ApplicationId',
        'JobSeekId',
        'EmployerId',
        'template',
        'subject',
        'body',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'ApplicationId', 'ApplicationId');
    }

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class, 'EmployerId', 'EmployerId');
    }

    /**
     * The table only exists once the migration has run. Both the coach page and the
     * employer pages ask instead of catching a query exception, so an un-migrated
     * database still renders.
     */
    public static function tableIsReady(): bool
    {
        return SchemaReady::hasTable('application_follow_up_messages');
    }

    /**
     * Messages the employer has not opened yet, for the sidebar badge. Returns zero
     * for a visitor who is not an employer and for an un-migrated database, so the
     * navigation never has to know which guard is active.
     */
    public static function unreadForEmployer(mixed $employerId): int
    {
        $employerId = is_numeric($employerId) ? (int) $employerId : 0;

        if ($employerId <= 0 || !static::tableIsReady()) {
            return 0;
        }

        try {
            return static::query()
                ->where('EmployerId', $employerId)
                ->whereNull('read_at')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * The most recent delivery for this application, if any. Used to hold the seeker
     * back from sending the same employer message after message.
     */
    public static function lastSentAt(int $applicationId): ?\Illuminate\Support\Carbon
    {
        if (!static::tableIsReady()) {
            return null;
        }

        $sentAt = static::query()
            ->where('ApplicationId', $applicationId)
            ->max('sent_at');

        return $sentAt === null ? null : \Illuminate\Support\Carbon::parse($sentAt);
    }

    /** Whether the employer has looked at it, phrased for the seeker's card. */
    public static function deliveryLabel(mixed $readAt): string
    {
        if ($readAt === null) {
            return 'Delivered. Not viewed yet.';
        }

        $readAt = $readAt instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($readAt)
            : \Illuminate\Support\Carbon::parse($readAt);

        return 'Viewed by the employer on '.$readAt->format('M j, Y').'.';
    }

    /** The guard the current employer id lives behind. */
    public static function currentEmployerId(): int
    {
        $employer = Auth::guard('employer')->user();

        return $employer instanceof Employer ? (int) $employer->EmployerId : 0;
    }

    /** The job this message sits under, for activity logging. */
    public function jobId(): ?int
    {
        $jobId = $this->application?->JobId ?? null;

        return is_numeric($jobId) ? (int) $jobId : null;
    }
}
