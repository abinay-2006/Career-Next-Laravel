<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Services\JobMatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobMatchScoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->string('CompanyName');
            $table->string('JobTitle');
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->default('Any');
            $table->text('Description')->nullable();
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
    }

    private function seeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
        ]);

        return JobSeeker::query()->findOrFail($id);
    }

    private function job(string $title, string $qualification, ?string $description, string $status = 'approved'): int
    {
        return DB::table('job_master')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => $title,
            'Vacancy' => 1,
            'MinQualification' => $qualification,
            'Description' => $description,
            'Status' => $status,
        ]);
    }

    private function resume(int $jobSeekerId, string $skills, string $updatedAt): void
    {
        DB::table('jobseeker_resumes')->insert([
            'JobSeekId' => $jobSeekerId,
            'title' => 'Saved Resume',
            'template' => 'classic',
            'content' => json_encode(['skills' => $skills]),
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    /**
     * Two approved listings: one fully covered by the resume, one not at all.
     *
     * @return array{support: int, laravel: int}
     */
    private function seedListings(): array
    {
        return [
            'laravel' => $this->job('Laravel Developer', 'BCA', 'Build APIs with Laravel and MySQL for our team.'),
            'support' => $this->job('Support Executive', 'HSC', 'Answer inbound calls and update customer records.'),
        ];
    }

    public function test_job_card_shows_score_label_and_matched_skills_from_the_latest_resume(): void
    {
        $seeker = $this->seeker('Skillful Seeker');
        $this->resume($seeker->JobSeekId, 'PHP, Laravel', '2026-01-01 10:00:00');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-02-01 10:00:00');
        $ids = $this->seedListings();

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Excellent Match')
            ->assertSee('Matching: Laravel, MySQL');

        // The score comes from the newest resume only, so PHP is no longer listed.
        $this->assertStringNotContainsString('Not in this job: PHP', $page->getContent());
        $this->assertStringContainsString('ID: '.$ids['laravel'], $page->getContent());
    }

    public function test_match_is_skipped_until_the_seeker_has_resume_skills(): void
    {
        $seeker = $this->seeker('Fresh Seeker');
        $this->seedListings();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee(JobMatchService::NO_RESUME_MESSAGE)
            ->assertDontSee('Excellent Match');
    }

    public function test_match_filter_keeps_only_jobs_at_or_above_the_selected_percentage(): void
    {
        $seeker = $this->seeker('Filtered Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');
        $ids = $this->seedListings();

        $filtered = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['cmbMatch' => 80]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$ids['laravel'], $filtered);
        $this->assertStringNotContainsString('ID: '.$ids['support'], $filtered);

        $unfiltered = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$ids['support'], $unfiltered);
    }

    public function test_the_match_filter_combines_with_the_existing_qualification_filter(): void
    {
        $seeker = $this->seeker('Combined Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');

        $support = $this->job('Support Executive', 'HSC', 'Answer inbound calls and update customer records.');
        $laravel = $this->job('Laravel Developer', 'BCA', 'Build APIs with Laravel and MySQL for our team.');
        $specialist = $this->job('Laravel Specialist', 'HSC', 'Own Laravel packages and MySQL schemas end to end.');

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['cmbQual' => 'HSC', 'cmbMatch' => 80]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ID: '.$specialist, $page);
        $this->assertStringNotContainsString('ID: '.$laravel, $page);
        $this->assertStringNotContainsString('ID: '.$support, $page);
    }

    public function test_best_match_sorting_comes_first_and_latest_order_stays_the_default(): void
    {
        $seeker = $this->seeker('Sorted Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');
        $ids = $this->seedListings();

        $default = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->getContent();

        // The weaker listing is the newer one, so the default order keeps it first.
        $this->assertLessThan(
            strpos($default, 'ID: '.$ids['laravel']),
            strpos($default, 'ID: '.$ids['support']),
            'Newest listings must stay first without the sort control.'
        );

        $bestMatch = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs', ['sort' => 'best_match']))
            ->getContent();

        $this->assertLessThan(
            strpos($bestMatch, 'ID: '.$ids['support']),
            strpos($bestMatch, 'ID: '.$ids['laravel'])
        );
    }

    public function test_an_unapproved_listing_is_never_scored_or_displayed(): void
    {
        $seeker = $this->seeker('Private Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');
        $this->seedListings();
        $hidden = $this->job('Hidden Backend Role', 'BCA', 'Laravel and MySQL only.', 'pending');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertDontSee('Hidden Backend Role');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $hidden))
            ->assertNotFound();
    }

    public function test_the_latest_resume_is_read_once_for_the_whole_result_set(): void
    {
        $seeker = $this->seeker('Efficient Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');
        $this->seedListings();
        $this->job('Junior QA Analyst', 'BCA', 'Write test cases and review Laravel bug reports.');
        $this->job('Data Officer', 'MCA', 'Maintain MySQL reports and internal dashboards.');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk();

        $resumeQueries = array_filter(
            DB::getQueryLog(),
            // The stored table name also appears in the schema probe that keeps
            // a page rendering when the table is missing, so only real reads count.
            fn (array $query): bool => str_contains($query['query'], 'select * from "jobseeker_resumes"')
        );

        $this->assertCount(1, $resumeQueries);
    }

    public function test_another_seekers_resume_skills_are_never_revealed(): void
    {
        $owner = $this->seeker('Zephyr Owner');
        $this->resume($owner->JobSeekId, 'Zephyr, Laravel', '2026-03-01 10:00:00');

        $other = $this->seeker('Zephyr Visitor');
        $this->resume($other->JobSeekId, 'Laravel, MySQL', '2026-03-02 10:00:00');

        $this->job('Laravel Developer', 'BCA', 'Build APIs with Laravel and MySQL for our team.');

        $this->actingAs($owner, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertSee('Zephyr');

        $this->actingAs($other, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk()
            ->assertDontSee('Zephyr');

        $this->actingAs($other, 'jobseeker')
            ->get(route('jobseeker.jobs'))
            ->assertOk();
    }

    public function test_job_details_page_shows_the_full_match_breakdown(): void
    {
        $seeker = $this->seeker('Detail Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL, Docker', '2026-03-01 10:00:00');
        $jobId = $this->job('Laravel Developer', 'BCA', 'Build APIs with Laravel and MySQL for our team.');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('Smart Job Match')
            ->assertSee('67%')
            ->assertSee('Good Match')
            ->assertSee('Matching skills')
            ->assertSee('Skills not found in this job')
            ->assertSee('Docker')
            ->assertSee('Apply Now');
    }

    public function test_job_details_explains_when_the_listing_is_too_thin_to_compare(): void
    {
        $seeker = $this->seeker('Sparse Seeker');
        $this->resume($seeker->JobSeekId, 'Laravel, MySQL', '2026-03-01 10:00:00');
        $jobId = $this->job('Executive', 'Any', null);

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.jobs.show', $jobId))
            ->assertOk()
            ->assertSee('A match cannot be calculated');
    }

    public function test_job_match_requires_an_authenticated_job_seeker(): void
    {
        $this->get(route('jobseeker.jobs'))->assertRedirect(route('login'));
    }
}
