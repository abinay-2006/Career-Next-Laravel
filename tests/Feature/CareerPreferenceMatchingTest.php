<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use App\Services\CareerPreferenceMatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The preference matcher on its own: pay compared as a normalised monthly
 * rupee range, experience compared as a band rather than identical wording,
 * and recommendations that stand on the non-title preferences when the seeker
 * never named a job title. Everything a side left blank stays unchecked
 * instead of becoming a rejection.
 */
class CareerPreferenceMatchingTest extends TestCase
{
    private CareerPreferenceMatchService $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new CareerPreferenceMatchService();

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
            $table->string('SalaryFrom', 50)->nullable();
            $table->string('SalaryTo', 50)->nullable();
            $table->string('Status')->nullable();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function listing(array $attributes = []): Job
    {
        return new Job(array_merge(['JobTitle' => 'Laravel Developer'], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function preferences(array $attributes = []): JobSeekerCareerPreference
    {
        return new JobSeekerCareerPreference(array_merge(['JobSeekId' => 1], $attributes));
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
            'Status' => 'approved',
        ], $attributes));
    }

    /**
     * @param  array<int, string>  $matched
     */
    private function assertMatched(array $matched, string $needle): void
    {
        $this->assertContains(
            $needle,
            $matched,
            'Expected the listing to be credited for ' . $needle . '.'
        );
    }

