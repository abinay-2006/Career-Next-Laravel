<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });
        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->string('CompanyName');
            $table->string('JobTitle');
            $table->string('Status')->nullable();
        });
        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status')->nullable();
            $table->string('Description')->nullable();
            $table->dateTime('ApplicationDate')->nullable();
        });
        Schema::create('jobseeker_education', function (Blueprint $table): void {
            $table->increments('EduId'); $table->integer('JobSeekId');
        });
        Schema::create('jobseeker_school_education', function (Blueprint $table): void {
            $table->increments('id'); $table->integer('JobSeekId');
        });
        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocId'); $table->integer('JobSeekId'); $table->string('DocumentType');
        });
        Schema::create('news_master', function (Blueprint $table): void {
            $table->increments('NewsId'); $table->string('News', 200); $table->date('NewsDate');
        });
    }

    public function test_employer_analytics_only_include_jobs_and_applications_for_the_employers_company(): void
    {
        $employerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Northwind']);
        $ownJob = DB::table('job_master')->insertGetId(['CompanyName' => 'Northwind', 'JobTitle' => 'Analyst', 'Status' => 'approved']);
        $otherJob = DB::table('job_master')->insertGetId(['CompanyName' => 'Private Co', 'JobTitle' => 'Private Role', 'Status' => 'pending']);
        DB::table('application_master')->insert([
            ['JobSeekId' => 1, 'JobId' => $ownJob, 'Status' => 'Pending', 'Description' => '', 'ApplicationDate' => now()],
            ['JobSeekId' => 2, 'JobId' => $otherJob, 'Status' => 'Accepted', 'Description' => '', 'ApplicationDate' => now()],
        ]);

        $this->actingAs(Employer::query()->findOrFail($employerId), 'employer')
            ->getJson(route('employer.analytics', ['range' => 'all']))
            ->assertOk()->assertJsonPath('cards.0.value', 1)->assertJsonPath('cards.2.value', 1)
            ->assertJsonPath('jobStatuses.approved', 1)
            ->assertJsonMissing(['title' => 'Private Role']);
    }

    public function test_job_seeker_analytics_only_include_the_authenticated_seekers_applications(): void
    {
        $seekerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Mine']);
        $otherId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other']);
        $ownJob = DB::table('job_master')->insertGetId(['CompanyName' => 'Northwind', 'JobTitle' => 'My role', 'Status' => 'approved']);
        $privateJob = DB::table('job_master')->insertGetId(['CompanyName' => 'Private Co', 'JobTitle' => 'Private role', 'Status' => 'approved']);
        DB::table('application_master')->insert([
            ['JobSeekId' => $seekerId, 'JobId' => $ownJob, 'Status' => 'Accepted', 'Description' => '', 'ApplicationDate' => now()],
            ['JobSeekId' => $otherId, 'JobId' => $privateJob, 'Status' => 'Pending', 'Description' => '', 'ApplicationDate' => now()],
        ]);

        $this->actingAs(JobSeeker::query()->findOrFail($seekerId), 'jobseeker')
            ->getJson(route('jobseeker.analytics', ['range' => 'all']))
            ->assertOk()->assertJsonPath('cards.0.value', 1)->assertJsonPath('applicationStatuses.accepted', 1)
            ->assertJsonMissing(['title' => 'Private role']);
    }

    public function test_analytics_ranges_are_validated_and_filter_application_counts(): void
    {
        $seekerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Mine']);
        $jobId = DB::table('job_master')->insertGetId(['CompanyName' => 'Northwind', 'JobTitle' => 'Role', 'Status' => 'approved']);
        DB::table('application_master')->insert([
            ['JobSeekId' => $seekerId, 'JobId' => $jobId, 'Status' => 'Pending', 'Description' => '', 'ApplicationDate' => now()],
            ['JobSeekId' => $seekerId, 'JobId' => $jobId, 'Status' => 'Rejected', 'Description' => '', 'ApplicationDate' => now()->subDays(40)],
        ]);
        $seeker = JobSeeker::query()->findOrFail($seekerId);

        $this->actingAs($seeker, 'jobseeker')->getJson(route('jobseeker.analytics', ['range' => '7']))
            ->assertOk()->assertJsonPath('cards.3.value', 1)->assertJsonPath('applicationStatuses.pending', 1);
        $this->getJson(route('jobseeker.analytics', ['range' => 'all']))
            ->assertOk()->assertJsonPath('cards.3.value', 2)->assertJsonPath('applicationStatuses.rejected', 1);
        $this->getJson(route('jobseeker.analytics', ['range' => '365']))->assertUnprocessable();
    }

    public function test_analytics_routes_remain_role_protected(): void
    {
        $this->get(route('employer.analytics'))->assertRedirect(route('login'));
        $this->get(route('jobseeker.analytics'))->assertRedirect(route('login'));
        $seekerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Mine']);
        $this->actingAs(JobSeeker::query()->findOrFail($seekerId), 'jobseeker')
            ->getJson(route('employer.analytics'))->assertForbidden();
    }

    public function test_news_listing_search_and_detail_use_existing_news_records(): void
    {
        $latestId = DB::table('news_master')->insertGetId(['News' => 'Career fair registration opens today.', 'NewsDate' => now()->toDateString()]);
        DB::table('news_master')->insert(['News' => 'A new learning resource is available.', 'NewsDate' => now()->subDay()->toDateString()]);

        $this->get(route('news.index', ['q' => 'Career fair']))
            ->assertOk()->assertSee('Career fair registration opens today.')->assertDontSee('A new learning resource');
        $this->get(route('news.show', $latestId))
            ->assertOk()->assertSee('Career fair registration opens today.')->assertSee('Back to Career News');
    }

    public function test_empty_job_seeker_dashboard_renders_without_fake_activity(): void
    {
        $seekerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'New Seeker']);

        $this->actingAs(JobSeeker::query()->findOrFail($seekerId), 'jobseeker')
            ->get(route('jobseeker.index'))
            ->assertOk()->assertSee('Your career activity')->assertSee('0');
    }
}
