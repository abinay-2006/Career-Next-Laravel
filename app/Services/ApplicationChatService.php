<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationChatMessage;
use App\Models\ApplicationConversation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who may talk in an application conversation, and what counts as a message.
 *
 * The rules all hang off the live application status rather than the conversation
 * row, so a decision changed on the application page takes effect on the very next
 * request: approved opens the thread, a revoked approval suspends it, and a
 * rejection or withdrawal closes it to new messages while leaving history readable.
 * ApplicationFollowUpService::statusKey() does the grouping so the wording of
 * Status here matches the rest of the product. Nothing in this class writes to the
 * application row.
 */
class ApplicationChatService
{
    /** Messaging is live only for an application the employer has approved. */
    public const STATE_OPEN = 'open';

    /** Approval was revoked, or was never given: history stays, sending stops. */
    public const STATE_SUSPENDED = 'suspended';

    /** Rejected or withdrawn: the thread is finished. */
    public const STATE_CLOSED = 'closed';

    public const MESSAGES_PER_PAGE = 30;

    public const THREADS_PER_PAGE = 20;

    /** Threads to open in one inbox visit, so a huge history cannot stall the page. */
    public const AUTO_OPEN_LIMIT = 100;

    public function __construct(
        private readonly ApplicationFollowUpService $coach = new ApplicationFollowUpService(),
    ) {}

    /**
     * 'Accepted' opens the chat, 'Rejected' or 'Withdrawn' ends it, and anything else
     * (including an interview still in progress) only pauses it, because a decision
     * that has not been made yet should not be described as one.
     */
    public function stateFor(?string $applicationStatus): string
    {
        return match ($this->coach->statusKey($applicationStatus)) {
            'accepted' => self::STATE_OPEN,
            'rejected', 'withdrawn' => self::STATE_CLOSED,
            default => self::STATE_SUSPENDED,
        };
    }

    /**
     * Whether this thread may accept new traffic right now, phrased for the person
     * who is blocked. Re-read from the application on every request.
     *
     * @return array{state:string,can_send:bool,reason:?string}
     */
    public function gate(ApplicationConversation $conversation): array
    {
        $key = $this->coach->statusKey($conversation->application?->Status);
        $state = $this->stateFor($conversation->application?->Status);

        $reason = match (true) {
            $state === self::STATE_OPEN => null,
            $key === 'withdrawn' => 'This application was withdrawn, so new messages are closed. Earlier messages stay readable.',
            $key === 'rejected' => 'This application was rejected, so new messages are closed. Earlier messages stay readable.',
            $key === 'interview' => 'Messaging is on hold while this application is at the interview stage. It reopens once the employer decides.',
            default => 'Messaging is paused while this application is pending again. It reopens as soon as the employer approves it.',
        };

        return [
            'state' => $state,
            'can_send' => $state === self::STATE_OPEN,
            'reason' => $reason,
        ];
    }

    /**
     * The thread for an approved application, created the first time either side
     * looks for it and never duplicated: ApplicationId is unique, and a race between
     * the two participants is resolved by reading back the row that won.
     */
    public function openFor(Application $application): ?ApplicationConversation
    {
        if (!ApplicationConversation::tableIsReady()) {
            return null;
        }

        $applicationId = (int) $application->ApplicationId;

        if ($applicationId <= 0) {
            return null;
        }

        $existing = ApplicationConversation::query()
            ->where('ApplicationId', $applicationId)
            ->first();

        if ($existing !== null) {
            return $this->syncStatus($existing, $application);
        }

        // A thread cannot be started before approval, and it cannot be started at all
        // once the job row is gone, because the employer id would have no source.
        if ($this->stateFor($application->Status) !== self::STATE_OPEN) {
            return null;
        }

        $employerId = (int) ($application->job?->EmployerId ?? 0);
        $jobSeekId = (int) $application->JobSeekId;

        if ($employerId <= 0 || $jobSeekId <= 0) {
            return null;
        }

        try {
            $conversation = ApplicationConversation::query()->create([
                'ApplicationId' => $applicationId,
                'EmployerId' => $employerId,
                'JobSeekId' => $jobSeekId,
                'status' => ApplicationConversation::STATUS_ACTIVE,
            ]);
        } catch (QueryException) {
            // Another participant created it between the check and the insert.
            $race = ApplicationConversation::query()
                ->where('ApplicationId', $applicationId)
                ->first();

            return $race instanceof ApplicationConversation ? $this->syncStatus($race, $application) : null;
        }

        return $conversation;
    }

    /**
     * Keep the stored thread status honest about the live application status without
     * touching the application itself.
     */
    public function syncStatus(ApplicationConversation $conversation, ?Application $application = null): ApplicationConversation
    {
        $application ??= $conversation->application;

        $shouldClose = $application === null
            || $this->stateFor($application->Status) !== self::STATE_OPEN;
        $isClosed = !$conversation->isOpen();

        if ($shouldClose === $isClosed) {
            return $conversation;
        }

        $conversation->update([
            'status' => $shouldClose
                ? ApplicationConversation::STATUS_CLOSED
                : ApplicationConversation::STATUS_ACTIVE,
        ]);

        return $conversation->refresh();
    }

