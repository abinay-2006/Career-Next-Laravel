<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('user_master', function (Blueprint $table) {
            $table->increments('UserId'); $table->string('UserName'); $table->string('Password');
        });
        Schema::create('jobseeker_reg', function (Blueprint $table) {
            $table->increments('JobSeekId'); $table->string('JobSeekerName'); $table->string('Status');
            $table->string('UserName')->nullable(); $table->string('Email')->nullable(); $table->string('Mobile')->nullable();
            $table->string('City')->nullable(); $table->string('Qualification')->nullable(); $table->string('Gender')->nullable();
        });
        Schema::create('employer_reg', function (Blueprint $table) {
            $table->increments('EmployerId'); $table->string('CompanyName'); $table->string('Status');
            $table->string('UserName')->nullable(); $table->string('Email')->nullable(); $table->string('Mobile')->nullable();
            $table->string('City')->nullable(); $table->string('Area_Work')->nullable(); $table->string('ContactPerson')->nullable();
        });
        Schema::create('job_master', function (Blueprint $table) {
            $table->increments('JobId'); $table->string('CompanyName'); $table->string('JobTitle');
            $table->integer('Vacancy'); $table->string('MinQualification'); $table->string('Description');
            $table->string('Status')->nullable();
        });
        Schema::create('jobseeker_documents', function (Blueprint $table) {
            $table->increments('DocId'); $table->integer('JobSeekId'); $table->string('DocumentType');
            $table->string('FileName'); $table->string('OriginalFileName'); $table->dateTime('UploadDate');
            $table->string('VerificationStatus'); $table->text('AdminNotes')->nullable();
        });

        $admin = new Admin(['UserName' => 'test-admin']);
        $admin->UserId = 1;
        $this->actingAs($admin, 'admin');
        Storage::fake('local');
    }

    public function test_admin_user_actions_update_only_the_requested_record(): void
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Pending Seeker', 'Status' => 'Pending',
        ], 'JobSeekId');

        $this->postJson(route('admin.users.confirm', ['type' => 'jobseeker', 'id' => $id]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('Confirm', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Status'));

        $this->postJson(route('admin.users.suspend', ['type' => 'jobseeker', 'id' => $id]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('Suspended', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Status'));
        $this->deleteJson(route('admin.users.delete', ['type' => 'jobseeker', 'id' => $id]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('jobseeker_reg', ['JobSeekId' => $id]);
    }

    public function test_admin_job_approval_and_rejection_persist_existing_lowercase_status_values(): void
    {
        $id = DB::table('job_master')->insertGetId([
            'CompanyName' => 'Example', 'JobTitle' => 'Engineer', 'Vacancy' => 1,
            'MinQualification' => 'Graduate', 'Description' => 'Role', 'Status' => 'Pending',
        ], 'JobId');

        $this->post(route('admin.jobs.approve', $id))->assertRedirect(route('admin.manage.jobs'));
        $this->assertSame('approved', DB::table('job_master')->where('JobId', $id)->value('Status'));
        $this->post(route('admin.jobs.reject', $id))->assertRedirect(route('admin.manage.jobs'));
        $this->assertSame('rejected', DB::table('job_master')->where('JobId', $id)->value('Status'));
    }

    public function test_pending_job_details_include_named_approve_and_reject_actions(): void
    {
        $id = DB::table('job_master')->insertGetId([
            'CompanyName' => 'Example', 'JobTitle' => 'Engineer', 'Vacancy' => 1,
            'MinQualification' => 'Graduate', 'Description' => 'Role', 'Status' => 'Pending',
        ], 'JobId');

        $this->get(route('admin.manage.jobs'))
            ->assertOk()
            ->assertSee('Approve Job')
            ->assertSee('Reject Job')
            ->assertSee(route('admin.jobs.approve', ['id' => '__JOB_ID__']), false)
            ->assertSee(route('admin.jobs.reject', ['id' => '__JOB_ID__']), false);
    }

    public function test_admin_job_status_filter_matches_status_case_insensitively(): void
    {
        DB::table('job_master')->insert([
            'CompanyName' => 'Example', 'JobTitle' => 'Engineer', 'Vacancy' => 1,
            'MinQualification' => 'Graduate', 'Description' => 'Role', 'Status' => 'Pending',
        ]);

        $this->get(route('admin.manage.jobs', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Engineer');
    }

    public function test_admin_document_status_and_protected_view_use_private_storage(): void
    {
        Storage::disk('local')->put('documents/test.pdf', '%PDF-1.4 test');
        $id = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => 1, 'DocumentType' => 'resume', 'FileName' => 'documents/test.pdf',
            'OriginalFileName' => 'resume.pdf', 'UploadDate' => now(), 'VerificationStatus' => 'pending',
        ], 'DocId');

        $this->get(route('admin.documents.view', $id))->assertOk()->assertHeader('cache-control', 'private, no-store');
        $this->post(route('admin.documents.approve', $id))->assertRedirect(route('admin.manage.documents'));
        $this->assertSame('approved', DB::table('jobseeker_documents')->where('DocId', $id)->value('VerificationStatus'));
    }
}
