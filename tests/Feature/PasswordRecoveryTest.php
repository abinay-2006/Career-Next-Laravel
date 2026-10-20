<?php

namespace Tests\Feature;

use App\Mail\PasswordRecoveryMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId'); $table->string('Email'); $table->string('Password');
            $table->string('UserName')->nullable(); $table->string('Status')->nullable();
        });
        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId'); $table->string('Email'); $table->string('Password');
        });
        Schema::create('password_recovery_tokens', function (Blueprint $table): void {
            $table->id(); $table->string('account_type'); $table->unsignedBigInteger('account_id');
            $table->string('email'); $table->string('token_hash')->unique(); $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function test_eligible_email_gets_single_use_recovery_and_can_log_in_with_new_password(): void
    {
        $old = Hash::make('OldPassword123');
        $id = DB::table('jobseeker_reg')->insertGetId([
            'Email' => 'seeker@example.test', 'Password' => $old, 'UserName' => 'seeker', 'Status' => 'confirmed',
        ]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'seeker@example.test'])->assertRedirect();
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
        $this->from(route('forgot'))->post(route('forgot.send'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('forgot'))->assertSessionHas('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
        Mail::assertNothingSent();

        DB::table('jobseeker_reg')->insert(['Email' => 'valid@example.test', 'Password' => Hash::make('OldPassword123')]);
        $this->from(route('forgot'))->post(route('forgot.send'), ['email' => 'valid@example.test'])
            ->assertRedirect(route('forgot.verify'));
        Mail::assertSent(PasswordRecoveryMail::class);
    }
}
