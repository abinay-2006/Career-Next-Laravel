<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('user_master', function (Blueprint $table) {
            $table->increments('UserId');
            $table->string('UserName');
            $table->string('Password');
        });
        Schema::create('employer_reg', function (Blueprint $table) {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Email');
            $table->string('Status');
            $table->string('UserName');
            $table->string('Password');
        });
        Schema::create('jobseeker_reg', function (Blueprint $table) {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Email');
            $table->string('Status');
            $table->string('UserName');
            $table->string('Password');
        });
        Schema::create('jobseeker_education', function (Blueprint $table) {
            $table->increments('EduId');
            $table->integer('JobSeekId');
        });
        Schema::create('jobseeker_school_education', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('JobSeekId');
        });
        Schema::create('jobseeker_documents', function (Blueprint $table) {
            $table->increments('DocId');
            $table->integer('JobSeekId');
            $table->string('DocumentType');
        });

        \DB::table('user_master')->insert(['UserName' => 'admin', 'Password' => Hash::make('secret')]);
        \DB::table('employer_reg')->insert([
            'CompanyName' => 'Example Co', 'Email' => 'employer@example.test', 'Status' => 'Confirm',
            'UserName' => 'employer', 'Password' => Hash::make('secret'),
        ]);
        \DB::table('jobseeker_reg')->insert([
            'JobSeekerName' => 'Example Seeker', 'Email' => 'seeker@example.test', 'Status' => 'Approved',
            'UserName' => 'seeker', 'Password' => Hash::make('secret'),
        ]);
    }

    public function test_admin_login_uses_session_guard_and_protects_admin_route(): void
    {
        $this->post('/login', ['txtUser' => 'admin', 'txtPass' => 'secret', 'cmbUser' => 'Administrator'])
            ->assertRedirect(route('admin.index'));

        $this->get(route('admin.index'))->assertOk();
        $this->get(route('employer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_employer_can_login_by_email_and_access_only_employer_routes(): void
    {
        $this->post('/login', ['txtUser' => 'employer@example.test', 'txtPass' => 'secret', 'cmbUser' => 'Employer'])
            ->assertRedirect(route('employer.dashboard'));

        $this->get(route('employer.dashboard'))->assertOk();
        $this->get(route('admin.index'))->assertRedirect(route('login'));
        $this->get(route('jobseeker.index'))->assertRedirect(route('login'));
    }

    public function test_jobseeker_can_login_by_username_and_cross_role_access_is_rejected(): void
    {
        $this->post('/login', ['txtUser' => 'seeker', 'txtPass' => 'secret', 'cmbUser' => 'JobSeeker'])
            ->assertRedirect(route('jobseeker.index'));

        $this->get(route('jobseeker.index'))->assertOk();
        $this->get(route('employer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.index'))->assertRedirect(route('login'));
    }

    public function test_wrong_password_and_inactive_status_do_not_authenticate(): void
    {
        $this->from('/login')->post('/login', [
            'txtUser' => 'employer', 'txtPass' => 'wrong', 'cmbUser' => 'Employer',
        ])->assertRedirect('/login')->assertSessionHas('error', 'Wrong username or password.');

        \DB::table('jobseeker_reg')->where('UserName', 'seeker')->update(['Status' => 'Pending']);
        $this->from('/login')->post('/login', [
            'txtUser' => 'seeker', 'txtPass' => 'secret', 'cmbUser' => 'JobSeeker',
        ])->assertRedirect('/login')->assertSessionHas('error', 'Your account is awaiting admin approval.');

        $this->get(route('jobseeker.index'))->assertRedirect(route('login'));

        \DB::table('employer_reg')->where('UserName', 'employer')->update(['Status' => 'Suspended']);
        $this->from('/login')->post('/login', [
            'txtUser' => 'employer', 'txtPass' => 'secret', 'cmbUser' => 'Employer',
        ])->assertRedirect('/login')->assertSessionHas('error', 'Your account has been suspended. Please contact the administrator.');
    }

    public function test_logout_clears_the_authenticated_session(): void
    {
        $this->post('/login', ['txtUser' => 'employer', 'txtPass' => 'secret', 'cmbUser' => 'Employer']);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('employer.dashboard'))->assertRedirect(route('login'));
    }
}
