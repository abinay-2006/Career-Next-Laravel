<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use App\Services\JobSuitabilityService;
use Tests\TestCase;

/**
 * Job Suitability Assessment on its own: which requirement a listing states,
 * which value the seeker has recorded, and what follows from the two. Missing
 * information is expected to read as unknown rather than as a failure, and no
 * status here is decided by a percentage cut-off.
 *
 * The Smart Job Match numbers themselves are covered by JobMatchServiceTest and
 * the pay and experience comparisons by CareerPreferenceMatchingTest; this suite
 * only covers how those results are turned into a suitability verdict.
 */
class JobSuitabilityServiceTest extends TestCase
{
    private JobSuitabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new JobSuitabilityService(
            new \App\Services\JobMatchService(),
            new \App\Services\CareerPreferenceMatchService()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function context(array $overrides = []): array
    {
        return array_merge([
            'job_seeker_id' => 1,
            'skills' => [],
            'software' => [],
            'qualifications' => [],
            'experience_level' => null,
            'preferences' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function job(array $attributes = []): Job
    {
        return new Job(array_merge([
            'JobTitle' => 'Laravel Developer',
            'MinQualification' => 'Any',
        ], $attributes));
    }

    /**
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
     * @param  array<string, mixed>  $overrides
     */
    private function preferences(array $overrides): JobSeekerCareerPreference
    {
        return new JobSeekerCareerPreference(array_merge(['JobSeekId' => 1], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function assess(array $overrides = [], array $jobAttributes = []): array
    {
        return $this->service->assess($this->context($overrides), $this->job($jobAttributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function criterion(array $assessment, string $key): array
    {
        return $assessment['criteria'][$key];
    }

    /* ---------------------------------------------------------------- result */

    public function test_the_result_carries_every_section_both_views_render(): void
    {
        $assessment = $this->assess();

        foreach ([
            'status', 'label', 'tone', 'icon', 'percentage', 'essential_met', 'essential_total',
            'explanation', 'criteria',
            'matched_requirements', 'missing_requirements', 'unknown_requirements', 'suggestions',
        ] as $key) {
            $this->assertArrayHasKey($key, $assessment);
        }

        $this->assertSame([
            'qualification', 'mandatory_skills', 'preferred_skills', 'experience',
            'software', 'salary', 'employment_type', 'work_arrangement',
        ], array_keys($assessment['criteria']));

        foreach ($assessment['criteria'] as $criterion) {
            foreach (['name', 'status', 'label', 'essential', 'expected', 'candidate', 'explanation'] as $key) {
                $this->assertArrayHasKey($key, $criterion);
            }
        }
    }

    /* -------------------------------------------------------- qualification */

    public function test_a_qualification_above_the_minimum_satisfies_the_listing(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['MBA']],
            ['MinQualification' => 'B.C.A']
        );

        $criterion = $this->criterion($assessment, 'qualification');

        $this->assertSame(JobSuitabilityService::CRITERION_MET, $criterion['status']);
        $this->assertSame('B.C.A', $criterion['expected']);
        $this->assertSame('MBA', $criterion['candidate']);
        $this->assertStringContainsString('above', $criterion['explanation']);
    }

    public function test_a_qualification_below_the_minimum_is_not_suitable(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['Diploma']],
            ['MinQualification' => 'B.C.A']
        );

        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame('Not Suitable', $assessment['label']);
        $this->assertStringContainsString('requires', $assessment['explanation']);
        $this->assertContains('Qualification: B.C.A', $assessment['missing_requirements']);
    }

    public function test_equivalent_qualification_spellings_are_the_same_level(): void
    {
        // The profile form stores free text ("BCA"), the employer form stores the
        // picker value ("B.C.A"), and "B.E / B.Tech" names two degrees at one level.
        foreach ([['BCA', 'B.C.A'], ['b.c.a', 'BCA'], ['B.Tech', 'B.E / B.Tech'], ['Bachelor of Technology', 'B.E / B.Tech']] as [$held, $required]) {
            $assessment = $this->assess(
                ['qualifications' => [$held]],
                ['MinQualification' => $required]
            );

            $this->assertSame(
                JobSuitabilityService::CRITERION_MET,
                $this->criterion($assessment, 'qualification')['status'],
                $held.' should satisfy a listing asking for '.$required.'.'
            );
        }
    }

    public function test_a_missing_candidate_qualification_is_unknown_not_a_rejection(): void
    {
        $assessment = $this->assess([], ['MinQualification' => 'B.C.A']);

        $criterion = $this->criterion($assessment, 'qualification');

        $this->assertSame(JobSuitabilityService::CRITERION_UNKNOWN, $criterion['status']);
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame(JobSuitabilityService::STATUS_INSUFFICIENT, $assessment['status']);
        $this->assertNotEmpty($criterion['explanation']);
    }

    public function test_a_listing_that_states_no_qualification_never_rejects(): void
    {
        $assessment = $this->assess(['qualifications' => ['Diploma']]);

        $criterion = $this->criterion($assessment, 'qualification');

        $this->assertFalse($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_NOT_STATED, $criterion['status']);
        $this->assertSame([], $criterion['missing']);
    }

    public function test_a_free_text_requirement_is_left_unranked(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            ['MinQualification' => 'Certified Welder']
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_NOT_STATED,
            $this->criterion($assessment, 'qualification')['status']
        );
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
    }

    /* -------------------------------------------------------------- skills */

    public function test_every_tagged_mandatory_skill_covered_is_suitable(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel', 'MySQL'],
                'software' => ['Laravel', 'MySQL'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel', 'MySQL']),
            ]
        );

        $this->assertSame(JobSuitabilityService::CRITERION_MET, $this->criterion($assessment, 'mandatory_skills')['status']);
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertStringContainsString('Laravel', $assessment['criteria']['mandatory_skills']['explanation']);
    }

    public function test_a_missing_mandatory_skill_is_reported_and_disqualifies(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel', 'Docker']),
            ]
        );

        $criterion = $this->criterion($assessment, 'mandatory_skills');

        $this->assertSame(JobSuitabilityService::CRITERION_UNMET, $criterion['status']);
        $this->assertSame(['Docker'], $criterion['missing']);
        $this->assertSame(['Laravel'], $criterion['matched']);
        $this->assertStringContainsString('Docker', $criterion['explanation']);
        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertContains('Mandatory skills: Docker', $assessment['missing_requirements']);
    }

    public function test_missing_preferred_skills_only_reduce_to_partially_suitable(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => array_merge(
                    $this->requirements(['Laravel']),
                    $this->requirements(['Vue.js', 'Redis'], 'preferred')
                ),
            ]
        );

        $criterion = $this->criterion($assessment, 'preferred_skills');

        $this->assertFalse($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_PARTIAL, $criterion['status']);
        $this->assertSame(['Vue.js', 'Redis'], $criterion['missing']);
        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
    }

