<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationChatMessage;
use App\Models\ApplicationInterview;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Services\ApplicationChatService;
use App\Support\SchemaReady;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Interview scheduling and management on top of an accepted application.
 *
 * An invitation has two participants and four employer powers, so most tests sign in
 * as both of them in one run. The identity middleware treats two guards logged in at
 * once as an inconsistent browser session, which is why every request goes through
 * actAs(): it drops the resolved guards first, exactly as a fresh request would.
 *
 * The sqlite schema below mirrors 2026_11_06_160000_create_application_interviews_table
 * deliberately, including the unique index over (ApplicationId, round, active_marker) -
 * the one rule that has to hold even when two buttons are pressed in the same second.
 *
 * Status is written the way the employer page writes it, and application_master.Status
 * is asserted unchanged throughout, because an interview must never look like a decision.
 */
class ApplicationInterviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every "is this in the future" answer is measured against this clock.
        Carbon::setTestNow('2026-11-06 12:00:00');

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
            $table->string('Email')->nullable();
            $table->string('Mobile')->nullable();
            $table->string('City')->nullable();
            $table->string('Qualification')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
            $table->string('Email')->nullable();
            $table->string('City')->nullable();
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
            $table->integer('SalaryFrom')->nullable();
            $table->integer('SalaryTo')->nullable();
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

        Schema::create('application_conversations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('ApplicationId')->unique('chat_conversations_application_unique');
            $table->integer('EmployerId');
            $table->integer('JobSeekId');
            $table->string('status', 16)->default('active');
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_chat_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('ConversationId');
            $table->integer('SenderId');
            $table->string('SenderRole', 16);
            $table->text('message');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        // The same shape as the interview migration, including the open-invitation index.
        Schema::create('application_interviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('ApplicationId');
            $table->unsignedSmallInteger('round')->default(1);
            $table->dateTime('scheduled_at');
            $table->string('timezone', 64);
            $table->string('interview_type', 16);
            $table->string('meeting_link', 500)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('proposed');
            $table->unsignedTinyInteger('active_marker')->nullable()->default(null);
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('reschedule_requested_at')->nullable();
            $table->string('reschedule_reason', 500)->nullable();
            $table->dateTime('reschedule_preferred_at')->nullable();
            $table->unsignedTinyInteger('revision_count')->default(0);
            $table->dateTime('revised_at')->nullable();
            $table->dateTime('concluded_at')->nullable();
            $table->integer('created_by_employer_id');
            $table->timestamps();
            $table->unique(['ApplicationId', 'round', 'active_marker'], 'interviews_round_open_unique');
            $table->index(['ApplicationId', 'round'], 'interviews_application_round_index');
            $table->index(['created_by_employer_id', 'status'], 'interviews_employer_status_index');
            $table->index(['status', 'scheduled_at'], 'interviews_status_schedule_index');
        });

        SchemaReady::invalidate();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─────────────────── the employer raising an invitation ───────────────────

    public function test_the_employer_schedules_an_interview_for_an_accepted_application(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.store', $applicationId), $this->schedulePayload())
            ->assertRedirect(route('employer.interviews.index'))
            ->assertSessionHas('success', 'Invitation sent. The applicant can confirm, decline or ask for another time.');

        $row = ApplicationInterview::query()->firstOrFail();

        $this->assertSame($applicationId, (int) $row->ApplicationId);
        $this->assertSame(1, (int) $row->round);
        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $row->status);
        $this->assertSame(1, (int) $row->active_marker);
        $this->assertSame((int) $employer->EmployerId, (int) $row->created_by_employer_id);
        $this->assertSame('https://meet.example.com/priya', $row->meeting_link);
        $this->assertNull($row->location);
        $this->assertNull($row->responded_at);
        $this->assertSame('interview_proposed', $this->lastActivity()['action']);

        // The wall clock was typed in Asia/Kolkata, so the instant is 04:30 UTC.
        $this->assertSame('Asia/Kolkata', $row->timezone);
        $this->assertSame('2026-12-05 04:30:00', $row->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('Sat, Dec 5, 2026 · 10:00 AM', $row->scheduledLabel());
    }

    public function test_scheduling_an_interview_never_changes_the_application_status(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->schedule($employer, $applicationId);
        $confirmed = $this->answer($seeker, (int) ApplicationInterview::query()->firstOrFail()->id, 'confirm');

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.complete', $confirmed->id))
            ->assertRedirect(route('employer.interviews.index'));

        $this->assertSame('Accepted', $this->statusOf($applicationId));
        $this->assertSame(ApplicationInterview::STATUS_COMPLETED, $confirmed->refresh()->status);
    }

    public function test_a_pending_application_cannot_be_given_an_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Pending');

        $refused = $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.store', $applicationId), $this->schedulePayload());

        $refused->assertStatus(302);
        $refused->assertSessionHas('error');
        $this->assertStringContainsString(
            'only be scheduled after you accept',
            (string) session('error')
        );

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_a_rejected_application_cannot_be_given_an_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Rejected');

        $this->refuses(
            $this->actAs($employer, 'employer')
                ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload()),
            'was rejected'
        );

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_revoking_the_acceptance_stops_new_invitations_on_the_next_request(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->statusMove($applicationId, 'Rejected');

        $this->refuses(
            $this->actAs($employer, 'employer')
                ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload()),
            'was rejected'
        );

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_an_employer_cannot_schedule_on_another_employers_job(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $owner = $this->employerModel('Northwind Ltd');
        $intruder = $this->employerModel('Contoso Systems');
        $applicationId = $this->application($seeker, (int) $owner->EmployerId, 'Accepted');

        $this->refuses(
            $this->actAs($intruder, 'employer')
                ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload()),
            'another employer',
            403
        );

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_an_employer_cannot_touch_another_employers_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $owner = $this->employerModel('Northwind Ltd');
        $intruder = $this->employerModel('Contoso Systems');
        $applicationId = $this->application($seeker, (int) $owner->EmployerId, 'Accepted');
        $interview = $this->schedule($owner, $applicationId);

        foreach (['revise' => $this->schedulePayload(), 'cancel' => [], 'complete' => []] as $action => $payload) {
            $this->refuses(
                $this->actAs($intruder, 'employer')
                    ->postJson(route('employer.interviews.'.$action, $interview->id), $payload),
                'another employer',
                403
            );
        }

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
        $this->assertSame(0, (int) $interview->revision_count);
    }

    // ───────────────────── the applicant's three answers ─────────────────────

    public function test_the_applicant_confirms_an_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $updated = $this->answer($seeker, (int) $interview->id, 'confirm');

        $this->assertSame(ApplicationInterview::STATUS_CONFIRMED, $updated->status);
        $this->assertNotNull($updated->responded_at);
        $this->assertSame(1, (int) $updated->active_marker);

        // The time the employer chose is the time that was confirmed.
        $this->assertSame('2026-12-05 04:30:00', $updated->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('interview_confirmed', $this->lastActivity()['action']);

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('The applicant confirmed round 1');
    }

    public function test_the_applicant_declines_an_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $updated = $this->answer($seeker, (int) $interview->id, 'decline');

        $this->assertSame(ApplicationInterview::STATUS_DECLINED, $updated->status);
        $this->assertNull($updated->active_marker, 'A declined invitation stops occupying its round.');
        $this->assertNotNull($updated->concluded_at);

        // Declining is not a decision about the application itself.
        $this->assertSame('Accepted', $this->statusOf($applicationId));

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('The applicant declined round 1');
    }

    public function test_an_applicant_asking_for_another_time_does_not_move_the_interview(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);
        $before = $interview->scheduled_at;

        $this->actAs($seeker, 'jobseeker')
            ->post(route('jobseeker.interviews.reschedule', $interview->id), [
                'reason' => 'I have a hospital appointment that morning.',
                'preferred_at' => '2026-12-08T14:00',
                'preferred_timezone' => 'Asia/Kolkata',
                'goto' => 'application',
            ])
            ->assertRedirect(route('jobseeker.applications.show', $applicationId));

        $updated = $interview->refresh();

        $this->assertSame(ApplicationInterview::STATUS_RESCHEDULE_REQUESTED, $updated->status);
        $this->assertSame($before->getTimestamp(), $updated->scheduled_at->getTimestamp());
        $this->assertSame('I have a hospital appointment that morning.', $updated->reschedule_reason);
        $this->assertNotNull($updated->reschedule_requested_at);
        $this->assertSame('2026-12-08 08:30:00', $updated->reschedule_preferred_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, (int) $updated->active_marker, 'The round stays open while the employer decides.');
        $this->assertSame('interview_reschedule_requested', $this->lastActivity()['action']);

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk()
            ->assertSee('You asked for another time')
            ->assertSee('I have a hospital appointment that morning.');
    }

    public function test_a_reschedule_request_needs_a_useful_reason(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.interviews.reschedule', $interview->id), [
                'reason' => 'too busy',
                'preferred_at' => '2026-12-15T10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
        $this->assertNull($interview->reschedule_reason);
    }

    public function test_a_reschedule_preference_has_to_be_in_the_future(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.interviews.reschedule', $interview->id), [
                'reason' => 'I have a hospital appointment that morning.',
                'preferred_at' => '2026-01-05T10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_at']);

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
    }

    // ──────────────────── the employer's revision and powers ────────────────────

    public function test_the_employer_answers_a_request_with_a_revised_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);
        $requested = $this->requestReschedule($seeker, (int) $interview->id);

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.revise', $requested->id), $this->schedulePayload([
                'scheduled_at' => '2026-12-08T14:00',
                'instructions' => 'Same room, later in the week.',
            ]))
            ->assertRedirect(route('employer.interviews.index'))
            ->assertSessionHas('success', 'Revised invitation sent. The applicant is asked to confirm the new time.');

        $revised = $requested->refresh();

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $revised->status);
        $this->assertSame(1, (int) $revised->round, 'A revision answers the same round, not a new one.');
        $this->assertSame(1, (int) $revised->revision_count);
        $this->assertNotNull($revised->revised_at);
        $this->assertNull($revised->responded_at, 'The revised proposal waits for a fresh answer.');
        $this->assertSame('2026-12-08 08:30:00', $revised->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('Same room, later in the week.', $revised->instructions);

        // What the applicant asked for stays on the record the employer is answering.
        $this->assertSame('I have a hospital appointment that morning.', $revised->reschedule_reason);
        $this->assertNotNull($revised->reschedule_requested_at);

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('Revised 1 time');
    }

    public function test_the_applicant_can_answer_the_revised_invitation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);
        $requested = $this->requestReschedule($seeker, (int) $interview->id);

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.revise', $requested->id), $this->schedulePayload([
                'scheduled_at' => '2026-12-08T14:00',
            ]))
            ->assertRedirect(route('employer.interviews.index'));

        $answered = $this->answer($seeker, (int) $requested->id, 'confirm');

        $this->assertSame(ApplicationInterview::STATUS_CONFIRMED, $answered->status);
        $this->assertSame(1, (int) $answered->revision_count, 'The history of the request survives the answer.');
    }

    public function test_only_a_requested_invitation_can_be_revised(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->refuses(
            $this->actAs($employer, 'employer')
                ->postJson(route('employer.interviews.revise', $interview->id), $this->schedulePayload()),
            'Wait for the applicant to answer'
        );

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
        $this->assertSame(0, (int) $interview->revision_count);
    }

    public function test_the_employer_cancels_an_invitation_and_the_round_stays_visible(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.cancel', $interview->id))
            ->assertRedirect(route('employer.interviews.index'));

        $cancelled = $interview->refresh();

        $this->assertSame(ApplicationInterview::STATUS_CANCELLED, $cancelled->status);
        $this->assertNull($cancelled->active_marker);
        $this->assertNotNull($cancelled->concluded_at);
        $this->assertSame('Accepted', $this->statusOf($applicationId));
        $this->assertSame('interview_cancelled', $this->lastActivity()['action']);

        // Cancelled is shown, not hidden - that is what tells the applicant it stopped.
        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('was cancelled by the employer');

        $this->refuses(
            $this->actAs($employer, 'employer')->postJson(route('employer.interviews.cancel', $interview->id)),
            'already cancelled'
        );
    }

    public function test_the_employer_marks_a_confirmed_interview_completed(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->answer($seeker, (int) $this->schedule($employer, $applicationId)->id, 'confirm');

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.complete', $interview->id))
            ->assertRedirect(route('employer.interviews.index'));

        $completed = $interview->refresh();

        $this->assertSame(ApplicationInterview::STATUS_COMPLETED, $completed->status);
        $this->assertNull($completed->active_marker);
        $this->assertNotNull($completed->concluded_at);
        $this->assertSame('interview_completed', $this->lastActivity()['action']);
    }

    public function test_a_completed_interview_cannot_be_confirmed_again(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->answer($seeker, (int) $this->schedule($employer, $applicationId)->id, 'confirm');
        $this->actAs($employer, 'employer')->post(route('employer.interviews.complete', $interview->id));

        $completed = $interview->refresh();
        $this->assertSame(ApplicationInterview::STATUS_COMPLETED, $completed->status);

        $this->refuses(
            $this->actAs($seeker, 'jobseeker')->postJson(route('jobseeker.interviews.confirm', $completed->id)),
            'This interview is over'
        );

        $this->refuses(
            $this->actAs($employer, 'employer')->postJson(route('employer.interviews.complete', $completed->id)),
            'already completed'
        );

        $this->assertSame(ApplicationInterview::STATUS_COMPLETED, $completed->refresh()->status);
    }

    public function test_an_invitation_answered_once_cannot_be_answered_twice(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->answer($seeker, (int) $this->schedule($employer, $applicationId)->id, 'confirm');

        $this->refuses(
            $this->actAs($seeker, 'jobseeker')->postJson(route('jobseeker.interviews.decline', $interview->id)),
            'already confirmed'
        );

        $this->assertSame(ApplicationInterview::STATUS_CONFIRMED, $interview->refresh()->status);
    }

    public function test_the_seeker_has_no_route_to_the_employer_powers_and_vice_versa(): void
    {
        // These powers exist on one controller each, so the other role cannot even name
        // a route that reaches them.
        $this->assertTrue($this->hasRoute('jobseeker.interviews.confirm'));
        $this->assertTrue($this->hasRoute('jobseeker.interviews.decline'));
        $this->assertTrue($this->hasRoute('jobseeker.interviews.reschedule'));
        $this->assertTrue($this->hasRoute('employer.interviews.store'));
        $this->assertTrue($this->hasRoute('employer.interviews.revise'));
        $this->assertTrue($this->hasRoute('employer.interviews.cancel'));
        $this->assertTrue($this->hasRoute('employer.interviews.complete'));

        foreach ([
            'jobseeker.interviews.cancel',
            'jobseeker.interviews.complete',
            'jobseeker.interviews.store',
            'jobseeker.interviews.revise',
            'employer.interviews.confirm',
            'employer.interviews.decline',
            'employer.interviews.reschedule',
        ] as $missing) {
            $this->assertFalse($this->hasRoute($missing), $missing.' must not exist.');
        }
    }

    // ─────────────────────────── validation ───────────────────────────

    public function test_a_date_that_has_already_gone_by_is_rejected(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload([
                'scheduled_at' => '2026-11-06T09:00',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_a_past_date_is_rejected_when_revising_too(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->requestReschedule($seeker, (int) $this->schedule($employer, $applicationId)->id);

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.interviews.revise', $interview->id), $this->schedulePayload([
                'scheduled_at' => '2026-11-01T10:00',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);

        $this->assertSame('2026-12-05 04:30:00', $interview->refresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_an_undefined_time_is_rejected_rather_than_stored_as_midnight(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.interviews.store', $applicationId), [
                'scheduled_at' => 'not a date',
                'timezone' => 'Asia/Kolkata',
                'interview_type' => ApplicationInterview::TYPE_ONLINE,
                'meeting_link' => 'https://meet.example.com/room',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_an_invented_timezone_is_refused_rather_than_reinterpreted(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->refuses(
            $this->actAs($employer, 'employer')->postJson(
                route('employer.interviews.store', $applicationId),
                $this->schedulePayload(['timezone' => 'Mars/Olympus'])
            ),
            'Pick a real timezone'
        );

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_an_online_invitation_requires_a_meeting_link(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        foreach (['', 'not a url', 'ftp://meet.example.com/room'] as $link) {
            $this->actAs($employer, 'employer')
                ->postJson(route('employer.interviews.store', $applicationId), [
                    'scheduled_at' => '2026-12-05T10:00',
                    'timezone' => 'Asia/Kolkata',
                    'interview_type' => ApplicationInterview::TYPE_ONLINE,
                    'meeting_link' => $link,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['meeting_link']);
        }

        $this->assertSame(0, ApplicationInterview::query()->count());
    }

    public function test_an_in_person_invitation_requires_an_address_and_drops_the_link(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload([
                'interview_type' => ApplicationInterview::TYPE_IN_PERSON,
                'meeting_link' => 'https://meet.example.com/ignored',
                'location' => '   ',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location']);

        $interview = $this->schedule($employer, $applicationId, [
            'interview_type' => ApplicationInterview::TYPE_IN_PERSON,
            'location' => '2nd floor, Conference Room B',
            'meeting_link' => 'https://meet.example.com/ignored',
        ]);

        $this->assertSame(ApplicationInterview::TYPE_IN_PERSON, $interview->interview_type);
        $this->assertSame('2nd floor, Conference Room B', $interview->location);
        $this->assertNull($interview->meeting_link, 'A link typed beside an in-person interview is not stored.');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('2nd floor, Conference Room B')
            ->assertDontSee('https://meet.example.com/ignored');
    }

    public function test_a_phone_invitation_needs_neither_link_nor_address(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $interview = $this->schedule($employer, $applicationId, [
            'interview_type' => ApplicationInterview::TYPE_PHONE,
            'meeting_link' => null,
        ]);

        $this->assertSame(ApplicationInterview::TYPE_PHONE, $interview->interview_type);
        $this->assertNull($interview->meeting_link);
        $this->assertNull($interview->location);
    }

    public function test_markup_in_the_instructions_never_reaches_the_other_participant(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->schedule($employer, $applicationId, [
            'instructions' => '<script>alert(1)</script>Ask for Priya at reception',
        ]);

        $page = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('Ask for Priya at reception', $page);
    }

    public function test_a_meeting_link_is_only_linked_when_it_is_a_real_address(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $interview = $this->schedule($employer, $applicationId);
        DB::table('application_interviews')->where('id', $interview->id)
            ->update(['meeting_link' => 'javascript:alert(1)']);

        $page = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('href="javascript:', $page);

        $safe = $this->schedule($employer, $applicationId, ['scheduled_at' => '2026-12-20T10:00']);
        $this->assertNotNull($safe->meeting_link);

        $linked = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->getContent();

        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', (string) $linked);
    }

    // ─────────────────────── rounds and duplicates ───────────────────────

    public function test_a_second_invitation_for_the_same_application_takes_the_next_round(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $first = $this->schedule($employer, $applicationId);
        $second = $this->schedule($employer, $applicationId, ['scheduled_at' => '2026-12-12T11:00']);
        $third = $this->schedule($employer, $applicationId, ['scheduled_at' => '2026-12-19T11:00']);

        $this->assertSame([1, 2, 3], [(int) $first->round, (int) $second->round, (int) $third->round]);

        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($page, 'Round 3'));
        $this->assertStringContainsString('Round 2', $page);
        $this->assertStringContainsString('Round 1', $page);
        $this->assertStringContainsString('3 rounds', $page);

        // Newest first, and the schedule form offers the round that comes next.
        $this->assertLessThan(
            strpos((string) $page, 'Round 1'),
            strpos((string) $page, 'Round 3')
        );
        $this->assertStringContainsString('Schedule round 4 interview', $page);
    }

    public function test_two_open_invitations_cannot_exist_for_one_round(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $this->schedule($employer, $applicationId);

        $this->expectException(QueryException::class);

        ApplicationInterview::query()->create([
            'ApplicationId' => $applicationId,
            'round' => 1,
            'scheduled_at' => '2026-12-20 10:00:00',
            'timezone' => 'UTC',
            'interview_type' => ApplicationInterview::TYPE_PHONE,
            'status' => ApplicationInterview::STATUS_PROPOSED,
            'active_marker' => 1,
            'created_by_employer_id' => (int) $employer->EmployerId,
        ]);
    }

    public function test_a_concluded_round_frees_its_number_for_a_fresh_offer(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->answer($seeker, (int) $this->schedule($employer, $applicationId)->id, 'decline');

        $this->assertNull($interview->active_marker);
        $this->assertSame(2, (int) $this->schedule($employer, $applicationId)->round);
        $this->assertSame(2, ApplicationInterview::query()->count());
    }

    public function test_the_application_page_lists_every_round_and_the_right_forms(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $first = $this->schedule($employer, $applicationId);
        $this->answer($seeker, (int) $first->id, 'decline');
        $second = $this->schedule($employer, $applicationId, ['scheduled_at' => '2026-12-12T11:00']);

        $page = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Interview invitations', $page);
        $this->assertStringContainsString('2 rounds', $page);
        $this->assertStringContainsString('Confirm attendance', $page);
        $this->assertStringContainsString('Request another time', $page);
        $this->assertStringContainsString('Decline', $page);
        $this->assertStringContainsString(route('jobseeker.interviews.confirm', $second->id), $page);
        $this->assertStringNotContainsString(
            route('jobseeker.interviews.confirm', $first->id),
            $page,
            'A concluded round carries no answer form.'
        );
        $this->assertStringNotContainsString('Schedule round', $page, 'The applicant cannot raise an invitation.');
        $this->assertStringNotContainsString('Interview schedule', $page);
    }

    // ─────────────────────────── who may see what ───────────────────────────

    public function test_a_jobseeker_only_sees_their_own_invitations(): void
    {
        $priya = $this->seeker('Priya Raman');
        $other = $this->seeker('Arun Kumar');
        $employer = $this->employerModel('Northwind Ltd');
        $hers = $this->application($priya, (int) $employer->EmployerId, 'Accepted');
        $theirs = $this->application($other, (int) $employer->EmployerId, 'Accepted', 'Helpdesk Associate');
        $hersInterview = $this->schedule($employer, $hers);
        $theirsInterview = $this->schedule($employer, $theirs, ['scheduled_at' => '2026-12-12T11:00']);

        $page = $this->actAs($priya, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('jobseeker.interviews.confirm', $hersInterview->id), $page);
        $this->assertStringNotContainsString(route('jobseeker.interviews.confirm', $theirsInterview->id), $page);
        $this->assertStringNotContainsString('Helpdesk Associate', $page);

        // Their own application page is not readable through her session either.
        $this->actAs($priya, 'jobseeker')
            ->get(route('jobseeker.applications.show', $theirs))
            ->assertNotFound();

        foreach (['confirm' => [], 'decline' => [], 'reschedule' => [
            'reason' => 'Not available at that time at all.',
            'preferred_at' => '2026-12-20T10:00',
        ]] as $action => $payload) {
            $this->actAs($priya, 'jobseeker')
                ->postJson(route('jobseeker.interviews.'.$action, $theirsInterview->id), $payload)
                ->assertStatus(404);
        }

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $theirsInterview->refresh()->status);
    }

    public function test_an_employer_only_sees_invitations_on_their_own_jobs(): void
    {
        $priya = $this->seeker('Priya Raman');
        $arun = $this->seeker('Arun Kumar');
        $northwind = $this->employerModel('Northwind Ltd');
        $contoso = $this->employerModel('Contoso Systems');
        $mine = $this->application($priya, (int) $northwind->EmployerId, 'Accepted');
        $theirs = $this->application($arun, (int) $contoso->EmployerId, 'Accepted', 'Helpdesk Associate');
        $mineInterview = $this->schedule($northwind, $mine);
        $theirsInterview = $this->schedule($contoso, $theirs, ['scheduled_at' => '2026-12-12T11:00']);

        $page = $this->actAs($northwind, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Priya Raman', $page);
        $this->assertStringNotContainsString('Arun Kumar', $page);
        $this->assertStringContainsString(route('employer.interviews.cancel', $mineInterview->id), $page);
        $this->assertStringNotContainsString(route('employer.interviews.cancel', $theirsInterview->id), $page);

        $this->refuses(
            $this->actAs($northwind, 'employer')->postJson(route('employer.interviews.cancel', $theirsInterview->id)),
            'another employer',
            403
        );
    }

    public function test_a_visitor_is_sent_to_sign_in_instead_of_an_interview(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        // Nothing from the fixtures may stay signed in: the guards are dropped so the
        // next request really comes from a visitor.
        $this->app['auth']->forgetGuards();

        $this->get(route('employer.interviews.index'))->assertRedirect(route('login'));
        $this->post(route('jobseeker.interviews.confirm', $interview->id))->assertRedirect(route('login'));
        $this->postJson(route('employer.interviews.cancel', $interview->id))->assertStatus(401);

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
    }

    public function test_a_jobseeker_cannot_reach_an_employer_power_and_vice_versa(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        // The applicant's own session cannot post to an employer route, whatever the id says.
        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('employer.interviews.cancel', $interview->id))
            ->assertStatus(403);

        // And the employer cannot answer an invitation as the applicant.
        $this->actAs($employer, 'employer')
            ->postJson(route('jobseeker.interviews.confirm', $interview->id))
            ->assertStatus(403);

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->refresh()->status);
    }

    public function test_the_participants_are_read_from_the_guards_not_from_the_body(): void
    {
        $priya = $this->seeker('Priya Raman');
        $other = $this->seeker('Arun Kumar');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($priya, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        // A body that names a different applicant, application, employer or status
        // changes nothing about what is stored.
        $this->actAs($priya, 'jobseeker')
            ->post(route('jobseeker.interviews.confirm', $interview->id), [
                'JobSeekId' => (int) $other->JobSeekId,
                'ApplicationId' => 9999,
                'created_by_employer_id' => 4242,
                'status' => ApplicationInterview::STATUS_COMPLETED,
                'scheduled_at' => '2026-12-30T09:00',
                'round' => 9,
            ])
            ->assertRedirect(route('jobseeker.interviews.index'));

        $updated = $interview->refresh();

        $this->assertSame(ApplicationInterview::STATUS_CONFIRMED, $updated->status);
        $this->assertSame($applicationId, (int) $updated->ApplicationId);
        $this->assertSame(1, (int) $updated->round);
        $this->assertSame('2026-12-05 04:30:00', $updated->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame((int) $employer->EmployerId, (int) $updated->created_by_employer_id);
    }

    // ─────────────────────────── the pages themselves ───────────────────────────

    public function test_the_employer_page_offers_the_schedule_form_for_an_accepted_applicant(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Interview schedule', $page);
        $this->assertStringContainsString('Schedule round 1 interview', $page);
        $this->assertStringContainsString('No invitation has been sent for this applicant yet', $page);
        $this->assertStringContainsString(route('employer.interviews.store', $applicationId), $page);
        $this->assertStringContainsString('name="scheduled_at"', $page);
        $this->assertStringContainsString('name="interview_type"', $page);
        $this->assertStringContainsString('name="cn_form"', $page);
        $this->assertStringContainsString('cn-int-panel', $page);
        $this->assertStringContainsString('assets/css/application-interviews.css', $page);
        $this->assertStringContainsString('assets/js/application-interviews.js', $page);
        $this->assertStringNotContainsString('php artisan migrate', $page);
    }

    public function test_the_employer_page_explains_why_an_unaccepted_applicant_cannot_be_interviewed(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Pending');

        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Interviews can only be scheduled after you accept this applicant.', $page);
        $this->assertStringNotContainsString('name="scheduled_at"', $page);
        $this->assertStringNotContainsString('Round 1', $page);
    }

    public function test_the_panel_still_leaves_the_application_decision_form_alone(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $this->schedule($employer, $applicationId);

        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        // The decision form and the interview panel are separate forms, not one.
        $this->assertStringContainsString(
            route('employer.applications.update', $applicationId),
            $page
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]*applications\/'.$applicationId.'\/interviews.*?<\/form>/s',
            $page
        );
        $this->assertStringContainsString('name="Status"', $page);
        $this->assertSame(1, substr_count((string) $page, 'Interview schedule'));
    }

    public function test_a_refused_date_comes_back_marked_on_the_form_that_was_filled(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $this->schedule($employer, $applicationId);

        // A past date and an invented zone pass the shape rules and are refused by the
        // clock check, so the page has to show both reasons rather than one sentence.
        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.store', $applicationId), [
                'cn_form' => 'new',
                'scheduled_at' => '2026-11-01T09:00',
                'timezone' => 'Mars/Olympus',
                'interview_type' => 'online',
                'meeting_link' => 'https://meet.example.test/northwind-round-2',
            ])
            ->assertSessionHasErrors(['scheduled_at', 'timezone']);

        // The session id travels as a cookie the way a browser carries it. Without one the
        // request gets a fresh id, and the flashed bag never comes back out of storage as
        // the field errors this page reads.
        $page = $this->withCookie(session()->getName(), session()->getId())
            ->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Choose an interview date and time in the future.', $page);
        $this->assertStringContainsString('Pick a real timezone from the list.', $page);
        $this->assertStringContainsString('class="cn-int-field is-invalid"', $page);

        // The rejected input is still in the fields, and the form is open to show them.
        $this->assertStringContainsString('value="2026-11-01T09:00"', $page);
        $this->assertMatchesRegularExpression('/<details[^>]*data-cn-int-details="schedule"[^>]*\bopen\b/i', $page);

        // Nothing was written by the refused request.
        $this->assertSame(1, ApplicationInterview::query()->where('ApplicationId', $applicationId)->count());
    }

    public function test_a_short_reschedule_reason_marks_only_the_form_that_was_used(): void
    {
        $priya = $this->seeker('Priya Raman');
        $arun = $this->seeker('Arun Kumar');
        $employer = $this->employerModel('Northwind Ltd');
        $first = $this->application($priya, (int) $employer->EmployerId, 'Accepted', 'Reporting Analyst');
        $second = $this->application($arun, (int) $employer->EmployerId, 'Accepted', 'Helpdesk Associate');
        $asked = $this->schedule($employer, $first);
        $other = $this->schedule($employer, $second, ['scheduled_at' => '2026-12-12T11:00']);

        $this->actAs($priya, 'jobseeker')
            ->post(route('jobseeker.interviews.reschedule', $asked->id), [
                'cn_form' => 'reschedule-'.$asked->id,
                'reason' => 'Busy.',
                'preferred_at' => '2026-12-08T14:00',
                'preferred_timezone' => 'Asia/Kolkata',
            ])
            ->assertSessionHasErrors('reason');

        // The applicant's list holds one invitation, and it reopens with what they typed:
        // every card posts the same field names, so only the marker tells them apart.
        // The session id rides along as a cookie, the way a browser carries it back,
        // because the field errors only exist once storage has turned them around.
        $page = $this->withCookie(session()->getName(), session()->getId())
            ->actAs($priya, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('must be at least 10 characters', $page);
        $this->assertStringContainsString('cn-int-field is-invalid', $page);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="reason"[^>]*>\s*Busy\./', $page);
        $this->assertSame(1, preg_match_all(
            '/<details[^>]*data-cn-int-details="reschedule"[^>]*open[^>]*>/',
            (string) $page
        ));

        // Neither invitation moved.
        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $asked->refresh()->status);
        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $other->refresh()->status);
    }

    public function test_every_date_field_arrives_with_a_calendar_for_its_own_id(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $scheduleForm = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->schedule($employer, $applicationId);

        $rescheduleForm = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->getContent();

        // A calendar names the field it fills rather than the nearest input, because a
        // page can hold several forms and the employer's revision has to open the round
        // it belongs to. It also ships hidden: the button only appears once the script
        // has proved this browser can open a date picker, so a browser that cannot is
        // left with the plain field instead of a control that does nothing.
        foreach (['employer schedule' => $scheduleForm, 'applicant reschedule' => $rescheduleForm] as $label => $page) {
            preg_match_all('/<input id="([^"]+)" type="datetime-local"/', (string) $page, $fields);
            preg_match_all('/data-cn-int-picker="([^"]+)" hidden/', (string) $page, $buttons);

            $this->assertNotEmpty($fields[1], $label.' renders a date field');
            $this->assertSame($fields[1], $buttons[1], $label.' pairs each date field with its own calendar');
            $this->assertStringContainsString('cn-int-date', $page);
        }
    }

    public function test_both_list_pages_render_their_own_empty_state(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('No interviews to show');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('No interview invitations yet');
    }

    public function test_the_sidebar_links_to_interviews_for_both_roles(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');

        // The role layouts carry the sidebar, so any page of this feature proves the
        // navigation without depending on what the dashboards themselves query.
        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee(route('employer.interviews.index'), false);

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee(route('jobseeker.interviews.index'), false);
    }

    public function test_the_sidebar_badge_counts_only_what_waits_on_this_account(): void
    {
        $priya = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($priya, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        // Nothing waits on the employer yet; the applicant has one invitation to answer.
        $this->assertSame(0, ApplicationInterview::awaitingEmployerCount((int) $employer->EmployerId));
        $this->assertSame(1, ApplicationInterview::awaitingSeekerCount((int) $priya->JobSeekId));

        $this->actAs($priya, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('cn-cmp-nav-count', false);

        $this->requestReschedule($priya, (int) $interview->id);

        $this->assertSame(1, ApplicationInterview::awaitingEmployerCount((int) $employer->EmployerId));
        $this->assertSame(0, ApplicationInterview::awaitingSeekerCount((int) $priya->JobSeekId));

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('cn-cmp-nav-count', false);

        // Another employer's badge is untouched by this applicant's invitation.
        $this->assertSame(0, ApplicationInterview::awaitingEmployerCount((int) $this->employer('Contoso Systems')));
        $this->assertSame(0, ApplicationInterview::awaitingEmployerCount(0));
    }

    public function test_the_page_reports_the_interview_update_after_each_action(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.store', $applicationId), $this->schedulePayload([
                'scheduled_at' => '2026-12-12T11:00',
            ]))
            ->assertRedirect(route('employer.interviews.index'));

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('Invitation sent. The applicant can confirm, decline or ask for another time.');

        $this->actAs($seeker, 'jobseeker')
            ->post(route('jobseeker.interviews.confirm', $interview->id))
            ->assertRedirect(route('jobseeker.interviews.index'));

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('Attendance confirmed. The employer can see your answer.');
    }

    public function test_the_goto_parameter_returns_the_person_to_the_application_page(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($seeker, 'jobseeker')
            ->post(route('jobseeker.interviews.confirm', $interview->id), ['goto' => 'application'])
            ->assertRedirect(route('jobseeker.applications.show', $applicationId));

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.cancel', $interview->id), ['goto' => 'application'])
            ->assertRedirect(route('employer.applications.show', $applicationId));

        // Anything else in the parameter cannot point the redirect somewhere else.
        // This needs an interview the state machine still allows to complete, so the
        // cancelled round gets a replacement rather than being reused.
        $next = $this->schedule($employer, $applicationId, ['scheduled_at' => '2026-12-12T11:00']);
        $this->answer($seeker, (int) $next->id, 'confirm');

        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.complete', $next->id), ['goto' => 'https://evil.example.test'])
            ->assertRedirect(route('employer.interviews.index'));
    }

    public function test_the_list_pages_filter_by_status_and_search(): void
    {
        $priya = $this->seeker('Priya Raman');
        $arun = $this->seeker('Arun Kumar');
        $employer = $this->employerModel('Northwind Ltd');
        $analytics = $this->application($priya, (int) $employer->EmployerId, 'Accepted');
        $support = $this->application($arun, (int) $employer->EmployerId, 'Accepted', 'Helpdesk Associate');

        $open = $this->schedule($employer, $analytics);
        $this->schedule($employer, $support, ['scheduled_at' => '2026-12-12T11:00']);
        $this->answer($priya, (int) $open->id, 'decline');

        $declined = $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index', ['status' => ApplicationInterview::STATUS_DECLINED]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Reporting Analyst', $declined);
        $this->assertStringNotContainsString('Helpdesk Associate', $declined);

        $searched = $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index', ['search' => 'Helpdesk']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Helpdesk Associate', $searched);
        $this->assertStringNotContainsString('Reporting Analyst', $searched);

        // A status that is not one of the six is refused rather than searched for.
        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index', ['status' => 'whatever']))
            ->assertSessionHasErrors('status');
    }

    public function test_an_unanswered_invitation_is_flagged_as_waiting_on_the_other_side(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $this->schedule($employer, $applicationId);

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('waiting on the applicant');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('waiting on the employer');
    }

    // ─────────────────────── alongside the rest of the app ───────────────────────

    public function test_the_application_chat_keeps_working_next_to_interviews(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $thread = app(ApplicationChatService::class)->openFor(Application::query()->findOrFail($applicationId));
        $this->assertNotNull($thread);

        $interview = $this->schedule($employer, $applicationId);

        $this->actAs($employer, 'employer')
            ->post(route('employer.messages.send', $thread->id), ['message' => 'Sent you an interview slot.'])
            ->assertRedirect();

        $this->actAs($seeker, 'jobseeker')
            ->post(route('jobseeker.messages.send', $thread->id), ['message' => 'Confirming now.'])
            ->assertRedirect();

        $this->assertSame(2, ApplicationChatMessage::query()->where('ConversationId', $thread->id)->count());

        $page = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Chat with Employer', $page);
        $this->assertStringContainsString('Interview invitations', $page);
        $this->assertStringContainsString(route('jobseeker.interviews.confirm', $interview->id), $page);

        // An interview adds no second messaging surface; the thread stays the only one.
        $this->assertSame(1, substr_count((string) $page, 'Chat with Employer'));
    }

    public function test_the_interview_actions_are_logged_for_the_activity_trail(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $interview = $this->schedule($employer, $applicationId);
        $this->answer($seeker, (int) $interview->id, 'confirm');
        $this->actAs($employer, 'employer')->post(route('employer.interviews.complete', $interview->id));

        $actions = DB::table('logs')->where('target_type', 'application_interview')->pluck('action')->all();

        $this->assertSame(
            ['interview_proposed', 'interview_confirmed', 'interview_completed'],
            $actions
        );
    }

    public function test_the_interview_routes_are_throttled_rather_than_left_open(): void
    {
        foreach ([
            ['employer.interviews.store', 'throttle:20,1'],
            ['employer.interviews.revise', 'throttle:20,1'],
            ['employer.interviews.cancel', 'throttle:20,1'],
            ['employer.interviews.complete', 'throttle:30,1'],
            ['jobseeker.interviews.confirm', 'throttle:20,1'],
            ['jobseeker.interviews.decline', 'throttle:20,1'],
            ['jobseeker.interviews.reschedule', 'throttle:10,1'],
        ] as [$name, $expect]) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, $name.' should be registered.');
            $this->assertContains($expect, $route->gatherMiddleware(), $name.' should be rate limited.');
        }
    }

    // ─────────────────────── before the migration has run ───────────────────────

    public function test_the_pages_still_work_when_the_interview_table_is_missing(): void
    {
        Schema::drop('application_interviews');
        SchemaReady::invalidate();

        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $employerPage = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('php artisan migrate', $employerPage);
        $this->assertStringNotContainsString('name="scheduled_at"', $employerPage);

        $this->actAs($employer, 'employer')
            ->get(route('employer.interviews.index'))
            ->assertOk()
            ->assertSee('Interviews are not set up on this database yet');

        // A seeker with nothing to answer is not warned about a table they cannot create.
        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk()
            ->assertDontSee('Interview invitations');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.interviews.index'))
            ->assertOk()
            ->assertSee('Interviews are not set up on this database yet');

        // The sidebar badge answers zero instead of throwing.
        $this->assertSame(0, ApplicationInterview::awaitingEmployerCount((int) $employer->EmployerId));
        $this->assertSame(0, ApplicationInterview::awaitingSeekerCount((int) $seeker->JobSeekId));

        $this->refuses(
            $this->actAs($employer, 'employer')
                ->postJson(route('employer.interviews.store', $applicationId), $this->schedulePayload()),
            'php artisan migrate',
            503
        );

        foreach ([
            route('employer.interviews.cancel', 1),
            route('employer.interviews.revise', 1),
            route('employer.interviews.complete', 1),
        ] as $url) {
            $this->actAs($employer, 'employer')->postJson($url)->assertStatus(503);
        }

        foreach ([
            route('jobseeker.interviews.confirm', 1),
            route('jobseeker.interviews.decline', 1),
            route('jobseeker.interviews.reschedule', 1),
        ] as $url) {
            $this->actAs($seeker, 'jobseeker')->postJson($url)->assertStatus(503);
        }

        $this->assertSame('Accepted', $this->statusOf($applicationId));
    }

    // ──────────────────────────── fixtures ────────────────────────────

    /**
     * A complete, valid schedule payload; override any key for the negative cases.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(array $overrides = []): array
    {
        return array_replace([
            'scheduled_at' => '2026-12-05T10:00',
            'timezone' => 'Asia/Kolkata',
            'interview_type' => ApplicationInterview::TYPE_ONLINE,
            'meeting_link' => 'https://meet.example.com/priya',
            'instructions' => 'Ask for Priya at reception.',
        ], $overrides);
    }

    /** Schedule through the employer's own route, failing loudly if it is refused. */
    private function schedule(Employer $employer, int $applicationId, array $overrides = []): ApplicationInterview
    {
        $this->actAs($employer, 'employer')
            ->post(route('employer.interviews.store', $applicationId), $this->schedulePayload($overrides))
            ->assertRedirect(route('employer.interviews.index'))
            ->assertSessionHas('success');

        $interview = ApplicationInterview::query()
            ->where('ApplicationId', $applicationId)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame(ApplicationInterview::STATUS_PROPOSED, $interview->status);

        return $interview;
    }

    /**
     * One of the applicant's answers, through their own route.
     *
     * @param  array<string, mixed>  $payload
     */
    private function answer(JobSeeker $seeker, int $interviewId, string $action, array $payload = []): ApplicationInterview
    {
        $this->actAs($seeker, 'jobseeker')
            ->post(route('jobseeker.interviews.'.$action, $interviewId), $payload)
            ->assertRedirect();

        return ApplicationInterview::query()->findOrFail($interviewId);
    }

    /** A realistic reschedule request, as the applicant's form would send it. */
    private function requestReschedule(JobSeeker $seeker, int $interviewId): ApplicationInterview
    {
        return $this->answer($seeker, $interviewId, 'reschedule', [
            'reason' => 'I have a hospital appointment that morning.',
            'preferred_at' => '2026-12-08T14:00',
            'preferred_timezone' => 'Asia/Kolkata',
        ]);
    }

    /**
     * A refusal that says what is wrong in its own words rather than by field name.
     */
    private function refuses(mixed $response, string $needle, int $status = 422): void
    {
        $response->assertStatus($status);

        $this->assertStringContainsString(
            $needle,
            (string) $response->getContent(),
            'Expected a refusal mentioning "'.$needle.'".'
        );
    }

    private function statusOf(int $applicationId): string
    {
        return (string) DB::table('application_master')->where('ApplicationId', $applicationId)->value('Status');
    }

    /** @return array{action: string, metadata: ?string} */
    private function lastActivity(): array
    {
        $row = DB::table('logs')->orderByDesc('Id')->first();
        $this->assertNotNull($row, 'An interview action should leave an activity row.');

        return ['action' => (string) $row->action, 'metadata' => $row->metadata ?? null];
    }

    private function hasRoute(string $name): bool
    {
        return app('router')->getRoutes()->getByName($name) !== null;
    }

    private function actAs(mixed $account, string $role): self
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($account, $role);
    }

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
            'Email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'City' => 'Chennai',
            'Qualification' => 'B.E',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    private function employer(string $company): int
    {
        return (int) DB::table('employer_reg')->insertGetId([
            'CompanyName' => $company,
            'Status' => 'Approved',
            'Email' => strtolower(str_replace(' ', '.', $company)).'@example.test',
        ]);
    }

    private function employerModel(string $company): Employer
    {
        return Employer::query()->findOrFail($this->employer($company));
    }

    /** One job of this employer, and one application against it. */
    private function application(
        JobSeeker $seeker,
        int $employerId,
        string $status = 'Accepted',
        string $jobTitle = 'Reporting Analyst',
    ): int {
        $company = (string) DB::table('employer_reg')->where('EmployerId', $employerId)->value('CompanyName');

        $jobId = (int) DB::table('job_master')->insertGetId([
            'EmployerId' => $employerId,
            'CompanyName' => $company,
            'JobTitle' => $jobTitle,
            'Status' => 'approved',
            'Description' => 'Hands-on reporting work.',
        ]);

        return (int) DB::table('application_master')->insertGetId([
            'JobSeekId' => (int) $seeker->JobSeekId,
            'JobId' => $jobId,
            'Status' => $status,
            'Description' => 'Application Submitted',
            'ApplicationDate' => now()->subDay(),
        ]);
    }

    /** The employer page's own write, so the tests react to real status values. */
    private function statusMove(int $applicationId, string $status): void
    {
        DB::table('application_master')->where('ApplicationId', $applicationId)->update(['Status' => $status]);
    }
}
