<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\ApplicationInterviewController;
use App\Models\Application;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The employer's side of interview scheduling.
 *
 * These four powers belong to the employer alone: offering a time, offering a new one
 * after the applicant asks, cancelling, and recording that the interview happened. The
 * applicant is never able to reach them, because they exist on this class and nowhere
 * else - the seeker's controller has only its own three answers.
 *
 * Ownership is settled from the job on every request, so a hand-edited application or
 * interview id in the URL only ever buys a refusal. Interviews do not touch the
 * application decision: accepting an applicant is what makes scheduling possible, and
 * revoking that acceptance is what stops it again.
 */
class InterviewController extends ApplicationInterviewController
{
    protected function role(): string
    {
        return 'employer';
    }

    /**
     * Offer an interview for the next round. Only an accepted applicant can be offered
     * one, because an invitation promises a conversation that has not been agreed yet.
     */
    public function store(Request $request, Application $application): Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $this->ownApplication($application);

        $eligibility = $this->interviews->eligibility($application);

        if (! $eligibility['can_schedule']) {
            return $this->failed($request, (string) $eligibility['reason']);
        }

        $data = $this->validated($request, $this->scheduleRulesFor($request));

        if ($data instanceof Response) {
            return $data;
        }

        if ($errors = $this->timeErrors($data)) {
            return $this->failed($request, 'Please correct the highlighted fields.', 422, $errors);
        }

        $interview = $this->interviews->propose($application, $this->actorId(), $data);

        app(ActivityLogger::class)->record(
            'interview_proposed',
            'application_interview',
            (int) $interview->id,
            $application->JobId !== null ? (int) $application->JobId : null,
            [
                'application_id' => (int) $application->ApplicationId,
                'round' => (int) $interview->round,
                'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                'interview_type' => (string) $interview->interview_type,
            ],
        );

        return $this->done(
            $request,
            $interview,
            'Invitation sent. The applicant can confirm, decline or ask for another time.'
        );
    }

    /**
     * Answer a reschedule request with a new time. The same invitation is revised, so
     * what the applicant then confirms is the round they have been answering all along,
     * and what they asked for stays on the card.
     */
    public function revise(Request $request, int $interview): Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $row = $this->ownInterview($request, $interview);

        if ($row instanceof Response) {
            return $row;
        }

        if ($denied = $this->employerMay($request, $row, 'can_revise')) {
            return $denied;
        }

        $data = $this->validated($request, $this->scheduleRulesFor($request));

        if ($data instanceof Response) {
            return $data;
        }

        if ($errors = $this->timeErrors($data)) {
            return $this->failed($request, 'Please correct the highlighted fields.', 422, $errors);
        }

        $previousAt = $row->scheduled_at;
        $updated = $this->interviews->revise($row, $data);

        app(ActivityLogger::class)->record(
            'interview_revised',
            'application_interview',
            (int) $updated->id,
            $updated->jobId(),
            [
                'application_id' => (int) $updated->ApplicationId,
                'round' => (int) $updated->round,
                'from' => $previousAt?->toIso8601String(),
                'to' => $updated->scheduled_at?->toIso8601String(),
                'revision_count' => (int) $updated->revision_count,
            ],
        );

        return $this->done($request, $updated, 'Revised invitation sent. The applicant is asked to confirm the new time.');
    }

    /**
     * Cancel without deleting. The round stays visible to the applicant as cancelled,
     * which is what tells them the process stopped rather than vanished.
     */
    public function cancel(Request $request, int $interview): Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $row = $this->ownInterview($request, $interview);

        if ($row instanceof Response) {
            return $row;
        }

        if ($denied = $this->employerMay($request, $row, 'can_cancel')) {
            return $denied;
        }

        $updated = $this->interviews->cancel($row);

        app(ActivityLogger::class)->record(
            'interview_cancelled',
            'application_interview',
            (int) $updated->id,
            $updated->jobId(),
            ['application_id' => (int) $updated->ApplicationId, 'round' => (int) $updated->round],
        );

        return $this->done($request, $updated, 'Interview cancelled. The applicant sees that it was cancelled, not hidden.');
    }

    /** Record that a confirmed interview actually took place. */
    public function complete(Request $request, int $interview): Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $row = $this->ownInterview($request, $interview);

        if ($row instanceof Response) {
            return $row;
        }

        if ($denied = $this->employerMay($request, $row, 'can_complete')) {
            return $denied;
        }

        $updated = $this->interviews->complete($row);

        app(ActivityLogger::class)->record(
            'interview_completed',
            'application_interview',
            (int) $updated->id,
            $updated->jobId(),
            ['application_id' => (int) $updated->ApplicationId, 'round' => (int) $updated->round],
        );

        return $this->done($request, $updated, 'Interview marked as completed.');
    }
}