    public function test_skills_the_listing_never_asked_for_are_not_gaps(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel', 'Photoshop', 'Rugby'],
                'software' => ['Laravel', 'Photoshop', 'Rugby'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertSame([], $assessment['criteria']['mandatory_skills']['missing']);
        $this->assertNotContains('Photoshop', $assessment['missing_requirements']);
    }

    public function test_skill_aliases_reach_the_same_requirement(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['JS', 'React'],
                'software' => ['JS', 'React'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['JavaScript', 'ReactJS']),
            ]
        );

        $this->assertSame(JobSuitabilityService::CRITERION_MET, $this->criterion($assessment, 'mandatory_skills')['status']);
        $this->assertSame([], $this->criterion($assessment, 'mandatory_skills')['missing']);
    }

    public function test_a_listing_with_no_tagged_skills_claims_nothing_about_skills(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            ['MinQualification' => 'B.C.A', 'Description' => 'Build Laravel APIs.']
        );

        foreach (['mandatory_skills', 'preferred_skills'] as $key) {
            $this->assertSame(
                JobSuitabilityService::CRITERION_NOT_STATED,
                $this->criterion($assessment, $key)['status'],
                $key.' should not be judged when the listing tags nothing.'
            );
        }
    }

    public function test_a_seeker_with_no_saved_skills_is_unknown_not_unsuitable(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
            ]
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_UNKNOWN,
            $this->criterion($assessment, 'mandatory_skills')['status']
        );
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
    }

    /* ---------------------------------------------------------- experience */

    public function test_experience_bands_are_compared_as_ranges(): void
    {
        $cases = [
            'more than the listing wants' => ['3-5_years', '1-2_years', JobSuitabilityService::CRITERION_MET],
            'exactly the listing band' => ['3-5_years', '3-5_years', JobSuitabilityService::CRITERION_MET],
            'well above a junior listing' => ['10_plus_years', '1-2_years', JobSuitabilityService::CRITERION_MET],
            'junior against a senior listing' => ['internship', '10_plus_years', JobSuitabilityService::CRITERION_UNMET],
            'fresher against an experienced listing' => ['fresher', '3-5_years', JobSuitabilityService::CRITERION_UNMET],
        ];

        foreach ($cases as $description => [$seekerLevel, $jobLevel, $expected]) {
            $assessment = $this->assess(
                [
                    'qualifications' => ['B.C.A'],
                    'experience_level' => $seekerLevel,
                ],
                [
                    'MinQualification' => 'B.C.A',
                    'ExperienceLevel' => $jobLevel,
                ]
            );

            $this->assertSame(
                $expected,
                $this->criterion($assessment, 'experience')['status'],
                $description.': a seeker at '.$seekerLevel.' against a listing asking '.$jobLevel.'.'
            );
        }
    }

    public function test_a_missing_experience_level_stays_unknown(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
            ]
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_UNKNOWN,
            $this->criterion($assessment, 'experience')['status']
        );
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
    }

    public function test_an_experience_preference_of_any_is_not_treated_as_a_level(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A'], 'experience_level' => 'any'],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
            ]
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_UNKNOWN,
            $this->criterion($assessment, 'experience')['status']
        );
    }

    public function test_a_listing_that_states_no_experience_level_requires_nothing(): void
    {
        $assessment = $this->assess(['qualifications' => ['B.C.A']]);

        $criterion = $this->criterion($assessment, 'experience');

        $this->assertFalse($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_NOT_STATED, $criterion['status']);
    }

    public function test_a_listing_starting_at_zero_years_is_not_an_eligibility_bar(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A'], 'experience_level' => 'fresher'],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => 'entry_level',
            ]
        );

        $criterion = $this->criterion($assessment, 'experience');

        $this->assertFalse($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_MET, $criterion['status']);
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
    }

    /* ------------------------------------------------------------ software */

    public function test_every_named_mandatory_tool_covered_is_an_essential_requirement_met(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel', 'PhpStorm'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => $this->requirements(['PhpStorm']),
            ]
        );

        $criterion = $this->criterion($assessment, 'software');

        $this->assertTrue($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_MET, $criterion['status']);
        $this->assertSame(['PhpStorm'], $criterion['matched']);
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertSame(100, $assessment['percentage']);
    }

    public function test_a_preferred_tool_never_disqualifies_on_its_own(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => $this->requirements(['Jira'], 'preferred'),
            ]
        );

        $criterion = $this->criterion($assessment, 'software');

        $this->assertSame(JobSuitabilityService::CRITERION_UNMET, $criterion['status']);
        $this->assertFalse($criterion['essential']);
        $this->assertStringContainsString('never added to the skill percentage', $criterion['explanation']);
        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
        $this->assertSame(100, $assessment['percentage']);
    }

    public function test_a_missing_mandatory_tool_disqualifies_and_caps_the_score(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => $this->requirements(['Jira']),
            ]
        );

        $criterion = $this->criterion($assessment, 'software');

        $this->assertTrue($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_UNMET, $criterion['status']);
        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame(67, $assessment['percentage']);
        $this->assertSame(2, $assessment['essential_met']);
        $this->assertSame(3, $assessment['essential_total']);
    }

    /**
     * Entries saved before the type column existed carry no type at all, and the
     * employer form defaults to mandatory, so they count as essential here too.
     */
    public function test_an_untagged_tool_entry_uses_the_application_mandatory_default(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => [['name' => 'Jira', 'proficiency' => null]],
            ]
        );

        $this->assertTrue($this->criterion($assessment, 'software')['essential']);
    }

    public function test_a_listing_naming_no_software_says_so(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A'], 'skills' => ['Laravel'], 'software' => ['Laravel']],
            ['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_NOT_STATED,
            $this->criterion($assessment, 'software')['status']
        );
    }

    /* -------------------------------------------------------- preferences */

    public function test_an_overlapping_salary_is_reported_against_the_saved_expectation(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'preferences' => $this->preferences(['minimum_salary' => '40000', 'maximum_salary' => '60000']),
            ],
            [
                'MinQualification' => 'B.C.A',
                'SalaryFrom' => '45000',
                'SalaryTo' => '70000',
            ]
        );

        $criterion = $this->criterion($assessment, 'salary');

        $this->assertSame(JobSuitabilityService::CRITERION_MET, $criterion['status']);
        $this->assertSame('₹45,000 – ₹70,000', $criterion['expected']);
        $this->assertSame('₹40,000 – ₹60,000', $criterion['candidate']);
    }

    public function test_a_salary_outside_the_expectation_is_a_preference_gap_not_a_rejection(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'preferences' => $this->preferences(['minimum_salary' => '90000']),
            ],
            [
                'MinQualification' => 'B.C.A',
                'SalaryFrom' => '45000',
                'SalaryTo' => '70000',
            ]
        );

        $this->assertSame(JobSuitabilityService::CRITERION_UNMET, $this->criterion($assessment, 'salary')['status']);
        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
    }

    public function test_an_unsaved_salary_expectation_leaves_the_listings_pay_unchecked(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            ['MinQualification' => 'B.C.A', 'SalaryFrom' => '45000', 'SalaryTo' => '70000']
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_UNKNOWN,
            $this->criterion($assessment, 'salary')['status']
        );
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
    }

    public function test_a_foreign_currency_expectation_stays_unknown(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'preferences' => $this->preferences([
                    'minimum_salary' => '5000',
                    'maximum_salary' => '7000',
                    'currency' => 'USD',
                ]),
            ],
            ['MinQualification' => 'B.C.A', 'SalaryFrom' => '45000', 'SalaryTo' => '70000']
        );

        $criterion = $this->criterion($assessment, 'salary');

        $this->assertSame(JobSuitabilityService::CRITERION_UNKNOWN, $criterion['status']);
        $this->assertStringContainsString('cannot be compared', $criterion['explanation']);
        $this->assertSame('USD 5,000 – USD 7,000', $criterion['candidate']);
    }

    public function test_a_matching_employment_type_is_credited(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'preferences' => $this->preferences(['employment_types' => ['Full-time', 'Contract']]),
            ],
            ['MinQualification' => 'B.C.A', 'EmploymentType' => 'Full-time']
        );

        $criterion = $this->criterion($assessment, 'employment_type');

        $this->assertSame(JobSuitabilityService::CRITERION_MET, $criterion['status']);
        $this->assertSame('Full-time', $criterion['expected']);
        $this->assertSame('Full-time, Contract', $criterion['candidate']);
    }

    public function test_a_work_arrangement_the_seeker_ruled_out_is_a_preference_conflict(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'preferences' => $this->preferences(['work_arrangements' => ['Remote']]),
            ],
            ['MinQualification' => 'B.C.A', 'WorkArrangement' => 'On-site']
        );

        $criterion = $this->criterion($assessment, 'work_arrangement');

        $this->assertFalse($criterion['essential']);
        $this->assertSame(JobSuitabilityService::CRITERION_UNMET, $criterion['status']);
        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
    }

    public function test_no_saved_preferences_at_all_leaves_every_preference_unchecked(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            [
                'MinQualification' => 'B.C.A',
                'SalaryFrom' => '45000',
                'SalaryTo' => '70000',
                'EmploymentType' => 'Full-time',
                'WorkArrangement' => 'Hybrid',
            ]
        );

        foreach (['salary', 'employment_type', 'work_arrangement'] as $key) {
            $this->assertSame(
                JobSuitabilityService::CRITERION_UNKNOWN,
                $this->criterion($assessment, $key)['status'],
                $key.' cannot be checked without saved preferences.'
            );
        }

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertContains('Salary', $assessment['unknown_requirements']);
    }

    /* ----------------------------------------------------- overall verdicts */

    public function test_a_listing_whose_essential_requirements_are_all_met_is_suitable(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['MCA'],
                'skills' => ['Laravel', 'Docker', 'Vue.js'],
                'software' => ['Laravel', 'Docker', 'Vue.js', 'PhpStorm'],
                'experience_level' => '3-5_years',
                'preferences' => $this->preferences([
                    'minimum_salary' => '40000',
                    'employment_types' => ['Full-time'],
                    'work_arrangements' => ['Hybrid'],
                ]),
            ],
            [
                'MinQualification' => 'M.C.A',
                'ExperienceLevel' => '1-2_years',
                'EmploymentType' => 'Full-time',
                'WorkArrangement' => 'Hybrid',
                'SalaryFrom' => '45000',
                'SalaryTo' => '60000',
                'RequiredSkills' => array_merge(
                    $this->requirements(['Laravel', 'Docker']),
                    $this->requirements(['Vue.js'], 'preferred')
                ),
                'RequiredSoftware' => $this->requirements(['PhpStorm']),
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
        $this->assertSame('Suitable', $assessment['label']);
        $this->assertSame('success', $assessment['tone']);
        $this->assertSame('Your profile meets every requirement this listing states.', $assessment['explanation']);
        $this->assertSame([], $assessment['missing_requirements']);
        $this->assertSame([], $assessment['suggestions']);
    }

    public function test_several_non_disqualifying_gaps_are_partially_suitable(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'preferences' => $this->preferences(['work_arrangements' => ['Remote']]),
            ],
            [
                'MinQualification' => 'B.C.A',
                'WorkArrangement' => 'On-site',
                'RequiredSkills' => array_merge(
                    $this->requirements(['Laravel']),
                    $this->requirements(['Redis'], 'preferred')
                ),
                'RequiredSoftware' => $this->requirements(['Jira'], 'preferred'),
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
        $this->assertSame('warning', $assessment['tone']);
        $this->assertCount(3, $assessment['missing_requirements']);
        $this->assertNotEmpty($assessment['suggestions']);
    }

    public function test_a_confirmed_shortfall_wins_over_every_other_signal(): void
    {
        $assessment = $this->assess(
            [
                'qualifications' => ['HSC'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel', 'Docker']),
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame('danger', $assessment['tone']);
        $this->assertStringContainsString('HSC', $assessment['explanation']);
        $this->assertStringContainsString('Mandatory skills', $assessment['explanation']);
    }

    public function test_a_listing_that_states_nothing_checkable_is_insufficient_information(): void
    {
        $assessment = $this->assess([
            'qualifications' => ['B.C.A'],
            'skills' => ['Laravel'],
            'software' => ['Laravel'],
        ]);

        $this->assertSame(JobSuitabilityService::STATUS_INSUFFICIENT, $assessment['status']);
        $this->assertSame('Insufficient Information', $assessment['label']);
        $this->assertSame('muted', $assessment['tone']);
        $this->assertNull($assessment['percentage']);
        $this->assertSame(0, $assessment['essential_total']);
        $this->assertStringContainsString('Not enough information', $assessment['explanation']);
    }

    public function test_an_empty_profile_is_insufficient_rather_than_rejected(): void
    {
        $assessment = $this->assess([], [
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '3-5_years',
            'RequiredSkills' => $this->requirements(['Laravel']),
        ]);

        $this->assertSame(JobSuitabilityService::STATUS_INSUFFICIENT, $assessment['status']);
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertNotEmpty($assessment['unknown_requirements']);
        $this->assertNotEmpty($assessment['suggestions']);
    }

    public function test_missing_data_never_produces_a_false_rejection(): void
    {
        // The same seeker, assessed against the same listing with one fact at a
        // time added: no step may turn an unknown into a rejection.
        $job = $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '3-5_years',
            'RequiredSkills' => $this->requirements(['Laravel']),
        ]);

        $stages = [
            $this->context(),
            $this->context(['qualifications' => ['B.C.A']]),
            $this->context(['qualifications' => ['Diploma'], 'skills' => ['Laravel'], 'software' => ['Laravel']]),
            $this->context([
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'experience_level' => '3-5_years',
            ]),
        ];

        foreach ($stages as $index => $context) {
            $assessment = $this->service->assess($context, $job);

            if ($index === 2) {
                // The one stage that states a real shortfall: a diploma against a
                // degree requirement.
                $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);

                continue;
            }

            $this->assertNotSame(
                JobSuitabilityService::STATUS_NOT_SUITABLE,
                $assessment['status'],
                'Stage '.$index.' has no confirmed shortfall and must not read as unsuitable.'
            );
        }

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $this->service->assess($stages[3], $job)['status']);
    }

    /* ------------------------------------------------------ overall score */

    /**
     * The case the percentage exists for: the Smart Job Match maths still says
     * the profile covers every tagged skill, so it stays at 100%, while the
     * overall suitability score drops because the experience bar is short.
     */
    public function test_full_skill_coverage_with_a_failed_experience_bar_scores_below_one_hundred(): void
    {
        $job = $this->job([
            'MinQualification' => 'B.C.A',
            'ExperienceLevel' => '3-5_years',
            'RequiredSkills' => array_merge(
                $this->requirements(['Laravel']),
                $this->requirements(['Redis'], 'preferred')
            ),
        ]);

        $matcher = new \App\Services\JobMatchService();
        $match = $matcher->calculate(['Laravel', 'Redis'], $job, []);

        $assessment = $this->service->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel', 'Redis'],
                'software' => [],
                'experience_level' => 'fresher',
            ],
            $job,
            $match
        );

        $this->assertSame(100, $match['percentage'], 'The skill percentage itself is unchanged.');
        $this->assertSame('Excellent Match', $match['label']);
        $this->assertSame(67, $assessment['percentage']);
        $this->assertLessThan(100, $assessment['percentage']);
        $this->assertSame(2, $assessment['essential_met']);
        $this->assertSame(3, $assessment['essential_total']);
        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
    }

    public function test_a_requirement_that_could_not_be_checked_withholds_full_marks(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A']],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
            ]
        );

        $this->assertSame(
            JobSuitabilityService::CRITERION_UNKNOWN,
            $this->criterion($assessment, 'mandatory_skills')['status']
        );
        $this->assertSame(75, $assessment['percentage']);
        $this->assertSame(1, $assessment['essential_met']);
        $this->assertSame(2, $assessment['essential_total']);
    }

    public function test_only_the_requirements_the_listing_states_are_scored(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['B.C.A'], 'skills' => ['Laravel'], 'software' => ['Laravel']],
            ['MinQualification' => 'B.C.A']
        );

        $this->assertSame(100, $assessment['percentage']);
        $this->assertSame(1, $assessment['essential_total']);
        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);
    }

    public function test_preferences_do_not_enter_the_score(): void
    {
        // Every essential requirement is met and only a saved preference
        // contradicts the listing, so the score stays full while the status
        // still reports the gap.
        $assessment = $this->assess(
            [
                'qualifications' => ['B.C.A'],
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'preferences' => $this->preferences([
                    'minimum_salary' => '90000',
                    'work_arrangements' => ['Remote'],
                ]),
            ],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
                'WorkArrangement' => 'On-site',
                'SalaryFrom' => '45000',
                'SalaryTo' => '70000',
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_PARTIALLY_SUITABLE, $assessment['status']);
        $this->assertSame(100, $assessment['percentage']);
        $this->assertSame(2, $assessment['essential_total']);
    }

    public function test_a_failed_requirement_alongside_unrecorded_ones_stays_well_below_full_marks(): void
    {
        $assessment = $this->assess(
            ['qualifications' => ['HSC'], 'skills' => [], 'software' => []],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
                'RequiredSkills' => $this->requirements(['Laravel']),
            ]
        );

        $this->assertSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $assessment['status']);
        $this->assertSame(33, $assessment['percentage']);
        $this->assertSame(0, $assessment['essential_met']);
        $this->assertSame(3, $assessment['essential_total']);
    }
}
