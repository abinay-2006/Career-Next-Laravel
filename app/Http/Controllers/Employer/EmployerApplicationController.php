<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\ApplicationFollowUpMessage;
use App\Models\Job;
use App\Support\PrivateUpload;
use App\Services\ActivityLogger;
use App\Services\ApplicationChatService;

class EmployerApplicationController extends Controller
{
    /**
     * Display applications for jobs posted by the logged-in employer.
     */
    public function index(Request $request)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        // Get only jobs posted by this employer.
        $jobs = Job::where('EmployerId', $employer->EmployerId)
            ->orderByDesc('JobId')
            ->get();

        $selectedJobId = $request->query('cmbTitle');

        $selectedJob = null;
        $applications = collect();

        if ($selectedJobId !== null && $selectedJobId !== '') {
            abort_unless(is_scalar($selectedJobId) && ctype_digit((string) $selectedJobId), 404);

            // Resolve selections only within this employer's jobs.
            $selectedJob = $jobs->firstWhere('JobId', (int) $selectedJobId);
            abort_unless($selectedJob, 404);

            $applications = Application::with('jobSeeker')
                ->where('JobId', $selectedJob->JobId)
                ->get();
        }

        return view('employer.applications.index', compact(
            'jobs',
            'applications',
            'selectedJob',
            'selectedJobId'
        ));
    }

    /**
     * Display a single application.
     */
    public function show(Application $application, ApplicationChatService $chat)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $application->load(['job', 'jobSeeker']);

        // Make sure the application belongs to a job
        // posted by this employer.
        if (
            !$application->job ||
            (int) $application->job->EmployerId !== (int) $employer->EmployerId
        ) {
            abort(403, 'Unauthorized');
        }

        abort_unless($application->jobSeeker, 404);

        $resumeDocument = $application->jobSeeker->documents()
            ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
            ->first();

        // Follow-up messages the applicant delivered for this application. Filtered on
        // both the application and this employer, so only what was sent here is shown.
        $followUpMessages = ApplicationFollowUpMessage::tableIsReady()
            ? ApplicationFollowUpMessage::query()
                ->where('ApplicationId', (int) $application->ApplicationId)
                ->where('EmployerId', (int) $employer->EmployerId)
                ->orderByDesc('sent_at')
                ->get()
            : collect();

        // The chat thread this application owns. Approval opens it; any other status
        // leaves it unstarted, so the button below only ever appears on an approved
        // applicant and the decision buttons keep being the only way to change Status.
        $chatThread = $chat->openFor($application);
        $chatOpen = $chatThread !== null
            && $chat->stateFor($application->Status) === ApplicationChatService::STATE_OPEN;

        return view('employer.applications.show', compact(
            'application',
            'resumeDocument',
            'followUpMessages',
            'chatThread',
            'chatOpen'
        ));
    }

    public function viewResume(Application $application)
    {
        $employer = Auth::guard('employer')->user();
        $application->load(['job', 'jobSeeker']);

        if (!$employer || !$application->job || (int) $application->job->EmployerId !== (int) $employer->EmployerId || !$application->jobSeeker) {
            abort(403);
        }

        $resume = $application->jobSeeker->documents()
            ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
            ->first();
        $path = $resume?->FileName ?: $application->jobSeeker->Resume;
        if (!$path) {
            abort(404);
        }

        return PrivateUpload::response((string) $path, $resume?->OriginalFileName ?: basename((string) $path), ['upload', 'uploads/resumes', 'uploads/documents']);
    }

    /**
     * Update an application status.
     */
    public function update(Request $request, Application $application, ApplicationChatService $chat)
    {
        $request->validate([
            'Status' => 'required|in:Pending,Accepted,Rejected',
            'Description' => 'nullable|string|max:200',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $application->load('job');

        // Ensure this application belongs to the logged-in employer.
        if (
            !$application->job ||
            (int) $application->job->EmployerId !== (int) $employer->EmployerId
        ) {
            abort(403, 'Unauthorized');
        }

        $previousStatus = $application->Status;
        $application->update([
            'Status' => $request->Status,
            'Description' => $request->Description,
        ]);
        app(ActivityLogger::class)->record('application_status_changed', 'application', $application->ApplicationId, $application->JobId, ['from' => $previousStatus, 'to' => $application->Status]);

        // Approval opens the thread straight away, so the employer does not have to
        // visit the Messages page first. Only Status itself was changed above.
        $chat->openFor($application);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Application updated successfully.',
                'status' => $application->Status,
            ]);
        }

        return redirect()
            ->route(
                'employer.applications.show',
                $application->ApplicationId
            )
            ->with('success', 'Application updated successfully.');
    }
}
