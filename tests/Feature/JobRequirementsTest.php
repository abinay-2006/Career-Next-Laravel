<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobRequirementsTest extends TestCase
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
            $table->string('Status')->nullable();
            $table->json('RequiredSkills')->nullable();
            $table->json('RequiredSoftware')->nullable();
        });

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
        });

        Schema::create('jobseeker_resumes', function (Blueprint $table): void {
            $table->id();
            $table->integer('JobSeekId');
            $table->string('title')->default('My Resume');
            $table->string('template')->default('classic');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status')->nullable();
            $table->text('Description')->nullable();
            $table->dateTime('ApplicationDate')->nullable();
        });

        $this->employerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Career Next Demo']);
    }

    private function employer(): Employer
    {
        return Employer::query()->findOrFail($this->employerId);
    }

    private function seeker(string $name, string $skills): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ]);

        DB::table('jobseeker_resumes')->insert([
            'JobSeekId' => $id,
            'title' => 'Saved Resume',
            'template' => 'classic',
            'content' => json_encode(['skills' => $skills]),
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-03-01 10:00:00',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  list<array{name: string, type?: string, proficiency?: string|null}>  $skills
     * @param  list<array{name: string, type?: string, proficiency?: string|null}>  $software
     */
    private function job(array $skills = [], array $software = [], string $status = 'approved'): int
    {
        return DB::table('job_master')->insertGetId([
            'EmployerId' => $this->employer()->EmployerId,
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Backend Developer',
            'Vacancy' => 2,
            'MinQualification' => 'B.E',
            'Description' => 'Own our internal services.',
            'Status' => $status,
            'RequiredSkills' => $skills === [] ? null : json_encode($skills),
            'RequiredSoftware' => $software === [] ? null : json_encode($software),
        ]);
    }

    private function storedRequirements(int $jobId): array
    {
        return [
            'skills' => json_decode((string) DB::table('job_master')->where('JobId', $jobId)->value('RequiredSkills'), true),
            'software' => json_decode((string) DB::table('job_master')->where('JobId', $jobId)->value('RequiredSoftware'), true),
        ];
    }

    public function test_the_post_and_edit_forms_render_both_requirement_pickers(): void
    {
        $jobId = $this->job([['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced']]);

        $page = $this->actingAs($this->employer(), 'employer')
            ->get(route('employer.jobs.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($page, 'data-cn-req-picker="required_skills"'), 'The post form and the edit modal each need a skills picker.');
        $this->assertSame(2, substr_count($page, 'data-cn-req-picker="required_software"'));
        $this->assertStringContainsString('id="editSkillPicker"', $page);

        // The edit modal reads each row's own copy because an AJAX refresh only
        // replaces the dashboard region; a page-level cache would go stale.
        preg_match('/data-job-id="' . $jobId . '"\s+data-requirements="([^"]*)"/', $page, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Each job row must carry its saved requirements.');

        $saved = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertSame(
            [['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced']],
            $saved['skills']
        );
        $this->assertSame([], $saved['software']);
        $this->assertStringNotContainsString('window.JOB_REQUIREMENTS', $page);
    }

    public function test_an_employer_can_post_a_job_with_classified_skills_and_software(): void
    {
        $payload = [
            'txtTitle' => 'Backend Developer',
            'txtTotal' => 2,
            'cmbQual' => 'B.E',
            'txtDesc' => 'Own our internal services.',
            'required_skills' => json_encode([
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Teamwork', 'type' => 'preferred', 'proficiency' => null],
                ['name' => 'In-house framework', 'type' => 'mandatory', 'proficiency' => 'basic'],
            ]),
            'required_software' => json_encode([
                ['name' => 'Postman', 'type' => 'mandatory', 'proficiency' => null],
            ]),
        ];

        $this->actingAs($this->employer(), 'employer')
            ->postJson(route('employer.jobs.store'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $jobId = (int) DB::table('job_master')->max('JobId');

        $this->assertSame([
            ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
            ['name' => 'Teamwork', 'type' => 'preferred', 'proficiency' => null],
            ['name' => 'In-house framework', 'type' => 'mandatory', 'proficiency' => 'basic'],
        ], $this->storedRequirements($jobId)['skills']);

        $this->assertSame([
            ['name' => 'Postman', 'type' => 'mandatory', 'proficiency' => null],
        ], $this->storedRequirements($jobId)['software']);
    }

    public function test_repeated_skill_names_are_stored_once(): void
    {
        $this->actingAs($this->employer(), 'employer')
            ->postJson(route('employer.jobs.store'), [
                'txtTitle' => 'Repeat Job',
                'txtTotal' => 1,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Description.',
                'required_skills' => json_encode([
                    ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
                    ['name' => ' laravel ', 'type' => 'preferred', 'proficiency' => null],
                    ['name' => 'React', 'type' => 'mandatory', 'proficiency' => null],
                ]),
            ])
            ->assertOk();

        $stored = $this->storedRequirements((int) DB::table('job_master')->max('JobId'))['skills'];

        $this->assertCount(2, $stored);
        $this->assertSame(['Laravel', 'React'], array_column($stored, 'name'));
        $this->assertSame('mandatory', $stored[0]['type']);
    }

    public function test_malicious_or_malformed_requirement_payloads_are_rejected_without_creating_a_job(): void
    {
        $bad = [
            'not json at all',
            json_encode(['Laravel']),
            json_encode(['name' => 'Laravel']),
            json_encode([['name' => '', 'type' => 'mandatory']]),
            json_encode([['name' => 'Laravel', 'type' => 'nice-to-have']]),
            json_encode([['name' => 'Laravel', 'proficiency' => 'genius']]),
            json_encode([['name' => str_repeat('x', 101), 'type' => 'mandatory']]),
            json_encode(array_fill(0, 31, ['name' => 'Skill', 'type' => 'mandatory'])),
        ];

        foreach ($bad as $index => $payload) {
            $response = $this->actingAs($this->employer(), 'employer')
                ->postJson(route('employer.jobs.store'), [
                    'txtTitle' => 'Rejected '.$index,
                    'txtTotal' => 1,
                    'cmbQual' => 'B.E',
                    'txtDesc' => 'Description.',
                    'required_skills' => $payload,
                ]);

            $response->assertStatus(422)->assertJsonValidationErrors('required_skills');
        }

        $this->assertSame(0, DB::table('job_master')->count());
    }

    public function test_a_full_page_post_keeps_every_typed_value_when_validation_fails(): void
    {
        $response = $this->actingAs($this->employer(), 'employer')
            ->post(route('employer.jobs.store'), [
                'txtTitle' => 'Backend Developer',
                'txtTotal' => 2,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Own our internal services.',
                'required_skills' => 'broken json',
                'required_software' => json_encode([['name' => 'Figma', 'type' => 'mandatory', 'proficiency' => null]]),
            ]);

        $response->assertSessionHasErrors('required_skills');

        $old = session()->get('_old_input');
        $this->assertSame('broken json', $old['required_skills'] ?? null);
        $this->assertSame('Backend Developer', $old['txtTitle'] ?? null);
        $this->assertSame(0, DB::table('job_master')->count());
    }

    public function test_an_employer_can_replace_and_clear_stored_requirements(): void
    {
        $jobId = $this->job([['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null]]);

        $this->actingAs($this->employer(), 'employer')
            ->putJson(route('employer.jobs.update', $jobId), [
                'txtTitle' => 'Backend Developer',
                'txtTotal' => 2,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Own our internal services.',
                'required_skills' => json_encode([['name' => 'Node.js', 'type' => 'preferred', 'proficiency' => 'basic']]),
                'required_software' => json_encode([['name' => 'Git', 'type' => 'mandatory', 'proficiency' => null]]),
            ])
            ->assertOk();

        $this->assertSame([
            ['name' => 'Node.js', 'type' => 'preferred', 'proficiency' => 'basic'],
        ], $this->storedRequirements($jobId)['skills']);

        $this->actingAs($this->employer(), 'employer')
            ->putJson(route('employer.jobs.update', $jobId), [
                'txtTitle' => 'Backend Developer',
                'txtTotal' => 2,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Own our internal services.',
                'required_skills' => '',
                'required_software' => '[]',
            ])
            ->assertOk();

        $this->assertNull($this->storedRequirements($jobId)['skills']);
        $this->assertNull($this->storedRequirements($jobId)['software']);
    }

    public function test_a_request_without_requirement_fields_leaves_stored_requirements_untouched(): void
    {
        $jobId = $this->job([['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null]]);

        $this->actingAs($this->employer(), 'employer')
            ->putJson(route('employer.jobs.update', $jobId), [
                'txtTitle' => 'Renamed Role',
                'txtTotal' => 3,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Own our internal services.',
            ])
            ->assertOk();

        $this->assertSame([
            ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
        ], $this->storedRequirements($jobId)['skills']);
        $this->assertSame('Renamed Role', DB::table('job_master')->where('JobId', $jobId)->value('JobTitle'));
    }

    public function test_another_employer_cannot_change_a_jobs_requirements(): void
    {
        $ownerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Owner Co']);
        $jobId = DB::table('job_master')->insertGetId([
            'EmployerId' => $ownerId,
            'CompanyName' => 'Owner Co',
            'JobTitle' => 'Private Role',
            'Vacancy' => 1,
            'MinQualification' => 'B.E',
            'Description' => 'Private.',
            'Status' => 'pending',
            'RequiredSkills' => json_encode([['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null]]),
        ]);

        $this->actingAs($this->employer(), 'employer')
            ->putJson(route('employer.jobs.update', $jobId), [
                'txtTitle' => 'Hijacked',
                'txtTotal' => 1,
                'cmbQual' => 'B.E',
                'txtDesc' => 'Changed.',
                'required_skills' => json_encode([['name' => 'Excel', 'type' => 'mandatory', 'proficiency' => null]]),
            ])
            ->assertNotFound();

        $this->assertSame([
            ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
        ], $this->storedRequirements($jobId)['skills']);
    }

    public function test_the_job_details_page_lists_mandatory_and_preferred_requirements_with_levels(): void
    {
        $jobId = $this->job(
            [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Teamwork', 'type' => 'preferred', 'proficiency' => null],
            ],
            [
                ['name' => 'Docker', 'type' => 'mandatory', 'proficiency' => null],
            ]
        );

        $this->actingAs($this->seeker('Detail Seeker', 'Laravel'), 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('Required Skills &amp; Software', false)
            ->assertSee('Mandatory skills')
            ->assertSee('Preferred skills')
            ->assertSee('Mandatory software')
            ->assertSee('Advanced')
            ->assertSee('Teamwork');
    }

    public function test_a_legacy_job_shows_no_requirements_section_and_keeps_the_old_match(): void
    {
        $jobId = $this->job();

        $this->actingAs($this->seeker('Legacy Seeker', 'Laravel, MySQL'), 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertDontSee('Required Skills')
            ->assertSee('Matching skills')
            ->assertSee('Skills not found in this job');
    }

    public function test_structured_requirements_drive_the_smart_job_match_weighting(): void
    {
        $jobId = $this->job(
            [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
                ['name' => 'MySQL', 'type' => 'mandatory', 'proficiency' => null],
                ['name' => 'Docker', 'type' => 'preferred', 'proficiency' => null],
            ],
            [
                ['name' => 'Figma', 'type' => 'preferred', 'proficiency' => null],
            ]
        );

        $this->actingAs($this->seeker('Match Seeker', 'Laravel, Docker'), 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('60%')
            ->assertSee('Requirements your profile covers')
            ->assertSee('Mandatory:</strong> Laravel', false)
            ->assertSee('Preferred:</strong> Docker', false)
            ->assertSee('Requirements not in your profile')
            ->assertSee('Not listed: Figma')
            ->assertDontSee('Matching skills');
    }

    public function test_job_cards_summarise_required_skills_without_listing_everything(): void
    {
        $jobId = $this->job([
            ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
            ['name' => 'MySQL', 'type' => 'mandatory', 'proficiency' => null],
            ['name' => 'Redis', 'type' => 'mandatory', 'proficiency' => null],
            ['name' => 'Kafka', 'type' => 'mandatory', 'proficiency' => null],
            ['name' => 'Teamwork', 'type' => 'preferred', 'proficiency' => null],
        ]);

        $page = $this->actingAs($this->seeker('Card Seeker', 'Laravel'), 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<div class="job-req-card-summary">', $page);
        $this->assertStringContainsString('+1 more', $page);
        $this->assertStringNotContainsString('job-req-badge">Teamwork', $page);
        $this->assertStringContainsString('ID: '.$jobId, $page);
    }

    public function test_the_admin_details_modal_is_given_the_saved_requirements(): void
    {
        $jobId = $this->job(
            [['name' => 'Laravel', 'type' => 'preferred', 'proficiency' => 'advanced'], ['name' => 'MySQL', 'type' => 'mandatory']],
            [['name' => 'Postman', 'type' => 'mandatory']]
        );

        $admin = new Admin(['UserName' => 'cn-verify-admin']);
        $admin->UserId = 1;

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.manage.jobs'))
            ->assertOk()
            ->assertSee('id="mRequiredSkills"', false)
            ->assertSee('id="mRequiredSoftware"', false)
            ->getContent();

        // The modal is filled in JavaScript from the embedded job rows, and the
        // listing query returns these columns as raw JSON strings.
        preg_match('/const jobs = (.*);/', $page, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'The admin modal needs the job rows it reads requirements from.');

        $row = collect(json_decode(html_entity_decode($matches[1], ENT_QUOTES), true))
            ->firstWhere('JobId', $jobId);

        $this->assertSame(
            [['name' => 'Laravel', 'type' => 'preferred', 'proficiency' => 'advanced'], ['name' => 'MySQL', 'type' => 'mandatory']],
            json_decode($row['RequiredSkills'], true)
        );
        $this->assertSame([['name' => 'Postman', 'type' => 'mandatory']], json_decode($row['RequiredSoftware'], true));
    }
}
