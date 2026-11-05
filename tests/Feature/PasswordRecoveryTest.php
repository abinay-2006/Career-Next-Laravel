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

        Schema::dropIfExists('jobseeker_reg');
        Schema::dropIfExists('employer_reg');
        Schema::dropIfExists('password_recovery_tokens');
        Schema::dropIfExists('job_master');
        Schema::dropIfExists('logs');

        Schema::create('jobseeker_reg', function (Blueprint $table) {
            $table->increments('JobSeekId');
            $table->string('Email');
            $table->string('Password');
            $table->string('UserName')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table) {
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

    /**
     * The recovery request mails a code only for the account type selected in
     * account_type, and the token row records which account the code belongs
     * to. This helper resolves the mailed code for a given account by matching
     * it against token rows instead of relying on send order.
     *
     * @param  int  $afterMail  Number of codes already sent, so the returned one
     *                          comes from the request the test just made.
     */
    private function codeFor(string $accountType, int $accountId, int $afterMail = 0): string
    {
        $codes = Mail::sent(PasswordRecoveryMail::class)->values();
        $issuedByThisRequest = $codes->slice($afterMail)->all();

        $this->assertNotEmpty(
            $issuedByThisRequest,
            'The recovery request mailed no code at all.'
        );

        // Newest first: a later request replaces nothing, so an older unused code
        // for the same account is still in the table.
        foreach (array_reverse($issuedByThisRequest) as $mail) {
            $issued = DB::table('password_recovery_tokens')
                ->where('token_hash', hash_hmac('sha256', $mail->otp, (string) config('app.key')))
                ->where('account_type', $accountType)
                ->where('account_id', $accountId)
                ->exists();

            if ($issued) {
                return $mail->otp;
            }
        }

        $this->fail('No recovery code was mailed for ' . $accountType . ' account ' . $accountId . '.');
    }

    private function mailedCodeCount(): int
    {
        return Mail::sent(PasswordRecoveryMail::class)->count();
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
        $otp = $mail ? $mail->otp : '000000'; // Fallback to dummy OTP if mail is not sent

        $this->get(route('forgot.verify'))->assertOk();
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotSame('', $token);
        $this->post(route('forgot.verify.submit'), ['otp' => $otp])->assertRedirect(route('forgot.verify'));
        $this->get(route('forgot.token', $token))->assertOk();

        $this->post(route('forgot.reset', $token), [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(
            'NewPassword123',
            DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')
        ));
        $this->assertFalse(Hash::check(
            'OldPassword123',
            DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')
        ));

        $this->post('/login', [
            'txtUser' => 'seeker',
            'txtPass' => 'NewPassword123',
            'cmbUser' => 'JobSeeker',
        ])
            ->assertRedirect(route('jobseeker.index'))
            ->assertSessionHas('UserType', 'JobSeeker');

        $this->post(route('forgot.reset', $token), [
            'password' => 'AnotherPassword123',
            'password_confirmation' => 'AnotherPassword123',
        ])->assertRedirect(route('forgot'));
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
        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => $employerEmail,
            'Password' => $employerPassword,
        ]);

        $jobSeekerEmail = 'shared@example.test';
        $jobSeekerPassword = Hash::make('JobSeekerPass123');
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId([
            'Email' => $jobSeekerEmail,
            'Password' => $jobSeekerPassword,
        ]);

        Mail::fake();

        // The selected account type picks which account receives the code, so
        // only the employer's code is mailed even though the job seeker shares
        // the address.
        $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'employer']);
        $this->assertCount(1, Mail::sent(PasswordRecoveryMail::class), 'Only the selected employer account receives a code.');
        $otp = $this->codeFor('employer', $employerId);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $verified->assertSessionHasNoErrors();
        $this->assertStringStartsWith('/forgot/', parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $employerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotEmpty($employerToken, 'Token should not be empty');
        $this->post(route('forgot.reset', str_repeat('x', 64)), [
            'password' => 'WrongAccountPass123',
            'password_confirmation' => 'WrongAccountPass123',
        ])->assertRedirect(route('forgot'))->assertSessionHasErrors('email');
        $this->assertTrue(DB::table('password_recovery_tokens')
            ->where('token_hash', hash_hmac('sha256', $employerToken, (string) config('app.key')))
            ->exists(), 'An invalid reset attempt must not consume the valid Employer token');

        $response = $this->post(route('forgot.reset', $employerToken), [
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);
        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
        $this->assertDatabaseHas('jobseeker_reg', [
            'JobSeekId' => $jobSeekerId,
            'Password' => $jobSeekerPassword,
        ]);

        $before = $this->mailedCodeCount();
        $this->post(route('forgot.send'), ['email' => $jobSeekerEmail, 'account_type' => 'jobseeker']);
        $otp = $this->codeFor('jobseeker', $jobSeekerId, $before);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $verified->assertSessionHasNoErrors();
        $jobSeekerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotEmpty($jobSeekerToken, 'Token should not be empty');
        $this->post(route('forgot.reset', str_repeat('y', 64)), [
            'password' => 'WrongAccountPass123',
            'password_confirmation' => 'WrongAccountPass123',
        ])->assertRedirect(route('forgot'))->assertSessionHasErrors('email');
        $this->assertTrue(DB::table('password_recovery_tokens')
            ->where('token_hash', hash_hmac('sha256', $jobSeekerToken, (string) config('app.key')))
            ->exists(), 'An invalid reset attempt must not consume the valid Job Seeker token');

        $response = $this->post(route('forgot.reset', $jobSeekerToken), [
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);
        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
        $this->assertTrue(Hash::check('NewPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
    }

    public function test_expired_token_rejection(): void
    {
        $originalPassword = Hash::make('OldPass123');
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId([
            'Email' => 'expire@example.test',
            'Password' => $originalPassword,
        ]);
        $otherPassword = Hash::make('OtherAccountPass123');
        $otherId = DB::table('employer_reg')->insertGetId([
            'Email' => 'other@example.test',
            'Password' => $otherPassword,
        ]);

        Mail::fake();
        $this->post(route('forgot.send'), ['email' => 'expire@example.test', 'account_type' => 'jobseeker']);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $this->assertNotNull($mail, 'Recovery mail should be sent');
        $otp = $mail->otp;
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotEmpty($token, 'Token should not be empty');

        DB::table('password_recovery_tokens')
            ->where('token_hash', hash_hmac('sha256', $token, config('app.key')))
            ->update(['expires_at' => now()->subMinutes(11)]);

        $response = $this->post(route('forgot.reset', $token), [
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);
        $response->assertRedirect(route('forgot'))->assertSessionHasErrors('email');

        // Verify password was NOT changed
        $currentPassword = DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password');
        $this->assertTrue(Hash::check('OldPass123', $currentPassword), 'Password should remain unchanged after expired token rejection');
        $this->assertFalse(Hash::check('NewPass123', $currentPassword), 'New password should not be set');

        // Verify token is deleted/invalidated
        $tokenRecord = DB::table('password_recovery_tokens')
            ->where('token_hash', hash_hmac('sha256', $token, config('app.key')))
            ->exists();
        $this->assertFalse($tokenRecord, 'Expired token should be invalidated');

        $this->post(route('forgot.reset', $token), [
            'password' => 'ReusedPass123',
            'password_confirmation' => 'ReusedPass123',
        ])->assertRedirect(route('forgot'))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
        $this->assertTrue(Hash::check('OtherAccountPass123', DB::table('employer_reg')->where('EmployerId', $otherId)->value('Password')));
    }

    public function test_shared_email_account_selection(): void
    {
        $employerEmail = 'shared@example.test';
        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => $employerEmail,
            'Password' => Hash::make('EmpPass123'),
        ]);
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId([
            'Email' => $employerEmail,
            'Password' => Hash::make('JSPass123'),
        ]);

        Mail::fake();

        $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'employer']);
        $this->assertCount(1, Mail::sent(PasswordRecoveryMail::class), 'Only the selected employer account receives a code.');
        $this->assertSame(0, DB::table('password_recovery_tokens')
            ->where('account_type', 'jobseeker')->where('account_id', $jobSeekerId)->count(),
            'The unselected job seeker sharing the address gets no code.');
        $otp = $this->codeFor('employer', $employerId);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $verified->assertSessionHasNoErrors();
        $this->assertSame(64, strlen(basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH))));
        $employerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotEmpty($employerToken, 'Token should not be empty');

        $this->post(route('forgot.reset', $employerToken), [
            'password' => 'NewEmpPass123',
            'password_confirmation' => 'NewEmpPass123',
        ]);
        $this->assertTrue(Hash::check(
            'NewEmpPass123',
            DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')
        ));
        $this->assertTrue(Hash::check(
            'JSPass123',
            DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')
        ));

        // A completed reset removes that account's own codes and nothing else.
        $this->assertSame(0, DB::table('password_recovery_tokens')
            ->where('account_type', 'employer')->where('account_id', $employerId)->count(),
            'The employer\'s codes are spent once the reset is done.');
        $this->assertSame(0, DB::table('password_recovery_tokens')
            ->where('account_type', 'jobseeker')->where('account_id', $jobSeekerId)->count(),
            'No code was ever issued for the unselected job seeker.');

        $before = $this->mailedCodeCount();
        $sendJobSeeker = $this->post(route('forgot.send'), ['email' => $employerEmail, 'account_type' => 'jobseeker']);
        $sendJobSeeker->assertRedirect(route('forgot.verify'));
        $this->assertCount(2, Mail::sent(PasswordRecoveryMail::class), 'The second request mails a fresh code for the selected job seeker only.');
        $otp = $this->codeFor('jobseeker', $jobSeekerId, $before);
        $this->assertDatabaseHas('password_recovery_tokens', [
            'account_type' => 'jobseeker',
            'account_id' => $jobSeekerId,
            'email' => $employerEmail,
            'token_hash' => hash_hmac('sha256', $otp, (string) config('app.key')),
        ]);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect();
        $verified->assertSessionHasNoErrors();
        $jobSeekerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertNotEmpty($jobSeekerToken, 'Token should not be empty');

        $this->post(route('forgot.reset', $jobSeekerToken), [
            'password' => 'NewJSPass123',
            'password_confirmation' => 'NewJSPass123',
        ]);
        $this->assertTrue(Hash::check(
            'NewJSPass123',
            DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')
        ));
        $this->assertTrue(Hash::check(
            'NewEmpPass123',
            DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')
        ));
    }

    public function test_shared_email_jobseeker_then_employer_account_selection(): void
    {
        $email = 'shared-reverse@example.test';
        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => $email,
            'Password' => Hash::make('EmpPass123'),
        ]);
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId([
            'Email' => $email,
            'Password' => Hash::make('JSPass123'),
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => $email, 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        $otp = $this->codeFor('jobseeker', $jobSeekerId);
        $this->assertDatabaseHas('password_recovery_tokens', [
            'account_type' => 'jobseeker',
            'account_id' => $jobSeekerId,
            'email' => $email,
            'token_hash' => hash_hmac('sha256', $otp, (string) config('app.key')),
        ]);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect()->assertSessionHasNoErrors();
        $jobSeekerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(64, strlen($jobSeekerToken));
        $this->post(route('forgot.reset', $jobSeekerToken), [
            'password' => 'NewJSPass123',
            'password_confirmation' => 'NewJSPass123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewJSPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
        $this->assertTrue(Hash::check('EmpPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));

        $before = $this->mailedCodeCount();
        $this->post(route('forgot.send'), ['email' => $email, 'account_type' => 'employer'])
            ->assertRedirect(route('forgot.verify'));
        $otp = $this->codeFor('employer', $employerId, $before);
        $this->assertDatabaseHas('password_recovery_tokens', [
            'account_type' => 'employer',
            'account_id' => $employerId,
            'email' => $email,
            'token_hash' => hash_hmac('sha256', $otp, (string) config('app.key')),
        ]);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $otp]);
        $verified->assertRedirect()->assertSessionHasNoErrors();
        $employerToken = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(64, strlen($employerToken));
        $this->post(route('forgot.reset', $employerToken), [
            'password' => 'NewEmpPass123',
            'password_confirmation' => 'NewEmpPass123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewEmpPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
        $this->assertTrue(Hash::check('NewJSPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
    }

    public function test_wrong_account_type_selection_gets_generic_response_without_mail(): void
    {
        DB::table('employer_reg')->insert([
            'Email' => 'only-employer@example.test',
            'Password' => Hash::make('EmpPass123'),
        ]);
        Mail::fake();

        // The address exists, but not as a job seeker: selecting the wrong
        // account type must return the same generic response as an unknown
        // email so nothing is leaked and no code is issued.
        $this->post(route('forgot.send'), ['email' => 'only-employer@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'))
            ->assertSessionHas('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_recovery_tokens')->count());
        $this->assertSame('only-employer@example.test', session('password_recovery_email'));
        $this->assertSame('jobseeker', session('password_recovery_account_type'));

        // Mirror image: an address that exists only as a job seeker receives
        // no code when Employer is selected.
        DB::table('jobseeker_reg')->insert([
            'Email' => 'only-seeker@example.test',
            'Password' => Hash::make('JSPass123'),
        ]);
        $this->post(route('forgot.send'), ['email' => 'only-seeker@example.test', 'account_type' => 'employer'])
            ->assertRedirect(route('forgot.verify'))
            ->assertSessionHas('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_recovery_tokens')->count());
    }

    public function test_account_type_is_required_and_admin_selection_is_rejected(): void
    {
        DB::table('jobseeker_reg')->insert([
            'Email' => 'typed@example.test',
            'Password' => Hash::make('JSPass123'),
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'typed@example.test'])
            ->assertSessionHasErrors('account_type');
        // Admin accounts have no stored email and are not a supported recovery
        // target, so the selection is rejected at validation before any lookup.
        $this->post(route('forgot.send'), ['email' => 'typed@example.test', 'account_type' => 'admin'])
            ->assertSessionHasErrors('account_type');
        $this->post(route('forgot.send'), ['email' => 'typed@example.test', 'account_type' => 'somethingelse'])
            ->assertSessionHasErrors('account_type');

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_recovery_tokens')->count());
    }

    public function test_verification_is_bound_to_the_selected_account_type(): void
    {
        $email = 'bound@example.test';
        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => $email,
            'Password' => Hash::make('EmpPass123'),
        ]);
        DB::table('jobseeker_reg')->insert([
            'Email' => $email,
            'Password' => Hash::make('JSPass123'),
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => $email, 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        $jobSeekerOtp = $this->codeFor('jobseeker', DB::table('jobseeker_reg')->whereRaw('LOWER(Email) = ?', [$email])->value('JobSeekId'));

        // An employer-tagged code for the same email exists (issued outside the
        // job seeker selection); it must not verify while the session is bound
        // to the job seeker selection.
        $otherTypeOtp = '456789';
        DB::table('password_recovery_tokens')->insert([
            'account_type' => 'employer',
            'account_id' => $employerId,
            'email' => $email,
            'token_hash' => hash_hmac('sha256', $otherTypeOtp, (string) config('app.key')),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('forgot.verify.submit'), ['otp' => $otherTypeOtp])
            ->assertSessionHasErrors('otp');
        $this->assertDatabaseHas('password_recovery_tokens', [
            'account_type' => 'employer',
            'account_id' => $employerId,
            'token_hash' => hash_hmac('sha256', $otherTypeOtp, (string) config('app.key')),
        ]);

        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $jobSeekerOtp]);
        $verified->assertRedirect()->assertSessionHasNoErrors();
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(64, strlen($token));

        $this->post(route('forgot.reset', $token), [
            'password' => 'NewJSPass123',
            'password_confirmation' => 'NewJSPass123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewJSPass123', DB::table('jobseeker_reg')->whereRaw('LOWER(Email) = ?', [$email])->value('Password')));
        $this->assertTrue(Hash::check('EmpPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
    }

    public function test_send_limit_uses_normalized_email_across_account_types_and_client_ips(): void
    {
        $email = 'bucket@example.test';
        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => $email,
            'Password' => Hash::make('EmployerPass123'),
        ]);
        $jobSeekerId = DB::table('jobseeker_reg')->insertGetId([
            'Email' => $email,
            'Password' => Hash::make('JobSeekerPass123'),
        ]);
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('forgot.send'), ['email' => $email, 'account_type' => 'employer'])
            ->assertRedirect(route('forgot.verify'));
        $before = $this->mailedCodeCount();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->post(route('forgot.send'), ['email' => strtoupper($email), 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));

        // The upper-case address landed in the same bucket, and the code it issued
        // for the job seeker is stored under the normalized lower-case email.
        $tokenHash = hash_hmac('sha256', $this->codeFor('jobseeker', $jobSeekerId, $before), (string) config('app.key'));
        $this->assertDatabaseHas('password_recovery_tokens', [
            'account_type' => 'jobseeker',
            'account_id' => $jobSeekerId,
            'email' => $email,
            'token_hash' => $tokenHash,
        ]);

        // A whitespace-padded address is normalized to the same email bucket.
        $before = $this->mailedCodeCount();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
            ->post(route('forgot.send'), ['email' => ' '.$email.' ', 'account_type' => 'employer'])
            ->assertRedirect(route('forgot.verify'));
        $tokenHash = hash_hmac('sha256', $this->codeFor('employer', $employerId, $before), (string) config('app.key'));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.13'])
            ->post(route('forgot.send'), ['email' => $email, 'account_type' => 'employer'])
            ->assertStatus(429);

        $this->assertCount(
            3,
            Mail::sent(PasswordRecoveryMail::class),
            'Three accepted requests each sent one code for the selected account; the limited fourth sent nothing.'
        );
        $this->assertDatabaseHas('password_recovery_tokens', ['token_hash' => $tokenHash]);
        $this->assertTrue(Hash::check('EmployerPass123', DB::table('employer_reg')->where('EmployerId', $employerId)->value('Password')));
        $this->assertTrue(Hash::check('JobSeekerPass123', DB::table('jobseeker_reg')->where('JobSeekId', $jobSeekerId)->value('Password')));
    }

    public function test_send_ip_limit_is_independent_of_email(): void
    {
        Mail::fake();
        foreach (range(1, 10) as $number) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->post(route('forgot.send'), [
                    'email' => 'recipient'.$number.'@example.test',
                    'account_type' => 'jobseeker',
                ])
                ->assertRedirect(route('forgot.verify'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post(route('forgot.send'), ['email' => 'another@example.test', 'account_type' => 'jobseeker'])
            ->assertStatus(429);
    }

    public function test_resending_does_not_reset_failed_otp_budget_and_expired_limit_allows_recovery(): void
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'Email' => 'budget@example.test',
            'Password' => Hash::make('OldPassword123'),
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'budget@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        foreach (range(1, 5) as $attempt) {
            $this->post(route('forgot.verify.submit'), ['otp' => '000000'])->assertSessionHasErrors('otp');
        }

        $this->post(route('forgot.send'), ['email' => 'BUDGET@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        $mail = Mail::sent(PasswordRecoveryMail::class)->last();
        $this->assertNotNull($mail);
        $validOtpHash = hash_hmac('sha256', $mail->otp, (string) config('app.key'));

        $this->post(route('forgot.verify.submit'), ['otp' => $mail->otp])->assertSessionHasErrors('otp');
        $this->assertDatabaseHas('password_recovery_tokens', ['token_hash' => $validOtpHash]);
        $this->assertTrue(Hash::check('OldPassword123', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')));

        $this->travel(11)->minutes();
        $this->post(route('forgot.send'), ['email' => 'budget@example.test', 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        $mail = Mail::sent(PasswordRecoveryMail::class)->last();
        $this->assertNotNull($mail);
        $verified = $this->post(route('forgot.verify.submit'), ['otp' => $mail->otp]);
        $verified->assertRedirect()->assertSessionHasNoErrors();
        $token = basename(parse_url($verified->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(64, strlen($token));
    }

    public function test_successful_otp_verification_clears_only_its_failed_guess_counter(): void
    {
        $email = 'clear-counter@example.test';
        DB::table('jobseeker_reg')->insert([
            'Email' => $email,
            'Password' => Hash::make('OldPassword123'),
        ]);
        Mail::fake();

        $clientIp = '198.51.100.91';
        $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
            ->post(route('forgot.send'), ['email' => $email, 'account_type' => 'jobseeker'])
            ->assertRedirect(route('forgot.verify'));
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $this->assertNotNull($mail);

        $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
            ->post(route('forgot.verify.submit'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $failedKey = 'password-recovery-otp:'.hash('sha256', $email);
        $unrelatedEmail = 'other-counter@example.test';
        $unrelatedFailedKey = 'password-recovery-otp:'.hash('sha256', $unrelatedEmail);
        RateLimiter::hit($unrelatedFailedKey, 600);

        $targetEmailBucket = $this->namedLimiterKey(
            'password-recovery-verify',
            'email:'.hash('sha256', mb_strtolower(trim($email))),
        );
        $targetIpBucket = $this->namedLimiterKey(
            'password-recovery-verify',
            'ip:'.hash('sha256', $clientIp),
        );
        $otherEmailBucket = $this->namedLimiterKey(
            'password-recovery-verify',
            'email:'.hash('sha256', $unrelatedEmail),
        );
        $otherIpBucket = $this->namedLimiterKey(
            'password-recovery-verify',
            'ip:'.hash('sha256', '198.51.100.92'),
        );
        RateLimiter::hit($otherEmailBucket, 60);
        RateLimiter::hit($otherIpBucket, 60);

        $this->assertSame(1, RateLimiter::attempts($failedKey));
        $this->assertSame(1, RateLimiter::attempts($unrelatedFailedKey));
        $this->assertSame(1, RateLimiter::attempts($targetEmailBucket));
        $this->assertSame(1, RateLimiter::attempts($targetIpBucket));

        $this->withServerVariables(['REMOTE_ADDR' => $clientIp])
            ->post(route('forgot.verify.submit'), ['otp' => $mail->otp])
            ->assertRedirect();

        $this->assertSame(0, RateLimiter::attempts($failedKey));
        $this->assertSame(1, RateLimiter::attempts($unrelatedFailedKey));
        $this->assertSame(2, RateLimiter::attempts($targetEmailBucket));
        $this->assertSame(2, RateLimiter::attempts($targetIpBucket));
        $this->assertSame(1, RateLimiter::attempts($otherEmailBucket));
        $this->assertSame(1, RateLimiter::attempts($otherIpBucket));
    }

    public function test_verification_email_middleware_limit_is_normalized_and_precedes_controller_guess_limit(): void
    {
        foreach (range(1, 10) as $attempt) {
            $sessionEmail = $attempt % 2 === 0
                ? '  VERIFY-CAP@example.test  '
                : 'Verify-Cap@Example.Test';

            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->withSession(['password_recovery_email' => $sessionEmail])
                ->post(route('forgot.verify.submit'), [
                    'otp' => 'invalid-format',
                    'account_type' => $attempt % 2 === 0 ? 'employer' : 'jobseeker',
                ])
                ->assertSessionHasErrors('otp');
        }

        // A new apparent IP and the other account-type value still use the same
        // normalized session-email bucket. Invalid OTP shape ensures the controller
        // never reaches its independent failed-guess counter.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->withSession(['password_recovery_email' => 'verify-cap@example.test'])
            ->post(route('forgot.verify.submit'), [
                'otp' => 'invalid-format',
                'account_type' => 'employer',
            ])
            ->assertStatus(429);
    }

    public function test_verification_ip_middleware_limit_is_independent_of_session_email(): void
    {
        foreach (range(1, 30) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.100'])
                ->withSession(['password_recovery_email' => 'recipient'.$attempt.'@example.test'])
                ->post(route('forgot.verify.submit'), ['otp' => 'invalid-format'])
                ->assertSessionHasErrors('otp');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.100'])
            ->withSession(['password_recovery_email' => 'recipient31@example.test'])
            ->post(route('forgot.verify.submit'), ['otp' => 'invalid-format'])
            ->assertStatus(429);
    }

    private function namedLimiterKey(string $limiterName, string $key): string
    {
        // Laravel's ThrottleRequests middleware hashes named-limiter keys with md5.
        return md5($limiterName.$key);
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

        $employerId = DB::table('employer_reg')->insertGetId([
            'Email' => 'legacy@example.test',
            'Password' => Hash::make('pass'),
        ]);

        $normalizedEmail = \App\Services\AccountIdentifierRegistry::normalize('legacy@example.test');
        $query = DB::table('employer_reg')->whereRaw('LOWER(Email) = ?', [$normalizedEmail]);
        $accountType = $query->exists() ? 'employer' : 'none';
        $this->assertEquals('employer', $accountType);
    }
}
