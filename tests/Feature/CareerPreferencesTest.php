<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Models\JobSeekerCareerPreference;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CareerPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName');
            $table->string('JobTitle');
            $table->integer('Vacancy')->nullable();
            $table->string('MinQualification')->nullable();
            $table->string('Description')->nullable();
            $table->string('SalaryFrom')->nullable();
            $table->string('SalaryTo')->nullable();
            $table->string('Status')->nullable();
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
            $table->string('Description')->nullable();
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

        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
        });
    }

    private function createSeeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    private function createJob(string $title, string $status = 'approved'): int
    {
        return DB::table('job_master')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => $title,
            'Vacancy' => 1,
            'MinQualification' => 'B.Tech',
            'Description' => 'Nice role',
            'Status' => $status,
        ]);
    }

    public function test_guests_are_redirected_from_career_preferences(): void
    {
        $this->get(route('jobseeker.preferences.edit'))->assertRedirect(route('login'));
        $this->put(route('jobseeker.preferences.update'))->assertRedirect(route('login'));
    }

    public function test_job_seeker_can_create_career_preferences(): void
    {
        $seeker = $this->createSeeker('One');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Frontend Developer', 'Data Analyst'],
                'preferred_locations' => ['Bengaluru', 'Remote only'],
                'minimum_salary' => '25000',
                'maximum_salary' => '45000',
                'salary_period' => 'monthly',
                'currency' => 'INR',
                'employment_types' => ['Full-time'],
                'work_arrangements' => ['Hybrid'],
                'preferred_industries' => ['Information Technology'],
                'experience_level' => '1-2_years',
                'willing_to_relocate' => 'yes',
                'notice_period' => '30_days',
                'preferred_working_hours' => 'day',
                'job_search_status' => 'actively_looking',
            ])
            ->assertRedirect(route('jobseeker.index'))
            ->assertSessionHas('success');

        $preferences = JobSeekerCareerPreference::query()->where('JobSeekId', $seeker->JobSeekId)->firstOrFail();

        $this->assertSame(['Frontend Developer', 'Data Analyst'], $preferences->preferred_job_titles);
        $this->assertSame(['Bengaluru', 'Remote only'], $preferences->preferred_locations);
        $this->assertSame('25000.00', (string) $preferences->minimum_salary);
        $this->assertSame('monthly', $preferences->salary_period);
        $this->assertSame('actively_looking', $preferences->job_search_status);
        $this->assertNotNull($preferences->completed_at);
    }

    public function test_partial_preferences_can_be_saved_without_required_fields(): void
    {
        $seeker = $this->createSeeker('Partial');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Content Writer'],
            ])
            ->assertRedirect(route('jobseeker.index'));

        $preferences = JobSeekerCareerPreference::query()->where('JobSeekId', $seeker->JobSeekId)->firstOrFail();

        $this->assertSame(['Content Writer'], $preferences->preferred_job_titles);
        $this->assertNull($preferences->preferred_locations);
        $this->assertNull($preferences->minimum_salary);
        $this->assertNull($preferences->experience_level);
    }

    public function test_custom_tags_are_accepted_and_deduplicated_case_insensitively(): void
    {
        $seeker = $this->createSeeker('Tags');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['  Ethical   Hacker ', 'ethical hacker', 'Ethical Hacker', 'Web Developer'],
                'preferred_locations' => ['Mumbai', 'mumbai ', 'HYDERABAD', 'Hyderabad'],
            ])
            ->assertRedirect(route('jobseeker.index'));

        $preferences = JobSeekerCareerPreference::query()->where('JobSeekId', $seeker->JobSeekId)->firstOrFail();

        $this->assertSame(['Ethical Hacker', 'Web Developer'], $preferences->preferred_job_titles);
        $this->assertSame(['Mumbai', 'HYDERABAD'], $preferences->preferred_locations);
    }

    public function test_minimum_salary_cannot_exceed_maximum(): void
    {
        $seeker = $this->createSeeker('Salary');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'minimum_salary' => '60000',
                'maximum_salary' => '40000',
            ])
            ->assertSessionHasErrors('minimum_salary');

        $this->assertDatabaseCount('jobseeker_career_preferences', 0);
    }

    public function test_only_known_enum_values_are_accepted_for_choice_fields(): void
    {
        $seeker = $this->createSeeker('Enums');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'experience_level' => 'telepathist',
                'salary_period' => 'weekly',
                'willing_to_relocate' => 'absolutely',
            ])
            ->assertSessionHasErrors(['experience_level', 'salary_period', 'willing_to_relocate']);
    }

    public function test_saving_twice_updates_the_same_record(): void
    {
        $seeker = $this->createSeeker('Twice');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), ['preferred_job_titles' => ['Accountant']]);
        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), ['preferred_job_titles' => ['Auditor']]);

        $this->assertDatabaseCount('jobseeker_career_preferences', 1);

        $preferences = JobSeekerCareerPreference::query()->where('JobSeekId', $seeker->JobSeekId)->firstOrFail();
        $this->assertSame(['Auditor'], $preferences->preferred_job_titles);
    }

    public function test_seekers_cannot_see_or_overwrite_each_others_preferences(): void
    {
        $owner = $this->createSeeker('Owner');
        $intruder = $this->createSeeker('Intruder');

        $this->actingAs($owner, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Secret Role'],
            ]);

        $this->actingAs($intruder, 'jobseeker')
            ->get(route('jobseeker.preferences.edit'))
            ->assertOk()
            ->assertDontSee('Secret Role');

        $this->actingAs($intruder, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Intruder Role'],
            ]);

        $ownerPreferences = JobSeekerCareerPreference::query()->where('JobSeekId', $owner->JobSeekId)->firstOrFail();
        $this->assertSame(['Secret Role'], $ownerPreferences->preferred_job_titles);
        $this->assertDatabaseCount('jobseeker_career_preferences', 2);
    }

    public function test_empty_save_does_not_mark_preferences_complete(): void
    {
        $seeker = $this->createSeeker('Empty');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [])
            ->assertRedirect(route('jobseeker.index'));

        $preferences = JobSeekerCareerPreference::query()->where('JobSeekId', $seeker->JobSeekId)->firstOrFail();

        $this->assertNull($preferences->completed_at);
        $this->assertFalse($preferences->hasAnyPreference());
    }

    public function test_dashboard_prompts_for_preferences_when_none_exist(): void
    {
        $seeker = $this->createSeeker('Fresh');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee("Tell us what kind of job you're looking for", false)
            ->assertSee('Set Career Preferences');
    }

    public function test_dashboard_recommends_approved_jobs_matching_preferred_titles(): void
    {
        $seeker = $this->createSeeker('Match');
        $this->createJob('Senior Laravel Developer');
        $this->createJob('Hidden Ghost Role', 'pending');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Laravel Developer'],
            ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('Recommended Jobs')
            ->assertSee('Senior Laravel Developer')
            ->assertDontSee('Hidden Ghost Role')
            ->assertSee('100%');
    }

    public function test_recommendations_do_not_require_location_or_industry_job_columns(): void
    {
        // job_master has no location/industry columns; those preferences must
        // neither exclude nor fake-match jobs.
        $seeker = $this->createSeeker('Wide');
        $this->createJob('Data Analyst');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.preferences.update'), [
                'preferred_job_titles' => ['Data Analyst'],
                'preferred_locations' => ['Chennai'],
                'preferred_industries' => ['Healthcare'],
            ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()
            ->assertSee('Data Analyst');
    }

    public function test_the_preferences_page_carries_the_same_skills_section_as_the_profile(): void
    {
        $seeker = $this->createSeeker('Blender Fan');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => json_encode([['name' => 'Blender', 'proficiency' => 'intermediate']]),
            ]);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.preferences.edit'))
            ->assertOk()
            ->assertSee('Skills &amp; Software', false)
            ->assertSee('id="cnSkillPicker"', false)
            ->assertSee('Blender');
    }
}
