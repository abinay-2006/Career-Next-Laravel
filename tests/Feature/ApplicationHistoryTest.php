<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicationHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId'); $table->string('JobSeekerName'); $table->string('Status');
        });
        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId'); $table->string('CompanyName'); $table->string('JobTitle'); $table->string('Status')->nullable();
        });
        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId'); $table->integer('JobSeekId'); $table->integer('JobId');
            $table->string('Status'); $table->string('Description'); $table->dateTime('ApplicationDate')->nullable();
        });
    }

    public function test_job_seeker_sees_only_their_own_applications_and_can_filter_them(): void
    {
        $mine = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Mine', 'Status' => 'Approved']);
        $other = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other', 'Status' => 'Approved']);
        $job = DB::table('job_master')->insertGetId(['CompanyName' => 'Northwind', 'JobTitle' => 'Analyst', 'Status' => 'approved']);
        $otherJob = DB::table('job_master')->insertGetId(['CompanyName' => 'Private Co', 'JobTitle' => 'Private Role', 'Status' => 'approved']);
        DB::table('application_master')->insert([
            ['JobSeekId' => $mine, 'JobId' => $job, 'Status' => 'Accepted', 'Description' => 'Updated', 'ApplicationDate' => now()],
            ['JobSeekId' => $other, 'JobId' => $otherJob, 'Status' => 'Accepted', 'Description' => 'Submitted', 'ApplicationDate' => now()],
        ]);

        $seeker = JobSeeker::query()->findOrFail($mine);
        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.index', ['status' => 'Accepted']))
            ->assertOk()->assertSee('Northwind')->assertSee('Accepted')->assertDontSee('Private Role');
    }

    public function test_application_history_requires_job_seeker_role(): void
    {
        $this->get(route('jobseeker.applications.index'))->assertRedirect(route('login'));
    }

    public function test_job_seeker_cannot_open_another_accounts_application_detail(): void
    {
        $mine = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Mine', 'Status' => 'Approved']);
        $other = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other', 'Status' => 'Approved']);
        $job = DB::table('job_master')->insertGetId(['CompanyName' => 'Northwind', 'JobTitle' => 'Analyst', 'Status' => 'approved']);
        $application = DB::table('application_master')->insertGetId([
            'JobSeekId' => $other, 'JobId' => $job, 'Status' => 'Pending', 'Description' => 'Submitted',
        ]);

        $seeker = JobSeeker::query()->findOrFail($mine);
        $this->actingAs($seeker, 'jobseeker')
            ->get(route('jobseeker.applications.show', $application))->assertNotFound();
    }
}
