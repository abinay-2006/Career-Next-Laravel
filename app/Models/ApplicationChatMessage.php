<?php

namespace App\Models;

use App\Support\SchemaReady;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message inside an application conversation.
 *
 * SenderId and SenderRole are set by the server from the authenticated guard, so a
 * participant cannot forge the other side. read_at is the recipient's own stamp:
 * it drives the unread badge, and the sender's "Read" receipt is simply the same
 * column read from the other end. Nothing here can move an application status.
 */
class ApplicationChatMessage extends Model
{
    public const ROLE_EMPLOYER = 'employer';

    public const ROLE_JOBSEEKER = 'jobseeker';

    /** Room for a paragraph, small enough to keep a thread readable on a phone. */
    public const MAX_MESSAGE = 2000;

    /** Length of the inbox preview line. */
    public const PREVIEW_LENGTH = 90;

    protected $table = 'application_chat_messages';

    protected $fillable = [
        'ConversationId',
        'SenderId',
        'SenderRole',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ApplicationConversation::class, 'ConversationId', 'id');
    }

    public static function tableIsReady(): bool
    {
        return SchemaReady::hasTable('application_chat_messages');
    }

    /** Which of the two roles is allowed to read this message. */
    public function recipientRole(): string
    {
        return $this->SenderRole === self::ROLE_JOBSEEKER
            ? self::ROLE_EMPLOYER
            : self::ROLE_JOBSEEKER;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /** Display name of whoever wrote this row, looked up by role. */
    public function senderName(): string
    {
        if ($this->SenderRole === self::ROLE_EMPLOYER) {
            $name = $this->conversation?->employer?->CompanyName
                ?? $this->conversation?->application?->job?->CompanyName;
        } else {
            $name = $this->conversation?->jobSeeker?->JobSeekerName;
        }

        return is_string($name) && $name !== '' ? $name : 'Someone';
    }

    /** Short, single-line excerpt for the conversation list. */
    public function preview(int $length = self::PREVIEW_LENGTH): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', (string) $this->message));

        if ($flat === '') {
            return '';
        }

        if (mb_strlen($flat) <= $length) {
            return $flat;
        }

        return rtrim(mb_substr($flat, 0, $length)).'…';
    }

    /** The job this thread sits under, for activity logging. */
    public function jobId(): ?int
    {
        $jobId = $this->conversation?->application?->JobId;

        return is_numeric($jobId) ? (int) $jobId : null;
    }
}
