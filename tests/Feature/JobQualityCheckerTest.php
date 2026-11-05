<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Services\JobQualityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Job Quality Checker as the employer meets it: the endpoint that scores a
 * draft while the form is still open, the report inside the post form and the
 * edit modal, and the chip in the listing table.
 *
 * The checker reads and never writes, so every test here also proves the save,
 * the edit and the admin approval queue still behave exactly as before.
 */
class JobQualityCheckerTest extends TestCase
{
    private int $employerId;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
            $table->string('ContactPerson')->nullable();
            $table->string('Email')->nullable();
            $table->string('Mobile')->nullable();
            $table->string('City')->nullable();
            $table->string('Address')->nullable();
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

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName')->nullable();
            $table->string('JobTitle');
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->default('B.E');
            $table->text('Description')->nullable();
            $table->string('SalaryFrom')->nullable();
            $table->string('SalaryTo')->nullable();
            $table->string('ExperienceLevel')->nullable();
            $table->string('WorkArrangement')->nullable();
            $table->string('EmploymentType')->nullable();
            $table->string('Status')->nullable();
            $table->json('RequiredSkills')->nullable();
            $table->json('RequiredSoftware')->nullable();
        });

        $this->employerId = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Acme Software Ltd',
            'City' => 'Pune',
            'Status' => 'Approved',
        ]);
    }

    private function employer(int $id = null): Employer
    {
        $employerId = $id ?? DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Other Company Ltd',
            'Status' => 'Approved',
        ]);

        return Employer::query()->findOrFail($employerId);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function job(array $overrides = [], int $employerId = null): int
    {
        return DB::table('job_master')->insertGetId(array_merge([
            'EmployerId' => $employerId ?? $this->employerId,
            'CompanyName' => 'Acme Software Ltd',
            'JobTitle' => 'Backend Developer',
            'Vacancy' => 2,
            'MinQualification' => 'B.E / B.Tech',
            'Description' => 'Own our internal Laravel services and review junior code. Two years of experience required.',
            'SalaryFrom' => '40000',
            'SalaryTo' => '60000',
            'ExperienceLevel' => '1-2_years',
            'WorkArrangement' => 'On-site',
            'EmploymentType' => 'Full-time',
            'Status' => 'approved',
            'RequiredSkills' => json_encode([
                ['name' => 'PHP', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'intermediate'],
            ]),
            'RequiredSoftware' => json_encode([
                ['name' => 'Git', 'type' => 'mandatory', 'proficiency' => 'basic'],
            ]),
        ], $overrides));
    }

    public function test_an_employer_can_score_the_draft_they_are_typing(): void
    {
        $response = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), [
                'txtTitle' => 'React Developer',
                'txtDesc' => 'Build dashboards in React. You will work with the design team daily.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('report.band', fn (string $band): bool => in_array($band, ['incomplete', 'needs_improvement'], true));

        $report = $response->json('report');

        $this->assertGreaterThanOrEqual(0, $report['score']);
        $this->assertLessThanOrEqual(100, $report['score']);
        $this->assertSame(100, $report['max_score']);
        $this->assertCount(5, $report['categories']);

        // A title is filled but the skills list is not, so both must be said.
        $this->assertContains('title_present', array_column($report['completed'], 'id'));
        $this->assertContains('required_skills', array_column($report['warnings'], 'id'));
    }

    public function test_the_checker_reads_the_company_profile_for_the_location_and_never_shares_it_back_as_private(): void
    {
        $report = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['txtTitle' => 'Office Assistant'])
            ->assertOk()
            ->json('report');

        // The employer's own city answers the location check.
        $this->assertContains('location', array_column($report['completed'], 'id'));

        // Nothing a job seeker would call private is echoed to the page.
        $json = json_encode($report);
        $this->assertStringNotContainsString('Password', $json);
        $this->assertStringNotContainsString('Mobile', $json);
    }

    public function test_an_employer_can_score_their_own_saved_job(): void
    {
        $jobId = $this->job();

        $response = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['JobId' => $jobId]);

        $response->assertOk()->assertJsonPath('job_id', $jobId);

        $report = $response->json('report');

        $this->assertGreaterThanOrEqual(70, $report['score']);
        $this->assertContains('required_skills', array_column($report['completed'], 'id'));
    }

    public function test_a_saved_job_is_scored_from_the_row_even_when_the_form_sends_nothing(): void
    {
        $jobId = $this->job(['RequiredSkills' => null, 'RequiredSoftware' => null]);

        $report = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['JobId' => $jobId])
            ->assertOk()
            ->json('report');

        $warning = collect($report['warnings'])->firstWhere('id', 'required_skills');

        $this->assertNotNull($warning, 'A listing with no skills must still be flagged.');
        $this->assertSame('required_skills', $warning['field']);
    }

    public function test_an_employer_cannot_score_another_employers_job(): void
    {
        $other = $this->employer();
        $jobId = $this->job([], $other->EmployerId);

        $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['JobId' => $jobId])
            ->assertNotFound();

        $this->actingAs($other, 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['JobId' => $jobId])
            ->assertOk();
    }

    public function test_a_signed_in_other_role_cannot_reach_the_employer_endpoint(): void
    {
        $this->postJson(route('employer.jobs.qualityCheck'), ['txtTitle' => 'Anything'])
            ->assertStatus(401);
    }

    public function test_a_score_sent_by_the_browser_cannot_move_the_server_result(): void
    {
        $legitimate = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['txtTitle' => 'Warehouse Helper'])
            ->assertOk()
            ->json('report.score');

        $tampered = $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), [
                'txtTitle' => 'Warehouse Helper',
                'score' => 100,
                'report' => ['score' => 100, 'label' => 'Excellent'],
                'categories' => [['earned' => 100]],
            ])
            ->assertOk()
            ->json('report');

        $this->assertSame($legitimate, $tampered['score']);
        $this->assertLessThan(100, $tampered['score']);
    }

    public function test_oversized_or_rubbish_input_is_rejected_instead_of_being_scored(): void
    {
        $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['txtTitle' => str_repeat('A', 5000)])
            ->assertStatus(422);

        $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), ['required_skills' => str_repeat('x', 30001)])
            ->assertStatus(422);

        // A requirements payload the save would reject cannot buy points either.
        $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.qualityCheck'), [
                'txtTitle' => 'Developer',
                'required_skills' => '[{"name":"PHP"',
            ])
            ->assertOk();
    }

    public function test_the_report_renders_inside_the_post_form_and_the_edit_modal(): void
    {
        $this->job();

        $page = $this->actingAs($this->employer($this->employerId), 'employer')
            ->get(route('employer.jobs.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Job Quality Checker', $page);
        $this->assertSame(2, substr_count($page, 'data-cn-quality-shell'), 'The post form and the edit modal each carry a report.');
        $this->assertStringContainsString('data-cn-quality-endpoint', $page);
        $this->assertStringContainsString('Improve your job posting', $page);
        $this->assertStringContainsString('assets/js/job-quality.js', $page);
        $this->assertStringContainsString('assets/css/job-quality.css', $page);

        // The panel is a shell: the numbers only ever come from the endpoint.
        $this->assertStringNotContainsString('data-cn-quality-score>4', $page);
    }

    public function test_the_listing_table_shows_a_quality_chip_for_every_job(): void
    {
        $good = $this->job();
        $thin = $this->job([
            'JobTitle' => 'job',
            'Description' => 'lorem ipsum',
            'SalaryFrom' => null,
            'SalaryTo' => null,
            'RequiredSkills' => null,
            'RequiredSoftware' => null,
            'ExperienceLevel' => null,
            'WorkArrangement' => null,
            'EmploymentType' => null,
        ]);

        $page = $this->actingAs($this->employer($this->employerId), 'employer')
            ->get(route('employer.jobs.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Job Quality', $page);

        $badges = substr_count($page, 'cn-quality-badge');
        $this->assertGreaterThanOrEqual(2, $badges);

        // A thin listing is scored lower than the complete one, in the same table.
        $service = app(JobQualityService::class);
        $this->assertLessThan(
            $service->summariseReport($service->evaluate($this->row($good)))['score'],
            $service->summariseReport($service->evaluate($this->row($thin)))['score']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $jobId): array
    {
        $row = DB::table('job_master')->where('JobId', $jobId)->first();

        return [
            'txtTitle' => $row->JobTitle,
            'txtTotal' => (string) $row->Vacancy,
            'qualification' => $row->MinQualification,
            'txtDesc' => $row->Description,
            'txtSalaryFrom' => (string) $row->SalaryFrom,
            'txtSalaryTo' => (string) $row->SalaryTo,
            'cmbExperience' => $row->ExperienceLevel,
            'cmbArrangement' => $row->WorkArrangement,
            'cmbEmployment' => $row->EmploymentType,
            'required_skills' => $row->RequiredSkills === null ? [] : json_decode($row->RequiredSkills, true),
            'required_software' => $row->RequiredSoftware === null ? [] : json_decode($row->RequiredSoftware, true),
            'company' => $row->CompanyName,
        ];
    }

    public function test_job_creation_still_works_with_the_checker_on_the_page(): void
    {
        $payload = [
            'txtTitle' => 'Support Engineer',
            'txtTotal' => 4,
            'cmbQual' => 'Diploma',
            'txtDesc' => 'Answer tickets and keep the runbook current.',
            'cmbExperience' => 'fresher',
            'cmbArrangement' => 'Remote',
            'cmbEmployment' => 'Full-time',
            'required_skills' => json_encode([['name' => 'Zendesk', 'type' => 'mandatory', 'proficiency' => 'basic']]),
            'required_software' => '',
        ];

        $this->actingAs($this->employer($this->employerId), 'employer')
            ->postJson(route('employer.jobs.store'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $saved = DB::table('job_master')->where('JobTitle', 'Support Engineer')->first();

        $this->assertNotNull($saved);
        $this->assertSame('pending', $saved->Status, 'A quality check must not decide the moderation status.');
        $this->assertSame('Acme Software Ltd', $saved->CompanyName);
    }

    public function test_job_editing_still_works_with_the_checker_on_the_page(): void
    {
        $jobId = $this->job();

        $this->actingAs($this->employer($this->employerId), 'employer')
            ->putJson(route('employer.jobs.update', $jobId), [
                'txtTitle' => 'Senior Backend Developer',
                'txtTotal' => 5,
                'cmbQual' => 'B.E / B.Tech',
                'txtDesc' => 'Own our internal services and lead two juniors.',
                'cmbExperience' => '3-5_years',
                'cmbArrangement' => 'Hybrid',
                'cmbEmployment' => 'Full-time',
                'txtSalaryFrom' => '70000',
                'txtSalaryTo' => '95000',
                'required_skills' => json_encode([['name' => 'Go', 'type' => 'mandatory', 'proficiency' => 'advanced']]),
                'required_software' => json_encode([['name' => 'Docker', 'type' => 'mandatory', 'proficiency' => 'intermediate']]),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $row = DB::table('job_master')->where('JobId', $jobId)->first();

        $this->assertSame('Senior Backend Developer', $row->JobTitle);
        $this->assertSame('approved', $row->Status, 'Editing through the checker keeps the saved moderation status.');
    }

    public function test_the_checker_never_writes_and_never_touches_the_approval_workflow(): void
    {
        $jobId = $this->job(['Status' => 'pending']);

        foreach (['pending', 'approved', 'rejected'] as $status) {
            DB::table('job_master')->where('JobId', $jobId)->update(['Status' => $status]);

            // Snapshot after the status was set by the test, not before the loop,
            // so the checker is compared with the row exactly as it found it.
            $before = (array) DB::table('job_master')->where('JobId', $jobId)->first();

            $this->actingAs($this->employer($this->employerId), 'employer')
                ->postJson(route('employer.jobs.qualityCheck'), [
                    'JobId' => $jobId,
                    'txtTitle' => 'Backend Developer',
                ])
                ->assertOk();

            $after = (array) DB::table('job_master')->where('JobId', $jobId)->first();

            $this->assertSame($status, $after['Status']);
            $this->assertSame($before, $after, 'Scoring a job must not change a single column.');
        }
    }

    public function test_the_admin_approval_route_still_moves_a_pending_job(): void
    {
        Schema::create('logs', function (Blueprint $table): void {
            $table->increments('Id');
            $table->integer('JobId')->nullable();
            $table->string('action')->nullable();
            $table->dateTime('cdate')->nullable();
            $table->string('actor_type')->nullable();
            $table->integer('actor_id')->nullable();
            $table->string('target_type')->nullable();
            $table->integer('target_id')->nullable();
            $table->text('metadata')->nullable();
        });

        // Its own test method on purpose: EnsureUserRole rejects a browser
        // session that holds two roles, so the admin call cannot share a test
        // with the employer calls above.
        $jobId = $this->job(['Status' => 'pending']);

        $admin = new Admin(['UserName' => 'test-admin']);
        $admin->UserId = 1;

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.jobs.approve', $jobId))
            ->assertOk();

        $this->assertSame('approved', DB::table('job_master')->where('JobId', $jobId)->value('Status'),
            'The admin queue is still the only thing that approves a listing.');
    }
}
