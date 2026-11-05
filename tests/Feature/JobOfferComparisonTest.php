<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Services\JobMatchService;
use App\Support\JobComparisonState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Job Offer Comparison Lab: who may reach it, what may enter a comparison
 * slot, and which numbers the browser is allowed to influence.
 */
class JobOfferComparisonTest extends TestCase
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
            $table->string('CompanyName')->nullable();
            $table->string('JobTitle')->nullable();
            $table->integer('Vacancy')->nullable();
            $table->string('MinQualification')->nullable();
            $table->string('EmploymentType')->nullable();
            $table->string('WorkArrangement')->nullable();
            $table->string('ExperienceLevel')->nullable();
            $table->text('Description')->nullable();
            $table->json('RequiredSkills')->nullable();
            $table->json('RequiredSoftware')->nullable();
            $table->string('SalaryFrom')->nullable();
            $table->string('SalaryTo')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName')->nullable();
            $table->string('City')->nullable();
            $table->string('Area_Work')->nullable();
            $table->string('Email')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });

        Schema::create('jobseeker_career_preferences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('preferred_locations')->nullable();
            $table->string('willing_to_relocate', 10)->nullable();
            $table->string('experience_level', 30)->nullable();
            $table->json('preferred_job_titles')->nullable();
            $table->json('employment_types')->nullable();
            $table->json('work_arrangements')->nullable();
            $table->timestamps();
        });

        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_jobs', function (Blueprint $table): void {
            $table->bigIncrements('SavedJobId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->timestamps();
        });

        // The job search and job details pages the Compare buttons live on read
        // these, so the pages can be rendered at all.
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
    }

    private function createSeeker(string $name = 'Seeker One'): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createJob(array $attributes = []): int
    {
        return DB::table('job_master')->insertGetId(array_merge([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Laravel Developer',
            'Vacancy' => 2,
            'MinQualification' => 'B.Tech',
            'Description' => 'Build applications',
            'Status' => 'approved',
        ], $attributes));
    }

    private function createEmployer(string $city): int
    {
        return DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'City' => $city,
            'Area_Work' => 'Bengaluru',
            'Email' => 'private@career-next.test',
            'Status' => 'Approved',
        ]);
    }

    private function comparedIds(): array
    {
        return array_map(
            static fn (array $entry): int => (int) $entry['id'],
            session('job_comparison.jobs', [])
        );
    }

    public function test_job_seeker_opens_the_comparison_lab(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.compare.add', $jobId))
            ->assertRedirect();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Job Offer Comparison Lab')
            ->assertSee('Compare opportunities and discover which job best fits your career priorities.')
            ->assertSee('Laravel Developer');
    }

    public function test_the_lab_is_reachable_before_anything_is_selected(): void
    {
        $this->actingAs($this->createSeeker(), 'jobseeker')
            ->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Nothing to compare yet');
    }

    public function test_employer_and_admin_cannot_reach_the_lab(): void
    {
        $employerId = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Employer Co',
            'City' => 'Chennai',
            'Status' => 'Approved',
        ]);

        $employer = Employer::query()->findOrFail($employerId);

        $this->actingAs($employer, 'employer')
            ->get(route('jobseeker.compare.index'))
            ->assertForbidden();

        $this->actingAs($employer, 'employer')
            ->post(route('jobseeker.compare.add', 1))
            ->assertForbidden();

        $admin = new Admin(['UserName' => 'admin']);
        $admin->UserId = 1;

        $this->actingAs($admin, 'admin')
            ->get(route('jobseeker.compare.index'))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('jobseeker.compare.index'))->assertRedirect(route('login'));
    }

    public function test_only_the_four_most_recent_additions_are_kept(): void
    {
        $seeker = $this->createSeeker();
        $ids = [$this->createJob(), $this->createJob(), $this->createJob(), $this->createJob(), $this->createJob()];

        $this->actingAs($seeker, 'jobseeker');

        foreach ($ids as $id) {
            $this->post(route('jobseeker.compare.add', $id));
        }

        $this->assertCount(JobComparisonState::MAX_JOBS, $this->comparedIds());
        $this->assertSame(array_slice($ids, 0, 4), $this->comparedIds());
    }

    public function test_the_fifth_job_is_offered_as_a_replacement_instead_of_silently_rejected(): void
    {
        $seeker = $this->createSeeker();
        $ids = [$this->createJob(), $this->createJob(), $this->createJob(), $this->createJob(), $this->createJob()];

        $this->actingAs($seeker, 'jobseeker');

        foreach (array_slice($ids, 0, 4) as $id) {
            $this->post(route('jobseeker.compare.add', $id));
        }

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.add', $ids[4]));

        $response->assertOk()->assertJson([
            'state' => JobComparisonState::FULL,
            'count' => 4,
        ]);

        $this->assertSame($ids[4], $response->json('added.id'));
        $this->assertCount(4, $response->json('entries'));
        $this->assertNotContains($ids[4], $this->comparedIds());
    }

    public function test_adding_the_same_job_twice_does_not_duplicate_the_column(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.add', $jobId));

        $response->assertOk()->assertJson(['state' => JobComparisonState::DUPLICATE]);
        $this->assertSame([$jobId], $this->comparedIds());
    }

    public function test_withdrawn_or_missing_listings_cannot_be_compared(): void
    {
        $seeker = $this->createSeeker();
        $pending = $this->createJob(['Status' => 'pending']);
        $removed = $this->createJob(['Status' => 'removed']);

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.compare.add', $pending))
            ->assertNotFound();

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.compare.add', $removed))
            ->assertNotFound();

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.compare.add', 999999))
            ->assertNotFound();

        $this->assertSame([], $this->comparedIds());
    }

    public function test_a_listing_pulled_down_after_it_was_selected_leaves_the_comparison(): void
    {
        $seeker = $this->createSeeker();
        $kept = $this->createJob(['JobTitle' => 'Still Hiring']);
        $withdrawn = $this->createJob(['JobTitle' => 'Withdrawn Role']);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $kept));
        $this->post(route('jobseeker.compare.add', $withdrawn));

        DB::table('job_master')->where('JobId', $withdrawn)->update(['Status' => 'blocked']);

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Still Hiring')
            ->assertDontSee('Withdrawn Role');

        $this->assertSame([$kept], $this->comparedIds());
    }

    public function test_a_job_can_be_removed_and_the_comparison_cleared(): void
    {
        $seeker = $this->createSeeker();
        $first = $this->createJob(['JobTitle' => 'First Role']);
        $second = $this->createJob(['JobTitle' => 'Second Role']);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $first));
        $this->post(route('jobseeker.compare.add', $second));

        $this->post(route('jobseeker.compare.remove', $first))->assertRedirect();
        $this->assertSame([$second], $this->comparedIds());

        $this->post(route('jobseeker.compare.clear'))->assertRedirect();
        $this->assertSame([], $this->comparedIds());
    }

    public function test_a_slot_can_be_replaced_without_losing_its_position(): void
    {
        $seeker = $this->createSeeker();
        $first = $this->createJob(['JobTitle' => 'First Role']);
        $second = $this->createJob(['JobTitle' => 'Second Role']);
        $third = $this->createJob(['JobTitle' => 'Third Role']);

        $this->actingAs($seeker, 'jobseeker');

        foreach ([$first, $second, $third] as $id) {
            $this->post(route('jobseeker.compare.add', $id));
        }

        $this->post(route('jobseeker.compare.replace', $second), ['with' => $first])
            ->assertSessionHas('error');

        $this->assertSame([$first, $second, $third], $this->comparedIds());

        $replacement = $this->createJob(['JobTitle' => 'Replaced Role']);
        $this->post(route('jobseeker.compare.replace', $second), ['with' => $replacement])
            ->assertSessionHas('success');

        $this->assertSame([$first, $replacement, $third], $this->comparedIds());

        $this->post(route('jobseeker.compare.replace', 4242), ['with' => $replacement])
            ->assertSessionHas('error');
    }

    public function test_replacement_only_accepts_a_visible_listing(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob(['JobTitle' => 'First Role']);
        $hidden = $this->createJob(['JobTitle' => 'Hidden Role', 'Status' => 'pending']);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $this->post(route('jobseeker.compare.replace', $jobId), ['with' => $hidden])
            ->assertNotFound();

        $this->assertSame([$jobId], $this->comparedIds());
    }

    public function test_columns_can_be_moved_left_and_right(): void
    {
        $seeker = $this->createSeeker();
        $ids = [$this->createJob(), $this->createJob(), $this->createJob()];

        $this->actingAs($seeker, 'jobseeker');

        foreach ($ids as $id) {
            $this->post(route('jobseeker.compare.add', $id));
        }

        $this->post(route('jobseeker.compare.move', $ids[2]), ['direction' => 'left']);
        $this->assertSame([$ids[0], $ids[2], $ids[1]], $this->comparedIds());

        $this->post(route('jobseeker.compare.move', $ids[2]), ['direction' => 'right']);
        $this->assertSame($ids, $this->comparedIds());

        // The first column has nothing to its left, and the last nothing to its
        // right, so both requests leave the order alone.
        $this->post(route('jobseeker.compare.move', $ids[0]), ['direction' => 'left']);
        $this->assertSame($ids, $this->comparedIds());
    }

    public function test_only_the_five_priority_weights_are_read_from_the_request(): void
    {
        $seeker = $this->createSeeker();
        $this->createJob();

        $this->actingAs($seeker, 'jobseeker');

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(route('jobseeker.compare.weights'), [
            'salary' => 90,
            'location' => 30,
            'work_life_balance' => 500,
            'career_growth' => -40,
            'skill_match' => 10,
            'personalized' => 100,
            'score' => 100,
            'job_id' => 999,
        ]);

        $response->assertOk()->assertJson([
            'weights' => [
                'salary' => 90,
                'location' => 30,
                'work_life_balance' => 100,
                'career_growth' => 0,
                'skill_match' => 10,
            ],
        ]);

        $stored = session('job_comparison.weights');

        $this->assertSame(
            ['salary', 'location', 'work_life_balance', 'career_growth', 'skill_match'],
            array_keys($stored)
        );
    }

    public function test_weights_can_be_reset_without_touching_the_selection(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $this->post(route('jobseeker.compare.weights'), ['salary' => 100]);
        $this->assertSame(100, session('job_comparison.weights.salary'));

        $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.weights'), ['reset' => 1, 'salary' => 0])
            ->assertOk()
            ->assertJson(['weights' => ['salary' => JobComparisonState::DEFAULT_WEIGHT]]);

        $this->assertSame([$jobId], $this->comparedIds());
    }

    public function test_the_server_scores_the_comparison_and_the_page_shows_its_numbers(): void
    {
        $seeker = $this->createSeeker();
        $employer = $this->createEmployer('Hyderabad');

        $lower = $this->createJob([
            'EmployerId' => $employer,
            'JobTitle' => 'Junior Developer',
            'SalaryFrom' => '20000',
            'SalaryTo' => '25000',
        ]);

        $higher = $this->createJob([
            'EmployerId' => $employer,
            'JobTitle' => 'Senior Developer',
            'SalaryFrom' => '90000',
            'SalaryTo' => '120000',
        ]);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $lower));
        $this->post(route('jobseeker.compare.add', $higher));

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.weights'), ['salary' => 100, 'location' => 0]);

        $response->assertOk();

        $jobs = $response->json('results.jobs');

        $this->assertGreaterThan($jobs[$lower]['personalized'], $jobs[$higher]['personalized']);
        $this->assertSame($higher, $response->json('results.highest.job_id'));
        $this->assertSame($higher, $response->json('results.highlights.salary'));

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Best match for your current priorities')
            ->assertSee('Senior Developer');
    }

    public function test_undisclosed_details_are_shown_rather_than_invented(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob(['SalaryFrom' => null, 'SalaryTo' => null]);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Not disclosed')
            ->assertSee('Salary', false)
            ->assertSee('Benefits', false)
            ->assertSee('Left out of this job', false);
    }

    public function test_the_priority_panel_names_both_numbers_it_shows(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Set each factor\'s importance from 0–100. Career Next automatically converts your preferences into percentage weights used to rank jobs.', false)
            ->assertSee('Importance')
            ->assertSee('Comparison Weight')
            // The captions sit next to the sliders they belong to.
            ->assertSee('for="cnCmpWeight_salary"', false)
            ->assertSee('for="cnCmpWeight_skill_match"', false);
    }

    public function test_equal_importance_shows_twenty_percent_for_each_slider(): void
    {
        $seeker = $this->createSeeker();

        DB::table('jobseeker_skills')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'skills' => json_encode([['name' => 'Laravel', 'proficiency' => 'advanced']]),
            'software' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employer = $this->createEmployer('Bengaluru');

        DB::table('jobseeker_career_preferences')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'preferred_locations' => json_encode(['Bengaluru']),
            'willing_to_relocate' => 'no',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requirements = json_encode([['name' => 'Laravel', 'type' => 'mandatory']]);
        $described = 'Flexible hours and paid leave, with structured training and a mentor.';

        $this->actingAs($seeker, 'jobseeker');

        foreach ([50000, 80000] as $index => $from) {
            $id = $this->createJob([
                'EmployerId' => $employer,
                'JobTitle' => 'Disclosed ' . ($index + 1),
                'SalaryFrom' => (string) $from,
                'SalaryTo' => (string) ($from + 10000),
                'Description' => $described,
                'RequiredSkills' => $requirements,
            ]);

            $this->post(route('jobseeker.compare.add', $id));
        }

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.weights'), [
                'salary' => 100,
                'location' => 100,
                'work_life_balance' => 100,
                'career_growth' => 100,
                'skill_match' => 100,
            ]);

        $response->assertOk();

        // Every factor is answered somewhere, so a fifth of the ranking each.
        $this->assertSame(
            [
                'salary' => 20,
                'location' => 20,
                'work_life_balance' => 20,
                'career_growth' => 20,
                'skill_match' => 20,
            ],
            $response->json('results.shares')
        );

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('20%')
            ->assertDontSee('cn-cmp-slider-off', false);
    }

    public function test_a_slider_no_listing_can_answer_hands_its_weight_to_the_ones_that_do(): void
    {
        $seeker = $this->createSeeker();

        // No saved places, no resume skills and no career-growth phrase, so three
        // of the five sliders cannot be scored from this comparison at all.
        $this->actingAs($seeker, 'jobseeker');

        foreach ([
            ['Quiet One', 50000, 'Flexible hours for the team.'],
            ['Quiet Two', 80000, 'Build applications with a fast paced team.'],
        ] as [$title, $from, $description]) {
            $this->post(route('jobseeker.compare.add', $this->createJob([
                'JobTitle' => $title,
                'SalaryFrom' => (string) $from,
                'SalaryTo' => (string) ($from + 5000),
                'Description' => $description,
            ])));
        }

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('jobseeker.compare.weights'), [
                'salary' => 100,
                'location' => 100,
                'work_life_balance' => 100,
                'career_growth' => 100,
                'skill_match' => 100,
            ]);

        $shares = $response->json('results.shares');

        $this->assertSame(0, $shares['location']);
        $this->assertSame(0, $shares['career_growth']);
        $this->assertSame(0, $shares['skill_match']);
        $this->assertSame(50, $shares['salary']);
        $this->assertSame(50, $shares['work_life_balance']);
        $this->assertSame(100, array_sum($shares));

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('cn-cmp-slider-off', false)
            ->assertSee('It holds no weight in this comparison.', false)
            ->assertSee('Career Preferences')
            ->assertSee('1 of 2 listings leave this out, so its weight moves onto the factors those jobs do answer.', false);
    }

    public function test_a_comparison_with_nothing_to_score_says_so(): void
    {
        $seeker = $this->createSeeker();
        DB::table('jobseeker_career_preferences')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'preferred_locations' => json_encode(['Chennai']),
            'willing_to_relocate' => 'no',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = [
            $this->createJob(['JobTitle' => 'Silent Role One', 'SalaryFrom' => null, 'SalaryTo' => null]),
            $this->createJob(['JobTitle' => 'Silent Role Two', 'SalaryFrom' => null, 'SalaryTo' => null]),
        ];

        $this->actingAs($seeker, 'jobseeker');

        foreach ($ids as $id) {
            $this->post(route('jobseeker.compare.add', $id));
        }

        $response = $this->withHeaders(['Accept' => 'application/json'])->post(route('jobseeker.compare.weights'), [
            'salary' => 100,
            'location' => 0,
            'work_life_balance' => 0,
            'career_growth' => 0,
            'skill_match' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('results.message', 'Not enough information to calculate a reliable comparison.')
            ->assertJsonPath('results.highest', null);

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Not enough information to calculate a reliable comparison.');
    }

    public function test_skill_compatibility_is_the_existing_smart_job_match(): void
    {
        $seeker = $this->createSeeker();

        DB::table('jobseeker_skills')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'skills' => json_encode([
                ['name' => 'Laravel', 'proficiency' => 'advanced'],
                ['name' => 'MySQL', 'proficiency' => 'intermediate'],
            ]),
            'software' => json_encode([['name' => 'VS Code', 'proficiency' => 'advanced']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = $this->createJob([
            'RequiredSkills' => json_encode([
                ['name' => 'Laravel', 'type' => 'mandatory'],
                ['name' => 'MySQL', 'type' => 'mandatory'],
            ]),
            'RequiredSoftware' => json_encode([['name' => 'VS Code', 'type' => 'mandatory']]),
        ]);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $smartMatch = app(JobMatchService::class)->calculate(
            ['Laravel', 'MySQL'],
            Job::query()->findOrFail($jobId),
            ['VS Code']
        );

        $this->assertSame(
            $smartMatch['percentage'],
            $this->withHeaders(['Accept' => 'application/json'])
                ->post(route('jobseeker.compare.weights'))
                ->json('results.jobs.' . $jobId . '.points.skill_match.score')
        );

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Skill compatibility', false);
    }

    public function test_private_employer_details_never_reach_the_page(): void
    {
        $seeker = $this->createSeeker();
        $employer = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Quiet Company',
            'City' => 'Pune',
            'Area_Work' => 'Pune',
            'Email' => 'hidden@career-next.test',
            'Status' => 'Approved',
        ]);

        $jobId = $this->createJob(['EmployerId' => $employer]);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $jobId));

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertDontSee('hidden@career-next.test');
    }

    public function test_a_shared_link_only_adopts_visible_listings(): void
    {
        $seeker = $this->createSeeker();
        $first = $this->createJob(['JobTitle' => 'Shared One']);
        $second = $this->createJob(['JobTitle' => 'Shared Two']);
        $hidden = $this->createJob(['JobTitle' => 'Shared Hidden', 'Status' => 'pending']);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.compare.index', [
                'jobs' => $first . ',' . $hidden . ',' . $second . ',' . $first . ',999999',
            ]))
            ->assertOk()
            ->assertSee('Shared One')
            ->assertSee('Shared Two')
            ->assertDontSee('Shared Hidden');

        $this->assertSame([$first, $second], $this->comparedIds());
    }

    public function test_a_shared_link_cannot_ask_for_more_than_four_listings(): void
    {
        $seeker = $this->createSeeker();
        $ids = [$this->createJob(), $this->createJob(), $this->createJob(), $this->createJob(), $this->createJob()];

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.compare.index', ['jobs' => implode(',', $ids)]))
            ->assertOk();

        $this->assertSame(array_slice($ids, 0, 4), $this->comparedIds());
    }

    public function test_the_saved_jobs_picker_offers_only_comparable_listings(): void
    {
        $seeker = $this->createSeeker();
        $compared = $this->createJob(['JobTitle' => 'Already Compared']);
        $pickable = $this->createJob(['JobTitle' => 'Saved But Not Compared']);
        $withdrawn = $this->createJob(['JobTitle' => 'Withdrawn Saved Job', 'Status' => 'blocked']);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $compared));

        foreach ([$compared, $pickable, $withdrawn] as $id) {
            DB::table('saved_jobs')->insert([
                'JobSeekId' => $seeker->JobSeekId,
                'JobId' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->get(route('jobseeker.compare.index'));

        $response->assertOk()
            ->assertSee('Saved But Not Compared')
            ->assertDontSee('Withdrawn Saved Job');

        // The picker holds the one saved listing that is not already a column.
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-cmp-job="'));
        $this->assertStringNotContainsString('data-cmp-job="' . $compared, $html);
        $this->assertStringContainsString('data-cmp-job="' . $pickable, $html);

        $this->post(route('jobseeker.compare.add', $withdrawn))->assertNotFound();
    }

    public function test_the_lab_shows_saved_job_picker_when_room_is_left(): void
    {
        $seeker = $this->createSeeker();
        $compared = $this->createJob(['JobTitle' => 'Already Compared']);
        $pickable = $this->createJob(['JobTitle' => 'Saved But Not Compared']);

        $this->actingAs($seeker, 'jobseeker');
        $this->post(route('jobseeker.compare.add', $compared));

        foreach ([$compared, $pickable] as $id) {
            DB::table('saved_jobs')->insert([
                'JobSeekId' => $seeker->JobSeekId,
                'JobId' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get(route('jobseeker.compare.index'))
            ->assertOk()
            ->assertSee('Add from your saved jobs')
            ->assertSee('Saved But Not Compared');
    }

    public function test_add_to_compare_is_offered_on_job_cards_and_the_details_page(): void
    {
        $seeker = $this->createSeeker();
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee('Add to Compare');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('Add to Compare');

        $this->assertSame([], $this->comparedIds());
    }
}
