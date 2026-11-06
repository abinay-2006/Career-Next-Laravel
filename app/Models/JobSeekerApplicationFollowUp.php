<?php

namespace App\Models;

use App\Services\ApplicationFollowUpService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * One thing the job seeker recorded about their own application: a follow-up they
 * sent, a reply they received, a date they are waiting for, or a private note.
 *
 * These rows never change Application::$Status. The official status stays whatever
 * the employer's workflow wrote, which is why the two are shown in separate parts
 * of the coach page.
 */
class JobSeekerApplicationFollowUp extends Model
{
    public const TYPE_FOLLOW_UP_SENT = 'follow_up_sent';

    public const TYPE_RESPONSE_RECEIVED = 'response_received';

    public const TYPE_EXPECTED_RESPONSE = 'expected_response';

    public const TYPE_NOTE = 'note';

    public const TYPES = [
        self::TYPE_FOLLOW_UP_SENT,
        self::TYPE_RESPONSE_RECEIVED,
        self::TYPE_EXPECTED_RESPONSE,
        self::TYPE_NOTE,
    ];

    protected $table = 'jobseeker_application_follow_ups';

    protected $fillable = [
        'ApplicationId',
        'JobSeekId',
        'activity_type',
        'activity_date',
        'note',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'ApplicationId', 'ApplicationId');
    }

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * The table only exists once the migration has run. A database that has not
     * been migrated yet must still be able to open the coach page, so callers ask
     * instead of catching a query exception.
     */
    public static function tableIsReady(): bool
    {
        return Schema::hasTable('jobseeker_application_follow_ups');
    }

    /** What one entry looks like in the seeker's own history list. */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_FOLLOW_UP_SENT => 'Follow-up sent',
            self::TYPE_RESPONSE_RECEIVED => 'Employer response received',
            self::TYPE_EXPECTED_RESPONSE => 'Expected response date',
            self::TYPE_NOTE => 'Private note',
            default => trim(str_replace('_', ' ', $type)) !== '' ? ucfirst(trim(str_replace('_', ' ', $type))) : 'Activity',
        };
    }

    /**
     * Only an expected-response date may sit in the future; the rest describe
     * something that already happened.
     */
    public static function allowsFutureDate(string $type): bool
    {
        return $type === ApplicationFollowUpService::ACTIVITY_EXPECTED_RESPONSE;
    }
}
