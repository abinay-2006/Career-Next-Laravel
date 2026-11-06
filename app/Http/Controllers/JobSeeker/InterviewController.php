<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\ApplicationInterviewController;
use App\Models\ApplicationInterview;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The applicant's side of interview scheduling.
 *
 * Three answers and nothing else: confirm, decline, or ask for another time. A seeker
 * cannot write a date, a meeting link, a location or an instruction, and cannot cancel
 * or complete an interview, because those methods are not on this class at all - they
 * live on the employer's controller. What is asked for here is only ever recorded as a
 * request; the interview itself keeps the time the employer chose until the employer
 * moves it, which the last method checks for itself on every reply.
 *
 * Ownership runs through the application the interview belongs to, so a hand-edited
 * interview id in the URL answers 404 rather than describing someone else's
 * interview.
 */
class InterviewController extends ApplicationInterviewController
{
    protected function role(): string
    {
        return 'jobseeker';
    }

    /** Accept the invitation exactly as the employer scheduled it. */
    public function confirm(Request $request, int $interview): Response
    {
        $row = $this->answerable($request, $interview, 'can_confirm');

        if ($row instanceof Response) {
            return $row;
        }

        $updated = $this->interviews->respond($row, ApplicationInterview::STATUS_CONFIRMED);

        return $this->answered(
            $request,
            $updated,
            'interview_confirmed',
            'Attendance confirmed. The employer can see your answer.'
        );
    }

    /** Turn the invitation down. The round stays on the card as declined. */
    public function decline(Request $request, int $interview): Response
    {
        $row = $this->answerable($request, $interview, 'can_decline');

        if ($row instanceof Response) {
            return $row;
        }

        $updated = $this->interviews->respond($row, ApplicationInterview::STATUS_DECLINED);

        return $this->answered(
            $request,
            $updated,
            'interview_declined',
            'Invitation declined. The employer can still offer you another time.'
        );
    }

    /**
     * Ask for another time, with a reason and a preference. Nothing about the arranged
     * interview changes: the employer sees the request and decides.
     */
    public function requestReschedule(Request $request, int $interview): Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $row = $this->ownInterview($request, $interview);

        if ($row instanceof Response) {
            return $row;
        }

        if ($denied = $this->seekerMay($request, $row, 'can_request')) {
            return $denied;
        }

        $data = $this->validated($request, $this->interviews->requestRules());

        if ($data instanceof Response) {
            return $data;
        }

        // The preference is typed in the interview's own zone unless the form picked one,
        // so the two people are always reading the same clock.
        $zone = filled($data['preferred_timezone'] ?? null)
            ? (string) $data['preferred_timezone']
            : (string) $row->timezone;

        $errors = $this->timeErrors(
            ['scheduled_at' => $data['preferred_at'], 'timezone' => $zone],
            'preferred_at'
        );

        if ($errors !== []) {
            return $this->failed($request, 'Please correct the highlighted fields.', 422, $errors);
        }

        $scheduledBefore = $row->scheduled_at;
        $updated = $this->interviews->respond($row, ApplicationInterview::STATUS_RESCHEDULE_REQUESTED, $data);

        // Asking must never move the interview. A mismatch here is a regression in the
        // service, so it is reported instead of being quietly stored.
        if (! $this->sameInstant($scheduledBefore, $updated->scheduled_at)) {
            report(new \RuntimeException(
                'A reschedule request moved scheduled_at on interview '.$updated->id
            ));
        }

        app(ActivityLogger::class)->record(
            'interview_reschedule_requested',
            'application_interview',
            (int) $updated->id,
            $updated->jobId(),
            [
                'application_id' => (int) $updated->ApplicationId,
                'round' => (int) $updated->round,
                'preferred_at' => $updated->reschedule_preferred_at?->toIso8601String(),
            ],
        );

        return $this->done(
            $request,
            $updated,
            'Request sent. Your interview stays at '.$updated->scheduledLabel().' until the employer proposes a new time.'
        );
    }

    /**
     * The shared front door for confirm and decline: table, ownership, then the answer
     * the state machine still allows.
     *
     * @return ApplicationInterview|Response
     */
    private function answerable(Request $request, int $interview, string $capability): ApplicationInterview|Response
    {
        if ($blocked = $this->ready($request)) {
            return $blocked;
        }

        $row = $this->ownInterview($request, $interview);

        if ($row instanceof Response) {
            return $row;
        }

        return $this->seekerMay($request, $row, $capability) ?? $row;
    }
}
