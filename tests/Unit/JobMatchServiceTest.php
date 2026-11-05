<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Services\JobMatchService;
use Tests\TestCase;

class JobMatchServiceTest extends TestCase
{
    private JobMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JobMatchService();
    }

    private function job(string $title, ?string $description = null, string $qualification = 'B.E'): Job
    {
        return new Job([
            'JobTitle' => $title,
            'Description' => $description,
            'MinQualification' => $qualification,
        ]);
    }

    public function test_it_matches_skills_written_exactly_in_the_listing(): void
    {
        $match = $this->service->calculate(
            ['Laravel', 'MySQL'],
            $this->job('Laravel Developer', 'Build APIs with Laravel and MySQL.')
        );

        $this->assertSame('scored', $match['status']);
        $this->assertSame(['Laravel', 'MySQL'], $match['matched']);
        $this->assertSame([], $match['missing']);
        $this->assertSame(100, $match['percentage']);
        $this->assertSame('Excellent Match', $match['label']);
    }

    public function test_matching_ignores_letter_case(): void
    {
        $match = $this->service->calculate(
            ['REACT', 'react developer'],
            $this->job('Frontend Engineer', 'Work with React and JavaScript.', 'B.Tech')
        );

        $this->assertSame(['REACT'], $match['matched']);
        $this->assertSame(['react developer'], $match['missing']);
    }

    public function test_duplicate_and_repeated_skills_count_once(): void
    {
        $this->assertSame([], $this->service->extractSkills(null));

        $record = new \App\Models\JobSeekerResume([
            'content' => ['skills' => "React, react ,REACT\nNode.js\nnodejs"],
        ]);

        $this->assertSame(['React', 'Node.js'], $this->service->extractSkills($record));

        $match = $this->service->calculate(['React', 'React', 'React'], $this->job('React Developer', 'React and Redux work.', 'B.E'));
        $this->assertSame(100, $match['percentage']);
        $this->assertSame(1, $match['total_skills']);
    }

    public function test_it_does_not_match_a_skill_inside_a_longer_word(): void
    {
        $javaJob = $this->job('JavaScript Developer', 'Strong JavaScript and React skills required.', 'B.E');

        $java = $this->service->calculate(['Java'], $javaJob);
        $this->assertSame([], $java['matched']);
        $this->assertSame(['Java'], $java['missing']);
        $this->assertSame(0, $java['percentage']);

        $javascript = $this->service->calculate(['JavaScript'], $this->job('Backend Developer', 'Java and Spring experience.', 'B.E'));
        $this->assertSame([], $javascript['matched']);

        $cpp = $this->service->calculate(['C++'], $this->job('Systems Developer', 'C programming and Linux.', 'B.E'));
        $this->assertSame([], $cpp['matched']);
    }

    public function test_it_reports_candidate_skills_that_the_listing_omits(): void
    {
        $match = $this->service->calculate(
            ['Laravel', 'Docker', 'Kubernetes'],
            $this->job('Laravel Developer', 'Design APIs with Laravel and MySQL.', 'BCA')
        );

        $this->assertSame(['Laravel'], $match['matched']);
        $this->assertSame(['Docker', 'Kubernetes'], $match['missing']);
        $this->assertSame(33, $match['percentage']);
        $this->assertSame('Low Match', $match['label']);
    }

    public function test_a_resume_without_skills_asks_for_resume_builder(): void
    {
        $match = $this->service->calculate([], $this->job('Any Role', 'Anything.', 'B.E'));

        $this->assertSame('needs_resume', $match['status']);
        $this->assertNull($match['percentage']);
        $this->assertSame(JobMatchService::NO_RESUME_MESSAGE, $match['explanation']);
    }

    public function test_a_listing_without_requirements_is_not_scored(): void
    {
        $match = $this->service->calculate(['Laravel'], $this->job('Developer', null, ''));

        $this->assertSame('insufficient_job', $match['status']);
        $this->assertNull($match['percentage']);
        $this->assertStringContainsString('cannot be calculated', $match['explanation']);
    }

    public function test_skill_variations_written_differently_still_match(): void
    {
        $match = $this->service->calculate(
            ['Node.js', 'C#', 'Machine Learning'],
            $this->job('Backend Engineer', 'NodeJS, CSharp and ML experience expected.', 'B.Tech')
        );

        $this->assertSame(['Node.js', 'C#', 'Machine Learning'], $match['matched']);
        $this->assertSame(100, $match['percentage']);
    }

    public function test_match_labels_follow_the_published_bands(): void
    {
        $this->assertSame('Excellent Match', $this->service->label(80));
        $this->assertSame('Good Match', $this->service->label(79));
        $this->assertSame('Good Match', $this->service->label(60));
        $this->assertSame('Moderate Match', $this->service->label(59));
        $this->assertSame('Moderate Match', $this->service->label(40));
        $this->assertSame('Low Match', $this->service->label(39));
    }

    public function test_comma_and_newline_separated_skills_are_split(): void
    {
        $record = new \App\Models\JobSeekerResume([
            'content' => ['skills' => "PHP, MySQL\nFigma,\n\n  AWS  "],
        ]);

        $this->assertSame(['PHP', 'MySQL', 'Figma', 'AWS'], $this->service->extractSkills($record));
    }

    public function test_structured_mandatory_skills_outrank_preferred_ones(): void
    {
        $match = $this->service->calculate(
            ['Laravel', 'Docker'],
            $this->jobWithRequirements('Backend Developer', [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'advanced'],
                ['name' => 'MySQL', 'type' => 'mandatory', 'proficiency' => null],
                ['name' => 'Docker', 'type' => 'preferred', 'proficiency' => null],
            ])
        );

        $this->assertSame('scored', $match['status']);
        $this->assertTrue($match['structured']);
        // (2 + 1) of (2 + 2 + 1) weighted coverage.
        $this->assertSame(60, $match['percentage']);
        $this->assertSame(['Laravel'], $match['matched_mandatory']);
        $this->assertSame(['MySQL'], $match['missing_mandatory']);
        $this->assertSame(['Docker'], $match['matched_preferred']);
        $this->assertSame([], $match['missing_preferred']);
        $this->assertSame(['Laravel', 'Docker'], $match['matched']);
        $this->assertSame(['MySQL'], $match['missing']);
        $this->assertStringContainsString('weighted skill requirements', $match['explanation']);
    }

    public function test_software_requirements_are_reported_but_never_lift_the_score(): void
    {
        $match = $this->service->calculate(
            ['Figma'],
            $this->jobWithRequirements('Designer', [
                ['name' => 'Illustrator', 'type' => 'mandatory', 'proficiency' => null],
            ], [
                ['name' => 'Figma', 'type' => 'mandatory', 'proficiency' => null],
            ])
        );

        $this->assertSame(0, $match['percentage']);
        $this->assertSame(['Figma'], $match['software']['matched']);
        $this->assertSame([], $match['software']['missing']);
        $this->assertSame(1, $match['software']['total']);
        $this->assertStringContainsString('Tools: 1 named, 1 in your profile', $match['explanation']);
    }

    public function test_missing_software_is_listed_separately_from_missing_skills(): void
    {
        $match = $this->service->calculate(
            ['Laravel'],
            $this->jobWithRequirements('Backend Developer', [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
            ], [
                ['name' => 'Docker', 'type' => 'preferred', 'proficiency' => null],
                ['name' => 'Postman', 'type' => 'mandatory', 'proficiency' => null],
            ])
        );

        $this->assertSame(100, $match['percentage']);
        $this->assertSame([], $match['missing']);
        $this->assertSame(['Docker', 'Postman'], $match['software']['missing']);
        $this->assertStringNotContainsString('Docker', implode(', ', $match['missing']));
    }

    public function test_stated_proficiency_levels_never_change_the_score(): void
    {
        $withLevels = $this->service->calculate(
            ['Laravel'],
            $this->jobWithRequirements('Backend Developer', [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => 'expert'],
                ['name' => 'MySQL', 'type' => 'preferred', 'proficiency' => 'basic'],
            ])
        );

        $withoutLevels = $this->service->calculate(
            ['Laravel'],
            $this->jobWithRequirements('Backend Developer', [
                ['name' => 'Laravel', 'type' => 'mandatory', 'proficiency' => null],
                ['name' => 'MySQL', 'type' => 'preferred', 'proficiency' => null],
            ])
        );

        $this->assertSame($withoutLevels['percentage'], $withLevels['percentage']);
        $this->assertStringNotContainsString('expert', $withLevels['explanation']);
    }

    public function test_a_requirement_phrase_matches_a_longer_resume_skill(): void
    {
        $match = $this->service->calculate(
            ['React JS'],
            $this->jobWithRequirements('Frontend Developer', [
                ['name' => 'React', 'type' => 'mandatory', 'proficiency' => null],
            ])
        );

        $this->assertSame(['React'], $match['matched']);
        $this->assertSame(['Requirements'], $match['matched_in']['React']);
    }

    public function test_legacy_listings_keep_the_original_candidate_skill_formula(): void
    {
        $match = $this->service->calculate(
            ['Laravel', 'Docker'],
            $this->job('Laravel Developer', 'Design APIs with Laravel and MySQL.', 'BCA')
        );

        $this->assertFalse($match['structured']);
        $this->assertNull($match['software']);
        $this->assertSame(50, $match['percentage']);
        $this->assertSame(['Docker'], $match['missing']);
    }

    public function test_malformed_requirement_data_falls_back_to_legacy_scoring(): void
    {
        $job = $this->job('Laravel Developer', 'Design APIs with Laravel and MySQL.', 'BCA');
        $job->RequiredSkills = 'this is not json';

        $match = $this->service->calculate(['Laravel'], $job);

        $this->assertFalse($match['structured']);
        $this->assertSame(['Laravel'], $match['matched']);
    }

    public function test_a_json_string_column_is_read_as_structured_requirements(): void
    {
        $job = $this->job('Laravel Developer', 'Design APIs with Laravel and MySQL.', 'BCA');
        $job->setAttribute('RequiredSkills', json_encode([
            ['name' => 'Laravel', 'type' => 'mandatory'],
            ['name' => 'Kubernetes', 'type' => 'preferred'],
        ]));

        $match = $this->service->calculate(['Laravel'], $job);

        $this->assertTrue($match['structured']);
        $this->assertSame(67, $match['percentage']);
    }

    public function test_a_listing_with_only_software_entries_keeps_legacy_skill_scoring(): void
    {
        $match = $this->service->calculate(
            ['Photoshop'],
            $this->jobWithRequirements('Graphic Designer', [], [
                ['name' => 'Photoshop', 'type' => 'mandatory', 'proficiency' => null],
            ])
        );

        // Software alone cannot produce a coverage percentage, so the listing
        // stays on the original description-overlap formula.
        $this->assertFalse($match['structured']);
        $this->assertNull($match['software']);
        $this->assertSame(0, $match['percentage']);
        $this->assertSame(['Photoshop'], $match['missing']);
    }

    private function jobWithRequirements(string $title, array $skills, array $software = []): Job
    {
        return new Job([
            'JobTitle' => $title,
            'Description' => 'Role description that mentions nothing about skills.',
            'MinQualification' => 'B.E',
            'RequiredSkills' => $skills,
            'RequiredSoftware' => $software,
        ]);
    }
}
