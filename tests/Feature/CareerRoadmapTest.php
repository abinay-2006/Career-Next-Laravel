<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The gap-to-job roadmap where a seeker sees it: a compact preview on the search
 * cards and the full breakdown on the job details page. These cover what a
 * hand-built context cannot - the roadmap moving when the saved profile changes,
 * the queries a search still costs, and the Smart Job Match and suitability
 * panels the roadmap is built beside rather than instead of.
 */
class CareerRoadmapTest extends TestCase
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
     * @return list<array{name: string, type: string, proficiency: null}>
     */
    private function requirements(array $names, string $type = 'mandatory'): array
    {
        return array_map(
            static fn (string $name): array => ['name' => $name, 'type' => $type, 'proficiency' => null],
            $names
        );
    }

    /**
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
     * @param  array<string, mixed>  $attributes
     */
    private function preferences(int $jobSeekerId, array $attributes): void
    {
        DB::table('jobseeker_career_preferences')->insert(array_merge([
            'JobSeekId' => $jobSeekerId,
        ], $attributes));
    }

    private function fillSkills(int $jobSeekerId, array $skills): void
    {
        DB::table('jobseeker_skills')->where('JobSeekId', $jobSeekerId)->update([
            'skills' => json_encode(array_map(
                static fn (string $name): array => ['name' => $name, 'proficiency' => 'advanced'],
                $skills
            )),
        ]);
    }

    /**
     * The details page as the seeker sees it.
     */
    private function details(JobSeeker $seeker, int $jobId): string
    {
        return $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->getContent();
    }

    /* ----------------------------------------------------------- job cards */

    public function test_a_card_previews_the_roadmap_without_replacing_the_other_panels(): void
    {
        $seeker = $this->seeker('Preview Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['MySQL']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'MySQL'])),
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        // The card shows one progress line and one outstanding group.
        $this->assertStringContainsString('job-roadmap', $page);
        $this->assertStringContainsString('Roadmap', $page);
        $this->assertStringContainsString('67%', $page);
        $this->assertStringContainsString('2/3 steps covered', $page);
        $this->assertStringContainsString('Learn: 1 step left', $page);
        $this->assertStringContainsString('href="'.route('jobseeker.jobs.show', $jobId).'#jobRoadmap"', $page);

        // Smart Job Match and the suitability badge are untouched: the card keeps
        // its own skill-match figure (50%) beside the roadmap's 67%.
        $this->assertStringContainsString('Skill Match', $page);
        $this->assertStringContainsString('Moderate Match', $page);
        $this->assertStringContainsString('job-match', $page);
        $this->assertStringContainsString('job-fit-danger', $page);
        $this->assertStringContainsString('Not Suitable', $page);

        // A preview is a pointer, not the full roadmap.
        $this->assertStringNotContainsString('Already Matched', $page);
        $this->assertStringNotContainsString('Update Skills', $page);
    }

    public function test_a_card_stays_quiet_when_everything_the_listing_tags_is_covered(): void
    {
        $seeker = $this->seeker('Quiet Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
            'Description' => 'Build APIs with Laravel.',
        ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        // Every tag covered, so the preview keeps one figure and drops the
        // per-group lines rather than growing a second block on the card.
        $this->assertStringContainsString('2/2 steps covered', $page);
        $this->assertStringNotContainsString('steps left', $page);
    }

    /* --------------------------------------------------------- job details */

    public function test_the_details_page_lists_the_skill_gap_under_learn(): void
    {
        $seeker = $this->seeker('Learner Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['MySQL']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode(array_merge(
                $this->requirements(['Laravel']),
                $this->requirements(['Docker'], 'preferred')
            )),
        ]);

        $page = $this->details($seeker, $jobId);

        $this->assertStringContainsString('Your Job Readiness', $page);
        $this->assertStringContainsString('job-roadmap-detailed', $page);

        // The default view is the work only: a short action, one line about it,
        // and what kind of requirement it is.
        $this->assertStringContainsString('Requirements matched:', $page);
        $this->assertStringContainsString('<strong>Learn Docker</strong>', $page);
        $this->assertStringContainsString('<strong>Learn Laravel</strong>', $page);
        $this->assertStringContainsString('job-roadmap-cat">Skill Gap', $page);
        $this->assertStringContainsString('class="btn-roadmap-primary"', $page);
        $this->assertStringContainsString('Improve My Profile', $page);

        // A requirement the profile already covers is not repeated there.
        $this->assertStringNotContainsString('Covered by your profile.', $page);

        // The full breakdown, the matched list and the explanations are behind
        // the disclosure - still on the page, just not in the way.
        $this->assertStringContainsString('<details class="job-roadmap-all">', $page);
        $this->assertStringContainsString('View all requirements', $page);
        $this->assertStringContainsString('Learn', $page);
        $this->assertStringContainsString('essential skill that your profile does not mention', $page);
        $this->assertStringContainsString('preferred skill that your profile does not mention', $page);
        $this->assertStringContainsString('Already Matched', $page);
        $this->assertStringContainsString('MySQL', $page);

        // And the four profile actions.
        $this->assertStringContainsString('Update Skills', $page);
        $this->assertStringContainsString('Update Education', $page);
        $this->assertStringContainsString('Update Experience', $page);
        $this->assertStringContainsString('Update Resume', $page);
        $this->assertStringContainsString(route('jobseeker.profile.skills.update'), $page);
        $this->assertStringContainsString(route('jobseeker.education'), $page);
        $this->assertStringContainsString(route('jobseeker.preferences.edit'), $page);
        $this->assertStringContainsString(route('jobseeker.resumes.index'), $page);

        $this->assertStringContainsString('does not guarantee shortlisting', $page);
    }

    public function test_a_qualification_shortfall_is_listed_under_improve_against_education(): void
    {
        $seeker = $this->seeker('Diploma Seeker', ['Qualification' => 'Diploma']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $page = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]));

        $this->assertStringContainsString('Improve', $page);
        $this->assertStringContainsString('above the Diploma you have recorded', $page);
        $this->assertStringContainsString('Update Education', $page);
    }

    public function test_an_experience_shortfall_is_listed_under_gain_experience(): void
    {
        $seeker = $this->seeker('Junior Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);
        $this->preferences($seeker->JobSeekId, ['experience_level' => '1-2_years']);

        $page = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '3-5_years',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]));

        $this->assertStringContainsString('Gain Experience', $page);
        $this->assertStringContainsString('Gaining that experience is the way to close it', $page);
        $this->assertStringContainsString('Update Experience', $page);
    }

    public function test_requirements_the_profile_already_covers_are_reported_as_already_matched(): void
    {
        $seeker = $this->seeker('Covered Seeker', ['Qualification' => 'MCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel', 'MySQL'], ['VS Code']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'MySQL'])),
            'RequiredSoftware' => json_encode($this->requirements(['VS Code'])),
        ]);

        $page = $this->details($seeker, $jobId);

        $this->assertStringContainsString('Requirements matched:', $page);
        $this->assertStringContainsString('<strong>4/4</strong>', $page);
        $this->assertStringContainsString('role="progressbar" aria-label="Requirements matched by your profile"', $page);
        $this->assertStringContainsString('Your profile meets all the requirements listed for this job.', $page);
        $this->assertStringNotContainsString('job-roadmap-gaps', $page);
        $this->assertStringNotContainsString('job-roadmap-step-gap', $page);
        $this->assertStringContainsString('job-roadmap-success', $page);
        $this->assertStringContainsString('Already Matched', $page);
        $this->assertStringContainsString('View all requirements', $page);
    }

    public function test_an_uneven_roadmap_says_how_much_is_already_covered(): void
    {
        $seeker = $this->seeker('Half Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $page = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'Docker'])),
        ]));

        $this->assertStringContainsString('<strong>2/3</strong>', $page);
        $this->assertStringContainsString('job-roadmap-warning', $page);
    }

    /* ----------------------------------------- progress from real profile data */

    public function test_an_unrecorded_requirement_is_listed_without_being_counted_as_progress(): void
    {
        $seeker = $this->seeker('Part Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $page = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '3-5_years',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]));

        // Both checkable steps are covered, yet the band the listing asks for was
        // never recorded, so the default view shows the gap and says what it is.
        // The unrecorded step is not in the denominator: 2/2 cannot read as ready.
        $this->assertStringContainsString('<strong>2/2</strong>', $page);
        $this->assertStringContainsString('<strong>Record your experience level</strong>', $page);
        $this->assertStringContainsString('job-roadmap-cat">Experience', $page);
        $this->assertStringContainsString('1 requirement is not recorded in your profile, so it is counted as neither matched nor missed.', $page);
        $this->assertStringNotContainsString('Your profile meets all the requirements', $page);
    }

    public function test_the_roadmap_moves_when_the_seeker_records_the_missing_skill(): void
    {
        $seeker = $this->seeker('Growing Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['MySQL']);

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'MySQL'])),
        ]);

        $before = $this->details($seeker, $jobId);
        $this->assertStringContainsString('<strong>2/3</strong>', $before);
        $this->assertStringContainsString('<strong>Learn Laravel</strong>', $before);
        $this->assertStringContainsString('essential skill that your profile does not mention', $before);

        $this->fillSkills($seeker->JobSeekId, ['Laravel', 'MySQL']);

        $after = $this->details($seeker, $jobId);
        $this->assertStringContainsString('<strong>3/3</strong>', $after);
        $this->assertStringContainsString('Your profile meets all the requirements listed for this job.', $after);
        $this->assertStringNotContainsString('Learn Laravel', $after);
        $this->assertStringNotContainsString('essential skill that your profile does not mention', $after);
        $this->assertStringNotContainsString('job-roadmap-step-gap', $after);
        $this->assertStringContainsString('job-roadmap-success', $after);
    }

    public function test_a_blank_profile_reports_nothing_as_failed_and_claims_no_progress(): void
    {
        $seeker = $this->seeker('Blank Seeker');

        $jobId = $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
            'RequiredSoftware' => json_encode($this->requirements(['VS Code'])),
        ]);

        $page = $this->details($seeker, $jobId);

        $this->assertStringContainsString('recorded, so the comparison could not be made', $page);
        $this->assertStringContainsString('Nothing in your profile can be compared with this listing yet', $page);
        $this->assertStringContainsString('no progress to measure', $page);

        // Nothing was checkable, so the bar claims no figure and an unrecorded
        // value is worded as something to record rather than as a shortfall.
        $this->assertStringContainsString('not measured yet', $page);
        $this->assertStringContainsString('aria-valuenow="0"', $page);
        $this->assertStringNotContainsString('counted as neither matched nor missed', $page);
        $this->assertStringContainsString('<strong>Record your qualification</strong>', $page);
        $this->assertStringContainsString('Not recorded yet, so it could not be checked.', $page);
        $this->assertStringNotContainsString('Covered by your profile.', $page);
        $this->assertStringNotContainsString('job-roadmap-step-gap', $page);
        $this->assertStringNotContainsString('job-roadmap-detailed job-roadmap-danger', $page);

        // The roadmap offers the screens to fill in, without rejecting anyone.
        $this->assertStringContainsString('Update Skills', $page);
        $this->assertStringContainsString('Insufficient Information', $page);
    }

    /* -------------------------------------------------------------- promise */

    public function test_the_roadmap_never_promises_selection(): void
    {
        $seeker = $this->seeker('Hopeful Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['MySQL']);

        $page = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]));

        $roadmapSection = substr($page, (int) strpos($page, 'id="jobRoadmap"'));

        $this->assertStringContainsString('does not guarantee shortlisting or the job', $roadmapSection);
        $this->assertStringNotContainsString('guarantees you the job', $roadmapSection);
        $this->assertStringNotContainsString('you will be selected', $roadmapSection);
    }

    /* ----------------------------------------------------------------- cost */

    public function test_the_roadmap_adds_no_queries_per_listing(): void
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

        $page = $this->actingAs($seeker, 'jobseeker')->get(route('jobseeker.jobs'))->assertOk()->getContent();
        $threeListings = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $oneListing,
            $threeListings,
            'Roadmaps are derived from the assessments already built for the result, not read per card.'
        );

        $this->assertStringContainsString('1/1 steps covered', $page);
        $this->assertStringContainsString('Two', $page);
        $this->assertStringContainsString('Three', $page);
    }

    /* ------------------------------------------------------------- preserved */

    public function test_the_four_suitability_statuses_are_unchanged_beside_a_roadmap(): void
    {
        $seeker = $this->seeker('Status Seeker', ['Qualification' => 'BCA']);
        $this->profileSkills($seeker->JobSeekId, ['Laravel']);

        $suitable = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel'])),
        ]));
        $this->assertStringContainsString('job-fit-success', $suitable);
        $this->assertStringContainsString('Suitable', $suitable);

        $notSuitable = $this->details($seeker, $this->job([
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => json_encode($this->requirements(['Laravel', 'Docker'])),
        ]));
        $this->assertStringContainsString('job-fit-danger', $notSuitable);
        $this->assertStringContainsString('Not Suitable', $notSuitable);

        // The roadmap reports its own progress figure beside that verdict: it adds
        // no fifth status and changes none of the four.
        $this->assertStringContainsString('<strong>2/3</strong>', $notSuitable);
        $this->assertStringNotContainsString('Roadmap Suitable', $notSuitable);
    }
}
