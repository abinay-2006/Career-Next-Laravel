<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Experience level, work arrangement and employment type on a listing: the
 * employer chooses them once, and seekers, the admin review modal and the
 * preference matcher all read the same three columns.
 */
class JobClassificationTest extends TestCase
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
            $table->string('JobTitle')->nullable();
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->nullable();
            $table->string('ExperienceLevel', 30)->nullable();
            $table->string('WorkArrangement', 30)->nullable();
            $table->string('EmploymentType', 50)->nullable();
            $table->text('Description')->nullable();
            $table->string('SalaryFrom')->nullable();
            $table->string('SalaryTo')->nullable();
            $table->string('Status')->nullable();
            $table->json('RequiredSkills')->nullable();
            $table->json('RequiredSoftware')->nullable();
        });

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });

        Schema::create('jobseeker_resumes', function (Blueprint $table): void {
            $table->id();
            $table->integer('JobSeekId');
            $table->string('title')->default('My Resume');
            $table->string('template')->default('classic');
            $table->json('content')->nullable();
            $table->timestamps();
        });

        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
        });

        Schema::create('jobseeker_career_preferences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('preferred_job_titles')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->decimal('minimum_salary', 12, 2)->nullable();
            $table->decimal('maximum_salary', 12, 2)->nullable();
            $table->string('salary_period', 10)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('employment_types')->nullable();
            $table->json('work_arrangements')->nullable();
            $table->json('preferred_industries')->nullable();
            $table->string('experience_level', 30)->nullable();
            $table->string('willing_to_relocate', 10)->nullable();
            $table->string('notice_period', 20)->nullable();
            $table->string('preferred_working_hours', 10)->nullable();
            $table->string('job_search_status', 30)->nullable();
            $table->timestamp('completed_at')->nullable();
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

        Schema::create('jobseeker_education', function (Blueprint $table): void {
            $table->increments('EduId');
            $table->integer('JobSeekId');
        });

        Schema::create('jobseeker_school_education', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('JobSeekId');
        });

        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocId');
            $table->integer('JobSeekId');
            $table->string('DocumentType');
        });

        $this->employerId = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'ContactPerson' => 'Hiring Desk',
        ]);
    }

    private function employer(): Employer
    {
        return Employer::query()->findOrFail($this->employerId);
    }

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function job(array $overrides = [], string $status = 'approved'): int
    {
        return DB::table('job_master')->insertGetId(array_merge([
            'EmployerId' => $this->employerId,
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Laravel Developer',
            'Vacancy' => 2,
            'MinQualification' => 'B.E',
            'Description' => 'Own our internal services.',
            'Status' => $status,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'txtTitle' => 'Laravel Developer',
            'txtTotal' => '2',
            'cmbQual' => 'B.E / B.Tech',
            'txtDesc' => 'Build and maintain our internal services.',
            'cmbExperience' => '3-5_years',
            'cmbArrangement' => 'Hybrid',
            'cmbEmployment' => 'Full-time',
        ], $overrides);
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(int $jobId): array
    {
        return (array) DB::table('job_master')
            ->where('JobId', $jobId)
            ->first(['ExperienceLevel', 'WorkArrangement', 'EmploymentType']);
    }

    public function test_the_post_and_edit_forms_offer_all_three_classification_selects(): void
    {
        $page = $this->actingAs($this->employer(), 'employer')
            ->get(route('employer.jobs.index'))
            ->assertOk()
            ->getContent();

        foreach (['cmbExperience', 'cmbArrangement', 'cmbEmployment'] as $field) {
            $this->assertSame(
                2,
                substr_count($page, 'name="' . $field . '"'),
                $field . ' belongs in both the post form and the edit modal.'
            );
        }

        $this->assertStringContainsString('Experience Level', $page);
        $this->assertStringContainsString('Work Arrangement', $page);
        $this->assertStringContainsString('Employment Type', $page);
        $this->assertStringContainsString('value="3-5_years"', $page);
        $this->assertStringContainsString('value="On-site"', $page);
        $this->assertStringContainsString('value="Apprenticeship"', $page);
    }

    public function test_posting_a_job_stores_the_chosen_classification(): void
    {
        $this->actingAs($this->employer(), 'employer')
            ->post(route('employer.jobs.store'), $this->form())
            ->assertRedirect();

        $this->assertDatabaseHas('job_master', [
            'JobTitle' => 'Laravel Developer',
            'ExperienceLevel' => '3-5_years',
            'WorkArrangement' => 'Hybrid',
            'EmploymentType' => 'Full-time',
        ]);
    }

    public function test_an_unselected_classification_is_stored_as_not_specified(): void
    {
        $this->actingAs($this->employer(), 'employer')
            ->post(route('employer.jobs.store'), $this->form([
                'cmbExperience' => '',
                'cmbArrangement' => '',
                'cmbEmployment' => '',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('job_master', [
            'JobTitle' => 'Laravel Developer',
            'ExperienceLevel' => null,
            'WorkArrangement' => null,
            'EmploymentType' => null,
        ]);
    }

    public function test_an_unknown_classification_choice_is_rejected(): void
    {
        $this->actingAs($this->employer(), 'employer')
            ->post(route('employer.jobs.store'), $this->form(['cmbArrangement' => 'Astronaut']))
            ->assertSessionHasErrors('cmbArrangement');

        $this->assertSame(0, DB::table('job_master')->count());
    }

    public function test_updating_a_job_replaces_the_classification_it_is_given(): void
    {
        $jobId = $this->job();

        $this->actingAs($this->employer(), 'employer')
            ->put(route('employer.jobs.update', $jobId), $this->form([
                'cmbExperience' => 'entry_level',
                'cmbArrangement' => 'Remote',
                'cmbEmployment' => 'Internship',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('job_master', [
            'JobId' => $jobId,
            'ExperienceLevel' => 'entry_level',
            'WorkArrangement' => 'Remote',
            'EmploymentType' => 'Internship',
        ]);
    }

    public function test_a_request_that_sends_no_classification_leaves_it_untouched(): void
    {
        $jobId = $this->job([
            'ExperienceLevel' => '6-10_years',
            'WorkArrangement' => 'On-site',
            'EmploymentType' => 'Contract',
        ]);

        $form = $this->form();
        unset($form['cmbExperience'], $form['cmbArrangement'], $form['cmbEmployment']);

        $this->actingAs($this->employer(), 'employer')
            ->put(route('employer.jobs.update', $jobId), $form)
            ->assertRedirect();

        $this->assertSame([
            'ExperienceLevel' => '6-10_years',
            'WorkArrangement' => 'On-site',
            'EmploymentType' => 'Contract',
        ], $this->stored($jobId));
    }

    public function test_seekers_see_the_classification_on_cards_and_detail(): void
    {
        $seeker = $this->seeker('Classification Seeker');
        $jobId = $this->job([
            'ExperienceLevel' => '3-5_years',
            'WorkArrangement' => 'Hybrid',
            'EmploymentType' => 'Full-time',
        ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee('3–5 years')
            ->assertSee('Hybrid')
            ->assertSee('Full-time');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('Experience Level')
            ->assertSee('3–5 years')
            ->assertSee('Work Arrangement')
            ->assertSee('Employment Type');
    }

    public function test_a_listing_without_classification_shows_no_extra_details(): void
    {
        $seeker = $this->seeker('Plain Seeker');
        $jobId = $this->job();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertDontSee('Experience Level')
            ->assertDontSee('Work Arrangement')
            ->assertDontSee('Employment Type');
    }

    public function test_the_admin_detail_modal_carries_the_classification(): void
    {
        $this->job([
            'ExperienceLevel' => '1-2_years',
            'WorkArrangement' => 'Remote',
            'EmploymentType' => 'Part-time',
        ]);

        $admin = new Admin(['UserName' => 'classification-admin']);
        $admin->UserId = 1;

        $page = $this->actingAs($admin, 'admin')
            ->get(route('admin.manage.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="mExperience"', $page);
        $this->assertStringContainsString('id="mWorkArrangement"', $page);
        $this->assertStringContainsString('id="mEmploymentType"', $page);
        $this->assertStringContainsString('"WorkArrangement":"Remote"', $page);
        $this->assertStringContainsString('"EmploymentType":"Part-time"', $page);

        // The modal labels the stored slug from the same map the forms use, so
        // it is read back from the page rather than matched as escaped JSON.
        preg_match('/const experienceLabels = (\{.*?\});/s', $page, $labels);
        $decoded = json_decode($labels[1] ?? 'null', true);

        $this->assertIsArray($decoded, 'The detail modal needs the experience slug map.');
        $this->assertSame('1–2 years', $decoded['1-2_years'] ?? null);
    }

    public function test_recommendations_skip_a_job_that_contradicts_a_preferred_arrangement(): void
    {
        $seeker = $this->seeker('Remote Only Seeker');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Laravel Developer'],
                'work_arrangements' => ['Remote'],
            ]);

        $this->job(['WorkArrangement' => 'On-site']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('No approved jobs match your preferences right now')
            ->assertDontSee('Also matches:');

        $this->job(['WorkArrangement' => 'Remote']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('Laravel Developer')
            ->assertSee('Also matches: Remote');
    }

    public function test_an_experience_preference_of_any_never_counts_against_a_job(): void
    {
        $seeker = $this->seeker('Flexible Seeker');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Laravel Developer'],
                'experience_level' => 'any',
            ]);

        $this->job(['ExperienceLevel' => '10_plus_years']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('Laravel Developer')
            ->assertDontSee('Also matches:');
    }

    public function test_a_job_that_states_nothing_is_checked_rather_than_rejected(): void
    {
        $seeker = $this->seeker('Specific Seeker');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Laravel Developer'],
                'work_arrangements' => ['Remote'],
                'employment_types' => ['Full-time'],
                'experience_level' => '3-5_years',
            ]);

        $this->job();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('Laravel Developer')
            ->assertDontSee('Also matches:');
    }

    public function test_the_model_labels_only_the_known_experience_slugs(): void
    {
        $this->assertSame('3–5 years', Job::experienceLevelLabel('3-5_years'));
        $this->assertSame('Fresher', Job::EXPERIENCE_LEVELS['fresher']);
        $this->assertNull(Job::experienceLevelLabel(null));

        // A listing saved before the vocabulary changed still renders something
        // readable instead of a blank.
        $this->assertSame('legacy_value', Job::experienceLevelLabel('legacy_value'));

        foreach (Job::WORK_ARRANGEMENTS as $arrangement) {
            $this->assertIsString($arrangement);
        }

        $this->assertContains('Full-time', Job::EMPLOYMENT_TYPES);
        $this->assertNotContains('Work from home', Job::EMPLOYMENT_TYPES, 'Remote already covers it.');
    }
}
