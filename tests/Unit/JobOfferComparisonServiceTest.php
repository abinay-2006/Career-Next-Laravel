<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use App\Services\JobMatchService;
use App\Services\JobOfferComparisonService;
use App\Support\JobComparisonState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The scoring rules of the Comparison Lab, read straight off the service: what a
 * disclosed figure becomes, what a missing one does not, and how the five sliders
 * are renormalised when a listing cannot answer one of them.
 */
class JobOfferComparisonServiceTest extends TestCase
{
    private JobOfferComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('Status')->nullable();
        });

        $this->service = new JobOfferComparisonService(new JobMatchService());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createJob(array $attributes = []): int
    {
        return DB::table('job_master')->insertGetId(array_merge([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Developer',
            'Vacancy' => 1,
            'MinQualification' => 'B.Tech',
            'Status' => 'approved',
        ], $attributes));
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function setWeights(array $weights): void
    {
        session()->put('job_comparison.weights', array_merge(JobComparisonState::weights(), $weights));
    }

    /**
     * @param  list<int>  $jobIds
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function compare(array $jobIds, array $context = []): array
    {
        return $this->service->compare($jobIds, $context + [
            'skills' => [],
            'software' => [],
            'preferences' => null,
        ]);
    }

    public function test_the_highest_paying_listing_wins_the_salary_factor(): void
    {
        $lower = $this->createJob(['JobTitle' => 'Junior', 'SalaryFrom' => '20000', 'SalaryTo' => '30000']);
        $middle = $this->createJob(['JobTitle' => 'Mid', 'SalaryFrom' => '50000', 'SalaryTo' => '60000']);
        $top = $this->createJob(['JobTitle' => 'Lead', 'SalaryFrom' => '90000', 'SalaryTo' => '110000']);

        $this->setWeights(['salary' => 100]);

        $columns = $this->compare([$lower, $middle, $top])['columns'];

        $this->assertSame(0, $columns[$lower]['factors']['salary']['score']);
        $this->assertSame(100, $columns[$top]['factors']['salary']['score']);
        $this->assertGreaterThan($columns[$lower]['factors']['salary']['score'], $columns[$middle]['factors']['salary']['score']);

        $this->assertSame($top, $this->compare([$lower, $middle, $top])['highest']['job_id']);
    }

    public function test_one_listing_with_a_salary_cannot_be_ranked_against_anything(): void
    {
        $paid = $this->createJob(['SalaryFrom' => '40000', 'SalaryTo' => '60000']);
        $silent = $this->createJob();

        $this->setWeights(['salary' => 100]);

        $comparison = $this->compare([$paid, $silent]);
        $columns = $comparison['columns'];

        $this->assertFalse($comparison['salary_comparable']);
        $this->assertFalse($columns[$paid]['factors']['salary']['available']);
        $this->assertFalse($columns[$silent]['factors']['salary']['available']);
    }

    public function test_an_unreadable_salary_range_is_never_scored_as_zero(): void
    {
        $broken = $this->createJob([
            'SalaryFrom' => 'negotiable',
            'SalaryTo' => null,
            'Description' => 'This role includes paid leave.',
        ]);
        $clear = $this->createJob(['SalaryFrom' => '30000', 'SalaryTo' => '60000']);
        $higher = $this->createJob(['SalaryFrom' => '70000', 'SalaryTo' => '80000']);

        $this->setWeights(['salary' => 100]);

        $columns = $this->compare([$broken, $clear, $higher])['columns'];

        $this->assertFalse($columns[$broken]['factors']['salary']['available']);
        $this->assertNull($columns[$broken]['factors']['salary']['score']);
        $this->assertSame(0, $columns[$clear]['factors']['salary']['score']);
        $this->assertSame(100, $columns[$higher]['factors']['salary']['score']);

        // Silence costs the broken listing nothing: it is scored only on the
        // factors it answers, so its personalised score still exists.
        $this->assertNotNull($columns[$broken]['score']['personalized']);
    }

    public function test_benefits_and_salary_frequency_are_reported_as_not_disclosed(): void
    {
        $jobId = $this->createJob([
            'SalaryFrom' => '30000',
            'SalaryTo' => '40000',
            'Description' => 'Great team, great coffee.',
        ]);

        $rows = $this->compare([$jobId])['columns'][$jobId]['rows'];

        $this->assertSame(JobOfferComparisonService::NOT_DISCLOSED, $rows['benefits']['display']);
        $this->assertSame(JobOfferComparisonService::NOT_DISCLOSED, $rows['salary_basis']['display']);
        $this->assertSame(JobOfferComparisonService::NOT_DISCLOSED, $rows['extra_pay']['display']);
        $this->assertTrue($rows['benefits']['missing']);
    }

    public function test_a_posting_date_is_only_shown_when_the_database_records_one(): void
    {
        $jobId = $this->createJob();

        // No logs table exists in this fixture, so nothing is claimed.
        $rows = $this->compare([$jobId])['columns'][$jobId]['rows'];

        $this->assertSame(JobOfferComparisonService::NOT_DISCLOSED, $rows['posted_on']['display']);
    }

    public function test_an_unanswered_factor_is_dropped_and_its_share_spread_over_the_rest(): void
    {
        $requirements = json_encode([['name' => 'Laravel', 'type' => 'mandatory']]);

        $silent = $this->createJob([
            'JobTitle' => 'Silent',
            'SalaryFrom' => null,
            'SalaryTo' => null,
            'RequiredSkills' => $requirements,
        ]);
        $paid = $this->createJob([
            'JobTitle' => 'Paid',
            'SalaryFrom' => '10000',
            'SalaryTo' => '20000',
            'RequiredSkills' => $requirements,
        ]);
        $better = $this->createJob([
            'JobTitle' => 'Better',
            'SalaryFrom' => '80000',
            'SalaryTo' => '90000',
            'RequiredSkills' => $requirements,
        ]);

        $this->setWeights(['salary' => 60, 'work_life_balance' => 0, 'career_growth' => 0, 'skill_match' => 40]);

        $columns = $this->compare([$silent, $paid, $better], ['skills' => ['Laravel']])['columns'];

        $points = $columns[$silent]['score']['points'];

        $this->assertFalse($columns[$silent]['factors']['salary']['available']);
        $this->assertSame(0, $points['salary']['share']);

        // Only the factors this listing answers carry the whole hundred.
        $used = array_filter($points, static fn (array $point): bool => $point['share'] > 0);

        $this->assertNotEmpty($used);
        $this->assertSame(100, array_sum(array_column($used, 'share')));
        $this->assertSame(100, $points['skill_match']['share']);
        $this->assertSame(40, $points['skill_match']['weight']);
    }

    public function test_weights_are_normalised_to_a_hundred_percent(): void
    {
        [$ids, $context] = $this->fullyDisclosedPair();

        $this->setWeights([
            'salary' => 30,
            'location' => 30,
            'work_life_balance' => 0,
            'career_growth' => 0,
            'skill_match' => 40,
        ]);

        $shares = $this->compare($ids, $context)['shares'];

        $this->assertSame(100, array_sum($shares));
        $this->assertSame(30, $shares['salary']);
        $this->assertSame(40, $shares['skill_match']);
    }

    public function test_five_equal_sliders_split_the_weight_evenly_when_every_factor_is_answered(): void
    {
        [$ids, $context] = $this->fullyDisclosedPair();

        $this->setWeights(array_fill_keys(array_keys(JobComparisonState::FACTORS), 100));

        $comparison = $this->compare($ids, $context);

        $this->assertSame(
            array_fill_keys(array_keys(JobComparisonState::FACTORS), 100),
            $comparison['weights']
        );

        // Nothing is left out, so each slider carries a fifth of the ranking.
        $this->assertSame(
            array_fill_keys(array_keys(JobComparisonState::FACTORS), 20),
            $comparison['shares']
        );

        $this->assertSame(100, array_sum($comparison['shares']));
    }

    public function test_a_factor_no_listing_answers_hands_its_weight_to_the_ones_that_do(): void
    {
        [$ids, $context] = $this->fullyDisclosedPair();

        $this->setWeights(array_fill_keys(array_keys(JobComparisonState::FACTORS), 100));

        // The same four answerable sliders, with location ruled out because no
        // preferred places are saved.
        $comparison = $this->compare($ids, ['preferences' => null] + $context);
        $shares = $comparison['shares'];

        $this->assertSame(0, $shares['location']);
        $this->assertSame(100, array_sum($shares));
        $this->assertSame(25, $shares['salary']);
        $this->assertSame(25, $shares['work_life_balance']);
        $this->assertSame(25, $shares['career_growth']);
        $this->assertSame(25, $shares['skill_match']);

        $state = $comparison['availability']['location'];

        $this->assertFalse($state['available']);
        $this->assertSame(0, $state['disclosed_by']);
        $this->assertSame(2, $state['compared']);
        $this->assertFalse($state['partial']);
        $this->assertStringContainsString('Career Preferences', $state['reason']);
    }

    public function test_the_availability_panel_speaks_about_the_comparison_not_one_listing(): void
    {
        [$ids, $context] = $this->fullyDisclosedPair();

        $comparison = $this->compare($ids, $context);

        // Every factor is answerable somewhere, so none of them reports a reason.
        foreach (array_keys(JobComparisonState::FACTORS) as $key) {
            $this->assertTrue($comparison['availability'][$key]['available']);
            $this->assertNull($comparison['availability'][$key]['reason']);
        }

        // A career-growth phrase no listing writes is reported once, in words that
        // cover the whole set instead of blaming a single listing.
        $quiet = [
            $this->createJob(['JobTitle' => 'Quiet One', 'Description' => 'Flexible hours and paid leave.']),
            $this->createJob(['JobTitle' => 'Quiet Two', 'Description' => 'Four-day week with ample leave.']),
        ];

        $quietComparison = $this->compare($quiet);
        $growth = $quietComparison['availability']['career_growth'];

        $this->assertFalse($growth['available']);
        $this->assertStringNotContainsString('This listing', (string) $growth['reason']);
        $this->assertStringContainsString('None of these listings mention career growth', (string) $growth['reason']);
    }

    public function test_a_factor_answered_by_only_some_listings_is_flagged_as_partial(): void
    {
        $grown = $this->createJob(['JobTitle' => 'Grown', 'Description' => 'Structured training and a mentor.']);
        $quiet = $this->createJob(['JobTitle' => 'Quiet', 'Description' => 'A fast paced team shipping features.']);

        $growth = $this->compare([$grown, $quiet])['availability']['career_growth'];

        $this->assertTrue($growth['available']);
        $this->assertTrue($growth['partial']);
        $this->assertSame(1, $growth['disclosed_by']);
        $this->assertSame(2, $growth['compared']);
        $this->assertNull($growth['reason']);
    }

    /**
     * Two listings that between them answer all five factors: pay stated both
     * ways, a city the seeker saved, both soft phrases written into the
     * description, and requirements a resume can be matched against.
     *
     * @return array{0: list<int>, 1: array<string, mixed>}
     */
    private function fullyDisclosedPair(): array
    {
        $employer = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'City' => 'Bengaluru',
            'Area_Work' => 'Bengaluru',
            'Email' => 'hidden@career-next.test',
            'Status' => 'Approved',
        ]);

        $requirements = json_encode([['name' => 'Laravel', 'type' => 'mandatory']]);

        return [
            [
                $this->createJob([
                    'JobTitle' => 'Disclosed One',
                    'EmployerId' => $employer,
                    'SalaryFrom' => '50000',
                    'SalaryTo' => '60000',
                    'Description' => 'Flexible hours and paid leave, with structured training for joiners.',
                    'RequiredSkills' => $requirements,
                ]),
                $this->createJob([
                    'JobTitle' => 'Disclosed Two',
                    'EmployerId' => $employer,
                    'SalaryFrom' => '70000',
                    'SalaryTo' => '90000',
                    'Description' => 'A four-day week, a mentor and a promotion track.',
                    'RequiredSkills' => $requirements,
                ]),
            ],
            [
                'skills' => ['Laravel'],
                'software' => [],
                'preferences' => new JobSeekerCareerPreference([
                    'preferred_locations' => ['Bengaluru'],
                    'willing_to_relocate' => 'no',
                ]),
            ],
        ];
    }

    public function test_a_slider_at_zero_takes_no_part_in_the_score(): void
    {
        $requirements = json_encode([['name' => 'Laravel', 'type' => 'mandatory']]);

        $lower = $this->createJob([
            'SalaryFrom' => '5000',
            'SalaryTo' => '6000',
            'RequiredSkills' => $requirements,
        ]);
        $higher = $this->createJob([
            'SalaryFrom' => '90000',
            'SalaryTo' => '95000',
            'RequiredSkills' => $requirements,
        ]);

        $this->setWeights([
            'salary' => 0,
            'location' => 0,
            'work_life_balance' => 0,
            'career_growth' => 0,
            'skill_match' => 100,
        ]);

        $comparison = $this->compare([$lower, $higher], ['skills' => ['Laravel']]);
        $column = $comparison['columns'][$higher];

        $this->assertTrue($column['factors']['salary']['available']);
        $this->assertSame(0, $comparison['shares']['salary']);
        $this->assertSame(0, $column['score']['points']['salary']['share']);
        $this->assertSame(100, $column['score']['points']['skill_match']['share']);
    }

    public function test_everything_unanswered_says_there_is_not_enough_information(): void
    {
        $first = $this->createJob(['JobTitle' => 'Silent One']);
        $second = $this->createJob(['JobTitle' => 'Silent Two']);

        $this->setWeights(['salary' => 100, 'location' => 100]);

        $comparison = $this->compare([$first, $second]);

        $this->assertSame(JobOfferComparisonService::INSUFFICIENT, $comparison['message']);
        $this->assertNull($comparison['highest']);
        $this->assertNull($comparison['columns'][$first]['score']['personalized']);
        $this->assertSame(
            JobOfferComparisonService::INSUFFICIENT,
            $comparison['columns'][$first]['score']['message']
        );
    }

    public function test_all_sliders_at_zero_is_reported_as_no_priorities(): void
    {
        $jobId = $this->createJob(['SalaryFrom' => '40000', 'SalaryTo' => '50000']);

        $this->setWeights(['salary' => 0, 'location' => 0, 'work_life_balance' => 0, 'career_growth' => 0, 'skill_match' => 0]);

        $comparison = $this->compare([$jobId]);

        $this->assertSame(JobOfferComparisonService::NO_PRIORITIES, $comparison['message']);
        $this->assertSame('no_priorities', $comparison['columns'][$jobId]['score']['status']);
    }

    public function test_work_life_balance_is_scored_only_on_what_the_employer_wrote(): void
    {
        $stated = $this->createJob([
            'JobTitle' => 'Stated',
            'Description' => 'We offer flexible hours and paid leave for every employee.',
        ]);
        $quiet = $this->createJob(['JobTitle' => 'Quiet', 'Description' => 'Fast paced team shipping features.']);
        $single = $this->createJob(['JobTitle' => 'Single', 'Description' => 'This role includes paid leave.']);

        $this->setWeights(['work_life_balance' => 100]);

        $columns = $this->compare([$stated, $quiet, $single])['columns'];

        $this->assertTrue($columns[$stated]['factors']['work_life_balance']['available']);
        $this->assertSame(100, $columns[$stated]['factors']['work_life_balance']['score']);
        $this->assertStringContainsString('flexible hours', $columns[$stated]['factors']['work_life_balance']['basis']);

        $this->assertSame(75, $columns[$single]['factors']['work_life_balance']['score']);
        $this->assertFalse($columns[$quiet]['factors']['work_life_balance']['available']);
        $this->assertStringContainsString('work-life balance', $columns[$quiet]['factors']['work_life_balance']['basis']);
    }

    public function test_career_growth_looks_for_training_rather_than_for_adjectives(): void
    {
        $grown = $this->createJob([
            'Description' => 'Structured training plus a mentor for every new joiner.',
        ]);
        $plain = $this->createJob(['Description' => 'You will be a fast growing part of the team.']);

        $this->setWeights(['career_growth' => 100]);

        $columns = $this->compare([$grown, $plain])['columns'];

        $this->assertSame(100, $columns[$grown]['factors']['career_growth']['score']);
        $this->assertFalse($columns[$plain]['factors']['career_growth']['available']);
    }

    public function test_location_needs_saved_preferences_before_it_can_be_judged(): void
    {
        $employer = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'City' => 'Bengaluru',
            'Area_Work' => 'Bengaluru',
            'Email' => 'hidden@career-next.test',
            'Status' => 'Approved',
        ]);

        $jobId = $this->createJob(['EmployerId' => $employer]);

        $this->setWeights(['location' => 100]);

        $without = $this->compare([$jobId])['columns'][$jobId]['factors']['location'];

        $this->assertFalse($without['available']);

        $preference = new JobSeekerCareerPreference([
            'preferred_locations' => ['Bengaluru'],
            'willing_to_relocate' => 'no',
        ]);

        $matching = $this->compare([$jobId], ['preferences' => $preference])['columns'][$jobId]['factors']['location'];

        $this->assertTrue($matching['available']);
        $this->assertSame(100, $matching['score']);
        $this->assertStringContainsString('Bengaluru', $matching['basis']);

        $away = $this->compare([$jobId], [
            'preferences' => new JobSeekerCareerPreference([
                'preferred_locations' => ['Chennai'],
                'willing_to_relocate' => 'yes',
            ]),
        ])['columns'][$jobId]['factors']['location'];

        $this->assertSame(60, $away['score']);

        // "no" is not a yes: relocating earns nothing when the seeker ruled it out.
        $refused = $this->compare([$jobId], [
            'preferences' => new JobSeekerCareerPreference([
                'preferred_locations' => ['Chennai'],
                'willing_to_relocate' => 'no',
            ]),
        ])['columns'][$jobId]['factors']['location'];

        $this->assertSame(0, $refused['score']);
    }

    public function test_a_remote_listing_needs_no_city_to_be_judged(): void
    {
        $jobId = $this->createJob(['WorkArrangement' => 'Remote']);

        $this->setWeights(['location' => 100]);

        $factor = $this->compare([$jobId], [
            'preferences' => new JobSeekerCareerPreference(['preferred_locations' => ['Chennai']]),
        ])['columns'][$jobId]['factors']['location'];

        $this->assertTrue($factor['available']);
        $this->assertSame(100, $factor['score']);
    }

    public function test_skill_compatibility_reuses_the_smart_job_match_percentage(): void
    {
        $jobId = $this->createJob([
            'RequiredSkills' => json_encode([
                ['name' => 'Laravel', 'type' => 'mandatory'],
                ['name' => 'Vue', 'type' => 'mandatory'],
            ]),
        ]);

        $this->setWeights(['skill_match' => 100]);

        $job = Job::query()->findOrFail($jobId);
        $smartMatch = app(JobMatchService::class)->calculate(['Laravel'], $job, []);

        $comparison = $this->compare([$jobId], ['skills' => ['Laravel'], 'software' => []]);
        $factor = $comparison['columns'][$jobId]['factors']['skill_match'];

        $this->assertSame($smartMatch['percentage'], $factor['score']);
        $this->assertStringContainsString('Smart Job Match', $factor['basis']);

        // A listing that describes no requirements at all is left out rather
        // than scored zero; a thin listing still gets whatever Smart Match says.
        $bare = $this->createJob([
            'RequiredSkills' => null,
            'JobTitle' => 'Bare',
            'MinQualification' => null,
            'Description' => null,
        ]);

        $bareComparison = $this->compare([$bare], ['skills' => ['Laravel']]);

        $this->assertFalse($bareComparison['columns'][$bare]['factors']['skill_match']['available']);
    }

    public function test_unapproved_listings_are_not_compared_at_all(): void
    {
        $hidden = $this->createJob(['Status' => 'pending', 'JobTitle' => 'Pending Role']);
        $shown = $this->createJob(['JobTitle' => 'Live Role']);

        $comparison = $this->compare([$hidden, $shown]);

        $this->assertArrayNotHasKey($hidden, $comparison['columns']);
        $this->assertArrayHasKey($shown, $comparison['columns']);
        $this->assertSame([$hidden, $shown], $comparison['requested']);
    }

    public function test_the_whole_comparison_is_read_with_a_handful_of_queries(): void
    {
        $employer = DB::table('employer_reg')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'City' => 'Bengaluru',
            'Area_Work' => 'Bengaluru',
            'Status' => 'Approved',
        ]);

        $ids = [
            $this->createJob(['EmployerId' => $employer, 'SalaryFrom' => '20000', 'SalaryTo' => '30000']),
            $this->createJob(['EmployerId' => $employer, 'SalaryFrom' => '40000', 'SalaryTo' => '50000']),
            $this->createJob(['EmployerId' => $employer, 'SalaryFrom' => '60000', 'SalaryTo' => '70000']),
            $this->createJob(['EmployerId' => $employer, 'SalaryFrom' => '80000', 'SalaryTo' => '90000']),
        ];

        $reads = [];

        DB::listen(function (object $event) use (&$reads): void {
            // Schema guards ask sqlite what exists; only the data reads matter here.
            if (preg_match('/from\s+"?(job_master|employer_reg)"?/i', $event->sql)) {
                $reads[] = $event->sql;
            }
        });

        $this->compare($ids);

        // Four listings, two reads: the listings in one query and the employer
        // locations in another, so the count never grows with the selection.
        $this->assertCount(2, $reads);
        $this->assertSame(1, count(array_filter($reads, fn (string $sql): bool => str_contains($sql, 'job_master'))));
    }

    public function test_the_table_offers_the_three_documented_sections(): void
    {
        $comparison = $this->compare([$this->createJob()]);

        $this->assertSame(
            ['Job information', 'Compensation and benefits', 'Skills and compatibility'],
            array_keys($comparison['groups'])
        );

        $this->assertSame(JobComparisonState::FACTORS, $comparison['factors']);
    }
}
