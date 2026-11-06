<?php

namespace Tests\Feature;

use App\Models\ApplicationFollowUpMessage;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Delivering a follow-up draft to the employer.
 *
 * Each test signs in as one role only: the identity middleware treats two guards
 * being logged in at once as an inconsistent session, so the other side's rows are
 * seeded directly instead of being created through its endpoints.
 */
class FollowUpMessageDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // The repeat-window and the day counts are both measured in days.
        Carbon::setTestNow('2026-11-06 12:00:00');

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
            $table->string('Email')->nullable();
            $table->string('Mobile')->nullable();
            $table->string('City')->nullable();
            $table->string('Qualification')->nullable();
            $table->string('Gender')->nullable();
            $table->string('BirthDate')->nullable();
            $table->text('Address')->nullable();
            $table->string('Photo')->nullable();
            $table->string('Resume')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
            $table->string('ContactPerson')->nullable();
            $table->string('Email')->nullable();
            $table->string('Mobile')->nullable();
            $table->string('City')->nullable();
            $table->text('Address')->nullable();
        });

        Schema::create('user_master', function (Blueprint $table): void {
            $table->increments('UserId');
            $table->string('UserName');
            $table->string('Password')->nullable();
        });

        Schema::create('account_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_type', 20);
            $table->string('normalized_value', 255);
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id');
            $table->unique(['identifier_type', 'normalized_value']);
            $table->unique(['account_type', 'account_id', 'identifier_type']);
        });

        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocumentId');
            $table->integer('JobSeekId');
            $table->string('DocumentType')->nullable();
            $table->string('FileName')->nullable();
            $table->string('OriginalFileName')->nullable();
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName')->nullable();
            $table->string('JobTitle');
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->default('B.E');
            $table->text('Description')->nullable();
            $table->string('Status')->nullable();
            $table->text('RequiredSkills')->nullable();
            $table->text('RequiredSoftware')->nullable();
        });

        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status');
            $table->text('Description')->nullable();
            $table->dateTime('ApplicationDate')->nullable();
        });

        Schema::create('logs', function (Blueprint $table): void {
            $table->increments('Id');
            $table->integer('JobId')->nullable();
            $table->string('action', 80);
            $table->dateTime('cdate');
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('metadata')->nullable();
        });

        Schema::create('jobseeker_application_follow_ups', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('ApplicationId');
            $table->integer('JobSeekId');
            $table->string('activity_type', 32);
            $table->dateTime('activity_date');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('application_follow_up_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('EmployerId');
            $table->string('template', 32)->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->dateTime('sent_at');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─────────────────────── the seeker pressing send ───────────────────────

    public function test_a_delivered_draft_is_stored_for_the_employer_of_that_application(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'template' => 'status_inquiry',
                'subject' => 'Follow-up on my Reporting Analyst application',
                'body' => "Dear Acme Software Ltd Hiring Team,\n\nI am still very interested.",
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('refresh', true);

        $row = DB::table('application_follow_up_messages')->sole();

        $this->assertSame($applicationId, (int) $row->ApplicationId);
        $this->assertSame($seeker->JobSeekId, (int) $row->JobSeekId);
        $this->assertSame($employerId, (int) $row->EmployerId);
        $this->assertSame('Follow-up on my Reporting Analyst application', $row->subject);
        // The seeker's own wording, line breaks and all - not a regenerated draft.
        $this->assertSame("Dear Acme Software Ltd Hiring Team,\n\nI am still very interested.", $row->body);
        $this->assertSame('2026-11-06 12:00:00', $row->sent_at);
        $this->assertNull($row->read_at);
    }

    public function test_nothing_is_emailed_when_a_message_is_delivered(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer());

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'subject' => 'Quick follow-up',
                'body' => 'Any update on my application would be appreciated.',
            ])
            ->assertOk();

        Mail::assertNothingSent();
    }

    public function test_delivery_also_counts_as_the_follow_up_the_seeker_sent_today(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer(), daysAgo: 20);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'template' => 'status_inquiry',
                'subject' => 'Follow-up on my application',
                'body' => 'I would appreciate any update.',
            ])
            ->assertOk();

        $this->assertSame(
            1,
            DB::table('jobseeker_application_follow_ups')
                ->where('activity_type', 'follow_up_sent')
                ->where('note', 'Delivered to the employer inside Career Next.')
                ->count(),
        );

        // Which is what holds the next suggestion back rather than nagging twice.
        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('You followed up 0 days ago')
            ->assertSee('Another message is not suggested yet');
    }

    public function test_the_card_shows_the_delivered_message_and_says_it_has_not_been_viewed(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $this->message($applicationId, $seeker->JobSeekId, $employerId, [
            'subject' => 'Follow-up on my Reporting Analyst application',
            'sent_at' => '2026-11-05 09:00:00',
        ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Messages sent to this employer')
            ->assertSee('Follow-up on my Reporting Analyst application')
            ->assertSee('Delivered. Not viewed yet.')
            ->assertDontSee('Viewed by the employer');
    }

    public function test_the_card_reports_the_day_the_employer_opened_it(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $this->message($applicationId, $seeker->JobSeekId, $employerId, [
            'subject' => 'One last follow-up',
            'sent_at' => '2026-11-02 09:00:00',
            'read_at' => '2026-11-04 15:30:00',
        ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Viewed by the employer on Nov 4, 2026');
    }

    public function test_a_seeker_cannot_deliver_a_message_on_someone_elses_application(): void
    {
        $mine = $this->seeker('Seeker One');
        $theirs = $this->seeker('Seeker Two');
        $employerId = $this->employer();
        $foreign = $this->application($theirs, $employerId);

        $this->actingAs($mine, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $foreign), [
                'subject' => 'Hello?',
                'body' => 'Please read my application.',
            ])
            ->assertNotFound();

        $this->assertSame(0, DB::table('application_follow_up_messages')->count());
    }

    public function test_a_listing_without_an_employer_account_refuses_delivery(): void
    {
        $seeker = $this->seeker('Priya Raman');
        // A legacy listing whose ownership was never established.
        $applicationId = $this->application($seeker, null);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'subject' => 'Follow-up on my application',
                'body' => 'I would appreciate any update.',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This listing has no employer account that can receive a message, so Career Next cannot deliver it. Copy the draft and send it yourself.']);

        $this->assertSame(0, DB::table('application_follow_up_messages')->count());
    }

    public function test_an_empty_or_oversized_message_is_refused(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), ['subject' => '  ', 'body' => '  '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'body']);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'subject' => str_repeat('A', 201),
                'body' => str_repeat('B', 4001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'body']);

        $this->assertSame(0, DB::table('application_follow_up_messages')->count());
    }

    public function test_the_same_message_is_not_delivered_twice_in_one_day(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $payload = ['subject' => 'Follow-up on my application', 'body' => 'Any update would help.'];

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), $payload)
            ->assertOk();

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'A message was already delivered to this employer on Nov 6, 2026. One follow-up a day keeps the thread useful - try again after that.']);

        $this->assertSame(1, DB::table('application_follow_up_messages')->count());
    }

    public function test_a_message_from_yesterday_does_not_hold_the_next_one_back(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer();
        $applicationId = $this->application($seeker, $employerId);

        $this->message($applicationId, $seeker->JobSeekId, $employerId, ['sent_at' => '2026-11-05 09:00:00']);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), [
                'subject' => 'Following up again',
                'body' => 'Still interested in the role.',
            ])
            ->assertOk();

        $this->assertSame(2, DB::table('application_follow_up_messages')->count());
    }

    public function test_the_send_button_is_on_the_draft_and_only_leaves_the_box_the_seeker_can_see(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer());

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Send to employer')
            ->assertSee(route('jobseeker.followup.send', $applicationId), false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="body"', false);
    }

    // ─────────────────────── the employer reading it ───────────────────────

    public function test_the_employer_sees_the_message_on_the_applicant_page(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);
        $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId, [
            'subject' => 'Follow-up on my Reporting Analyst application',
            'body' => 'I would appreciate any update on the hiring process.',
        ]);

        $this->actingAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->assertSee('Follow-up messages from this applicant')
            ->assertSee('Follow-up on my Reporting Analyst application')
            ->assertSee('I would appreciate any update on the hiring process.')
            ->assertSee('Priya Raman')
            ->assertSee('New');
    }

    public function test_the_employer_never_sees_the_seekers_private_notes(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);

        $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId, ['subject' => 'Follow-up on my application']);
        DB::table('jobseeker_application_follow_ups')->insert([
            'ApplicationId' => $applicationId,
            'JobSeekId' => $seeker->JobSeekId,
            'activity_type' => 'note',
            'activity_date' => '2026-11-01 10:00:00',
            'note' => 'Told nobody that my real salary floor is higher',
        ]);

        $page = $this->actingAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('real salary floor', $page);
    }

    public function test_the_message_page_lists_only_this_employers_messages(): void
    {
        $employer = $this->employerModel();
        $otherEmployer = (int) DB::table('employer_reg')->insertGetId(['CompanyName' => 'Globex Corporation', 'Status' => 'Approved']);

        $mine = $this->application($this->seeker('Priya Raman'), $employer->EmployerId);
        $theirs = $this->application($this->seeker('Sam Iyer'), $otherEmployer, title: 'Intern Position');

        $this->message($mine, 1, $employer->EmployerId, [
            'subject' => 'Follow-up about the Analyst role',
            'sent_at' => '2026-11-05 09:00:00',
        ]);
        $this->message($theirs, 2, $otherEmployer, [
            'subject' => 'A message meant for Globex only',
            'read_at' => '2026-11-05 12:00:00',
        ]);

        $this->actingAs($employer, 'employer')
            ->get(route('employer.followupmessages.index'))
            ->assertOk()
            ->assertSee('Follow-up Messages')
            ->assertSee('Follow-up about the Analyst role')
            ->assertDontSee('A message meant for Globex only');
    }

    public function test_the_new_filter_hides_messages_the_employer_has_already_opened(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);

        $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId, ['subject' => 'Still waiting for an update']);
        $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId, [
            'subject' => 'Already answered this one',
            'read_at' => '2026-11-04 12:00:00',
        ]);

        $this->actingAs($employer, 'employer')
            ->get(route('employer.followupmessages.index', ['view' => 'new']))
            ->assertOk()
            ->assertSee('Still waiting for an update')
            ->assertDontSee('Already answered this one');
    }

    public function test_marking_a_message_as_seen_keeps_the_first_view_date(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);
        $messageId = $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId);

        $this->actingAs($employer, 'employer')
            ->post(route('employer.followupmessages.seen', $messageId))
            ->assertRedirect();

        $firstRead = DB::table('application_follow_up_messages')->where('id', $messageId)->value('read_at');
        $this->assertSame('2026-11-06 12:00:00', $firstRead);

        $this->actingAs($employer, 'employer')
            ->post(route('employer.followupmessages.seen', $messageId))
            ->assertRedirect();

        $this->assertSame($firstRead, DB::table('application_follow_up_messages')->where('id', $messageId)->value('read_at'));
    }

    public function test_marking_a_message_as_seen_does_not_touch_the_application(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId, status: 'Pending');
        $messageId = $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId);

        $this->actingAs($employer, 'employer')
            ->post(route('employer.followupmessages.seen', $messageId))
            ->assertRedirect();

        $this->assertSame('Pending', DB::table('application_master')->where('ApplicationId', $applicationId)->value('Status'));
        $this->assertSame('Marked as viewed. The applicant can now see that you read it.', session('success'));
    }

    public function test_another_employer_cannot_mark_my_message_as_seen(): void
    {
        $employer = $this->employerModel();
        $other = $this->employerModel('Globex Corporation');
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);
        $messageId = $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId);

        $this->actingAs($other, 'employer')
            ->post(route('employer.followupmessages.seen', $messageId))
            ->assertForbidden();

        $this->assertNull(DB::table('application_follow_up_messages')->where('id', $messageId)->value('read_at'));
    }

    public function test_the_sidebar_reaches_the_message_page_and_flags_unread_ones(): void
    {
        $employer = $this->employerModel();
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $employer->EmployerId);
        $this->message($applicationId, $seeker->JobSeekId, $employer->EmployerId);

        $this->assertSame(1, ApplicationFollowUpMessage::unreadForEmployer($employer->EmployerId));

        $this->actingAs($employer, 'employer')
            ->get(route('employer.jobs.index'))
            ->assertOk()
            ->assertSee('Follow-up Messages')
            ->assertSee(route('employer.followupmessages.index'), false);
    }

    public function test_a_job_seeker_cannot_open_the_employer_message_pages(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $this->application($seeker, $this->employer());

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('employer.followupmessages.index'))
            ->assertForbidden();
    }

    public function test_the_guest_is_sent_to_login_from_the_employer_side(): void
    {
        $this->get(route('employer.followupmessages.index'))->assertRedirect(route('login'));
    }

    // ─────────────────────── without the migration ───────────────────────

    public function test_both_sides_still_open_when_the_delivery_table_is_missing(): void
    {
        Schema::dropIfExists('application_follow_up_messages');

        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer());

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Nothing has been delivered for this application');

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.send', $applicationId), ['subject' => 'Hi', 'body' => 'Any update?'])
            ->assertStatus(503);
    }

    public function test_the_employer_page_explains_a_missing_table_instead_of_failing(): void
    {
        Schema::dropIfExists('application_follow_up_messages');

        $employer = $this->employerModel();

        $this->actingAs($employer, 'employer')
            ->get(route('employer.followupmessages.index'))
            ->assertOk()
            ->assertSee('php artisan migrate');
    }

    // ─────────────────────── helpers ───────────────────────

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
            'Email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'City' => 'Chennai',
            'Qualification' => 'B.Tech',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    private function employer(string $company = 'Acme Software Ltd'): int
    {
        return (int) DB::table('employer_reg')->insertGetId([
            'CompanyName' => $company,
            'Email' => 'hiring@'.strtolower(str_replace(' ', '', $company)).'.test',
            'Status' => 'Approved',
        ]);
    }

    private function employerModel(string $company = 'Acme Software Ltd'): Employer
    {
        return Employer::query()->findOrFail($this->employer($company));
    }

    private function application(JobSeeker $seeker, ?int $employerId, string $title = 'Reporting Analyst', string $status = 'Pending', int $daysAgo = 20): int
    {
        $jobId = DB::table('job_master')->insertGetId([
            'EmployerId' => $employerId,
            'CompanyName' => 'Acme Software Ltd',
            'JobTitle' => $title,
            'Vacancy' => 2,
            'MinQualification' => 'B.E',
            'Description' => 'Build reporting dashboards with the existing team.',
            'Status' => 'approved',
        ]);

        return (int) DB::table('application_master')->insertGetId([
            'JobSeekId' => $seeker->JobSeekId,
            'JobId' => $jobId,
            'Status' => $status,
            'Description' => 'Applied with resume.',
            'ApplicationDate' => Carbon::now()->subDays($daysAgo),
        ]);
    }

    /**
     * @param  array<string, string|null>  $overrides
     */
    private function message(int $applicationId, int $seekerId, int $employerId, array $overrides = []): int
    {
        return (int) DB::table('application_follow_up_messages')->insertGetId(array_merge([
            'ApplicationId' => $applicationId,
            'JobSeekId' => $seekerId,
            'EmployerId' => $employerId,
            'template' => 'status_inquiry',
            'subject' => 'Follow-up on my Reporting Analyst application',
            'body' => 'Dear Acme Software Ltd Hiring Team,

I am writing to politely follow up on my application.',
            'sent_at' => '2026-11-06 09:00:00',
            'read_at' => null,
        ], $overrides));
    }
}
