<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\ApplicationFollowUpMessage;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The employer's side of the Application Follow-up Coach.
 *
 * A job seeker can deliver a follow-up message to an application that belongs to
 * this employer; this controller is where those messages are read. Listing is
 * filtered by the employer's own id, so another employer's applicants are never
 * reachable, and the only thing an employer may do here is record that they looked
 * at it. Nothing is written to the application row, its status, or the seeker's
 * private tracker notes.
 */
class FollowUpMessageController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'view' => ['nullable', 'in:all,new'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $view = (string) ($filters['view'] ?? 'all');
        $ready = ApplicationFollowUpMessage::tableIsReady();
        $total = 0;
        $unread = 0;
        $messages = new LengthAwarePaginator([], 0, self::PER_PAGE);

        if ($ready) {
            $query = ApplicationFollowUpMessage::query()
                ->with(['application.job', 'jobSeeker'])
                ->where('EmployerId', (int) $employer->EmployerId);

            $total = (clone $query)->count();
            $unread = (clone $query)->whereNull('read_at')->count();

            if ($view === 'new') {
                $query->whereNull('read_at');
            }

            $messages = $query->orderByDesc('sent_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString();
        }

        return view('employer.follow-up-messages.index', [
            'messages' => $messages,
            'total' => $total,
            'unread' => $unread,
            'view' => $view,
            'ready' => $ready,
        ]);
    }

    /**
     * Record that this employer opened a message. The first read wins, so the seeker
     * always sees the date the employer actually looked rather than the last time
     * somebody pressed the button again.
     */
    public function seen(Request $request, ApplicationFollowUpMessage $message): RedirectResponse
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        if ((int) $message->EmployerId !== (int) $employer->EmployerId) {
            abort(403, 'This message belongs to another employer.');
        }

        $wasUnread = $message->read_at === null;
        $viewedOn = $wasUnread ? now() : $message->read_at;

        if ($wasUnread) {
            $message->update(['read_at' => $viewedOn]);

            app(ActivityLogger::class)->record(
                'follow_up_message_read',
                'follow_up_message',
                (int) $message->id,
                $message->jobId(),
                ['application_id' => (int) $message->ApplicationId],
            );
        }

        return back()->with('success', $wasUnread
            ? 'Marked as viewed. The applicant can now see that you read it.'
            : 'Already marked as viewed on '.$viewedOn->format('M j, Y').'.');
    }
}
