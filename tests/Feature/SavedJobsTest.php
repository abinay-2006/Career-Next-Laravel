<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SavedJobsTest extends TestCase
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
            $table->string('Description')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('saved_jobs', function (Blueprint $table): void {
            $table->bigIncrements('SavedJobId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->timestamps();
            $table->unique(['JobSeekId', 'JobId'], 'saved_jobs_seeker_job_unique');
        });
    }

    private function createSeeker(string $name): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => $name, 'Status' => 'Approved']);
        return JobSeeker::query()->findOrFail($id);
    }

    private function createJob(string $status = 'approved'): int
    {
        return DB::table('job_master')->insertGetId([
            'CompanyName' => 'Career Next Demo',
            'JobTitle' => 'Laravel Developer',
            'Vacancy' => 2,
            'MinQualification' => 'BCA',
            'Description' => 'Build web applications',
            'Status' => $status,
        ]);
    }

    public function test_job_seeker_can_save_a_job_and_duplicate_requests_do_not_create_duplicates(): void
    {
        $seeker = $this->createSeeker('Seeker One');
        $jobId = $this->createJob();

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.saved-jobs.store', $jobId))->assertRedirect();
        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.saved-jobs.store', $jobId))->assertRedirect();

        $this->assertDatabaseCount('saved_jobs', 1);
        $this->assertDatabaseHas('saved_jobs', ['JobSeekId' => $seeker->JobSeekId, 'JobId' => $jobId]);
    }

    public function test_job_seeker_can_only_see_and_remove_their_own_saved_jobs(): void
    {
        $owner = $this->createSeeker('Owner');
        $other = $this->createSeeker('Other');
        $jobId = $this->createJob();

        DB::table('saved_jobs')->insert([
            ['JobSeekId' => $owner->JobSeekId, 'JobId' => $jobId, 'created_at' => now(), 'updated_at' => now()],
            ['JobSeekId' => $other->JobSeekId, 'JobId' => $jobId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($owner, 'jobseeker')
            ->get(route('jobseeker.saved-jobs.index'))
            ->assertOk()->assertSee('Laravel Developer');

        $this->actingAs($owner, 'jobseeker')
            ->post(route('jobseeker.saved-jobs.destroy', $jobId))->assertRedirect();

        $this->assertDatabaseHas('saved_jobs', ['JobSeekId' => $other->JobSeekId, 'JobId' => $jobId]);
        $this->assertDatabaseMissing('saved_jobs', ['JobSeekId' => $owner->JobSeekId, 'JobId' => $jobId]);
    }

    public function test_unapproved_jobs_cannot_be_saved(): void
    {
        $seeker = $this->createSeeker('Seeker One');
        $jobId = $this->createJob('pending');

        $this->actingAs($seeker, 'jobseeker')
            ->post(route('jobseeker.saved-jobs.store', $jobId))->assertNotFound();

        $this->assertDatabaseCount('saved_jobs', 0);
    }

    public function test_saved_jobs_routes_require_job_seeker_authentication(): void
    {
        $jobId = $this->createJob();
        $this->get(route('jobseeker.saved-jobs.index'))->assertRedirect(route('login'));
        $this->post(route('jobseeker.saved-jobs.store', $jobId))->assertRedirect(route('login'));
    }
}
