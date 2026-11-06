<?php

namespace Tests\Unit;

use App\Models\Application;
use App\Models\ApplicationFollowUpMessage;
use App\Models\Job;
use App\Models\JobSeekerApplicationFollowUp;
use App\Models\Log;
use App\Services\ApplicationFollowUpService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The coach rules, measured against a frozen clock so the day the suite runs
 * cannot change a band. Nothing here touches the database.
 */
class ApplicationFollowUpServiceTest extends TestCase
{
    private ApplicationFollowUpService $coach;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = new ApplicationFollowUpService();
        $this->now = Carbon::parse('2026-11-06 12:00:00');
    }

    // ─────────────────────── age bands ───────────────────────

    public function test_a_two_day_old_application_is_not_asked_to_follow_up(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 2));

        $this->assertSame('no_followup', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
        $this->assertFalse($assessment['filters']['suggested']);
        $this->assertSame(2, $assessment['age_days']);
        $this->assertStringContainsString('Give the employer more time', $assessment['recommendation']['reason']);
    }

    public function test_a_five_day_old_application_is_still_told_to_keep_waiting(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 5));

        $this->assertSame('waiting', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
    }

    public function test_a_seven_day_old_application_is_told_to_consider_following_up(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 7));

        $this->assertSame('consider', $assessment['recommendation']['key']);
        $this->assertTrue($assessment['recommendation']['suggested']);
        $this->assertTrue($assessment['filters']['suggested']);
        $this->assertStringContainsString('awaiting a response for 7 days', $assessment['recommendation']['reason']);
        $this->assertSame('status_inquiry', $assessment['recommendation']['template']);
    }

    public function test_a_fourteen_day_old_application_is_given_a_recommended_follow_up(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 14));

        $this->assertSame('recommended', $assessment['recommendation']['key']);
        $this->assertTrue($assessment['recommendation']['suggested']);
    }

    public function test_a_thirty_day_old_application_is_offered_a_final_follow_up(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 31));

        $this->assertSame('final', $assessment['recommendation']['key']);
        $this->assertSame('final_follow_up', $assessment['recommendation']['template']);
        $this->assertSame('inactive', $assessment['category']['key']);
    }

    // ─────────────────────── exclusions ───────────────────────

    public function test_a_rejected_application_never_receives_a_follow_up_recommendation(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 45, status: 'Rejected'));

        $this->assertSame('closed_rejected', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
        $this->assertSame('rejected', $assessment['status_key']);
        $this->assertSame('closed', $assessment['category']['key']);
        $this->assertFalse($assessment['filters']['attention']);
    }

    public function test_an_accepted_application_never_receives_a_follow_up_recommendation(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 20, status: 'Accepted'));

        $this->assertSame('closed_accepted', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
        $this->assertFalse($assessment['filters']['awaiting']);
    }

    public function test_a_withdrawn_application_is_treated_as_closed(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 20, status: 'Withdrawn'));

        $this->assertSame('withdrawn', $assessment['status_key']);
        $this->assertSame('closed_withdrawn', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
    }

    public function test_a_declined_application_groups_with_the_rejected_ones(): void
    {
        $this->assertSame('rejected', $this->coach->statusKey('Declined'));
        $this->assertSame('accepted', $this->coach->statusKey('Approved'));
        $this->assertSame('awaiting', $this->coach->statusKey('Pending'));
        $this->assertSame('interview', $this->coach->statusKey('Interview Scheduled'));
    }

    // ─────────────────────── not repeating itself ───────────────────────

    public function test_a_follow_up_recorded_this_week_suppresses_the_next_recommendation(): void
    {
        $assessment = $this->assess(
            $this->application(submittedDaysAgo: 20),
            activities: [$this->activity(JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT, daysAgo: 3)],
        );

        $this->assertSame('awaiting_reply', $assessment['recommendation']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
        $this->assertStringContainsString('You followed up 3 days ago', $assessment['recommendation']['headline']);
    }

    public function test_an_old_follow_up_does_not_suppress_the_recommendation(): void
    {
        $assessment = $this->assess(
            $this->application(submittedDaysAgo: 20),
            activities: [$this->activity(JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT, daysAgo: 12)],
        );

        $this->assertSame('recommended', $assessment['recommendation']['key']);
    }

    public function test_a_recorded_expected_response_date_holds_the_recommendation_back(): void
    {
        $assessment = $this->assess(
            $this->application(submittedDaysAgo: 20),
            activities: [$this->activity(JobSeekerApplicationFollowUp::TYPE_EXPECTED_RESPONSE, daysAgo: -4)],
        );

        $this->assertSame('awaiting_reply', $assessment['recommendation']['key']);
        $this->assertStringContainsString('expecting a response by', $assessment['recommendation']['headline']);
    }

    // ─────────────────────── status changes ───────────────────────

    public function test_a_fresh_status_change_is_reported_instead_of_a_follow_up(): void
    {
        $assessment = $this->assess(
            $this->application(submittedDaysAgo: 10),
            events: [$this->log('application_status_changed', 1, ['from' => 'Pending', 'to' => 'Accepted'])],
        );

        $this->assertSame('review_update', $assessment['recommendation']['key']);
        $this->assertStringContainsString('Review the latest update', $assessment['recommendation']['reason']);
    }

    public function test_a_status_update_moves_the_waiting_clock_forward(): void
    {
        $withUpdate = $this->assess(
            $this->application(submittedDaysAgo: 30),
            events: [$this->log('application_status_changed', 4, ['from' => 'Pending', 'to' => 'Pending'])],
        );

        $this->assertSame(4, $withUpdate['waiting_days']);
        $this->assertStringContainsString('status update on', $withUpdate['waiting_source']);
        $this->assertSame('waiting', $withUpdate['recommendation']['key']);
        $this->assertSame('Status changed to Pending', $withUpdate['last_update']['label']);
        $this->assertSame(4, $withUpdate['last_update']['days_ago']);
    }

    // ─────────────────────── stale detection ───────────────────────

    public function test_the_stale_buckets_use_recorded_timestamps_not_assumptions(): void
    {
        $this->assertSame('recent', $this->assess($this->application(submittedDaysAgo: 1))['category']['key']);
        $this->assertSame('waiting', $this->assess($this->application(submittedDaysAgo: 5))['category']['key']);
        $this->assertSame('follow_up', $this->assess($this->application(submittedDaysAgo: 15))['category']['key']);
        $this->assertSame('inactive', $this->assess($this->application(submittedDaysAgo: 40))['category']['key']);
    }

    public function test_an_application_without_a_submission_date_cannot_be_timed(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: null));

        $this->assertNull($assessment['waiting_days']);
        $this->assertSame('unknown_date', $assessment['recommendation']['key']);
        $this->assertSame('unknown', $assessment['category']['key']);
        $this->assertFalse($assessment['recommendation']['suggested']);
    }

    // ─────────────────────── timeline ───────────────────────

    public function test_the_timeline_shows_the_submission_even_when_no_log_row_exists(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 9));

        $this->assertCount(1, $assessment['events']);
        $this->assertSame('Application submitted', $assessment['events'][0]['label']);
        $this->assertSame('Application record', $assessment['events'][0]['source']);
    }

    public function test_the_timeline_never_invents_a_review_or_shortlist_stage(): void
    {
        $events = $this->coach->officialEvents(
            [$this->log('application_submitted', 20), $this->log('application_status_changed', 6, ['from' => 'Pending', 'to' => 'Rejected'])],
            $this->date(daysAgo: 20),
        );

        $labels = array_column($events, 'label');

        $this->assertCount(2, $events);
        $this->assertContains('Application submitted', $labels);
        $this->assertContains('Status changed to Rejected', $labels);
        $this->assertNotContains('Application reviewed', $labels);
        $this->assertNotContains('Shortlisted', $labels);
    }

    public function test_unrelated_activity_log_actions_are_left_out_of_the_timeline(): void
    {
        $events = $this->coach->officialEvents(
            [$this->log('login_succeeded', daysAgo: 2), $this->log('job_approved', daysAgo: 1)],
            $this->date(daysAgo: 5),
        );

        $this->assertCount(1, $events);
        $this->assertSame('Application submitted', $events[0]['label']);
    }

    // ─────────────────────── message drafts ───────────────────────

    public function test_the_status_inquiry_draft_uses_the_real_application_information(): void
    {
        $draft = $this->coach->message('status_inquiry', [
            'candidate_name' => 'Abhinay Sharma',
            'job_title' => 'Software Developer',
            'company' => 'Northwind Technologies',
            'applied_on' => 'November 1, 2026',
            'waiting_days' => 7,
        ]);

        $this->assertStringContainsString('Software Developer', $draft['subject']);
        $this->assertStringContainsString('Dear Northwind Technologies Hiring Team,', $draft['body']);
        $this->assertStringContainsString('I submitted my application on November 1, 2026.', $draft['body']);
        $this->assertStringContainsString('awaiting a response for 7 days', $draft['body']);
        $this->assertStringContainsString('Abhinay Sharma', $draft['body']);
        $this->assertNull($draft['note']);
    }

    public function test_a_missing_company_name_is_written_around_instead_of_invented(): void
    {
        $draft = $this->coach->message('status_inquiry', [
            'candidate_name' => 'Abhinay Sharma',
            'job_title' => 'Data Analyst',
            'company' => null,
            'applied_on' => null,
        ]);

        $this->assertStringContainsString('Dear Hiring Team,', $draft['body']);
        $this->assertStringNotContainsString('[Company]', $draft['body']);
        $this->assertStringNotContainsString('null', $draft['body']);
        $this->assertStringNotContainsString('I submitted my application on', $draft['body']);
        $this->assertStringContainsString('the company name', $draft['note']);
        $this->assertStringContainsString('the application date', $draft['note']);
    }

    public function test_an_interview_date_is_only_used_when_one_was_supplied(): void
    {
        $without = $this->coach->message('post_interview', [
            'candidate_name' => 'Abhinay',
            'job_title' => 'Analyst',
            'company' => 'Northwind',
            'interview_on' => null,
        ]);

        $with = $this->coach->message('thank_you', [
            'candidate_name' => 'Abhinay',
            'job_title' => 'Analyst',
            'company' => 'Northwind',
            'interview_on' => 'November 3, 2026',
        ]);

        $this->assertStringContainsString('ask about the next steps', $without['body']);
        $this->assertDoesNotMatchRegularExpression('/\b(19|20)\d\d\b/', $without['body']);

        $this->assertStringContainsString('Our conversation on November 3, 2026', $with['body']);
        $this->assertStringContainsString('Thank you for the Analyst interview', $with['subject']);
    }

    public function test_each_scenario_has_its_own_draft(): void
    {
        $context = ['candidate_name' => 'Abhinay', 'job_title' => 'Analyst', 'company' => 'Northwind'];

        $bodies = array_map(
            fn (string $template): string => $this->coach->message($template, $context)['body'],
            array_keys(ApplicationFollowUpService::TEMPLATES),
        );

        $this->assertCount(5, $bodies);
        $this->assertSame(count($bodies), count(array_unique($bodies)));
    }

    public function test_a_rejected_candidate_can_ask_for_feedback(): void
    {
        $draft = $this->coach->message('feedback_request', [
            'candidate_name' => 'Abhinay',
            'job_title' => 'Analyst',
            'company' => 'Northwind',
        ]);

        $this->assertStringContainsString('Feedback request', $draft['subject']);
        $this->assertStringContainsString('grateful for any feedback', $draft['body']);
    }

    public function test_an_unknown_template_falls_back_to_the_status_inquiry(): void
    {
        $draft = $this->coach->message('not_a_real_template', ['candidate_name' => 'Abhinay', 'job_title' => 'Analyst']);

        $this->assertStringContainsString('Follow-up on my Analyst application', $draft['subject']);
    }

    // ─────────────────────── interview preparation ───────────────────────

    public function test_preparation_content_only_offers_itself_at_an_interview_stage(): void
    {
        $pending = $this->assess($this->application(submittedDaysAgo: 3));
        $interview = $this->assess($this->application(submittedDaysAgo: 3, status: 'Interview Scheduled'));

        $this->assertFalse($pending['interview']['available']);
        $this->assertTrue($interview['interview']['available']);
        $this->assertSame('interview_prep', $interview['recommendation']['key']);
        $this->assertNotEmpty($interview['interview']['checklist']);
    }

    public function test_preparation_reuses_the_structured_requirements_from_the_listing(): void
    {
        $assessment = $this->assess($this->application(
            submittedDaysAgo: 2,
            status: 'Shortlisted',
            job: [
                'RequiredSkills' => [
                    ['name' => 'SQL', 'type' => 'mandatory'],
                    ['name' => 'Tableau', 'type' => 'preferred'],
                ],
                'RequiredSoftware' => [['name' => 'Power BI', 'type' => 'mandatory']],
                'Description' => 'Reporting work for the finance team.',
            ],
        ));

        $prep = $assessment['interview'];

        $this->assertSame(['SQL'], $prep['skills']['mandatory']);
        $this->assertSame(['Tableau'], $prep['skills']['preferred']);
        $this->assertSame(['Power BI'], $prep['software']);
        $this->assertStringContainsString('finance team', (string) $prep['description']);
    }

    // ─────────────────────── configuration ───────────────────────

    public function test_a_longer_interval_moves_every_band_with_it(): void
    {
        $withSeven = $this->assess($this->application(submittedDaysAgo: 15));
        $withFourteen = $this->assess($this->application(submittedDaysAgo: 15), interval: 14);

        $this->assertSame('recommended', $withSeven['recommendation']['key']);
        $this->assertSame('consider', $withFourteen['recommendation']['key']);
    }

    public function test_an_unusable_interval_falls_back_to_the_default(): void
    {
        $this->assertSame(7, $this->coach->normaliseInterval(null));
        $this->assertSame(7, $this->coach->normaliseInterval('0'));
        $this->assertSame(7, $this->coach->normaliseInterval(91));
        $this->assertSame(21, $this->coach->normaliseInterval(21));
    }

    public function test_the_statistics_agree_with_the_cards_they_summarise(): void
    {
        $assessments = $this->coach->assessMany(
            collect([
                $this->application(submittedDaysAgo: 1, id: 1),
                $this->application(submittedDaysAgo: 20, id: 2),
                $this->application(submittedDaysAgo: 40, id: 3),
                $this->application(submittedDaysAgo: 5, status: 'Rejected', id: 4),
                $this->application(submittedDaysAgo: 5, status: 'Interview Scheduled', id: 5),
            ]),
            [],
            [],
            7,
            $this->now,
        );

        $statistics = $this->coach->statistics($assessments, 7);

        $this->assertSame(5, $statistics['total']);
        $this->assertSame(3, $statistics['awaiting']);
        $this->assertSame(2, $statistics['suggested']);
        $this->assertSame(1, $statistics['interview']);
        $this->assertSame(2, $statistics['attention']);
    }

    // ─────────────────────── delivered messages ───────────────────────

    public function test_delivered_messages_are_listed_newest_first_with_their_view_state(): void
    {
        $assessment = $this->assess(
            $this->application(submittedDaysAgo: 20),
            messages: [
                $this->delivered(daysAgo: 3, readDaysAgo: 1, subject: 'Following up on my application'),
                $this->delivered(daysAgo: 0, subject: 'One last follow-up'),
            ],
        );

        $this->assertCount(2, $assessment['messages']);
        $this->assertSame('One last follow-up', $assessment['messages'][0]['subject']);
        $this->assertSame(0, $assessment['messages'][0]['days_ago']);
        $this->assertFalse($assessment['messages'][0]['viewed']);
        $this->assertTrue($assessment['messages'][1]['viewed']);
        $this->assertSame('Viewed by the employer on Nov 5, 2026.', $assessment['messages'][1]['label']);
    }

    public function test_an_unopened_message_is_never_reported_as_read(): void
    {
        $assessment = $this->assess($this->application(submittedDaysAgo: 20), messages: [
            $this->delivered(daysAgo: 2, subject: 'Any update?'),
        ]);

        $this->assertSame('Delivered. Not viewed yet.', $assessment['messages'][0]['label']);
        $this->assertNull($assessment['messages'][0]['read_at']);
    }

    public function test_a_draft_that_was_never_delivered_leaves_no_trace(): void
    {
        $this->assertSame([], $this->assess($this->application(submittedDaysAgo: 20))['messages']);
    }

    public function test_a_delivered_message_is_not_mistaken_for_an_employer_reply(): void
    {
        // Reading a message is not answering it, so the waiting clock keeps running
        // from the submission date and the recommendation stays where it was.
        $without = $this->assess($this->application(submittedDaysAgo: 20));
        $withDelivery = $this->assess($this->application(submittedDaysAgo: 20), messages: [
            $this->delivered(daysAgo: 1, readDaysAgo: 0),
        ]);

        $this->assertSame($without['waiting_days'], $withDelivery['waiting_days']);
        $this->assertSame('recommended', $withDelivery['recommendation']['key']);
        $this->assertSame('submitted on Oct 17, 2026', $withDelivery['waiting_source']);
    }

    // ─────────────────────── helpers ───────────────────────

    private function assess(Application $application, array $events = [], array $activities = [], int $interval = 7, array $messages = []): array
    {
        return $this->coach->assess($application, $events, $activities, $interval, $this->now, $messages);
    }

    private function application(?int $submittedDaysAgo, string $status = 'Pending', array $job = [], int $id = 101): Application
    {
        $application = (new Application())->forceFill([
            'ApplicationId' => $id,
            'JobId' => 7,
            'JobSeekId' => 3,
            'Status' => $status,
            'ApplicationDate' => $this->date(daysAgo: $submittedDaysAgo),
        ]);

        $application->setRelation('job', (new Job())->forceFill($job + [
            'JobId' => 7,
            'JobTitle' => 'Software Developer',
            'CompanyName' => 'Northwind Technologies',
        ]));

        return $application;
    }

    private function activity(string $type, int $daysAgo, ?string $note = null): JobSeekerApplicationFollowUp
    {
        return (new JobSeekerApplicationFollowUp())->setRawAttributes([
            'id' => 1,
            'activity_type' => $type,
            'activity_date' => $this->date(daysAgo: $daysAgo),
            'note' => $note,
        ], true);
    }

    private function delivered(int $daysAgo, ?int $readDaysAgo = null, string $subject = 'Follow-up on my application'): ApplicationFollowUpMessage
    {
        return (new ApplicationFollowUpMessage())->setRawAttributes([
            'id' => $daysAgo + 1,
            'subject' => $subject,
            'body' => 'Dear Northwind Technologies Hiring Team,',
            'template' => 'status_inquiry',
            'sent_at' => $this->date(daysAgo: $daysAgo),
            'read_at' => $this->date(daysAgo: $readDaysAgo),
        ], true);
    }

    private function log(string $action, int $daysAgo, array $metadata = []): Log
    {
        return (new Log())->forceFill([
            'action' => $action,
            'cdate' => $this->date(daysAgo: $daysAgo),
            'target_type' => 'application',
            'target_id' => 101,
            'metadata' => $metadata === [] ? null : json_encode($metadata),
        ]);
    }

    private function date(?int $daysAgo): ?Carbon
    {
        return $daysAgo === null ? null : $this->now->copy()->subDays($daysAgo);
    }
}
