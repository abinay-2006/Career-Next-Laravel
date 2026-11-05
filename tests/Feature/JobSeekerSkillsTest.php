<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobSeekerSkill;
use App\Services\JobMatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The job seeker's own Skills & Software record: storage, the shared section on
 * the profile page and the union it feeds into Smart Job Match.
 */
class JobSeekerSkillsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Status');
            $table->string('Email')->nullable();
        });

        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->unique();
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
        });

        Schema::create('jobseeker_resumes', function (Blueprint $table): void {
            $table->id();
            $table->integer('JobSeekId');
            $table->string('title')->default('My Resume');
            $table->string('template')->default('classic');
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocId');
            $table->integer('JobSeekId');
            $table->string('DocumentType');
            $table->string('FileName');
            $table->string('VerificationStatus')->default('pending');
        });

        Schema::create('jobseeker_education', function (Blueprint $table): void {
            $table->increments('EduId');
            $table->integer('JobSeekId');
        });

        Schema::create('jobseeker_school_education', function (Blueprint $table): void {
            $table->increments('SchoolEduId');
            $table->integer('JobSeekId');
        });

        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName')->nullable();
            $table->string('JobTitle');
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->default('B.E');
            $table->text('Description')->nullable();
            $table->string('Status')->nullable();
            $table->json('RequiredSkills')->nullable();
            $table->json('RequiredSoftware')->nullable();
        });

        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status')->nullable();
            $table->text('Description')->nullable();
            $table->dateTime('ApplicationDate')->nullable();
        });
    }

    private function seeker(string $name = 'Skilled Seeker', ?string $resumeSkills = null): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => $name,
            'Status' => 'Approved',
            'Email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
        ]);

        if ($resumeSkills !== null) {
            DB::table('jobseeker_resumes')->insert([
                'JobSeekId' => $id,
                'title' => 'Saved Resume',
                'template' => 'classic',
                'content' => json_encode(['skills' => $resumeSkills]),
                'created_at' => '2026-03-01 10:00:00',
                'updated_at' => '2026-03-01 10:00:00',
            ]);
        }

        return JobSeeker::query()->findOrFail($id);
    }

    /**
     * @param  list<array{name: string, proficiency?: string|null}>  $entries
     */
    private function entries(array $entries): string
    {
        return json_encode(array_map(
            static fn (array $entry): array => ['name' => $entry['name'], 'proficiency' => $entry['proficiency'] ?? null],
            $entries
        ));
    }

    public function test_a_seeker_saves_their_skills_and_software(): void
    {
        $seeker = $this->seeker();

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([
                    ['name' => '  Laravel  ', 'proficiency' => 'advanced'],
                    ['name' => 'laravel'],
                    ['name' => 'MySQL', 'proficiency' => 'basic'],
                ]),
                'software' => $this->entries([
                    ['name' => 'Visual Studio Code'],
                    ['name' => 'Postman', 'proficiency' => 'expert'],
                ]),
            ])
            ->assertRedirect();

        $saved = JobSeekerSkill::findFor($seeker->JobSeekId);

        $this->assertNotNull($saved);
        // Whitespace is collapsed and a case variant never becomes a second entry.
        $this->assertSame(['Laravel', 'MySQL'], $saved->names('skills'));
        $this->assertSame(['Visual Studio Code', 'Postman'], $saved->names('software'));
        $this->assertSame('advanced', $saved->skills[0]['proficiency']);
        $this->assertSame('basic', $saved->skills[1]['proficiency']);
        $this->assertNull($saved->software[0]['proficiency']);
    }

    public function test_saving_again_replaces_the_lists_instead_of_adding_a_second_row(): void
    {
        $seeker = $this->seeker();

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), ['skills' => $this->entries([['name' => 'Java']])]);
        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), ['skills' => $this->entries([['name' => 'Python']])]);

        $this->assertSame(1, DB::table('jobseeker_skills')->where('JobSeekId', $seeker->JobSeekId)->count());
        $this->assertSame(['Python'], JobSeekerSkill::findFor($seeker->JobSeekId)->names('skills'));
    }

    public function test_an_empty_save_clears_both_lists(): void
    {
        $seeker = $this->seeker();

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([['name' => 'Java']]),
                'software' => $this->entries([['name' => 'Eclipse']]),
            ]);

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), ['skills' => '', 'software' => '[]']);

        $saved = JobSeekerSkill::findFor($seeker->JobSeekId);

        $this->assertSame([], $saved->names('skills'));
        $this->assertSame([], $saved->names('software'));
    }

    public function test_an_unknown_proficiency_is_rejected_and_nothing_is_stored(): void
    {
        $seeker = $this->seeker();

        $this->actingAs($seeker, 'jobseeker')
            ->from(route('jobseeker.profile.index'))
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([['name' => 'Laravel', 'proficiency' => 'ninja']]),
            ])
            ->assertRedirect(route('jobseeker.profile.index'))
            ->assertSessionHasErrors('skills');

        $this->assertSame(0, DB::table('jobseeker_skills')->where('JobSeekId', $seeker->JobSeekId)->count());
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $seeker = $this->seeker();

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), ['skills' => '["Laravel"]'])
            ->assertSessionHasErrors('skills');

        $this->assertSame(0, DB::table('jobseeker_skills')->where('JobSeekId', $seeker->JobSeekId)->count());
    }

    public function test_the_profile_page_shows_the_saved_lists_and_the_resume_shortcut(): void
    {
        $seeker = $this->seeker('Profile Seeker', 'Docker, Kubernetes');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([['name' => 'Laravel', 'proficiency' => 'advanced']]),
                'software' => $this->entries([['name' => 'Postman']]),
            ]);

        $page = $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.profile.index'))
            ->assertOk()
            ->assertSee('Skills &amp; Software', false)
            ->getContent();

        // A seeker's own list has no Mandatory/Preferred column.
        $this->assertStringContainsString('data-cn-req-picker="skills" data-cn-req-kind="skills" data-cn-req-types="off"', $page);

        $seeded = json_decode(html_entity_decode($this->hiddenValue($page, 'skills'), ENT_QUOTES), true);
        $this->assertSame([['name' => 'Laravel', 'proficiency' => 'advanced']], $seeded);

        $software = json_decode(html_entity_decode($this->hiddenValue($page, 'software'), ENT_QUOTES), true);
        $this->assertSame([['name' => 'Postman', 'proficiency' => null]], $software);

        // The resume skills are offered as a one-click copy (Blade escapes the
        // list because it is handed to the button as an inline attribute).
        $this->assertStringContainsString('Copy skills from my resume', $page);
        $this->assertStringContainsString('["Docker","Kubernetes"]', html_entity_decode($page, ENT_QUOTES));
    }

    public function test_the_resume_shortcut_is_hidden_without_a_saved_resume(): void
    {
        $seeker = $this->seeker('No Resume Seeker');

        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.profile.index'))
            ->assertOk()
            ->assertSee('Save a resume in Resume Builder')
            ->assertDontSee('Copy skills from my resume');
    }

    public function test_smart_job_match_counts_profile_entries_alongside_the_resume(): void
    {
        $seeker = $this->seeker('Union Seeker', 'Laravel');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([['name' => 'MySQL']]),
                'software' => $this->entries([['name' => 'Figma']]),
            ]);

        $jobId = DB::table('job_master')->insertGetId([
            'JobTitle' => 'Backend Developer',
            'Vacancy' => 1,
            'MinQualification' => 'B.E',
            'Description' => 'Builds services.',
            'Status' => 'approved',
            'RequiredSkills' => json_encode([
                ['name' => 'Laravel', 'type' => 'mandatory'],
                ['name' => 'MySQL', 'type' => 'mandatory'],
            ]),
            'RequiredSoftware' => json_encode([['name' => 'Figma', 'type' => 'mandatory']]),
        ]);

        $match = $this->match($seeker->JobSeekId, (int) $jobId);

        $this->assertSame(100, $match['percentage']);
        $this->assertSame(['Laravel', 'MySQL'], $match['matched']);
        $this->assertSame(['Figma'], $match['software']['matched']);
    }

    public function test_a_seeker_without_a_resume_is_still_scored_from_their_profile(): void
    {
        $seeker = $this->seeker('No Resume Seeker');

        $this->actingAs($seeker, 'jobseeker')
            ->put(route('jobseeker.profile.skills.update'), [
                'skills' => $this->entries([['name' => 'Laravel']]),
            ]);

        $jobId = DB::table('job_master')->insertGetId([
            'JobTitle' => 'Backend Developer',
            'Vacancy' => 1,
            'MinQualification' => 'B.E',
            'Description' => 'Builds services.',
            'Status' => 'approved',
            'RequiredSkills' => json_encode([['name' => 'Laravel', 'type' => 'mandatory']]),
        ]);

        $match = $this->match($seeker->JobSeekId, (int) $jobId);

        $this->assertSame(JobMatchService::STATUS_SCORED, $match['status']);
        $this->assertSame(100, $match['percentage']);
    }

    public function test_a_seeker_with_neither_source_is_asked_for_both(): void
    {
        $seeker = $this->seeker('Empty Seeker');

        $lists = app(JobMatchService::class)->candidateLists($seeker->JobSeekId);

        $this->assertSame([], $lists['skills']);

        $job = new Job(['JobTitle' => 'Any Role', 'Description' => 'Anything.', 'MinQualification' => 'B.E']);
        $match = app(JobMatchService::class)->calculate($lists['skills'], $job);

        $this->assertSame(JobMatchService::STATUS_NEEDS_RESUME, $match['status']);
        $this->assertStringContainsString('Skills & Software', JobMatchService::NO_RESUME_MESSAGE);
        $this->assertStringContainsString('Resume Builder', $match['explanation']);
    }

    public function test_a_seeker_without_a_saved_row_is_scored_from_the_resume_alone(): void
    {
        $seeker = $this->seeker('Legacy Seeker', 'Laravel');

        DB::table('jobseeker_skills')->where('JobSeekId', $seeker->JobSeekId)->delete();

        $lists = app(JobMatchService::class)->candidateLists($seeker->JobSeekId);

        $this->assertSame(['Laravel'], $lists['skills']);
        $this->assertSame(['Laravel'], $lists['software']);
    }

    /**
     * Score one listing the way the job search pages do: the seeker's unioned
     * profile and resume lists.
     *
     * @return array<string, mixed>
     */
    private function match(int $jobSeekerId, int $jobId): array
    {
        $service = app(JobMatchService::class);
        $lists = $service->candidateLists($jobSeekerId);

        return $service->calculate(
            $lists['skills'],
            Job::query()->findOrFail($jobId),
            $lists['software']
        );
    }

    private function hiddenValue(string $html, string $name): string
    {
        preg_match('/name="'.$name.'" value="([^"]*)"/', $html, $matches);

        $this->assertArrayHasKey(1, $matches, 'The '.$name.' picker needs its hidden JSON field.');

        return $matches[1];
    }
}
