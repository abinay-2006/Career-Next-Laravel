<?php

namespace App\Http\Controllers;

use App\Models\ApplicationChatMessage;
use App\Models\ApplicationConversation;
use App\Services\ActivityLogger;
use App\Services\ApplicationChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * The Messages inbox and chat panel, shared by both roles.
 *
 * Every entry point resolves the actor from its own guard and then looks the thread
 * up through that actor's column, so a conversation belonging to someone else is
 * not readable no matter what the URL says. The live application status is
 * re-checked on each request through ApplicationChatService::gate(), which is what
 * suspends or closes a thread the moment a decision changes. Sender identity always
 * comes from the guard, never from the body.
 */
abstract class ApplicationChatController extends Controller
{
    private const PER_PAGE_THREADS = 20;

    /** Newest messages returned by one poll, so a busy thread cannot flood the page. */
    private const POLL_LIMIT = 50;

    public function __construct(protected readonly ApplicationChatService $chat) {}

    /** 'employer' or 'jobseeker': the guard, the route prefix and the inbox column. */
    abstract protected function role(): string;

    public function index(Request $request): View
    {
        $filters = $this->validated($request, [
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        // A page-level failure here is only the page number, which is safer clamped.
        $page = min(max(1, (int) ($filters['page'] ?? 1)), 100000);
        $actorId = $this->actorId();
        $ready = ApplicationConversation::tableIsReady();
        $unread = [];
        $threads = new LengthAwarePaginator([], 0, self::PER_PAGE_THREADS, $page);

        if ($ready) {
            // Approval opens the thread whether or not either participant has visited.
            $this->chat->openMissingThreads($this->role(), $actorId);
            $threads = $this->inboxQuery($actorId)->paginate(self::PER_PAGE_THREADS)->withQueryString();
            $unread = $this->unreadMap($actorId);
        }

        return $this->inboxView($threads, $unread, $ready, null);
    }

    public function show(Request $request, int $conversation): View|JsonResponse|RedirectResponse
    {
        $actorId = $this->actorId();
        $ready = ApplicationConversation::tableIsReady();

        if (!$ready) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Messaging needs its database tables first.'], 503);
            }

            return redirect()->route($this->route('index'));
        }

        $thread = $this->thread($conversation, $actorId);
        $gate = $this->chat->gate($thread);

        // Opening the thread is the receipt: the other side's unread rows are stamped
        // before the transcript is read back, so what you see matches what you read.
        $this->chat->markRead($thread, $this->role());

        $messages = $this->chat->latestMessages($thread);
        $totalMessages = ApplicationChatMessage::query()->where('ConversationId', (int) $thread->id)->count();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'conversation' => $this->presentThread($thread, $actorId),
                'messages' => $messages->map(fn (ApplicationChatMessage $m): array => $this->presentMessage($m))->all(),
                'has_more' => $totalMessages > $messages->count(),
            ] + $gate);
        }

        return $this->inboxView(
            $this->inboxQuery($actorId)->paginate(self::PER_PAGE_THREADS)->withQueryString(),
            $this->unreadMap($actorId),
            true,
            $thread,
            $messages,
            $gate,
            $totalMessages > $messages->count(),
        );
    }

    /** AJAX refresh for the open thread: anything newer than the client's cursor. */
    public function poll(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        $actorId = $this->actorId();

        if (!ApplicationConversation::tableIsReady()) {
            return response()->json(['success' => false, 'message' => 'Messaging needs its database tables first.'], 503);
        }

        $thread = $this->thread($conversation, $actorId);
        $gate = $this->chat->gate($thread);

        $after = $request->input('after');
        $after = is_numeric($after) ? max(0, (int) $after) : 0;

        // Reading the poll is reading the messages, so the sender gets a receipt.
        $this->chat->markRead($thread, $this->role());

        $rows = ApplicationChatMessage::query()
            ->where('ConversationId', (int) $thread->id)
            ->when($after > 0, fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->limit(self::POLL_LIMIT)
            ->get();

        // The cursor for read receipts is the client's previous poll, so a message
        // only flickers to "Read" once, never on every tick.
        $since = $request->input('since');
        $since = is_numeric($since) && (int) $since > 0
            ? Carbon::createFromTimestamp((int) $since)
            : null;

        return response()->json([
            'success' => true,
            'messages' => $rows->map(fn (ApplicationChatMessage $m): array => $this->presentMessage($m))->all(),
            'read_ids' => $this->chat->readReceipts($thread, $this->role(), $since),
            'unread' => array_sum($this->unreadMap($actorId)),
            'server_time' => now()->timestamp,
        ] + $gate);
    }

    /** One page of older messages, newest-first on the way in, reading order out. */
    public function history(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        $actorId = $this->actorId();

        if (!ApplicationConversation::tableIsReady()) {
            return response()->json(['success' => false, 'message' => 'Messaging needs its database tables first.'], 503);
        }

        $thread = $this->thread($conversation, $actorId);

        $before = $request->input('before');
        $before = is_numeric($before) ? max(0, (int) $before) : null;

        $page = $this->chat->olderMessages($thread, $before);

        return response()->json([
            'success' => true,
            'messages' => $page['messages']
                ->map(fn (ApplicationChatMessage $m): array => $this->presentMessage($m))
                ->all(),
            'has_more' => $page['has_more'],
        ]);
    }

    /**
     * Store one message. The sender id and role come from the guard, so nothing in
     * the request body can claim to be the other participant.
     */
    public function send(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        $actorId = $this->actorId();

        if (!ApplicationConversation::tableIsReady()) {
            return $this->failed($request, 'Messaging needs its database tables before messages can be sent.', 503);
        }

        $thread = $this->thread($conversation, $actorId);
        $gate = $this->chat->gate($thread);

        if (!$gate['can_send']) {
            return $this->failed($request, $gate['reason'] ?? 'Messaging is closed for this application.', 403);
        }

        $data = $this->validated($request, $this->chat->rules());

        if ($data instanceof JsonResponse || $data instanceof RedirectResponse) {
            return $data;
        }

        $message = $this->chat->cleanMessage($data['message'] ?? null);

        if ($message === '') {
            return $this->invalid($request, 'message', 'Write a message before sending.');
        }

        if (mb_strlen($message) > ApplicationChatMessage::MAX_MESSAGE) {
            return $this->invalid(
                $request,
                'message',
                'Keep messages under '.ApplicationChatMessage::MAX_MESSAGE.' characters.',
            );
        }

        $stored = $this->chat->send($thread, $this->role(), $actorId, $message);

        app(ActivityLogger::class)->record(
            'application_chat_message_sent',
            'application_chat_message',
            (int) $stored->id,
            $thread->application?->JobId !== null ? (int) $thread->application->JobId : null,
            ['application_id' => (int) $thread->ApplicationId, 'conversation_id' => (int) $thread->id],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Message sent.',
                'sent' => $this->presentMessage($stored),
                'thread' => $this->presentThread($thread->refresh(), $actorId),
            ]);
        }

        return back()->with('success', 'Message sent.');
    }

    /** Delete-free view of a thread for the client. */
    private function presentMessage(ApplicationChatMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'body' => (string) $message->message,
            'sender_role' => (string) $message->SenderRole,
            'sender_name' => $message->senderName(),
            'mine' => (string) $message->SenderRole === $this->role(),
            'sent_at' => $message->created_at?->toIso8601String(),
            'sent_label' => $message->created_at?->format('M j, Y g:i A'),
            'read_at' => $message->read_at?->toIso8601String(),
            'read_label' => $message->read_at?->format('M j, Y'),
        ];
    }

    /** The list row the client needs to keep in step after a send. */
    private function presentThread(ApplicationConversation $thread, int $actorId): array
    {
        $preview = $thread->lastMessage;

        return [
            'id' => (int) $thread->id,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
            'preview' => $preview?->preview() ?? '',
            'preview_role' => $preview?->SenderRole,
            'unread' => $this->unreadMap($actorId)[(int) $thread->id] ?? 0,
        ];
    }

    /**
     * Look the thread up through the actor's own column. Another participant's id is
     * not found, which is the answer the project already gives for foreign rows:
     * employers get a 403, seekers a 404.
     */
    protected function thread(int $conversationId, int $actorId): ApplicationConversation
    {
        $thread = ApplicationConversation::query()
            ->with(['application.job', 'jobSeeker', 'employer'])
            ->where('id', $conversationId)
            ->where($this->actorColumn(), $actorId)
            ->first();

        if ($thread === null) {
            $this->deny();
        }

        return $this->chat->syncStatus($thread, $thread->application);
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    protected function inboxQuery(int $actorId)
    {
        return ApplicationConversation::query()
            ->with(['application.job', 'jobSeeker', 'employer', 'lastMessage'])
            ->where($this->actorColumn(), $actorId)
            // Threads with traffic first, then by the newest message.
            ->orderByRaw('case when application_conversations.last_message_at is null then 0 else 1 end desc')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /** @return array<int, int> */
    protected function unreadMap(int $actorId): array
    {
        return $this->role() === ApplicationChatMessage::ROLE_EMPLOYER
            ? ApplicationConversation::unreadMapForEmployer($actorId)
            : ApplicationConversation::unreadMapForJobSeeker($actorId);
    }

    protected function actorColumn(): string
    {
        return $this->role() === ApplicationChatMessage::ROLE_EMPLOYER ? 'EmployerId' : 'JobSeekId';
    }

    protected function route(string $action, array $params = []): string
    {
        return route($this->role().'.messages.'.$action, $params);
    }

    protected function actorId(): int
    {
        $user = Auth::guard($this->role())->user();

        if (!$user) {
            $this->deny();
        }

        $id = $this->role() === ApplicationChatMessage::ROLE_EMPLOYER
            ? $user->EmployerId
            : $user->JobSeekId;

        return (int) $id;
    }

    private function deny(): void
    {
        if ($this->role() === ApplicationChatMessage::ROLE_EMPLOYER) {
            abort(403, 'This conversation belongs to another employer.');
        }

        abort(404);
    }

    /**
     * @param  array<string, mixed>  $unread
     */
    private function inboxView(
        $threads,
        array $unread,
        bool $ready,
        ?ApplicationConversation $selected = null,
        $messages = null,
        ?array $gate = null,
        bool $hasMore = false,
    ): View {
        $actorId = $this->actorId();

        return view($this->role().'.messages.index', [
            'threads' => $threads,
            'unread' => $unread,
            'unreadTotal' => array_sum($unread),
            'ready' => $ready,
            'selected' => $selected,
            'messages' => $messages ?? collect(),
            'gate' => $gate ?? ['state' => 'list', 'can_send' => false, 'reason' => null],
            'hasMore' => $hasMore,
            'role' => $this->role(),
            'actorName' => $this->actorName($actorId),
        ]);
    }

    private function actorName(int $actorId): string
    {
        if ($this->role() === ApplicationChatMessage::ROLE_EMPLOYER) {
            $name = Auth::guard('employer')->user()?->CompanyName;
        } else {
            $name = Auth::guard('jobseeker')->user()?->JobSeekerName;
        }

        return is_string($name) && $name !== '' ? $name : ($this->role() === ApplicationChatMessage::ROLE_EMPLOYER ? 'Your company' : 'You');
    }

    /**
     * Validate without leaning on the exception handler: under the web guard a failed
     * validate() redirects, and an AJAX caller needs a JSON 422 instead.
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

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function failed(Request $request, string $message, int $status = 422, array $errors = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
        }

        return back()->withInput()->with('error', $message);
    }

    /** @param  array<string, string>  $messages */
    private function invalid(Request $request, string $field, string $message): JsonResponse|RedirectResponse
    {
        return $this->failed($request, $message, 422, [$field => [$message]]);
    }
}
