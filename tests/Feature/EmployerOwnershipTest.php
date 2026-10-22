<?php

namespace Tests\Feature;

use App\Models\Employer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployerOwnershipTest extends TestCase
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
        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName');
            $table->string('JobTitle');
            $table->integer('Vacancy')->default(1);
            $table->string('MinQualification')->default('Graduate');
            $table->string('Description')->default('Role');
            $table->string('Status')->nullable();
        });
        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->string('Status');
            $table->string('Description')->nullable();
        });
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
        });
        Schema::create('walkin_master', function (Blueprint $table): void {
            $table->increments('WalkInId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName');
            $table->string('JobTitle');
        });
    }

    public function test_duplicate_company_name_does_not_grant_access_to_another_employers_jobs(): void
    {
        $first = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $second = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $job = DB::table('job_master')->insertGetId([
            'EmployerId' => $first, 'CompanyName' => 'Same Company', 'JobTitle' => 'Private job', 'Status' => 'pending',
        ]);

        $this->actingAs(Employer::query()->findOrFail($second), 'employer')
            ->get(route('employer.jobs.index'))->assertOk()->assertDontSee('Private job');

        $this->put(route('employer.jobs.update', $job), [
            'txtTitle' => 'Hijacked', 'txtTotal' => 1, 'cmbQual' => 'Graduate', 'txtDesc' => 'Changed',
        ])->assertNotFound();

        $this->delete(route('employer.jobs.destroy', $job))->assertNotFound();
        $this->assertSame('Private job', DB::table('job_master')->where('JobId', $job)->value('JobTitle'));
    }

    public function test_employer_cannot_open_or_change_another_employers_applicant_record(): void
    {
        $first = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $second = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $job = DB::table('job_master')->insertGetId([
            'EmployerId' => $first, 'CompanyName' => 'Same Company', 'JobTitle' => 'Private job', 'Status' => 'pending',
        ]);
        $seeker = DB::table('jobseeker_reg')->insertGetId([]);
        $application = DB::table('application_master')->insertGetId([
            'JobSeekId' => $seeker, 'JobId' => $job, 'Status' => 'Pending', 'Description' => 'Submitted',
        ]);

        $this->actingAs(Employer::query()->findOrFail($second), 'employer')
            ->get(route('employer.applications.show', $application))->assertForbidden();

        $this->put(route('employer.applications.update', $application), ['Status' => 'Accepted'])
            ->assertForbidden();
        $this->assertSame('Pending', DB::table('application_master')->where('ApplicationId', $application)->value('Status'));
    }

    public function test_duplicate_company_name_does_not_grant_access_to_another_employers_walkins(): void
    {
        $first = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $second = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $walkin = DB::table('walkin_master')->insertGetId([
            'EmployerId' => $first, 'CompanyName' => 'Same Company', 'JobTitle' => 'Private event',
        ]);

        $this->actingAs(Employer::query()->findOrFail($second), 'employer')
            ->get(route('employer.walkins.index'))->assertOk()->assertDontSee('Private event');

        $this->delete(route('employer.walkins.destroy', $walkin))->assertNotFound();
        $this->assertDatabaseHas('walkin_master', ['WalkInId' => $walkin, 'JobTitle' => 'Private event']);
    }
}
