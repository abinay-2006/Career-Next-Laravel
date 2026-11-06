<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\JobSeeker;
use App\Models\JobSeekerApplicationFollowUp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The coach only ever reads application rows and writes the seeker's own follow-up
 * entries, so these tests are mostly about who can see what and about the
 * employer-owned status never moving.
 */
class ApplicationFollowUpCoachTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The bands count days, so the day the suite runs cannot change a result.
        Carbon::setTestNow('2026-11-06 12:00:00');

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->string('CompanyName');
            $table->string('JobTitle');
            $table->text('Description')->nullable();
            $table->string('RequiredSkills')->nullable();
            $table->string('RequiredSoftware')->nullable();
            $table->string('Status')->nullable();
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ─────────────────────── who can see it ───────────────────────

    public function test_the_coach_lists_only_the_signed_in_seekers_applications(): void
    {
        $mine = $this->seeker('Seeker One');
        $other = $this->seeker('Seeker Two');
        $this->application($mine, 'Northwind Analyst', 'Northwind Technologies', 20);
        $this->application($other, 'Globex Private Role', 'Globex Corporation', 20);

        $this->actingAs($mine, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Application Follow-up Coach')
            ->assertSee('Northwind Analyst')
            ->assertDontSee('Globex Private Role');
    }

    public function test_the_coach_requires_job_seeker_authentication(): void
    {
        $this->get(route('jobseeker.followup.index'))->assertRedirect(route('login'));
    }

    public function test_an_employer_cannot_open_the_follow_up_coach(): void
    {
        $employer = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Northwind Technologies', 'Status' => 'Approved']);

        $this->actingAs(Employer::query()->findOrFail($employer), 'employer')
            ->get(route('jobseeker.followup.index'))->assertForbidden();
    }

    public function test_no_coach_endpoint_works_for_another_seekers_application(): void
    {
        $mine = $this->seeker('Seeker One');
        $other = $this->seeker('Seeker Two');
        $theirs = $this->application($other, 'Globex Private Role', 'Globex Corporation', 20);

        $this->actingAs($mine, 'jobseeker')->postJson(route('jobseeker.followup.message', $theirs), [
            'template' => 'status_inquiry',
        ])->assertNotFound();

        $this->actingAs($mine, 'jobseeker')->postJson(route('jobseeker.followup.activity', $theirs), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT,
            'activity_date' => '2026-11-05',
        ])->assertNotFound();

        $this->actingAs($mine, 'jobseeker')->postJson(route('jobseeker.followup.prep', $theirs), [
            'item' => 're-read',
            'done' => true,
        ])->assertNotFound();

        $this->assertDatabaseCount('jobseeker_application_follow_ups', 0);
    }

    // ─────────────────────── recording activity ───────────────────────

    public function test_recording_a_follow_up_changes_the_advice_and_leaves_the_official_status_alone(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.followup.activity', $application), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT,
            'activity_date' => '2026-11-03',
            'note' => 'Emailed the hiring team.',
        ])->assertOk()->assertJson(['success' => true, 'refresh' => true]);

        $this->assertDatabaseHas('jobseeker_application_follow_ups', [
            'ApplicationId' => $application,
            'JobSeekId' => $seeker->JobSeekId,
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT,
        ]);

        // The employer's workflow still owns the status.
        $this->assertDatabaseHas('application_master', ['ApplicationId' => $application, 'Status' => 'Pending']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('You followed up 3 days ago')
            ->assertSee('Emailed the hiring team.');
    }

    public function test_a_private_note_is_never_shown_to_another_seeker(): void
    {
        $owner = $this->seeker('Owner');
        $stranger = $this->seeker('Stranger');
        $application = $this->application($owner, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($owner, 'jobseeker')->postJson(route('jobseeker.followup.activity', $application), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_NOTE,
            'activity_date' => '2026-11-01',
            'note' => 'Told me the salary band is ninety thousand.',
        ])->assertOk();

        $this->actingAs($owner, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()->assertSee('Told me the salary band is ninety thousand.');

        $this->actingAs($stranger, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()->assertDontSee('Told me the salary band is ninety thousand.');
    }

    public function test_something_that_already_happened_cannot_be_dated_in_the_future(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.followup.activity', $application), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_FOLLOW_UP_SENT,
            'activity_date' => '2026-11-20',
        ])->assertStatus(422)->assertJsonValidationErrors('activity_date');

        $this->assertDatabaseCount('jobseeker_application_follow_ups', 0);
    }

    public function test_a_future_expected_response_date_is_allowed_and_holds_the_advice_back(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.followup.activity', $application), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_EXPECTED_RESPONSE,
            'activity_date' => '2026-11-10',
        ])->assertOk();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('expecting a response by');
    }

    // ─────────────────────── drafts ───────────────────────

    public function test_a_draft_is_handed_back_as_text_and_nothing_is_sent(): void
    {
        Mail::fake();

        $seeker = $this->seeker('Priya Sharma');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $response = $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.message', $application), ['template' => 'status_inquiry'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $body = $response->json('body');
        $this->assertNotEmpty($body);
        $this->assertStringContainsString('Priya Sharma', $body);
        $this->assertStringContainsString('Northwind Technologies', $body);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('application_master', ['ApplicationId' => $application, 'Status' => 'Pending']);
        $this->assertDatabaseCount('jobseeker_application_follow_ups', 0);
    }

    public function test_a_draft_never_invents_an_interview_date(): void
    {
        $seeker = $this->seeker('Priya Sharma');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20, 'Interview Scheduled');

        $response = $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.message', $application), ['template' => 'post_interview'])
            ->assertOk();

        $this->assertNotContains('interview_on', $response->json('used'));
        $this->assertStringContainsString('interview date', $response->json('note'));
    }

    public function test_an_unknown_template_is_refused(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.message', $application), ['template' => 'free_text'])
            ->assertStatus(422)->assertJsonValidationErrors('template');
    }

    // ─────────────────────── preferences ───────────────────────

    public function test_the_follow_up_interval_rescales_the_advice_and_survives_the_next_request(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 15);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()->assertSee('There has been no recorded update for 15 days');

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.preferences'), ['days' => 14])
            ->assertOk()->assertJsonPath('interval', 14);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()->assertSee('Consider following up')->assertDontSee('There has been no recorded update for 15 days');
    }

    public function test_an_unusable_interval_is_refused(): void
    {
        $seeker = $this->seeker('Seeker One');

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.preferences'), ['days' => 500])
            ->assertStatus(422)->assertJsonValidationErrors('days');
    }

    // ─────────────────────── timeline and preparation ───────────────────────

    public function test_the_timeline_shows_recorded_events_and_nothing_else(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->log('application_submitted', $application, 20);
        $this->log('application_status_changed', $application, 6, ['from' => 'Pending', 'to' => 'Rejected']);
        $this->log('job_viewed', $application, 4);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('Status changed to Rejected')
            ->assertDontSee('job_viewed')
            ->assertDontSee('Reviewed')
            ->assertDontSee('Shortlisted');
    }

    public function test_interview_preparation_only_appears_when_a_status_records_an_interview(): void
    {
        $seeker = $this->seeker('Seeker One');
        $pending = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);
        $interview = $this->application($seeker, 'Globex Engineer', 'Globex Corporation', 4, 'Interview Scheduled', [
            'RequiredSkills' => json_encode([['name' => 'React', 'type' => 'mandatory']]),
        ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index', ['search' => 'Globex']))
            ->assertOk()
            ->assertSee('Interview preparation')
            ->assertSee('Re-read the job description')
            ->assertSee('React');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index', ['search' => 'Northwind']))
            ->assertOk()
            ->assertDontSee('Interview preparation');

        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.prep', $interview), ['item' => 're-read', 'done' => true])
            ->assertOk()->assertJsonPath('done', ['re-read']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index', ['search' => 'Globex']))
            ->assertOk()->assertSee('aria-pressed="true"', false);

        // The checklist is the seeker's own; a tick on one application says nothing about another.
        $this->actingAs($seeker, 'jobseeker')
            ->postJson(route('jobseeker.followup.prep', $pending), ['item' => 're-read', 'done' => false])
            ->assertOk()->assertJsonPath('done', []);
    }

    public function test_the_coach_opens_without_the_follow_up_table_and_explains_the_migration(): void
    {
        Schema::drop('jobseeker_application_follow_ups');

        $seeker = $this->seeker('Seeker One');
        $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()
            ->assertSee('php artisan migrate');
    }

    public function test_the_cards_paginate_and_a_literal_wildcard_in_search_is_not_a_pattern(): void
    {
        $seeker = $this->seeker('Seeker One');
        for ($day = 1; $day <= 12; $day++) {
            $this->application($seeker, sprintf('Role %02d', $day), 'Northwind Technologies', $day);
        }

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index'))
            ->assertOk()->assertSee('12 applications in this group.')->assertSee('Role 01')->assertDontSee('Role 11');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index', ['page' => 2]))
            ->assertOk()->assertSee('Role 11')->assertSee('Role 12');

        // "1%" has to be looked for as the characters the seeker typed, not as a LIKE pattern.
        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.followup.index', ['search' => '1%']))
            ->assertOk()->assertSee('Nothing in this group');
    }

    // ─────────────────────── the rest of the product ───────────────────────

    public function test_the_existing_application_history_is_untouched(): void
    {
        $seeker = $this->seeker('Seeker One');
        $application = $this->application($seeker, 'Northwind Analyst', 'Northwind Technologies', 20);

        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.followup.activity', $application), [
            'activity_type' => JobSeekerApplicationFollowUp::TYPE_RESPONSE_RECEIVED,
            'activity_date' => '2026-11-02',
        ])->assertOk();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.index'))
            ->assertOk()->assertSee('Northwind Analyst');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $application))
            ->assertOk()->assertSee('Application Details');

        $this->assertSame(1, DB::table('application_master')->where('Status', 'Pending')->count());
    }

    // ─────────────────────── helpers ───────────────────────

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => $name, 'Status' => 'Approved']);

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  array<string, string|null>  $job
     */
    private function application(JobSeeker $seeker, string $title, string $company, ?int $daysAgo, string $status = 'Pending', array $job = []): int
    {
        $jobId = DB::table('job_master')->insertGetId($job + [
            'CompanyName' => $company,
            'JobTitle' => $title,
            'Description' => 'Build and maintain reporting dashboards.',
            'Status' => 'approved',
        ]);

        return DB::table('application_master')->insertGetId([
            'JobSeekId' => $seeker->JobSeekId,
            'JobId' => $jobId,
            'Status' => $status,
            'Description' => 'Applied with resume.',
            'ApplicationDate' => $daysAgo === null ? null : Carbon::now()->subDays($daysAgo),
        ]);
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function log(string $action, int $applicationId, int $daysAgo, array $metadata = []): void
    {
        DB::table('logs')->insert([
            'JobId' => null,
            'action' => $action,
            'cdate' => Carbon::now()->subDays($daysAgo),
            'actor_type' => 'employer',
            'actor_id' => 1,
            'target_type' => 'application',
            'target_id' => $applicationId,
            'metadata' => $metadata === [] ? null : json_encode($metadata),
        ]);
    }
}