    /**
     * Open the threads that approval created but nobody has visited yet. Bounded by
     * AUTO_OPEN_LIMIT and silently skipped when the tables are not migrated.
     */
    public function openMissingThreads(string $role, int $id): int
    {
        if (!ApplicationConversation::tableIsReady() || $id <= 0) {
            return 0;
        }

        $isSeeker = $role === ApplicationChatMessage::ROLE_JOBSEEKER;

        $candidates = Application::query()
            ->with('job')
            ->select(['ApplicationId', 'JobSeekId', 'JobId', 'Status'])
            ->when(
                $isSeeker,
                fn ($query) => $query->where('JobSeekId', $id),
                fn ($query) => $query->whereHas('job', fn ($jobs) => $jobs->where('EmployerId', $id)),
            )
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('application_conversations')
                    ->whereColumn('application_conversations.ApplicationId', 'application_master.ApplicationId');
            })
            ->orderByDesc('ApplicationId')
            ->limit(self::AUTO_OPEN_LIMIT)
            ->get();

        $opened = 0;

        foreach ($candidates as $candidate) {
            if ($this->stateFor($candidate->Status) !== self::STATE_OPEN) {
                continue;
            }

            if ($this->openFor($candidate) !== null) {
                $opened++;
            }
        }

        return $opened;
    }

    /**
     * Plain text only. Tags are dropped rather than escaped so nothing that arrives
     * here can be reinterpreted as markup anywhere else in the product either.
     */
    public function cleanMessage(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }

        $text = strip_tags($raw);
        // Control characters survive strip_tags and would break the transcript layout.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Validation rules for the composer. The limit is checked before cleaning. */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.ApplicationChatMessage::MAX_MESSAGE],
        ];
    }

    /**
     * Store one message. The caller supplies the sender from its own guard, so the
     * identity in the row can never come from the request body.
     */
    public function send(
        ApplicationConversation $conversation,
        string $role,
        int $senderId,
        string $message,
    ): ApplicationChatMessage {
        $stored = ApplicationChatMessage::query()->create([
            'ConversationId' => (int) $conversation->id,
            'SenderId' => $senderId,
            'SenderRole' => $role,
            'message' => $message,
            'read_at' => null,
        ]);

        $conversation->update(['last_message_at' => $stored->created_at ?? now()]);

        return $stored->load('conversation');
    }

    /**
     * Stamp the messages the other side wrote. Only ever touches unread rows, so the
     * first read is the timestamp the sender sees.
     */
    public function markRead(ApplicationConversation $conversation, string $role): int
    {
        $otherRole = $role === ApplicationChatMessage::ROLE_EMPLOYER
            ? ApplicationChatMessage::ROLE_JOBSEEKER
            : ApplicationChatMessage::ROLE_EMPLOYER;

        return ApplicationChatMessage::query()
            ->where('ConversationId', (int) $conversation->id)
            ->where('SenderRole', $otherRole)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Ids of this reader's own messages that the other side opened since the given
     * moment, which is what the composer shows as a read receipt.
     *
     * @return array<int, int>
     */
    public function readReceipts(ApplicationConversation $conversation, string $role, ?Carbon $since): array
    {
        $query = ApplicationChatMessage::query()
            ->where('ConversationId', (int) $conversation->id)
            ->where('SenderRole', $role)
            ->whereNotNull('read_at');

        if ($since !== null) {
            $query->where('read_at', '>', $since);
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** Newest window of a thread, in reading order, for the first paint. */
    public function latestMessages(ApplicationConversation $conversation, int $limit = self::MESSAGES_PER_PAGE)
    {
        $rows = ApplicationChatMessage::query()
            ->where('ConversationId', (int) $conversation->id)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values();

        return $rows;
    }

    /**
     * One page of older messages, oldest first, with the flag the client needs to
     * know when to stop offering the button.
     *
     * @return array{messages:\Illuminate\Support\Collection,has_more:bool}
     */
    public function olderMessages(ApplicationConversation $conversation, ?int $beforeId, int $perPage = self::MESSAGES_PER_PAGE): array
    {
        $perPage = max(1, min(100, $perPage));

        $query = ApplicationChatMessage::query()->where('ConversationId', (int) $conversation->id);

        if ($beforeId !== null && $beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rows = (clone $query)->orderByDesc('id')->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;

        return [
            'messages' => $rows->take($perPage)->reverse()->values(),
            'has_more' => $hasMore,
        ];
    }

    /** Total waiting messages for one inbox, used by the sidebar badge. */
    public function unreadTotal(string $role, int $id): int
    {
        return ApplicationConversation::unreadTotalForRole($role, $id);
    }

    /** The guard a role's id lives behind, so callers never guess. */
    public function currentId(string $role): int
    {
        return $role === ApplicationChatMessage::ROLE_EMPLOYER
            ? ApplicationConversation::currentEmployerId()
            : ApplicationConversation::currentJobSeekerId();
    }
}
