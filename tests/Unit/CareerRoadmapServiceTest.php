<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use App\Services\CareerRoadmapService;
use App\Services\CareerPreferenceMatchService;
use App\Services\JobMatchService;
use App\Services\JobSuitabilityService;
use Tests\TestCase;

/**
 * The gap-to-job roadmap on its own: which steps a listing produces from an
 * assessment, how each is classified, and what the progress figure is allowed to
 * say. The assessment itself is covered by JobSuitabilityServiceTest and the
 * scoring by JobMatchServiceTest; this suite only checks that the roadmap reads
 * those results and never invents work, counts a gap twice, or promises a hire.
 */
class CareerRoadmapServiceTest extends TestCase
{
    private CareerRoadmapService $service;

    private JobSuitabilityService $suitability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suitability = new JobSuitabilityService(
            new JobMatchService(),
            new CareerPreferenceMatchService()
        );

        $this->service = new CareerRoadmapService($this->suitability);
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
        // forceFill, not mass assignment: the requirement columns are outside
        // Job::$fillable but are cast, exactly as a hydrated DB row delivers them.
        return (new Job())->forceFill(array_merge([
            'JobId' => 7,
            'JobTitle' => 'Laravel Developer',
            'MinQualification' => 'Any',
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
     * @param  array<string, mixed>  $contextOverrides
     * @param  array<string, mixed>  $jobAttributes
     * @return array<string, mixed>
     */
    private function roadmap(array $contextOverrides = [], array $jobAttributes = []): array
    {
        $context = $this->context($contextOverrides);
        $job = $this->job($jobAttributes);

        return $this->service->build($context, $job, $this->suitability->assess($context, $job));
    }

    /**
     * @param  array<string, mixed>  $roadmap
     * @return list<array<string, mixed>>
     */
    private function groupSteps(array $roadmap, string $group): array
    {
        return $roadmap['groups'][$group]['steps'] ?? [];
    }

    /* ---------------------------------------------------------------- result */

    public function test_the_result_carries_every_section_both_views_render(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel'], 'qualifications' => ['B.C.A']],
            ['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        foreach ([
            'job_id', 'status', 'tone', 'percentage', 'steps_total', 'steps_completed',
            'steps_outstanding', 'groups', 'steps', 'next_steps', 'actions', 'note',
        ] as $key) {
            $this->assertArrayHasKey($key, $roadmap);
        }

        $this->assertSame(7, $roadmap['job_id']);

        foreach ($roadmap['steps'] as $step) {
            foreach (['group', 'group_label', 'kind', 'action', 'state', 'complete', 'label', 'detail', 'title', 'hint', 'category'] as $key) {
                $this->assertArrayHasKey($key, $step);
            }
        }
    }

    /* ------------------------------------------------------- compact wording */

    public function test_an_outstanding_step_is_worded_as_a_short_action_with_a_category(): void
    {
        $roadmap = $this->roadmap(
            [
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'qualifications' => ['Diploma'],
                'experience_level' => '1-2_years',
            ],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
                'RequiredSkills' => $this->requirements(['Laravel', 'JavaScript']),
                'RequiredSoftware' => $this->requirements(['Adobe Photoshop']),
            ]
        );

        $wording = [];

        foreach ($roadmap['steps'] as $step) {
            if ($step['state'] === CareerRoadmapService::STATE_MATCHED) {
                continue;
            }

            $wording[$step['kind']] = [$step['title'], $step['category']];
        }

        $this->assertSame(
            [
                'skill' => ['Learn JavaScript', 'Skill Gap'],
                'software' => ['Add Adobe Photoshop', 'Software'],
                'qualification' => ['Improve your qualification', 'Education'],
                'experience' => ['Gain relevant experience', 'Experience'],
            ],
            $wording
        );
    }

    public function test_a_compact_step_explains_itself_in_one_line(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'qualifications' => ['Diploma']],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Docker']),
            ]
        );

        foreach ($roadmap['steps'] as $step) {
            $this->assertLessThanOrEqual(
                110,
                strlen($step['hint']),
                'A default-view line stays readable at a glance: '.$step['hint']
            );
        }

        $hints = array_column(
            array_filter($roadmap['steps'], static fn (array $s): bool => $s['kind'] !== 'qualification'),
            'hint'
        );

        $this->assertSame(
            ['The listing asks for Docker; your profile does not mention it.'],
            array_values($hints)
        );
    }

    public function test_a_matched_step_reads_as_covered_and_is_not_an_action(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel']],
            ['RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $step = $roadmap['steps'][0];

        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $step['state']);
        $this->assertSame('Laravel', $step['title'], 'A covered step keeps its own name.');
        $this->assertSame('Covered by your profile.', $step['hint']);
        $this->assertSame('Skill', $step['category'], 'Nothing to close, so it is not a gap.');
    }

    public function test_an_unrecorded_step_asks_for_the_value_without_calling_it_a_gap(): void
    {
        $roadmap = $this->roadmap(
            [],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => $this->requirements(['Laravel']),
            ]
        );

        $wording = [];

        foreach ($roadmap['steps'] as $step) {
            $wording[$step['kind']] = [$step['title'], $step['hint'], $step['category']];
        }

        $this->assertSame(
            [
                'profile' => ['Record your skills', 'Not recorded yet, so it could not be checked.', 'Profile'],
                'qualification' => ['Record your qualification', 'Not recorded yet, so it could not be checked.', 'Education'],
            ],
            $wording
        );

        $this->assertSame(0, $roadmap['steps_total'], 'An unrecorded value is not counted as progress or as a shortfall.');
    }

    public function test_groups_render_learn_then_improve_then_experience_then_matched(): void
    {
        $roadmap = $this->roadmap(
            [
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'qualifications' => ['Diploma'],
                'experience_level' => '1-2_years',
            ],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
                'RequiredSkills' => $this->requirements(['Laravel', 'Docker']),
            ]
        );

        $this->assertSame(
            [
                CareerRoadmapService::GROUP_LEARN,
                CareerRoadmapService::GROUP_IMPROVE,
                CareerRoadmapService::GROUP_EXPERIENCE,
            ],
            array_keys($roadmap['groups'])
        );

        $learn = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);
        $this->assertSame('Docker', $learn[0]['label'], 'Outstanding steps lead the group.');
        $this->assertSame('Laravel', $learn[1]['label']);
    }

    /* --------------------------------------------------------- missing skills */

    public function test_a_missing_mandatory_skill_becomes_a_learn_step(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['MySQL'], 'software' => ['MySQL']],
            ['RequiredSkills' => $this->requirements(['Laravel', 'MySQL'])]
        );

        $learn = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);

        $this->assertCount(2, $learn);
        $this->assertSame('Laravel', $learn[0]['label']);
        $this->assertSame(CareerRoadmapService::GROUP_LEARN, $learn[0]['group']);
        $this->assertSame(CareerRoadmapService::STATE_GAP, $learn[0]['state']);
        $this->assertFalse($learn[0]['complete']);
        $this->assertSame('skills', $learn[0]['action']);
        $this->assertStringContainsString('essential skill', $learn[0]['detail']);

        // The covered half of the same requirement set is not hidden either.
        $this->assertSame('MySQL', $learn[1]['label']);
        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $learn[1]['state']);
    }

    public function test_a_missing_preferred_skill_is_a_learn_step_ranked_below_the_mandatory_one(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Git'], 'software' => ['Git']],
            [
                'RequiredSkills' => array_merge(
                    $this->requirements(['Docker']),
                    $this->requirements(['MySQL'], 'preferred')
                ),
            ]
        );

        $learn = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);

        $this->assertCount(2, $learn);
        $this->assertSame('Docker', $learn[0]['label']);
        $this->assertStringContainsString('essential skill', $learn[0]['detail']);
        $this->assertSame('MySQL', $learn[1]['label']);
        $this->assertStringContainsString('as a preferred skill', $learn[1]['detail']);
        $this->assertStringContainsString('preferred skill', $learn[1]['detail']);
    }

    public function test_a_preferred_gap_alone_never_reads_as_a_rejection(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Docker'], 'software' => ['Docker']],
            ['RequiredSkills' => $this->requirements(['MySQL'], 'preferred')]
        );

        // A preferred shortfall alone is not evidence of a failed essential
        // requirement, so the assessment behind these steps never rejects.
        $this->assertNotSame(JobSuitabilityService::STATUS_NOT_SUITABLE, $roadmap['status']);

        $learn = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);

        $this->assertCount(1, $learn);
        $this->assertSame('MySQL', $learn[0]['label']);
        $this->assertSame(CareerRoadmapService::STATE_GAP, $learn[0]['state']);
    }

    /* ------------------------------------------------------------ software */

    public function test_a_missing_tool_becomes_a_learn_step_and_keeps_the_employer_tag(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel']],
            [
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => array_merge(
                    $this->requirements(['Adobe Photoshop']),
                    $this->requirements(['Figma'], 'preferred')
                ),
            ]
        );

        $labels = array_map(
            static fn (array $step): string => $step['label'],
            $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN)
        );

        $this->assertSame(['Adobe Photoshop', 'Figma', 'Laravel'], $labels);

        $learn = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);
        $this->assertSame('software', $learn[0]['kind']);
        $this->assertStringContainsString('essential tool', $learn[0]['detail']);
        $this->assertStringContainsString('preferred tool', $learn[1]['detail']);
        $this->assertSame('skills', $learn[0]['action']);
    }

    public function test_learn_steps_follow_the_tiers_the_listing_writes(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Git'], 'software' => ['Git']],
            [
                'RequiredSkills' => array_merge(
                    $this->requirements(['Laravel']),
                    $this->requirements(['MySQL'], 'preferred')
                ),
                'RequiredSoftware' => array_merge(
                    $this->requirements(['Adobe Photoshop']),
                    $this->requirements(['Figma'], 'preferred')
                ),
            ]
        );

        $this->assertSame(
            ['Laravel', 'Adobe Photoshop', 'MySQL', 'Figma'],
            array_map(
                static fn (array $step): string => $step['label'],
                $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN)
            ),
            'Essential skills, then essential tools, then the preferred pair in the same order.'
        );
    }

    public function test_a_recorded_tool_is_reported_as_already_matched(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel', 'Adobe Photoshop']],
            [
                'RequiredSkills' => $this->requirements(['Laravel']),
                'RequiredSoftware' => $this->requirements(['Adobe Photoshop']),
            ]
        );

        $steps = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_LEARN);

        $this->assertCount(2, $steps);
        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $steps[0]['state']);
        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $steps[1]['state']);
        $this->assertSame(100, $roadmap['percentage']);
        $this->assertSame(0, $roadmap['steps_outstanding']);
        $this->assertSame([], $roadmap['actions'], 'A fully covered roadmap has no outstanding action.');
    }

    /* -------------------------------------------------------- qualification */

    public function test_a_qualification_below_the_minimum_becomes_an_improve_step(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel'], 'qualifications' => ['Diploma']],
            ['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $improve = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_IMPROVE);

        $this->assertCount(1, $improve);
        $this->assertSame('Qualification', $improve[0]['label']);
        $this->assertSame(CareerRoadmapService::STATE_GAP, $improve[0]['state']);
        $this->assertSame('education', $improve[0]['action']);
        $this->assertSame('B.C.A', $improve[0]['expected']);
        $this->assertSame('Diploma', $improve[0]['current']);

        $this->assertStringContainsString('above the Diploma', $improve[0]['detail']);
        // An unreachable bar is stated, never dressed up as a course to take.
        $this->assertStringNotContainsString('guarantee', mb_strtolower($improve[0]['detail']));
    }

    public function test_a_qualification_that_clears_the_minimum_is_already_matched(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel'], 'qualifications' => ['MCA']],
            ['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $improve = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_IMPROVE);

        $this->assertCount(1, $improve);
        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $improve[0]['state']);
        $this->assertSame('education', $improve[0]['action']);
        $this->assertSame(100, $roadmap['percentage']);
    }

    /* --------------------------------------------------------- experience */

    public function test_an_experience_shortfall_becomes_the_only_gain_experience_step(): void
    {
        $preferences = new JobSeekerCareerPreference(['JobSeekId' => 1, 'experience_level' => '1-2_years']);

        $roadmap = $this->roadmap(
            [
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'experience_level' => '1-2_years',
                'preferences' => $preferences,
            ],
            ['ExperienceLevel' => '3-5_years', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $experience = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_EXPERIENCE);

        $this->assertCount(1, $experience);
        $this->assertSame('Experience', $experience[0]['label']);
        $this->assertSame(CareerRoadmapService::STATE_GAP, $experience[0]['state']);
        $this->assertSame('experience', $experience[0]['action']);
        $this->assertStringContainsString('3–5 years', $experience[0]['expected']);
        $this->assertStringContainsString('Gaining that experience', $experience[0]['detail']);

        $this->assertSame('Gain Experience', $roadmap['groups'][CareerRoadmapService::GROUP_EXPERIENCE]['label']);
    }

    public function test_experience_that_reaches_the_band_is_already_matched(): void
    {
        $roadmap = $this->roadmap(
            [
                'skills' => ['Laravel'],
                'software' => ['Laravel'],
                'experience_level' => '6-10_years',
                'preferences' => new JobSeekerCareerPreference(['JobSeekId' => 1, 'experience_level' => '6-10_years']),
            ],
            ['ExperienceLevel' => '3-5_years', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $experience = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_EXPERIENCE);

        $this->assertCount(1, $experience);
        $this->assertSame(CareerRoadmapService::STATE_MATCHED, $experience[0]['state']);
        $this->assertSame(100, $roadmap['percentage']);
    }

    /* -------------------------------------------------- unknown information */

    public function test_an_unrecorded_profile_lists_record_steps_and_no_gaps(): void
    {
        $roadmap = $this->roadmap([], [
            'MinQualification' => 'B.C.A',
            'RequiredSkills' => $this->requirements(['Laravel']),
            'RequiredSoftware' => $this->requirements(['VS Code']),
        ]);

        foreach ($roadmap['steps'] as $step) {
            $this->assertNotSame(CareerRoadmapService::STATE_GAP, $step['state']);
        }

        // Nothing has been confirmed either way, so the figure stays unclaimed.
        $this->assertSame(0, $roadmap['steps_total']);
        $this->assertNull($roadmap['percentage']);
        $this->assertStringContainsString('no progress to measure', (string) $roadmap['note']);

        $byKind = [];

        foreach ($roadmap['steps'] as $step) {
            $byKind[$step['kind']] = $step;
        }

        $this->assertSame(CareerRoadmapService::STATE_UNRECORDED, $byKind['profile']['state']);
        $this->assertSame('skills', $byKind['profile']['action'], 'Skills belong on the Skills & Software screen.');
        $this->assertSame('education', $byKind['qualification']['action']);

        foreach ($roadmap['steps'] as $step) {
            $this->assertStringContainsString('could not', $step['detail']);
            $this->assertStringNotContainsString('not qualified', mb_strtolower($step['detail']));
        }
    }

    public function test_an_unrecorded_experience_level_is_a_record_step_not_a_gap(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel']],
            ['ExperienceLevel' => '3-5_years', 'RequiredSkills' => $this->requirements(['Laravel'])]
        );

        $improve = $this->groupSteps($roadmap, CareerRoadmapService::GROUP_IMPROVE);

        $this->assertCount(1, $improve);
        $this->assertSame(CareerRoadmapService::STATE_UNRECORDED, $improve[0]['state']);
        $this->assertSame('experience', $improve[0]['action']);
        $this->assertSame('Your experience level', $improve[0]['label']);
        $this->assertSame(100, $roadmap['percentage']);
    }

    public function test_a_listing_that_states_no_comparable_requirement_produces_no_roadmap(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel']],
            ['Description' => 'Build things with Laravel and talk to people.']
        );

        $this->assertSame(0, $roadmap['steps_total']);
        $this->assertSame([], $roadmap['groups']);
        $this->assertNull($roadmap['percentage']);
        $this->assertSame('muted', $roadmap['tone']);
        $this->assertStringContainsString('nothing to work towards', (string) $roadmap['note']);

        // Prose alone is never turned into requirements the seeker is asked to chase.
        $this->assertStringNotContainsString('MySQL', json_encode($roadmap['steps']));
    }

    /* ------------------------------------------------------------ progress */

    public function test_progress_counts_the_steps_the_profile_covers_now(): void
    {
        $job = ['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel', 'Docker'])];

        $before = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel'], 'qualifications' => ['B.C.A']],
            $job
        );
        $this->assertSame(67, $before['percentage']);
        $this->assertSame(3, $before['steps_total']);
        $this->assertSame(2, $before['steps_completed']);
        $this->assertSame(1, $before['steps_outstanding']);

        // The same listing, read after the seeker added the missing skill.
        $after = $this->roadmap(
            ['skills' => ['Laravel', 'Docker'], 'software' => ['Laravel', 'Docker'], 'qualifications' => ['B.C.A']],
            $job
        );
        $this->assertSame(100, $after['percentage']);
        $this->assertSame(0, $after['steps_outstanding']);
        $this->assertSame('success', $after['tone']);
        $this->assertSame([], $after['actions']);
        $this->assertSame([], $after['next_steps']);
    }

    public function test_the_same_profile_always_produces_the_same_roadmap(): void
    {
        $context = $this->context([
            'skills' => ['Laravel'],
            'software' => ['Laravel'],
            'qualifications' => ['Diploma'],
        ]);
        $job = $this->job(['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel', 'MySQL'])]);
        $assessment = $this->suitability->assess($context, $job);

        $this->assertSame(
            $this->service->build($context, $job, $assessment),
            $this->service->build($context, $job, $assessment)
        );
    }

    public function test_build_assesses_the_listing_itself_when_no_assessment_is_handed_over(): void
    {
        $context = $this->context(['skills' => ['Laravel'], 'software' => ['Laravel']]);
        $job = $this->job(['RequiredSkills' => $this->requirements(['Laravel'])]);

        $this->assertSame(
            $this->service->build($context, $job, $this->suitability->assess($context, $job)),
            $this->service->build($context, $job)
        );
    }

    public function test_next_steps_lead_with_the_essential_gaps(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Git'], 'software' => ['Git']],
            [
                'MinQualification' => 'B.C.A',
                'RequiredSkills' => array_merge(
                    $this->requirements(['Laravel']),
                    $this->requirements(['MySQL'], 'preferred')
                ),
            ]
        );

        $this->assertSame('Laravel', $roadmap['next_steps'][0]['label']);
        $this->assertSame('MySQL', $roadmap['next_steps'][1]['label']);
        $this->assertSame('Your qualification', $roadmap['next_steps'][2]['label']);
        $this->assertSame(['skills', 'education'], $roadmap['actions']);
    }

    /* -------------------------------------------------------------- promises */

    public function test_no_step_or_note_promises_a_selection(): void
    {
        $roadmap = $this->roadmap(
            ['skills' => ['Laravel'], 'software' => ['Laravel'], 'qualifications' => ['Diploma']],
            [
                'MinQualification' => 'B.C.A',
                'ExperienceLevel' => '3-5_years',
                'RequiredSkills' => $this->requirements(['Laravel', 'Docker']),
                'RequiredSoftware' => $this->requirements(['VS Code']),
            ]
        );

        $text = $roadmap['note'];

        foreach ($roadmap['steps'] as $step) {
            $text .= ' '.$step['label'].' '.$step['detail'];
        }

        foreach (['groups'] as $key) {
            foreach ($roadmap[$key] as $group) {
                $text .= ' '.$group['note'];
            }
        }

        $this->assertStringNotContainsString('guarantee', mb_strtolower($text));
        $this->assertStringNotContainsString('will be hired', mb_strtolower($text));
        $this->assertStringNotContainsString('ensure selection', mb_strtolower($text));
    }

    public function test_a_suitable_listing_still_reads_as_suitable_in_the_roadmap(): void
    {
        $context = $this->context([
            'skills' => ['Laravel'],
            'software' => ['Laravel'],
            'qualifications' => ['B.C.A'],
        ]);
        $job = $this->job(['MinQualification' => 'B.C.A', 'RequiredSkills' => $this->requirements(['Laravel'])]);
        $assessment = $this->suitability->assess($context, $job);

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $assessment['status']);

        $roadmap = $this->service->build($context, $job, $assessment);

        $this->assertSame(JobSuitabilityService::STATUS_SUITABLE, $roadmap['status']);
        $this->assertSame(100, $roadmap['percentage']);
    }
}
