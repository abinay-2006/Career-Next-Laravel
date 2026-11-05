<?php

namespace Tests\Unit;

use App\Services\CareerPreferenceMatchService;
use PHPUnit\Framework\TestCase;

class CareerPreferenceMatchServiceTest extends TestCase
{
    private CareerPreferenceMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CareerPreferenceMatchService();
    }

    public function test_exact_title_match_scores_one_hundred(): void
    {
        $result = $this->service->scoreAgainstTitles(['Frontend Developer'], 'Frontend Developer');

        $this->assertSame(100, $result['percentage']);
        $this->assertSame(['Frontend Developer'], $result['matched_titles']);
    }

    public function test_preference_contained_in_longer_job_title_scores_one_hundred(): void
    {
        $result = $this->service->scoreAgainstTitles(['Laravel Developer'], 'Senior Laravel Developer (Remote)');

        $this->assertSame(100, $result['percentage']);
    }

    public function test_partial_token_overlap_is_scored_proportionally(): void
    {
        $result = $this->service->scoreAgainstTitles(['Data Analyst'], 'Business Analyst');

        // one of two meaningful tokens overlaps
        $this->assertSame(50, $result['percentage']);
        $this->assertSame(['Data Analyst'], $result['matched_titles']);
    }

    public function test_no_overlap_scores_zero_and_never_counts_as_false_mismatch_elsewhere(): void
    {
        $result = $this->service->scoreAgainstTitles(['Accountant'], 'Civil Engineer');

        $this->assertSame(0, $result['percentage']);
        $this->assertSame([], $result['matched_titles']);
    }

    public function test_best_preference_wins_when_several_are_given(): void
    {
        $result = $this->service->scoreAgainstTitles(
            ['Graphic Designer', 'DevOps Engineer'],
            'Junior DevOps Engineer'
        );

        $this->assertSame(100, $result['percentage']);
        $this->assertSame(['DevOps Engineer'], $result['matched_titles']);
    }

    public function test_stopwords_do_not_inflate_scores(): void
    {
        $result = $this->service->scoreAgainstTitles(['Engineer for the jobs and openings'], 'Sales Executive');

        $this->assertSame(0, $result['percentage']);
    }
}