    /**
     * @param  array<int, array{job: Job, percentage: int, matched_titles: array<int, string>, matched_classifications: array<int, string>}>  $rows
     * @return array<int, int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['job']->JobId, $rows);
    }

    public function test_an_overlapping_salary_range_is_a_match(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '40000', 'maximum_salary' => '60000']),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );

        $this->assertSame([], $fit['conflicts']);
        $this->assertMatched($fit['matched'], 'Salary ₹45,000 – ₹70,000');
    }

    public function test_a_listing_priced_below_the_expected_minimum_conflicts(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '80000']),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );

        $this->assertSame([], $fit['matched']);
        $this->assertContains('Salary ₹45,000 – ₹70,000', $fit['conflicts']);
    }

    public function test_a_minimum_only_expectation_accepts_a_generous_listing(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '50000']),
            $this->listing(['SalaryFrom' => '60000', 'SalaryTo' => '70000'])
        );

        $this->assertSame([], $fit['conflicts']);
        $this->assertCount(1, $fit['matched']);
    }

    public function test_a_maximum_only_expectation_rejects_an_expensive_listing(): void
    {
        $within = $this->matcher->classificationFit(
            $this->preferences(['maximum_salary' => '50000']),
            $this->listing(['SalaryFrom' => '30000', 'SalaryTo' => '40000'])
        );
        $this->assertSame([], $within['conflicts']);
        $this->assertMatched($within['matched'], 'Salary ₹30,000 – ₹40,000');

        $above = $this->matcher->classificationFit(
            $this->preferences(['maximum_salary' => '50000']),
            $this->listing(['SalaryFrom' => '60000', 'SalaryTo' => '70000'])
        );
        $this->assertSame([], $above['matched']);
        $this->assertCount(1, $above['conflicts']);
    }

    public function test_an_expectation_that_names_no_pay_is_never_checked(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['preferred_job_titles' => ['Laravel Developer']]),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );

        $this->assertSame(['matched' => [], 'conflicts' => []], $fit);
    }

    public function test_a_listing_without_usable_pay_is_checked_rather_than_rejected(): void
    {
        // Free text from before the forms asked for an amount, and a listing
        // that simply left both boxes empty.
        foreach ([['SalaryFrom' => 'Negotiable', 'SalaryTo' => 'LPA 45'], []] as $attributes) {
            $fit = $this->matcher->classificationFit(
                $this->preferences(['minimum_salary' => '50000']),
                $this->listing($attributes)
            );

            $this->assertSame(['matched' => [], 'conflicts' => []], $fit);
        }
    }

    public function test_an_annual_expectation_is_normalised_before_it_is_compared(): void
    {
        // ₹6,00,000 a year is ₹50,000 a month, so it overlaps this listing;
        // ₹12,00,000 does not.
        $fits = $this->matcher->classificationFit(
            $this->preferences([
                'minimum_salary' => '600000',
                'salary_period' => 'annual',
                'currency' => 'INR',
            ]),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );
        $this->assertSame([], $fits['conflicts']);
        $this->assertCount(1, $fits['matched']);

        $tooMuch = $this->matcher->classificationFit(
            $this->preferences([
                'minimum_salary' => '1200000',
                'salary_period' => 'annual',
                'currency' => 'INR',
            ]),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );
        $this->assertSame([], $tooMuch['matched']);
        $this->assertCount(1, $tooMuch['conflicts']);
    }

    public function test_a_currency_the_listings_do_not_price_in_is_left_unchecked(): void
    {
        // job_master has no currency column and the forms ask for rupees, so a
        // dollar expectation is not silently read as rupees or converted.
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '100', 'currency' => 'USD']),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => '70000'])
        );

        $this->assertSame(['matched' => [], 'conflicts' => []], $fit);
    }

    public function test_pay_written_with_a_sign_and_separators_still_compares(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '40000', 'maximum_salary' => '60000']),
            $this->listing(['SalaryFrom' => '₹45,000', 'SalaryTo' => null])
        );

        $this->assertSame([], $fit['conflicts']);
        $this->assertMatched($fit['matched'], 'Salary ₹45,000');
    }

    public function test_a_listing_that_promises_only_a_floor_below_expectation_says_nothing(): void
    {
        // '₹45,000+' neither reaches nor refuses a seeker who asked for ₹90,000,
        // so it is left unchecked rather than credited or ruled out.
        $fit = $this->matcher->classificationFit(
            $this->preferences(['minimum_salary' => '90000']),
            $this->listing(['SalaryFrom' => '45000', 'SalaryTo' => null])
        );

        $this->assertSame(['matched' => [], 'conflicts' => []], $fit);

        $this->job(['JobTitle' => 'Laravel Developer', 'SalaryFrom' => '45000']);

        $recommended = $this->matcher->recommendations(
            $this->preferences(['preferred_job_titles' => ['Laravel Developer'], 'minimum_salary' => '90000'])
        );

        $this->assertCount(1, $recommended, 'Unavailable pay detail never disqualifies a listing.');
    }

    public function test_more_experience_than_a_listing_asks_for_still_fits(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => '3-5_years']),
            $this->listing(['ExperienceLevel' => '1-2_years'])
        );

        $this->assertSame([], $fit['conflicts']);
        $this->assertMatched($fit['matched'], '1–2 years');
    }

    public function test_a_senior_listing_rules_out_a_fresher(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => 'fresher']),
            $this->listing(['ExperienceLevel' => '6-10_years'])
        );

        $this->assertSame([], $fit['matched']);
        $this->assertContains('6–10 years', $fit['conflicts']);
    }

    public function test_the_same_experience_band_matches_itself(): void
    {
        $fit = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => '3-5_years']),
            $this->listing(['ExperienceLevel' => '3-5_years'])
        );

        $this->assertSame([], $fit['conflicts']);
        $this->assertMatched($fit['matched'], '3–5 years');
    }

    public function test_an_experience_of_any_and_an_unmapped_level_are_left_unchecked(): void
    {
        $any = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => 'any']),
            $this->listing(['ExperienceLevel' => '10_plus_years'])
        );
        $this->assertSame(['matched' => [], 'conflicts' => []], $any);

        // A listing saved before the vocabulary existed says nothing we can read.
        $legacy = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => 'entry_level']),
            $this->listing(['ExperienceLevel' => 'legacy_value'])
        );
        $this->assertSame(['matched' => [], 'conflicts' => []], $legacy);

        $unspecified = $this->matcher->classificationFit(
            $this->preferences(['experience_level' => 'entry_level']),
            $this->listing()
        );
        $this->assertSame(['matched' => [], 'conflicts' => []], $unspecified);
    }

    public function test_the_experience_bands_cover_every_slug_either_side_stores(): void
    {
        // No invented categories: the bands are exactly the choice list the
        // employer forms offer, plus the 'any' only a seeker can pick.
        $this->assertSame(array_keys(Job::EXPERIENCE_LEVELS), array_slice(array_keys(Job::EXPERIENCE_YEARS), 0, 7));
        $this->assertSame([0, null], Job::EXPERIENCE_YEARS['any']);
        $this->assertSame([10, null], Job::EXPERIENCE_YEARS['10_plus_years']);

        foreach (Job::EXPERIENCE_LEVELS as $slug => $label) {
            $this->assertIsArray(Job::experienceYears($slug), $slug . ' needs a band to be compared.');
        }

        $this->assertNull(Job::experienceYears(''));
        $this->assertNull(Job::experienceYears(null));
        $this->assertNull(Job::experienceYears('telepathist'));
    }

    public function test_recommendations_work_without_a_preferred_job_title(): void
    {
        $jobId = $this->job([
            'JobTitle' => 'Backend Engineer',
            'ExperienceLevel' => '1-2_years',
            'WorkArrangement' => 'Hybrid',
        ]);

        $recommended = $this->matcher->recommendations(
            $this->preferences(['experience_level' => '3-5_years', 'work_arrangements' => ['Hybrid']])
        );

        $this->assertCount(1, $recommended);
        $this->assertSame($jobId, (int) $recommended[0]['job']->JobId);
        $this->assertSame(100, $recommended[0]['percentage']);
        $this->assertSame([], $recommended[0]['matched_titles']);
        $this->assertMatched($recommended[0]['matched_classifications'], 'Hybrid');
    }

    public function test_a_job_is_still_recommended_when_its_pay_is_not_stated(): void
    {
        $this->job(['JobTitle' => 'Laravel Developer', 'SalaryFrom' => null, 'SalaryTo' => null]);

        $recommended = $this->matcher->recommendations(
            $this->preferences([
                'preferred_job_titles' => ['Laravel Developer'],
                'minimum_salary' => '50000',
            ])
        );

        $this->assertCount(1, $recommended);
        $this->assertSame(100, $recommended[0]['percentage']);
    }

    public function test_a_title_free_percentage_reports_how_much_of_the_request_a_listing_delivers(): void
    {
        $full = $this->job([
            'JobTitle' => 'Backend Engineer',
            'ExperienceLevel' => '1-2_years',
            'WorkArrangement' => 'Hybrid',
            'SalaryFrom' => '45000',
            'SalaryTo' => '70000',
        ]);
        $partial = $this->job(['JobTitle' => 'IT Support Executive', 'WorkArrangement' => 'Hybrid']);

        $recommended = $this->matcher->recommendations(
            $this->preferences([
                'experience_level' => '3-5_years',
                'work_arrangements' => ['Hybrid'],
                'minimum_salary' => '40000',
            ])
        );

        $this->assertSame([$full, $partial], $this->ids($recommended));
        $this->assertSame([100, 33], array_column($recommended, 'percentage'));
    }

    public function test_a_title_free_seeker_is_not_shown_jobs_that_match_nothing(): void
    {
        $generous = $this->job(['JobTitle' => 'Architect', 'SalaryFrom' => '100000']);
        $this->job(['JobTitle' => 'Peon', 'SalaryFrom' => '45000']);
        $this->job(['JobTitle' => 'Librarian']);

        $recommended = $this->matcher->recommendations($this->preferences(['minimum_salary' => '90000']));

        $this->assertSame([$generous], $this->ids($recommended));
    }

    public function test_a_contradicted_preference_still_rules_out_a_title_free_recommendation(): void
    {
        $remote = $this->job(['JobTitle' => 'Support Executive', 'WorkArrangement' => 'Remote']);
        $this->job(['JobTitle' => 'Store Manager', 'WorkArrangement' => 'On-site']);

        $recommended = $this->matcher->recommendations(
            $this->preferences(['work_arrangements' => ['Remote']])
        );

        $this->assertSame([$remote], $this->ids($recommended));
    }

    public function test_the_existing_limit_and_deterministic_order_hold_without_titles(): void
    {
        $jobIds = [];

        foreach (range(1, 8) as $number) {
            $jobIds[] = $this->job(['JobTitle' => 'Ops Engineer ' . $number, 'WorkArrangement' => 'Remote']);
        }

        $this->job(['JobTitle' => 'Unapproved Role', 'WorkArrangement' => 'Remote', 'Status' => 'pending']);

        $preferences = $this->preferences(['work_arrangements' => ['Remote']]);

        $recommended = $this->matcher->recommendations($preferences);
        $again = $this->matcher->recommendations($preferences);

        $this->assertCount(6, $recommended, 'The dashboard keeps its six-card limit.');
        $this->assertSame($this->ids($recommended), $this->ids($again));
        $this->assertSame(
            array_slice(array_reverse($jobIds), 0, 6),
            $this->ids($recommended)
        );
        $this->assertSame(array_fill(0, 6, 100), array_column($recommended, 'percentage'));
    }

    public function test_a_seeker_with_nothing_filled_in_gets_nothing(): void
    {
        $this->job();

        $this->assertSame([], $this->matcher->recommendations($this->preferences()));
        $this->assertSame([], $this->matcher->recommendations($this->preferences([
            'preferred_locations' => ['Chennai'],
        ])));
        $this->assertSame([], $this->matcher->recommendations(null));
        $this->assertSame([], $this->matcher->recommendations(
            $this->preferences(['experience_level' => 'any']),
            6
        ));
        $this->assertSame([], $this->matcher->recommendations(
            $this->preferences(['preferred_job_titles' => ['Laravel Developer']]),
            0
        ));
    }
}
