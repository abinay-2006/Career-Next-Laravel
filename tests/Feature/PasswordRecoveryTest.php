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
        $id = DB::table('jobseeker_reg')->insertGetId(['Email' => 'seeker@example.test', 'Password' => $old]);
        Mail::fake();

        $this->post(route('forgot.send'), ['email' => 'seeker@example.test'])->assertRedirect();
        Mail::assertSent(PasswordRecoveryMail::class);
        $mail = Mail::sent(PasswordRecoveryMail::class)->first();
        $token = basename(parse_url($mail->recoveryUrl, PHP_URL_PATH));

        $this->post(route('forgot.reset', $token), ['password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')));
        $this->assertFalse(Hash::check('OldPassword123', DB::table('jobseeker_reg')->where('JobSeekId', $id)->value('Password')));
        $this->post(route('forgot.reset', $token), ['password' => 'AnotherPassword123', 'password_confirmation' => 'AnotherPassword123'])
            ->assertRedirect(route('forgot'));
    }

    public function test_unknown_email_gets_same_generic_recovery_response_without_mail(): void
    {
        Mail::fake();
        $this->from(route('forgot'))->post(route('forgot.send'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('forgot'))->assertSessionHas('status', 'If an eligible account matches that email, a password recovery link will arrive shortly.');
        Mail::assertNothingSent();
    }
}
