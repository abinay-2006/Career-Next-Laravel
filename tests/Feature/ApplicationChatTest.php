<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationChatMessage;
use App\Models\ApplicationConversation;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Services\ApplicationChatService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two-way application chat.
 *
 * A conversation has two participants, so most tests must sign in as both of them in
 * one run. The identity middleware treats two guards being logged in at once as an
 * inconsistent browser session, which is why every request here goes through actAs():
 * it drops the resolved guards first, exactly as a fresh request would.
 *
 * Status is written the way the employer page writes it, so the chat is always judged
 * against a real approval decision rather than a fixture invented for it.
 */
class ApplicationChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Read receipts, previews and the inbox time lines are all compared to the clock.
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
            $table->integer('SalaryFrom')->nullable();
            $table->integer('SalaryTo')->nullable();
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

        // The same shape as the two new migrations, including the unique ApplicationId.
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─────────────────────── opening and closing ───────────────────────

    public function test_no_conversation_is_offered_before_the_employer_approves(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer('Northwind Ltd');
        $applicationId = $this->application($seeker, $employerId, 'Pending');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertSee('No conversations yet');

        $this->assertSame(0, ApplicationConversation::query()->count());

        $page = $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId));

        $page->assertOk();
        $this->assertStringNotContainsString('Chat with Employer', (string) $page->getContent());
    }

    public function test_the_employer_detail_page_offers_no_chat_before_approval(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Pending');

        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId));

        $page->assertOk();
        $this->assertStringNotContainsString('Chat with Applicant', (string) $page->getContent());

        $this->assertSame(0, ApplicationConversation::query()->count());
    }

    public function test_approval_opens_exactly_one_conversation_linked_to_both_participants(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer('Northwind Ltd');
        $applicationId = $this->application($seeker, $employerId, 'Accepted');

        // Whichever side looks first is the one that opens it.
        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertSee('Reporting Analyst');

        $thread = ApplicationConversation::query()->sole();

        $this->assertSame($applicationId, (int) $thread->ApplicationId);
        $this->assertSame($employerId, (int) $thread->EmployerId);
        $this->assertSame((int) $seeker->JobSeekId, (int) $thread->JobSeekId);
        $this->assertTrue($thread->isOpen());
    }

    public function test_the_employer_detail_page_offers_chat_once_the_application_is_accepted(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertOk()
            ->assertSee('Chat with Applicant');

        $threadId = $this->threadFor($applicationId);
        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Priya Raman');
    }

    public function test_visiting_both_inboxes_never_creates_a_second_conversation(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($seeker, 'jobseeker')->get(route('jobseeker.messages.index'))->assertOk();
        $this->actAs($employer, 'employer')->get(route('employer.messages.index'))->assertOk();
        $this->actAs($employer, 'employer')->get(route('employer.messages.index'))->assertOk();

        $this->assertSame(1, ApplicationConversation::query()->count());
        $this->assertSame($applicationId, (int) ApplicationConversation::query()->sole()->ApplicationId);
    }

    public function test_the_database_rejects_a_second_conversation_for_one_application(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer('Northwind Ltd');
        $applicationId = $this->application($seeker, $employerId, 'Accepted');

        $this->threadFor($applicationId);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('application_conversations')->insert([
            'ApplicationId' => $applicationId,
            'EmployerId' => $employerId,
            'JobSeekId' => (int) $seeker->JobSeekId,
            'status' => 'active',
        ]);
    }

    public function test_each_approved_application_gets_its_own_thread(): void
    {
        $first = $this->seeker('Priya Raman');
        $second = $this->seeker('Arun Kumar');
        $employer = $this->employerModel('Northwind Ltd');
        $this->application($first, (int) $employer->EmployerId, 'Accepted');
        $this->application($second, (int) $employer->EmployerId, 'Accepted');

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.index'))
            ->assertOk()
            ->assertSee('Priya Raman')
            ->assertSee('Arun Kumar');

        $this->assertSame(2, ApplicationConversation::query()->count());
    }

    // ─────────────────────── who may look ───────────────────────

    public function test_a_seeker_cannot_open_another_seeker_s_thread(): void
    {
        $owner = $this->seeker('Priya Raman');
        $outsider = $this->seeker('Arun Kumar');
        $applicationId = $this->application($owner, $this->employer('Northwind Ltd'), 'Accepted');
        $threadId = $this->threadFor($applicationId);

        $this->actAs($outsider, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertDontSee('Reporting Analyst');

        $this->actAs($outsider, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertNotFound();

        $this->actAs($outsider, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'hello'])
            ->assertNotFound();

        $this->actAs($outsider, 'jobseeker')
            ->getJson(route('jobseeker.messages.poll', $threadId))
            ->assertNotFound();

        $this->assertSame(0, ApplicationChatMessage::query()->count());
    }

    public function test_an_employer_cannot_read_another_employer_s_thread(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer('Northwind Ltd'), 'Accepted');
        $threadId = $this->threadFor($applicationId);

        $neighbour = $this->employerModel('Rival Software Ltd');

        $this->actAs($neighbour, 'employer')
            ->get(route('employer.messages.index'))
            ->assertOk()
            ->assertDontSee('Priya Raman');

        $this->actAs($neighbour, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertForbidden();

        $this->actAs($neighbour, 'employer')
            ->postJson(route('employer.messages.send', $threadId), ['message' => 'hello'])
            ->assertForbidden();

        $this->actAs($neighbour, 'employer')
            ->getJson(route('employer.messages.poll', $threadId))
            ->assertForbidden();

        $this->assertSame(0, ApplicationChatMessage::query()->count());
    }

    public function test_an_employer_cannot_reach_an_application_that_is_not_theirs_by_url(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $applicationId = $this->application($seeker, $this->employer('Northwind Ltd'), 'Accepted');

        $this->actAs($this->employerModel('Rival Software Ltd'), 'employer')
            ->get(route('employer.applications.show', $applicationId))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('jobseeker.messages.index'))->assertRedirect(route('login'));
        $this->get(route('employer.messages.index'))->assertRedirect(route('login'));
    }

    public function test_a_seeker_guard_cannot_reach_the_employer_chat_routes(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $this->application($seeker, $this->employer('Northwind Ltd'), 'Accepted');

        // One authenticated role that is not the role the route asks for is refused
        // before the controller ever runs.
        $this->actAs($seeker, 'jobseeker')
            ->get(route('employer.messages.index'))
            ->assertForbidden();
    }

    public function test_an_employer_cannot_reach_the_seeker_chat_routes(): void
    {
        $this->actAs($this->employerModel('Northwind Ltd'), 'employer')
            ->get(route('jobseeker.messages.index'))
            ->assertForbidden();
    }

    // ─────────────────────── sending and reading ───────────────────────

    public function test_both_participants_can_write_and_see_the_transcript(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'When does the team start?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sent.body', 'When does the team start?')
            ->assertJsonPath('sent.mine', true);

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.messages.send', $threadId), ['message' => 'Monday, and HR will call you.'])
            ->assertOk()
            ->assertJsonPath('sent.sender_role', 'employer');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertOk()
            ->assertSee('When does the team start?')
            ->assertSee('Monday, and HR will call you.');

        $this->assertSame(2, ApplicationChatMessage::query()->count());

        $stored = ApplicationChatMessage::query()->orderBy('id')->get();
        $this->assertSame('jobseeker', $stored[0]->SenderRole);
        $this->assertSame((int) $seeker->JobSeekId, (int) $stored[0]->SenderId);
        $this->assertSame('employer', $stored[1]->SenderRole);
        $this->assertSame((int) $employer->EmployerId, (int) $stored[1]->SenderId);

        // Sending is the only thing that moved, and the thread now sorts by its traffic.
        $this->assertNotNull(ApplicationConversation::query()->sole()->last_message_at);
    }

    public function test_the_sender_identity_comes_from_the_guard_and_not_the_request(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), [
                'message' => 'Impersonating nobody.',
                'SenderId' => (int) $employer->EmployerId,
                'SenderRole' => 'employer',
                'read_at' => '2020-01-01 00:00:00',
            ])
            ->assertOk();

        $stored = ApplicationChatMessage::query()->sole();

        $this->assertSame('jobseeker', $stored->SenderRole);
        $this->assertSame((int) $seeker->JobSeekId, (int) $stored->SenderId);
        $this->assertNull($stored->read_at);
    }

    public function test_an_empty_message_is_refused(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, ApplicationChatMessage::query()->count());
    }

    public function test_markup_is_stripped_rather_than_stored(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), [
                'message' => '<script>alert("xss")</script>Happy <b>Monday</b>',
            ])
            ->assertOk()
            ->assertJsonPath('sent.body', 'alert("xss")Happy Monday');

        $this->assertSame('alert("xss")Happy Monday', ApplicationChatMessage::query()->sole()->message);
    }

    public function test_markup_that_survives_cleaning_is_still_escaped_on_the_page(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'if 5 < 10 && you agree'])
            ->assertOk();

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertOk()
            ->assertSee('5 &lt; 10 &amp;&amp;', false)
            ->assertDontSee('if 5 < 10', false);
    }

    public function test_a_message_that_is_pure_markup_is_refused_as_empty(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => '<p></p><br/>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, ApplicationChatMessage::query()->count());
    }

    public function test_the_length_limit_is_enforced_in_both_directions(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => str_repeat('a', 2000)])
            ->assertOk();

        $this->assertSame(2000, mb_strlen((string) ApplicationChatMessage::query()->sole()->message));
    }

    public function test_older_messages_are_paged_out_of_the_first_window(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();

        for ($i = 1; $i <= 35; $i++) {
            $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Message '.$i);
        }

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Message 35')
            ->assertSee('Message 6')
            ->assertDontSee('>Message 5<', false);

        // The window before the oldest painted message holds the rest of the history.
        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.history', $threadId).'?before=1000')
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('messages.0.body', 'Message 6');

        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.history', $threadId).'?before=6')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(5, 'messages')
            ->assertJsonPath('messages.0.body', 'Message 1')
            ->assertJsonPath('messages.4.body', 'Message 5');

        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.history', $threadId).'?before=1')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(0, 'messages');
    }

    public function test_polling_answers_only_with_newer_messages(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();
        $first = $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Older note');
        $second = $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Newer note');

        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.poll', $threadId).'?after='.$first)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $second)
            ->assertJsonPath('can_send', true);
    }

    public function test_opening_a_thread_stamps_the_other_side_s_messages_as_read(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $seekerMessage = $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Do you still need me?');
        $employerMessage = $this->threadRow($threadId, 'employer', (int) $employer->EmployerId, 'Yes, start Monday.');

        $this->assertNull(ApplicationChatMessage::query()->find($seekerMessage)->read_at);

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk();

        $this->assertNotNull(ApplicationChatMessage::query()->find($seekerMessage)->fresh()->read_at);
        // The reader never stamps their own words.
        $this->assertNull(ApplicationChatMessage::query()->find($employerMessage)->fresh()->read_at);
    }

    public function test_the_sender_sees_a_read_receipt_for_messages_the_other_side_opened(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $mine = $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Waiting for you.');
        $this->threadRow($threadId, 'employer', (int) $employer->EmployerId, 'Reading it now.');

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk();

        // A reply written after that read is still waiting, so both labels show.
        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Shall I bring the original?'])
            ->assertOk();

        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.poll', $threadId))
            ->assertOk()
            ->assertJsonPath('read_ids.0', $mine);

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Read Nov 6')
            ->assertSee('Delivered');
    }

    public function test_unread_counts_are_separate_for_each_participant(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'One');
        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Two');
        $this->threadRow($threadId, 'employer', (int) $employer->EmployerId, 'Three');

        // Each side waits on the other's words only.
        $this->assertSame(2, ApplicationConversation::unreadTotalForRole('employer', (int) $employer->EmployerId));
        $this->assertSame(1, ApplicationConversation::unreadTotalForRole('jobseeker', (int) $seeker->JobSeekId));

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.index'))
            ->assertOk()
            ->assertSee('2 new');

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk();

        $this->assertSame(0, ApplicationConversation::unreadTotalForRole('employer', (int) $employer->EmployerId));
        $this->assertSame(1, ApplicationConversation::unreadTotalForRole('jobseeker', (int) $seeker->JobSeekId));

        // A second employer with no traffic of their own keeps a zero badge.
        $this->assertSame(0, ApplicationConversation::unreadTotalForRole('employer', $this->employer('Rival Software Ltd')));
    }

    public function test_the_conversation_list_shows_the_latest_preview_and_time(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $this->threadRow($threadId, 'employer', (int) $employer->EmployerId, 'Bring your certificate on Monday.');
        DB::table('application_conversations')->where('id', $threadId)->update(['last_message_at' => now()->subDay()]);

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertSee('Northwind Ltd')
            ->assertSee('Reporting Analyst')
            ->assertSee('Bring your certificate on Monday.')
            ->assertSee('1d ago');
    }

    // ─────────────────────── decisions that change ───────────────────────

    public function test_revoking_the_approval_suspends_sending_but_keeps_history(): void
    {
        [$seeker, $employer, $threadId, $applicationId] = $this->approvedThreadPair();
        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Started on Monday.');

        $this->statusMove($applicationId, 'Pending');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Still hiring?'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Messaging is paused while this application is pending again. It reopens as soon as the employer approves it.');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Started on Monday.')
            ->assertSee('Messaging is paused');

        $this->assertSame(1, ApplicationChatMessage::query()->count());
        $this->assertSame('closed', ApplicationConversation::query()->sole()->status);

        // Reading is still allowed for both sides, just not writing.
        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Started on Monday.');
    }

    public function test_a_rejected_application_closes_the_thread_to_new_messages(): void
    {
        [$seeker, $employer, $threadId, $applicationId] = $this->approvedThreadPair();

        $this->statusMove($applicationId, 'Rejected');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Why?'])
            ->assertForbidden()
            ->assertJsonPath('message', 'This application was rejected, so new messages are closed. Earlier messages stay readable.');

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.messages.send', $threadId), ['message' => 'Closing.'])
            ->assertForbidden();

        $this->assertSame(0, ApplicationChatMessage::query()->count());
        $this->assertSame('closed', ApplicationConversation::query()->sole()->status);
    }

    public function test_a_withdrawn_application_closes_the_thread_too(): void
    {
        [$seeker, , $threadId, $applicationId] = $this->approvedThreadPair();

        $this->statusMove($applicationId, 'Withdrawn');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Coming back.'])
            ->assertForbidden()
            ->assertJsonPath('message', 'This application was withdrawn, so new messages are closed. Earlier messages stay readable.');

        $this->assertSame('closed', ApplicationConversation::query()->sole()->status);
    }

    public function test_approval_can_reopen_a_thread_that_was_suspended(): void
    {
        [$seeker, , $threadId, $applicationId] = $this->approvedThreadPair();

        $this->statusMove($applicationId, 'Pending');
        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Still there?'])
            ->assertForbidden();
        $this->assertSame('closed', ApplicationConversation::query()->sole()->status);

        $this->statusMove($applicationId, 'Accepted');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Back on!'])
            ->assertOk();

        $this->assertSame('active', ApplicationConversation::query()->sole()->status);
    }

    public function test_an_accepted_application_keeps_chat_open_for_onboarding(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();

        $this->actAs($employer, 'employer')
            ->postJson(route('employer.messages.send', $threadId), ['message' => 'Onboarding docs attached.'])
            ->assertOk();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Thank you, sending them today.'])
            ->assertOk()
            ->assertJsonPath('thread.unread', 1);

        $this->assertSame('Accepted', Application::query()->sole()->Status);
    }

    public function test_chat_never_writes_to_the_application_row(): void
    {
        [$seeker, $employer, $threadId, $applicationId] = $this->approvedThreadPair();
        $before = Application::query()->findOrFail($applicationId)->toArray();

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Hi.'])
            ->assertOk();
        $this->actAs($employer, 'employer')
            ->postJson(route('employer.messages.send', $threadId), ['message' => 'Hello.'])
            ->assertOk();
        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk();

        $this->assertSame($before, Application::query()->findOrFail($applicationId)->toArray());
    }

    public function test_an_inbox_visit_does_not_open_a_thread_for_a_pending_application(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $this->application($seeker, $this->employer('Northwind Ltd'), 'Pending');

        $this->assertSame(0, app(ApplicationChatService::class)->openMissingThreads('jobseeker', (int) $seeker->JobSeekId));
        $this->assertSame(0, ApplicationConversation::query()->count());
    }

    // ─────────────────────── living beside the other features ───────────────────────

    public function test_the_follow_up_messages_inbox_still_works_next_to_the_chat(): void
    {
        [$seeker, $employer, $threadId, $applicationId] = $this->approvedThreadPair();

        DB::table('application_follow_up_messages')->insert([
            'ApplicationId' => $applicationId,
            'JobSeekId' => (int) $seeker->JobSeekId,
            'EmployerId' => (int) $employer->EmployerId,
            'template' => 'status_inquiry',
            'subject' => 'Following up',
            'body' => 'Any news on my application?',
            'sent_at' => now()->subDay(),
        ]);

        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Left you a follow-up too.');

        $this->actAs($employer, 'employer')
            ->get(route('employer.followupmessages.index'))
            ->assertOk()
            ->assertSee('Following up')
            ->assertSee('Any news on my application?');

        // The two features stay separate: the chat is not in that list and the
        // follow-up is not in the thread.
        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.show', $threadId))
            ->assertOk()
            ->assertSee('Left you a follow-up too.')
            ->assertDontSee('Any news on my application?');

        $this->assertSame(1, ApplicationChatMessage::query()->count());
        $this->assertSame(1, DB::table('application_follow_up_messages')->count());
    }

    public function test_the_seeker_coach_page_still_works_next_to_the_chat(): void
    {
        [$seeker, , $threadId] = $this->approvedThreadPair();
        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'Ping.');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk();
    }

    public function test_approval_from_the_application_page_opens_the_conversation_immediately(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Pending');

        $this->assertSame(0, ApplicationConversation::query()->count());

        $this->actAs($employer, 'employer')
            ->putJson(route('employer.applications.update', $applicationId), [
                'Status' => 'Accepted',
                'Description' => 'Please join us.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Accepted');

        $thread = ApplicationConversation::query()->sole();

        $this->assertSame($applicationId, (int) $thread->ApplicationId);
        $this->assertSame((int) $employer->EmployerId, (int) $thread->EmployerId);
        $this->assertSame((int) $seeker->JobSeekId, (int) $thread->JobSeekId);
        $this->assertTrue($thread->isOpen());

        // The decision itself is unchanged: the same two fields, written the same way.
        $application = Application::query()->findOrFail($applicationId);
        $this->assertSame('Accepted', $application->Status);
        $this->assertSame('Please join us.', $application->Description);
    }

    public function test_rejecting_from_the_application_page_closes_any_thread(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel('Northwind Ltd');
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');
        $threadId = $this->threadFor($applicationId);

        $this->actAs($employer, 'employer')
            ->putJson(route('employer.applications.update', $applicationId), [
                'Status' => 'Rejected',
                'Description' => 'Role filled internally.',
            ])
            ->assertOk();

        $this->assertSame('closed', ApplicationConversation::query()->findOrFail($threadId)->status);

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', $threadId), ['message' => 'Really?'])
            ->assertForbidden();
    }

    public function test_both_messages_pages_offer_the_link_with_a_live_unread_badge(): void
    {
        [$seeker, $employer, $threadId] = $this->approvedThreadPair();
        $this->threadRow($threadId, 'employer', (int) $employer->EmployerId, 'Come in Monday.');
        $this->threadRow($threadId, 'jobseeker', (int) $seeker->JobSeekId, 'I will be there.');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertSee('cn-cmp-nav-count', false)
            ->assertSee(route('jobseeker.messages.index'), false);

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.index'))
            ->assertOk()
            ->assertSee('cn-cmp-nav-count', false)
            ->assertSee(route('employer.messages.index'), false);

        $this->assertSame(1, ApplicationConversation::unreadTotalForRole('jobseeker', (int) $seeker->JobSeekId));
        $this->assertSame(1, ApplicationConversation::unreadTotalForRole('employer', (int) $employer->EmployerId));
    }

    public function test_the_job_seeker_application_page_links_to_the_thread(): void
    {
        $seeker = $this->seeker('Priya Raman');
        $employerId = $this->employer('Northwind Ltd');
        $applicationId = $this->application($seeker, $employerId, 'Accepted');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk()
            ->assertSee('Chat with Employer');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.show', $this->threadFor($applicationId)))
            ->assertOk()
            ->assertSee('Northwind Ltd');
    }

    // ─────────────────────── a database without the tables ───────────────────────

    public function test_an_unmigrated_database_still_renders_both_pages(): void
    {
        [$seeker, $employer, , $applicationId] = $this->approvedThreadPair();

        Schema::drop('application_chat_messages');
        Schema::drop('application_conversations');

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.messages.index'))
            ->assertOk()
            ->assertSee('php artisan migrate');

        $this->actAs($employer, 'employer')
            ->get(route('employer.messages.index'))
            ->assertOk()
            ->assertSee('php artisan migrate');

        // The application page simply hides the chat button.
        $page = $this->actAs($employer, 'employer')
            ->get(route('employer.applications.show', $applicationId));

        $page->assertOk();
        $this->assertStringNotContainsString('Chat with Applicant', (string) $page->getContent());
    }

    public function test_ajax_on_a_missing_database_answers_as_json_instead_of_html(): void
    {
        [$seeker, , , $applicationId] = $this->approvedThreadPair();

        Schema::drop('application_chat_messages');
        Schema::drop('application_conversations');

        $this->actAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.messages.send', 1), ['message' => 'hello'])
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->actAs($seeker, 'jobseeker')
            ->getJson(route('jobseeker.messages.poll', 1))
            ->assertStatus(503);

        $this->actAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $applicationId))
            ->assertOk();
    }

    // ─────────────────────── the service on its own ───────────────────────

    public function test_the_service_reads_a_status_the_way_the_rest_of_the_product_does(): void
    {
        $chat = app(ApplicationChatService::class);

        $this->assertSame(ApplicationChatService::STATE_OPEN, $chat->stateFor('Accepted'));
        $this->assertSame(ApplicationChatService::STATE_SUSPENDED, $chat->stateFor('Pending'));
        $this->assertSame(ApplicationChatService::STATE_CLOSED, $chat->stateFor('Rejected'));
        $this->assertSame(ApplicationChatService::STATE_CLOSED, $chat->stateFor('Withdrawn'));
        // A stage that is not a decision, or a status nobody recognises, only pauses
        // the thread rather than claiming the application was turned down.
        $this->assertSame(ApplicationChatService::STATE_SUSPENDED, $chat->stateFor('Interview Scheduled'));
        $this->assertSame(ApplicationChatService::STATE_SUSPENDED, $chat->stateFor(null));
    }

    public function test_the_service_writes_plain_text_only(): void
    {
        $chat = app(ApplicationChatService::class);

        $this->assertSame(
            "Two lines\nand a tab spaced word",
            $chat->cleanMessage("Two lines\r\nand a tab\tspaced  word"),
        );
        $this->assertSame('bellgone', $chat->cleanMessage("bell\x07gone"));
        $this->assertSame('', $chat->cleanMessage("  \t \n "));
        $this->assertSame('', $chat->cleanMessage(null));
        $this->assertSame('', $chat->cleanMessage(['array']));
    }

    // ─────────────────────── helpers ───────────────────────

    /**
     * Sign in as one role for the next request.
     *
     * The guards resolved by the previous request would still count as logged in, and
     * the identity middleware reads two live role guards as a stolen session, so the
     * guard cache is dropped first the way a new incoming request would.
     */
    private function actAs(mixed $account, string $role): self
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($account, $role);
    }

    /**
     * Both participants of one approved application, their thread, and its application.
     *
     * @return array{0: JobSeeker, 1: Employer, 2: int, 3: int}
     */
    private function approvedThreadPair(string $company = 'Northwind Ltd'): array
    {
        $seeker = $this->seeker('Priya Raman');
        $employer = $this->employerModel($company);
        $applicationId = $this->application($seeker, (int) $employer->EmployerId, 'Accepted');

        return [$seeker, $employer, $this->threadFor($applicationId), $applicationId];
    }

    /**
     * The thread an approval owns, opened through the same service the pages use.
     *
     * Writing a Status straight to application_master is the employer's own decision,
     * but the thread only appears once something asks for it.
     */
    private function threadFor(int $applicationId): int
    {
        $thread = app(ApplicationChatService::class)
            ->openFor(Application::query()->findOrFail($applicationId));

        $this->assertNotNull($thread, 'An approved application should own a conversation.');

        return (int) $thread->id;
    }

    /** One stored message, written directly so the clock and the sender stay exact. */
    private function threadRow(int $threadId, string $role, int $senderId, string $text): int
    {
        return (int) DB::table('application_chat_messages')->insertGetId([
            'ConversationId' => $threadId,
            'SenderId' => $senderId,
            'SenderRole' => $role,
            'message' => $text,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** The employer page's own write, so the tests react to real status values. */
    private function statusMove(int $applicationId, string $status): void
    {
        DB::table('application_master')->where('ApplicationId', $applicationId)->update(['Status' => $status]);
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

    /** A job of this employer and one application against it. */
    private function application(JobSeeker $seeker, int $employerId, string $status = 'Accepted'): int
    {
        $jobId = (int) DB::table('job_master')->insertGetId([
            'EmployerId' => $employerId,
            'CompanyName' => 'Northwind Ltd',
            'JobTitle' => 'Reporting Analyst',
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
}
