<?php

namespace Tests\Unit;

use App\Services\JobQualityService;
use Tests\TestCase;

/**
 * The scoring rules of the Job Quality Checker, read straight off the service.
 *
 * Every test names the rule it is about, so a change of wording in one message
 * cannot quietly move the number: the points, the severity and the advice are
 * asserted together.
 */
class JobQualityServiceTest extends TestCase
{
    private JobQualityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new JobQualityService();
    }

    /**
     * A posting that could be published as it stands: everything the schema can
     * hold is filled in.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draft(array $overrides = []): array
    {
        return array_merge([
            'txtTitle' => 'Senior Laravel Developer',
            'txtTotal' => '3',
            'cmbQual' => 'B.E / B.Tech',
            'cmbExperience' => '3-5_years',
            'cmbArrangement' => 'Hybrid',
            'cmbEmployment' => 'Full-time',
            'txtSalaryFrom' => '60000',
            'txtSalaryTo' => '90000',
            'txtDesc' => 'You will design and maintain internal Laravel services. Duties include reviewing '
                . 'code. Strong PHP and MySQL experience required. Benefits: health insurance and a bonus.',
            'required_skills' => json_encode([
                ['name' => 'PHP', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Vue', 'type' => 'preferred', 'proficiency' => 'intermediate'],
            ]),
            'required_software' => json_encode([
                ['name' => 'VS Code', 'type' => 'mandatory', 'proficiency' => 'basic'],
            ]),
            'company' => 'Acme Software Ltd',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rule(array $report, string $id, string $list): ?array
    {
        foreach ($report[$list] as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function category(array $report): array
    {
        return $report['categories'];
    }

    /**
     * Points a single category won, read by key instead of by position.
     */
    private function points(array $report, string $key): int
    {
        foreach ($report['categories'] as $category) {
            if ($category['key'] === $key) {
                return (int) $category['earned'];
            }
        }

        $this->fail('Unknown category: ' . $key);
    }

    /**
     * The checker is handed the signed-in Employer and reads only its City, so a
     * stub keeps the unit tests off the database. The endpoint covers the model.
     */
    private function profile(string $city = 'Pune'): object
    {
        return new class ($city) {
            public function __construct(public string $City)
            {
            }
        };
    }

    public function test_the_categories_add_up_to_a_hundred_points(): void
    {
        $total = 0;

        foreach (JobQualityService::CATEGORIES as $meta) {
            $total += $meta['max'];
        }

        $this->assertSame(JobQualityService::MAX_SCORE, $total);

        $report = $this->service->evaluate($this->draft());

        foreach ($this->category($report) as $category) {
            $this->assertLessThanOrEqual($category['max'], $category['earned'], $category['label'] . ' over-awarded itself.');
        }
    }

    public function test_an_untouched_posting_is_scored_incomplete_and_earliest_nothing(): void
    {
        $report = $this->service->evaluate([]);

        $this->assertSame('incomplete', $report['band']);
        $this->assertSame('Incomplete', $report['label']);
        $this->assertLessThanOrEqual(10, $report['score']);
        $this->assertSame(0, $report['counts']['completed']);

        $this->assertNotNull($this->rule($report, 'title_present', 'warnings'));
        $this->assertNotNull($this->rule($report, 'description_length', 'warnings'));
        $this->assertNotNull($this->rule($report, 'required_skills', 'warnings'));

        // An empty description is not a placeholder and not a repetition, so
        // those rules stay quiet instead of warning three times about one gap.
        $this->assertNull($this->rule($report, 'description_content', 'warnings'));
        $this->assertNull($this->rule($report, 'description_repetition', 'warnings'));

        $this->assertContains('Job title', array_column($report['missing'], 'label'));
        $this->assertContains('Description', array_column($report['missing'], 'label'));
    }

    public function test_a_complete_posting_outranks_the_empty_one_and_lands_in_a_high_band(): void
    {
        $empty = $this->service->evaluate([]);
        $report = $this->service->evaluate($this->draft(), $this->profile());

        $this->assertGreaterThanOrEqual(80, $report['score']);
        $this->assertContains($report['band'], ['good', 'excellent']);
        $this->assertGreaterThan($empty['score'], $report['score']);

        $this->assertNotNull($this->rule($report, 'title_clear', 'completed'));
        $this->assertNotNull($this->rule($report, 'description_responsibilities', 'completed'));
        $this->assertNotNull($this->rule($report, 'required_skills', 'completed'));
        $this->assertNotNull($this->rule($report, 'salary_valid', 'completed'));
        $this->assertSame([], $report['warnings']);

        // Same posting, employer profile without a city: the location gap is the
        // only thing the checker has left to complain about.
        $noCity = $this->service->evaluate($this->draft());
        $this->assertNotNull($this->rule($noCity, 'location', 'warnings'));
        $this->assertCount(1, $noCity['warnings']);
    }

    public function test_a_remote_posting_needs_no_city_to_answer_the_location(): void
    {
        $report = $this->service->evaluate($this->draft(['cmbArrangement' => 'Remote']));

        $this->assertNull($this->rule($report, 'location', 'warnings'));
        $this->assertNotNull($this->rule($report, 'location', 'completed'));
    }

    public function test_a_missing_salary_is_an_informational_warning_and_costs_only_visibility_points(): void
    {
        $without = $this->service->evaluate($this->draft([
            'txtSalaryFrom' => '',
            'txtSalaryTo' => '',
        ]));

        $warning = $this->rule($without, 'salary_disclosed', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('info', $warning['severity'], 'A missing salary must not read as an important warning.');
        $this->assertStringContainsString('optional', $warning['detail']);
        $this->assertStringNotContainsString('fraud', mb_strtolower(json_encode($without)));

        // The range was never written, so it cannot be called invalid either: the
        // category keeps its three validity points and only loses the five for
        // disclosure.
        $this->assertNull($this->rule($without, 'salary_valid', 'warnings'));
        $this->assertSame(5, $this->points($without, 'compensation'));
    }

    public function test_a_missing_benefit_line_is_an_informational_warning(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtDesc' => 'You will build internal Laravel services and review junior code. Strong PHP experience required.',
        ]));

        $warning = $this->rule($report, 'benefits', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('info', $warning['severity']);
        $this->assertStringContainsString('no benefits field', $warning['detail']);

        $this->assertNotNull($this->rule($report, 'benefits', 'suggestions'));
    }

    public function test_benefits_written_in_the_description_are_credited(): void
    {
        $report = $this->service->evaluate($this->draft());

        $this->assertNotNull($this->rule($report, 'benefits', 'completed'));
        $this->assertNotNull($this->rule($report, 'extra_compensation', 'completed'));
    }

    public function test_a_posting_without_required_skills_is_flagged(): void
    {
        $report = $this->service->evaluate($this->draft(['required_skills' => '[]']));

        $warning = $this->rule($report, 'required_skills', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('warning', $warning['severity'], 'A missing skills list matters for Smart Job Match.');
        $this->assertSame('required_skills', $warning['field']);

        // Ten of the twenty-five requirement points hang on the skills list, so
        // one gap cannot empty the category: the software list and its
        // proficiency levels are still read and still counted.
        $this->assertGreaterThanOrEqual(9, $this->points($report, 'requirements'));
    }

    public function test_only_preferred_skills_is_read_as_a_weaker_requirement_list(): void
    {
        $report = $this->service->evaluate($this->draft([
            'required_skills' => json_encode([
                ['name' => 'Figma', 'type' => 'preferred', 'proficiency' => 'basic'],
            ]),
        ]));

        $warning = $this->rule($report, 'required_skills', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('warning', $warning['severity']);
        $this->assertStringContainsString('mandatory', $warning['label']);

        // Preferred skills still count for their own four points.
        $this->assertNotNull($this->rule($report, 'preferred_skills', 'completed'));
    }

    public function test_a_single_mandatory_skill_scores_less_than_a_real_list(): void
    {
        $one = $this->service->evaluate($this->draft([
            'required_skills' => json_encode([
                ['name' => 'PHP', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Vue', 'type' => 'preferred', 'proficiency' => 'basic'],
            ]),
        ]));

        $this->assertNotNull($this->rule($one, 'required_skills', 'warnings'));
        $this->assertGreaterThanOrEqual(20, $this->points($one, 'requirements'));
    }

    public function test_structured_required_software_is_evaluated_from_the_existing_column_shape(): void
    {
        $report = $this->service->evaluate($this->draft());

        $this->assertNotNull($this->rule($report, 'required_software', 'completed'));

        $without = $this->service->evaluate($this->draft(['required_software' => '']));

        $this->assertNotNull($this->rule($without, 'required_software', 'suggestions'));
        $this->assertLessThan($report['score'], $without['score']);
    }

    public function test_structured_skills_are_also_read_from_a_decoded_array(): void
    {
        // The saved row hands over the cast JSON, the form a string. Both are the
        // same listing, so neither may score differently.
        $fromString = $this->service->evaluate($this->draft());
        $fromArray = $this->service->evaluate($this->draft([
            'required_skills' => [
                ['name' => 'PHP', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'Vue', 'type' => 'preferred', 'proficiency' => 'intermediate'],
            ],
        ]));

        $this->assertSame($fromString['score'], $fromArray['score']);
    }

    public function test_rubbish_in_the_requirement_field_is_ignored_instead_of_trusted(): void
    {
        $report = $this->service->evaluate($this->draft([
            'required_skills' => 'this is not json',
            'required_software' => json_encode('not a list'),
        ]));

        $this->assertNotNull($this->rule($report, 'required_skills', 'warnings'));
        $this->assertGreaterThanOrEqual(0, $report['score']);
    }

    public function test_proficiency_levels_are_only_credited_when_they_are_actually_set(): void
    {
        $report = $this->service->evaluate($this->draft([
            'required_skills' => json_encode([
                ['name' => 'PHP', 'type' => 'mandatory', 'proficiency' => null],
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
            ]),
            'required_software' => json_encode([
                ['name' => 'Jira', 'type' => 'mandatory', 'proficiency' => null],
            ]),
        ]));

        $this->assertNotNull($this->rule($report, 'proficiency_levels', 'suggestions'));
        $this->assertNull($this->rule($report, 'proficiency_levels', 'completed'));
    }

    public function test_an_upside_down_salary_range_is_reported_as_invalid(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtSalaryFrom' => '90000',
            'txtSalaryTo' => '30000',
        ]));

        $warning = $this->rule($report, 'salary_valid', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('critical', $warning['severity']);
        $this->assertStringContainsString('wrong way round', $warning['label']);
        $this->assertSame('txtSalaryTo', $warning['field']);
        $this->assertStringContainsString('Swap', $this->rule($report, 'salary_valid', 'suggestions')['label']);
    }

    public function test_a_salary_bound_of_zero_is_reported_as_a_range_problem(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtSalaryFrom' => '0',
            'txtSalaryTo' => '90000',
        ]));

        $this->assertNotNull($this->rule($report, 'salary_valid', 'warnings'));
    }

    public function test_a_non_numeric_salary_is_reported_as_invalid(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtSalaryFrom' => 'negotiable',
            'txtSalaryTo' => '90000',
        ]));

        $warning = $this->rule($report, 'salary_valid', 'warnings');

        $this->assertNotNull($warning);
        $this->assertStringContainsString('numbers', $warning['label']);
    }

    public function test_only_one_end_of_the_range_is_asked_for_the_other(): void
    {
        $report = $this->service->evaluate($this->draft(['txtSalaryTo' => '']));

        $warning = $this->rule($report, 'salary_disclosed', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('txtSalaryTo', $warning['field']);
    }

    public function test_a_very_short_description_is_flagged(): void
    {
        $report = $this->service->evaluate($this->draft(['txtDesc' => 'Developer needed for our team soon.']));

        $warning = $this->rule($report, 'description_length', 'warnings');

        $this->assertNotNull($warning);
        $this->assertStringContainsString('characters', $warning['label']);
        $this->assertSame('warning', $warning['severity']);
    }

    public function test_a_medium_description_earns_partial_points_rather_than_a_warning_free_pass(): void
    {
        $prose = 'You will design and maintain internal Laravel services. Duties include reviewing pull '
            . 'requests, mentoring two juniors and writing runbooks. Strong PHP and MySQL experience is '
            . 'required, and you should be comfortable with a small product team every day.';

        $short = $this->points($this->service->evaluate($this->draft(['txtDesc' => mb_substr($prose, 0, 30)])), 'description');
        $medium = $this->points($this->service->evaluate($this->draft(['txtDesc' => mb_substr($prose, 0, 90)])), 'description');
        $long = $this->points($this->service->evaluate($this->draft(['txtDesc' => mb_substr($prose, 0, 160)])), 'description');

        $this->assertGreaterThan(0, $short, 'Thirty thin characters still beat silence.');
        $this->assertGreaterThan($short, $medium, 'Ninety characters is not a warning, but it is not full marks either.');
        $this->assertGreaterThan($medium, $long, 'More of the same prose has to earn more.');

        $shortReport = $this->service->evaluate($this->draft(['txtDesc' => mb_substr($prose, 0, 30)]));
        $mediumReport = $this->service->evaluate($this->draft(['txtDesc' => mb_substr($prose, 0, 90)]));

        $this->assertNotNull($this->rule($shortReport, 'description_length', 'warnings'));
        $this->assertNotNull($this->rule($mediumReport, 'description_length', 'completed'));
    }

    public function test_placeholder_text_is_flagged_and_earns_no_description_points(): void
    {
        foreach (['Lorem ipsum dolor sit amet, consectetur adipiscing elit.', 'test description', 'Description here'] as $filler) {
            $report = $this->service->evaluate($this->draft(['txtDesc' => $filler]));

            $warning = $this->rule($report, 'description_length', 'warnings');

            $this->assertNotNull($warning, 'Placeholder text was scored as content: ' . $filler);
            $this->assertSame('critical', $warning['severity']);
            $this->assertStringContainsString('placeholder', $warning['label']);
            $this->assertLessThanOrEqual(3, $this->points($report, 'description'));
        }
    }

    public function test_a_one_word_answer_is_not_mistaken_for_a_real_description(): void
    {
        $report = $this->service->evaluate($this->draft(['txtDesc' => 'none']));

        $this->assertSame('critical', $this->rule($report, 'description_length', 'warnings')['severity']);
    }

    public function test_valid_wording_that_happens_to_contain_a_signal_letter_is_not_flagged(): void
    {
        // Avoiding false positives matters more than catching every case.
        $report = $this->service->evaluate($this->draft([
            'txtDesc' => 'We need a Naive Bayes engineer to maintain our classification services. '
                . 'You will review models and train juniors. Strong Python experience required.',
        ]));

        $this->assertNull($this->rule($report, 'description_length', 'warnings'));
        $this->assertNotNull($this->rule($report, 'description_responsibilities', 'completed'));
    }

    public function test_repeated_sentences_are_flagged_once(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtDesc' => 'Manage the billing service. Manage the billing service. '
                . 'You will review code monthly and mentor two juniors. Strong PHP experience required.',
        ]));

        $warning = $this->rule($report, 'description_repetition', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('warning', $warning['severity']);
        $this->assertStringContainsString('repeat', $warning['label']);
    }

    public function test_a_description_that_only_repeats_the_title_is_flagged_as_having_no_information(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtTitle' => 'Python Developer',
            'txtDesc' => 'python developer python developer',
        ]));

        $this->assertNotNull($this->rule($report, 'description_content', 'warnings'));
    }

    public function test_a_description_longer_than_the_column_is_reported_as_a_save_blocker(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtDesc' => str_repeat('Laravel services for our internal tools. ', 12),
        ]));

        $warning = $this->rule($report, 'description_limit', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('critical', $warning['severity']);
        $this->assertStringContainsString((string) JobQualityService::DESCRIPTION_LIMIT, $warning['label']);
    }

    public function test_a_title_over_the_column_limit_is_reported_as_a_save_blocker(): void
    {
        $report = $this->service->evaluate($this->draft(['txtTitle' => str_repeat('A', JobQualityService::TITLE_LIMIT + 12)]));

        $warning = $this->rule($report, 'title_clear', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('critical', $warning['severity']);
        $this->assertStringContainsString('50 characters', $warning['label']);
    }

    public function test_a_placeholder_title_earns_fewer_points_than_a_named_role(): void
    {
        foreach (['job', 'vacancy', 'test', 'hiring'] as $placeholder) {
            $report = $this->service->evaluate($this->draft(['txtTitle' => $placeholder]));

            $warning = $this->rule($report, 'title_clear', 'warnings');

            $this->assertNotNull($warning, 'A placeholder title slipped through: ' . $placeholder);
            $this->assertStringContainsString('Add a clearer job title', $this->rule($report, 'title_clear', 'suggestions')['label']);
        }
    }

    public function test_a_one_word_real_role_is_asked_for_more_detail(): void
    {
        $report = $this->service->evaluate($this->draft(['txtTitle' => 'Accountant']));

        $warning = $this->rule($report, 'title_clear', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('info', $warning['severity'], 'A single real role name is not an important warning.');
    }

    public function test_an_empty_qualification_is_flagged_and_a_blank_other_is_a_save_blocker(): void
    {
        $missing = $this->service->evaluate($this->draft(['cmbQual' => '', 'txtOther' => '']));

        $this->assertNotNull($this->rule($missing, 'qualification', 'warnings'));
        $this->assertContains('Minimum qualification', array_column($missing['missing'], 'label'));

        $other = $this->service->evaluate($this->draft(['cmbQual' => 'Other', 'txtOther' => '']));

        $warning = $this->rule($other, 'qualification', 'warnings');

        $this->assertNotNull($warning);
        $this->assertSame('critical', $warning['severity']);
        $this->assertSame('txtOther', $warning['field']);
    }

    public function test_a_qualification_written_as_free_text_is_accepted_as_stated(): void
    {
        // A saved row keeps whatever the employer typed, including values that
        // are not in the degree list.
        $report = $this->service->evaluate($this->draft(['qualification' => 'Two years of trade school']));

        $this->assertNotNull($this->rule($report, 'qualification', 'completed'));
    }

    public function test_the_location_comes_from_the_work_mode_or_the_company_profile(): void
    {
        $remote = $this->service->evaluate($this->draft(['cmbArrangement' => 'Remote']));

        $this->assertNotNull($this->rule($remote, 'location', 'completed'));

        $unknown = $this->service->evaluate($this->draft(['cmbArrangement' => '']));

        $warning = $this->rule($unknown, 'location', 'warnings');

        $this->assertNotNull($warning);
        $this->assertStringContainsString('where the job is based', $warning['label']);
    }

    public function test_a_missing_vacancy_count_is_flagged(): void
    {
        $report = $this->service->evaluate($this->draft(['txtTotal' => '']));

        $this->assertNotNull($this->rule($report, 'vacancy', 'warnings'));
        $this->assertContains('Vacancies', array_column($report['missing'], 'label'));
    }

    public function test_missing_employment_details_are_flagged_and_named(): void
    {
        $report = $this->service->evaluate($this->draft([
            'cmbEmployment' => '',
            'cmbArrangement' => '',
            'cmbExperience' => '',
        ]));

        $this->assertNotNull($this->rule($report, 'employment_type', 'warnings'));
        $this->assertNotNull($this->rule($report, 'work_mode', 'warnings'));
        $this->assertNotNull($this->rule($report, 'experience_level', 'warnings'));
    }

    public function test_the_score_never_exceeds_the_maximum(): void
    {
        $report = $this->service->evaluate($this->draft([
            'txtTitle' => 'Senior Laravel Developer (Remote, Mumbai)',
            'txtDesc' => 'You will design, build and maintain internal Laravel services. Duties include reviewing '
                . 'code, mentoring two juniors and writing deployment runbooks. You need five years of PHP '
                . 'experience, strong MySQL knowledge and an engineering degree. Benefits: health insurance, '
                . 'paid leave, transport allowance and an annual performance bonus with profit sharing.',
        ]));

        $this->assertLessThanOrEqual(JobQualityService::MAX_SCORE, $report['score']);

        foreach ($this->category($report) as $category) {
            $this->assertLessThanOrEqual($category['max'], $category['earned']);
        }
    }

    public function test_the_score_never_drops_below_zero(): void
    {
        $report = $this->service->evaluate([
            'txtTitle' => '???',
            'txtTotal' => '-5',
            'cmbQual' => 'Other',
            'txtDesc' => 'zzz zzz zzz',
            'txtSalaryFrom' => 'abc',
            'txtSalaryTo' => '',
            'required_skills' => '[{"name":"","type":"nonsense"}]',
            'required_software' => 'not json at all',
        ]);

        $this->assertGreaterThanOrEqual(0, $report['score']);

        foreach ($this->category($report) as $category) {
            $this->assertGreaterThanOrEqual(0, $category['earned'], $category['label']);
        }
    }

    public function test_every_warning_names_the_check_and_explains_it(): void
    {
        $report = $this->service->evaluate([
            'txtTitle' => 'job',
            'txtDesc' => 'lorem ipsum',
            'txtSalaryFrom' => '90000',
            'txtSalaryTo' => '1000',
        ]);

        $this->assertNotEmpty($report['warnings']);

        $advice = array_column($report['suggestions'], 'label');

        foreach ($report['warnings'] as $warning) {
            $this->assertNotEmpty($warning['id']);
            $this->assertNotEmpty($warning['label'], 'A warning arrived without a name.');
            $this->assertGreaterThan(25, mb_strlen((string) $warning['detail']), 'Unexplained warning: ' . $warning['id']);
            $this->assertContains($warning['severity'], ['critical', 'warning', 'info']);

            // The employer is always told what to do next about a warning.
            $this->assertNotEmpty($advice);
        }
    }

    public function test_the_band_labels_are_the_documented_ones(): void
    {
        $labels = array_column(JobQualityService::BANDS, 'label');

        $this->assertSame(['Excellent', 'Good', 'Needs improvement', 'Incomplete'], $labels);
        $this->assertSame(90, JobQualityService::BANDS[0]['min']);
        $this->assertSame(75, JobQualityService::BANDS[1]['min']);
        $this->assertSame(60, JobQualityService::BANDS[2]['min']);
    }

    public function test_a_high_score_is_never_sold_as_proof_that_the_employer_is_genuine(): void
    {
        $report = $this->service->evaluate($this->draft());

        $this->assertStringContainsString('does not verify an employer', $report['disclaimer']);

        foreach ([$report['label'], $report['disclaimer']] as $text) {
            $this->assertDoesNotMatchRegularExpression('/fraud|scam|legitimate|genuine employer|verified employer/i', $text);
        }

        $this->assertStringNotContainsString('legitimacy', json_encode($report['categories']));
    }

    public function test_words_alone_never_buy_the_description_points(): void
    {
        // The same word count, with and without anything a candidate can use.
        $padded = $this->service->evaluate($this->draft([
            'txtDesc' => 'Nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice nice.',
        ]));

        $useful = $this->service->evaluate($this->draft());

        $this->assertLessThan($this->points($useful, 'description'), $this->points($padded, 'description'));
        $this->assertNotNull($this->rule($padded, 'description_repetition', 'warnings'));
    }

    public function test_a_job_is_scored_from_its_own_columns_without_the_form_field_names(): void
    {
        $job = new \App\Models\Job([
            'JobTitle' => 'Junior Data Analyst',
            'Vacancy' => 2,
            'MinQualification' => 'B.C.A',
            'Description' => 'You will prepare weekly reports and maintain the dashboards. SQL knowledge required.',
            'SalaryFrom' => '25000',
            'SalaryTo' => '35000',
            'ExperienceLevel' => 'fresher',
            'WorkArrangement' => 'On-site',
            'EmploymentType' => 'Full-time',
            'RequiredSkills' => [
                ['name' => 'SQL', 'type' => 'mandatory', 'proficiency' => 'intermediate'],
                ['name' => 'Excel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
            ],
            'RequiredSoftware' => [['name' => 'Power BI', 'type' => 'mandatory', 'proficiency' => 'basic']],
            'CompanyName' => 'Acme Analytics',
        ]);

        $fromJob = $this->service->evaluateJob($job);
        $fromFields = $this->service->evaluate($this->service->fieldsFromJob($job));

        $this->assertSame($fromJob['score'], $fromFields['score']);
        $this->assertGreaterThanOrEqual(70, $fromJob['score']);
        $this->assertNotNull($this->rule($fromJob, 'qualification', 'completed'));
    }

    public function test_a_saved_listing_is_summarised_for_the_jobs_table(): void
    {
        $job = new \App\Models\Job(['JobTitle' => 'job', 'Vacancy' => 1, 'Description' => 'lorem ipsum']);

        $summary = $this->service->summariseReport($this->service->evaluateJob($job));

        $this->assertSame($summary['score'], $this->service->evaluateJob($job)['score']);
        $this->assertSame('incomplete', $summary['band']);
        $this->assertNotEmpty($summary['missing']);
        $this->assertLessThanOrEqual(2, count($summary['missing']));
    }
}
