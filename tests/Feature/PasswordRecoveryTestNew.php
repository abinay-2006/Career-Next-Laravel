<?php

namespace Tests\Feature;

use App\Mail\PasswordRecoveryMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Drop all tables to ensure a clean state
        Schema::dropIfExists('jobseeker_reg');
        Schema::dropIfExists('employer_reg');
        Schema::dropIfExists('password_recovery_tokens');
        Schema::dropIfExists('job_master');
        Schema::dropIfExists('logs');

        // Create necessary tables
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('Email');
            $table->string('Password');
            $table->string('UserName')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('Email');
            $table->string('Password');
            $table->string('UserName')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('password_recovery_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('account_type');
            $table->unsignedBigInteger('account_id');
            $table->string('email');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function test_eligible_email_gets_single_use_recovery_and_can_log_in_with_new_password(): void
    {
        $oldPassword = Hash::make('OldPassword123');
        $id = DB::table('jobseeker_reg')->insertGetId([
            'Email' => 'seeker@example.test',
            'Password' => $oldPassword,
            'UserName' => 'seeker',
            'Status' => 'confirmed',
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'seeker@example.test', 'account_type' => 'jobseeker']);
        Mail::assertSent(PasswordRecoveryMail::class);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;

        $this->get(route('forgot.verify'))->assertOk();
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotSame('', $token);
        $this->post(route('forgot.verify.submit'), ['otp' => $otp])->assertRedirect(route('forgot.verify'));
        $this->get(route('forgot.token', $token))->assertOk();

        $this->post(route('forgot.reset', $token), ['password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')));
        $this->assertFalse(Hash::check('OldPassword123', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')));
        $this->post('/login', ['txtUser' => 'seeker', 'txtPass' => 'NewPassword123', 'cmbUser' => 'JobSeeker'])
            ->assertRedirect(route('jobseeker.index'))->assertSessionHas('UserType', 'JobSeeker');
        $this->post(route('forgot.reset', $token), ['password' => 'AnotherPassword123', 'password_confirmation' => 'AnotherPassword123'])
            ->assertRedirect(route('forgot'));
    }

    public function test_unknown_email_gets_same_generic_recovery_response_without_mail(): void
    {
        Mail::fake();
        $this->post(route('forgot.send'), ['email' => 'unknown@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'))
            ->assertSessionHas('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
        Mail::assertNothingSent();

        DB::table('jobseeker_reg')->insert(['Email' => 'valid@example.test', 'Password' => Hash::make('OldPassword123')]);
        $this->post(route('forgot.send'), ['email' => 'valid@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'))
            ->assertSessionHas('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
        Mail::assertSent(PasswordRecoveryMail::class);
    }

    public function test_recovery_code_guessing_is_limited_per_email(): void
    {
        DB::table('jobseeker_reg')->insert([
            'Email' => 'limited@example.test',
            'Password' => Hash::make('OldPassword123'),
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'limited@example.test', 'account_type' => 'jobseeker']);
        foreach (range(1, 5) as $attempt) {
            $this->post(route('forgot.verify.submit'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts(
            'password-recovery-otp:' . hash('sha256', 'limited@example.test'),
            5
        ));
    }

    public function test_cross_account_token_rejection(): void
    {
        $employerEmail = 'shared@example.test';
        $employerPassword = Hash::make('EmployerPass123');
        $employerId = DB::table('employer_reg')->insertGetId(['Email' => $employerEmail, 'Password' => $employerPassword]);

        $jobSeekerEmail = 'shared@example.test';
        $jobSeekerPassword = Hash::make('JobSeekerPass123');
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId(['Email' => $jobSeekerEmail, 'Password' => $jobSeekerPassword]);

        Mail::fake();

        // Generate employer token
        $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'employer']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $employerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));

        // Attempt to reset job seeker password with employer token
        $response = $this->post(route('forgot.reset', $employerToken), ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
        $response->assertRedirect(route('forgot'));
        $this->assertDatabaseHas('jobseeker_reg', ['JobSeekId' => $jobSeekerId, 'Password' => $jobSeekerPassword]);

        Mail::fake();

        // Generate job seeker token
        $this->post(route('forgot.send'), ['email' => $jobSeekerEmail, 'account_type' => 'jobseeker']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $jobSeekerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));

        // Attempt to reset employer password with job seeker token
        $response = $this->post(route('forgot.reset', $jobSeekerToken), ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
        $response->assertRedirect(route('forgot'));
        $this->assertDatabaseHas('employer_reg', ['EmployerId' => $employerId, 'Password' => $employerPassword]);
    }

    public function test_expired_token_rejection(): void
    {
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId(['Email' => 'expire@example.test', 'Password' => Hash::make('OldPass123')]);

        Mail::fake();
        $this->post(route('forgot.send'), ['email' => 'expire@example.test', 'account_type' => 'jobseeker']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));

        // Simulate expiration: set token's expires_at to past
        DB::table('password_recovery_tokens')->where('token_hash', hash_hmac('sha256', $token, config('app.key')))->update(['expires_at' => now()->subMinutes(11)]);

        $response = $this->post(route('forgot.reset', $token), ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
        $response->assertRedirect(route('forgot'))->assertSessionHasErrors('email');
        $this->assertDatabaseHas('jobseeker_reg', ['JobSeekId' => $jobSeekerId, 'Password' => Hash::make('OldPass123')]);
    }

    public function test_shared_email_account_selection(): void
    {
        $employerEmail = 'shared@example.test';
        $employerId = DB::table('employer_reg')->insertGetId(['Email' => $employerEmail, 'Password' => Hash::make('EmpPass123')]);
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId(['Email' => $employerEmail, 'Password' => Hash::make('JSPass123')]);

        Mail::fake();

        // Target employer account
        $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'employer']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $employerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->post(route('forgot.reset', $employerToken), ['password' => 'NewEmpPass123', 'password_confirmation' => 'NewEmpPass123']);
        $this->assertTrue(Hash::check('NewEmpPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
        $this->assertTrue(Hash::check('JSPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));

        Mail::fake();

        // Target job seeker account
        $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'jobseeker']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $jobSeekerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->post(route('forgot.reset', $jobSeekerToken), ['password' => 'NewJSPass123', 'password_confirmation' => 'NewJSPass123']);
        $this->assertTrue(Hash::check('NewJSPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
        $this->assertTrue(Hash::check('NewEmpPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
    }

    public function test_legacy_username_normalization(): void
    {
        $emailStyleUsername = 'user@example.test';
        $normalized = \App\Services\AccountIdentifierRegistry::normalize($emailStyleUsername);
        $this->assertEquals('user@example.test', $normalized);

        $uppercaseUsername = 'USER@EXAMPLE.TEST';
        $normalized = \App\Services\AccountIdentifierRegistry::normalize($uppercaseUsername);
        $this->assertEquals('user@example.test', $normalized);

        $whitespaceUsername = '  user@example.test  ';
        $normalized = \App\Services\AccountIdentifierRegistry::normalize($whitespaceUsername);
        $this->assertEquals('user@example.test', $normalized);

        $employerId = DB::table('employer_reg')->insertGetId(['Email' => 'legacy@example.test', 'Password' => Hash::make('pass')]);
        $normalizedEmail = \App\Services\AccountIdentifierRegistry::normalize('legacy@example.test');
        // Test case-insensitive email lookup
        $query = DB::table('employer_reg')->whereRaw('LOWER(Email) = ?', [$normalizedEmail]);
        $accountType = $query->exists() ? 'employer' : 'none';
        $this->assertEquals('employer', $accountType);
    }
}
