<?php

namespace App\Models;

use App\Support\SchemaReady;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * The private thread that belongs to one approved application.
 *
 * Exactly two people can ever see a row: the employer who owns the job and the
 * job seeker who applied to it. The pair is copied from the application when the
 * conversation is opened, so ownership can be re-checked on every request without
 * trusting the URL. Conversations are never deleted when an application is
 * rejected or an approval is revoked; only the status changes, so the history of
 * what was agreed stays readable.
 */
class ApplicationConversation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'application_conversations';

    protected $fillable = [
        'ApplicationId',
        'EmployerId',
        'JobSeekId',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(ApplicationChatMessage::class, 'ConversationId', 'id');
    }

    /** Newest row, eager-loadable so an inbox of fifty costs one query. */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(ApplicationChatMessage::class, 'ConversationId', 'id')->latestOfMany('id');
    }

    /**
     * The tables only exist once the migration has run. Every caller asks instead of
     * catching a query exception, so an un-migrated database still renders the rest
     * of both dashboards.
     */
    public static function tableIsReady(): bool
    {
        return SchemaReady::hasTable('application_conversations')
            && SchemaReady::hasTable('application_chat_messages');
    }

    /**
     * Messages waiting to be read in each of this employer's conversations, keyed by
     * conversation id. Counted per employer, so nobody can borrow another company's
     * unread totals.
     *
     * @return array<int, int>
     */
    public static function unreadMapForEmployer(mixed $employerId): array
    {
        return static::unreadMap('employer', $employerId);
    }

    /** @return array<int, int> */
    public static function unreadMapForJobSeeker(mixed $jobSeekerId): array
    {
        return static::unreadMap('jobseeker', $jobSeekerId);
    }

    /** Total waiting messages for the sidebar badge. */
    public static function unreadTotalForRole(string $role, mixed $id): int
    {
        return array_sum(static::unreadMap($role, $id));
    }

    /**
     * Unread totals for one inbox. Deliberately returns an empty array for a visitor
     * who does not hold that role and for an un-migrated database, because the
     * sidebar calls it on every page and must never fault.
     *
     * @return array<int, int>
     */
    private static function unreadMap(string $role, mixed $id): array
    {
        if (!is_numeric($id) || (int) $id <= 0 || !static::tableIsReady()) {
            return [];
        }

        $id = (int) $id;
        $column = $role === ApplicationChatMessage::ROLE_EMPLOYER ? 'EmployerId' : 'JobSeekId';
        // A message counts against the reader only when the other side wrote it.
        $otherRole = $role === ApplicationChatMessage::ROLE_EMPLOYER
            ? ApplicationChatMessage::ROLE_JOBSEEKER
            : ApplicationChatMessage::ROLE_EMPLOYER;

        try {
            $rows = static::query()
                ->join('application_chat_messages', 'application_chat_messages.ConversationId', '=', 'application_conversations.id')
                ->where('application_conversations.'.$column, $id)
                ->where('application_chat_messages.SenderRole', $otherRole)
                ->whereNull('application_chat_messages.read_at')
                ->groupBy('application_conversations.id')
                ->selectRaw('application_conversations.id, COUNT(*) as aggregate')
                ->get();

            $counts = [];
            foreach ($rows as $row) {
                $counts[(int) $row->id] = (int) $row->aggregate;
            }

            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Job title shown beside the thread, tolerant of a delisted job. */
    public function jobTitle(): string
    {
        $title = $this->application?->job?->JobTitle;

        return is_string($title) && $title !== '' ? $title : 'Job no longer listed';
    }

    /** Employer-facing label for the other half of the thread. */
    public function applicantName(): string
    {
        $name = $this->jobSeeker?->JobSeekerName;

        return is_string($name) && $name !== '' ? $name : 'Applicant unavailable';
    }

    /** Seeker-facing label for the other half of the thread. */
    public function companyName(): string
    {
        $name = $this->employer?->CompanyName ?: $this->application?->job?->CompanyName;

        return is_string($name) && $name !== '' ? $name : 'Company unavailable';
    }

    public function isOpen(): bool
    {
        return strtolower(trim((string) $this->status)) === self::STATUS_ACTIVE;
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

    /** Timestamp helper kept on the model so views never re-parse a string. */
    public static function asCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon
            ? $value
            : ($value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value));
    }
}
