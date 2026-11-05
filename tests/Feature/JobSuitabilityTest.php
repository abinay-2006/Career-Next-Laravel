<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobSeeker;
use App\Services\JobSuitabilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The suitability assessment where a job seeker actually sees it: on the search
 * cards beside the Smart Job Match percentage, and as its own section on the job
 * details page. These cover the pieces a hand-built context cannot - profile
 * skills and Resume Builder skills being read as one, the fixed set of queries a
 * search costs, and the existing filters still deciding which cards appear.
 */
class JobSuitabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Qualification')->nullable();
            $table->string('Status');
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName')->nullable();
            $table->string('JobTitle')->nullable();
            $table->integer('Vacancy')->nullable();
            $table->string('MinQualification')->nullable();
            $table->string('ExperienceLevel', 30)->nullable();
            $table->string('WorkArrangement', 30)->nullable();
            $table->string('EmploymentType', 50)->nullable();
            $table->text('Description')->nullable();
            $table->longText('RequiredSkills')->nullable();
            $table->longText('RequiredSoftware')->nullable();
            $table->string('SalaryFrom', 50)->nullable();
            $table->string('SalaryTo', 50)->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status')->nullable();
            $table->text('Description')->nullable();
            $table->dateTime('ApplicationDate')->nullable();
        });

        Schema::create('jobseeker_resumes', function (Blueprint $table): void {
            $table->id();
            $table->integer('JobSeekId');
            $table->string('title')->default('My Resume');
            $table->string('template')->default('classic');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId');
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
        });

        Schema::create('jobseeker_education', function (Blueprint $table): void {
            $table->increments('EduId');
            $table->integer('JobSeekId');
            $table->string('Degree')->nullable();
            $table->string('University')->nullable();
            $table->string('PassingYear', 10)->nullable();
            $table->string('Percentage', 20)->nullable();
        });

        Schema::create('jobseeker_career_preferences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId');
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
    }

    /* ------------------------------------------------------------- fixtures */

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seeker(string $name, array $attributes = []): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId(array_merge([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ], $attributes));

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function job(array $attributes = []): int
    {
        return DB::table('job_master')->insertGetId(array_merge([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Laravel Developer',
            'Vacancy' => 1,
            'MinQualification' => 'B.C.A',
            'Description' => 'Build APIs with Laravel and MySQL.',
            'Status' => 'approved',
        ], $attributes));
    }

    /**
     * @param  list<string>  $names
     */
    private function requirements(array $names, string $type = 'mandatory'): array
    {
        return array_map(
            static fn (string $name): array => ['name' => $name, 'type' => $type, 'proficiency' => null],
            $names
        );
    }

    /**
     * The saved Skills & Software row.
     *
     * @param  list<string>  $skills
     * @param  list<string>  $software
     */
    private function profileSkills(int $jobSeekerId, array $skills, array $software = []): void
    {
        DB::table('jobseeker_skills')->insert([
            'JobSeekId' => $jobSeekerId,
            'skills' => json_encode(array_map(
                static fn (string $name): array => ['name' => $name, 'proficiency' => 'advanced'],
                $skills
            )),
            'software' => json_encode(array_map(
                static fn (string $name): array => ['name' => $name, 'proficiency' => 'basic'],
                $software
            )),
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function resume(int $jobSeekerId, array $content): void
    {
        DB::table('jobseeker_resumes')->insert([
            'JobSeekId' => $jobSeekerId,
            'title' => 'Saved Resume',
            'template' => 'classic',
            'content' => json_encode($content),
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-01 10:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function preferences(int $jobSeekerId, array $attributes): void
    {
        DB::table('jobseeker_career_preferences')->insert(array_merge([
            'JobSeekId' => $jobSeekerId,
        ], $attributes));
    }

    /* ----------------------------------------------------------- job cards */

    public function test_a_job_card_shows_the_status_and_explanation_next_to_the_match_score(): void
    {
        $seeker = $this->seeker('Covered Seeker', ['Qualification' => 'MCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel', 'MySQL']);
        $this->job(['RequiredSkills' => json_encode($this->requirements(['Laravel']))]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('job-fit-success', $page);
        $this->assertStringContainsString('Suitable', $page);
        $this->assertStringContainsString('Your profile meets every requirement this listing states.', $page);

        // The Smart Job Match percentage and its label are still on the card.
        $this->assertStringContainsString('100%', $page);
        $this->assertStringContainsString('Excellent Match', $page);
        $this->assertStringContainsString('View Details', $page);
    }

    public function test_a_card_names_the_shortfall_behind_a_not_suitable_status(): void
    {
        $seeker = $this->seeker('Under-Qualified Seeker', ['Qualification' => 'Diploma']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$jobId, $page);
        $this->assertStringContainsString('job-fit-danger', $page);
        $this->assertStringContainsString('Not Suitable', $page);
        $this->assertStringContainsString('above the Diploma on your profile', $page);
    }

    public function test_an_empty_profile_shows_insufficient_information_rather_than_rejection(): void
    {
        $seeker = $this->seeker('Blank Seeker');
        $jobId = $this->job(['RequiredSkills' => json_encode($this->requirements(['Laravel']))]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $details = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('job-fit-muted', $page);
        $this->assertStringContainsString('Insufficient Information', $page);
        $this->assertStringNotContainsString('Not Suitable', $page);

        // Nothing was confirmed, so no number is invented for it.
        $this->assertStringNotContainsString('<span class="job-fit-value">', $page);
        $this->assertStringNotContainsString('essential requirements confirmed', $page);

        $this->assertStringContainsString('Insufficient Information', $details);
        $this->assertStringNotContainsString('<span class="job-fit-value">', $details);
        $this->assertStringNotContainsString('essential requirements confirmed', $details);
    }

    public function test_profile_skills_and_resume_builder_skills_are_read_as_one_profile(): void
    {
        // Laravel only on the saved Skills & Software row, Docker only in the
        // Resume Builder: the listing requires both, and the seeker meets both.
        $seeker = $this->seeker('Split Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);
        $this->resume($seeker->JobSeekId, ['skills' => 'Docker']);

        $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'Docker'])),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('job-fit-success', $page);
        $this->assertStringNotContainsString('Required skills not in your profile', $page);
    }

    public function test_an_education_record_counts_as_the_held_qualification(): void
    {
        $seeker = $this->seeker('Graduate Seeker');
        DB::table('jobseeker_education')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'Degree' => 'M.C.A',
            'University' => 'Pune University',
            'PassingYear' => '2022',
            'Percentage' => '72',
        ]);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $this->job([
            'MinQualification' => 'B.E / B.Tech',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('job-fit-success', $page);
    }

    /* --------------------------------------------------------- job details */

    public function test_the_details_page_renders_the_full_assessment(): void
    {
        $seeker = $this->seeker('Detailed Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel'], ['VS Code']);
        $this->preferences($seeker->JobSeekId, [
            'experience_level' => '3-5_years',
            'minimum_salary' => '90000',
            'work_arrangements' => json_encode(['Remote']),
        ]);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '1-2_years',
            'WorkArrangement' => 'On-site',
            'SalaryFrom' => '45000',
            'SalaryTo' => '70000',
            'RequiredSkills' => json_encode(array_merge(
                $this->requirements(['Laravel']),
                $this->requirements(['Redis'], 'preferred')
            )),
            'RequiredSoftware' => json_encode($this->requirements(['Figma'], 'preferred')),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Job Suitability Assessment', $page);
        $this->assertStringContainsString('Partially Suitable', $page);
        $this->assertStringContainsString('<span class="job-fit-value">100%</span>', $page);
        $this->assertStringContainsString('3 of 3 essential requirements confirmed', $page);
        $this->assertStringContainsString('of essential requirements', $page);

        foreach ([
            'Qualification', 'Mandatory skills', 'Preferred skills', 'Experience',
            'Software', 'Salary', 'Employment type', 'Work arrangement',
        ] as $name) {
            $this->assertStringContainsString($name, $page, 'Expected a row for '.$name.'.');
        }

        $this->assertStringContainsString('What is missing', $page);
        $this->assertStringContainsString('To improve your suitability', $page);
        $this->assertStringContainsString('Redis', $page);

        // Existing job information and Apply are untouched.
        $this->assertStringContainsString('Apply for this Job', $page);
        $this->assertStringContainsString('Minimum Qualification', $page);
    }

    /**
     * The number the card and the details page show is the suitability one, not
     * the skill one: this profile covers every tagged skill, so Smart Job Match
     * stays at 100%, while the six-year experience bar keeps overall
     * suitability to 67% on both views.
     */
    public function test_a_full_skill_match_with_a_failed_experience_bar_scores_below_one_hundred_on_both_views(): void
    {
        $seeker = $this->seeker('Skilled Junior', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);
        $this->preferences($seeker->JobSeekId, ['experience_level' => '1-2_years']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '6-10_years',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]);

        $http = $this->actingAs($seeker, 'jobseeker');

        $card = $http->get(route('jobseeker.jobs'))->assertOk()->getContent();
        $details = $http->get(route('jobseeker.jobs.show', $jobId))->assertOk()->getContent();

        foreach (['card' => $card, 'details' => $details] as $view => $page) {
            $this->assertStringContainsString(
                '<span class="job-fit-value">67%</span>',
                $page,
                'The '.$view.' should score suitability at 67%.'
            );
            $this->assertStringNotContainsString('<span class="job-fit-value">100%</span>', $page);
            $this->assertStringContainsString('Not Suitable', $page);
            $this->assertStringContainsString('Skill Match', $page);
            $this->assertStringContainsString('of essential requirements', $page);
            $this->assertStringContainsString('Excellent Match', $page);
            $this->assertStringContainsString('6–10', $page);
        }

        // The same numbers on both views: one assessment, not two calculations.
        $this->assertStringContainsString('2 of 3 essential requirements confirmed', $details);
        $this->assertStringContainsString('<span class="job-match-value">100%</span>', $card);
    }

    public function test_the_details_page_reports_the_experience_shortfall_for_a_senior_listing(): void
    {
        $seeker = $this->seeker('Junior Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);
        $this->preferences($seeker->JobSeekId, ['experience_level' => '1-2_years']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '6-10_years',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Not Suitable', $page);
        $this->assertStringContainsString('6–10 years', $page);
        $this->assertStringContainsString('which is below that minimum', $page);
    }

    /* ------------------------------------------------------- filters, cost */

    public function test_the_existing_filters_still_decide_which_cards_appear(): void
    {
        $seeker = $this->seeker('Filter Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel', 'MySQL']);

        $support = $this->job([
            'JobTitle' => 'Support Executive',
            'MinQualification' => 'HSC',
            'Description' => 'Answer inbound calls.',
            'RequiredSkills' => json_encode($this->requirements(['Zend', 'Freshdesk'])),
        ]);
        $laravel = $this->job([
            'JobTitle' => 'Laravel Developer',
            'MinQualification' => 'B.C.A',
            'Description' => 'Build APIs with Laravel and MySQL.',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'MySQL'])),
        ]);

        $qualified = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['cmbQual' => 'B.C.A']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$laravel, $qualified);
        $this->assertStringNotContainsString('ID: '.$support, $qualified);

        $above60 = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['cmbMatch' => 80]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$laravel, $above60);
        $this->assertStringNotContainsString('ID: '.$support, $above60);

        $sorted = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['sort' => 'best_match']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($sorted, 'ID: '.$support),
            strpos($sorted, 'ID: '.$laravel),
            'Best Match should rank the fully covered listing first.'
        );
    }

    public function test_the_assessment_adds_no_queries_per_listing(): void
    {
        $seeker = $this->seeker('Cheap Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $this->job(['JobTitle' => 'One']);

        DB::enableQueryLog();
        $this->actingAs($seeker, 'jobseeker')->get(route('jobseeker.jobs'))->assertOk();
        $oneListing = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->job(['JobTitle' => 'Two']);
        $this->job(['JobTitle' => 'Three']);
        DB::flushQueryLog();

        $this->actingAs($seeker, 'jobseeker')->get(route('jobseeker.jobs'))->assertOk();
        $threeListings = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $oneListing,
            $threeListings,
            'The profile, skills, resume, education and preferences are read once per request, not once per listing.'
        );
    }

    public function test_the_assessment_reuses_the_resume_row_smart_job_match_already_read(): void
    {
        $seeker = $this->seeker('Shared Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);
        // Only the Resume Builder records the master's degree, so the card can
        // reach "Suitable" if - and only if - the resume content is read.
        $this->resume($seeker->JobSeekId, ['qualification' => 'M.C.A']);

        $this->job(['JobTitle' => 'One', 'MinQualification' => 'M.C.A']);
        $this->job(['JobTitle' => 'Two', 'MinQualification' => 'M.C.A']);

        DB::enableQueryLog();
        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee('job-fit-success', false);
        $resumeReads = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'select * from "jobseeker_resumes"')
        );
        DB::disableQueryLog();

        $this->assertCount(1, $resumeReads, 'The whole resume row is fetched once, whatever the result size.');
    }

    public function test_the_assessment_is_never_shown_to_a_visitor_who_is_not_the_seeker(): void
    {
        $this->job();

        $this->get(route('jobseeker.jobs'))->assertRedirect(route('login'));
        $this->get(route('jobseeker.jobs.show', 1))->assertRedirect(route('login'));
    }

    public function test_the_status_labels_and_tones_are_published_for_the_views(): void
    {
        $assessment = (new \App\Services\JobSuitabilityService(
            new \App\Services\JobMatchService(),
            new \App\Services\CareerPreferenceMatchService()
        ))->assess([
            'job_seeker_id' => 1,
            'skills' => ['Laravel'],
            'software' => ['Laravel'],
            'qualifications' => ['B.C.A'],
            'experience_level' => null,
            'preferences' => null,
        ], new Job(['JobTitle' => 'Laravel Developer', 'MinQualification' => 'B.C.A']));

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertSame('success', $assessment['tone']);
        $this->assertSame('fa-circle-check', $assessment['icon']);
    }
}
