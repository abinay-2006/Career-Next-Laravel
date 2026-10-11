<?php

namespace Tests\Feature;

use App\Mail\RegistrationOtpMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table) {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName'); $table->string('Address')->nullable(); $table->string('City');
            $table->string('Email'); $table->bigInteger('Mobile'); $table->string('Qualification');
            $table->string('Gender'); $table->date('BirthDate'); $table->string('Resume')->nullable();
            $table->string('Status'); $table->string('UserName'); $table->string('Password');
            $table->string('Question')->nullable(); $table->string('Answer')->nullable(); $table->string('Photo')->nullable();
        });
        Schema::create('employer_reg', function (Blueprint $table) {
            $table->increments('EmployerId'); $table->string('CompanyName'); $table->string('ContactPerson')->nullable();
            $table->text('Address')->nullable(); $table->string('City')->nullable(); $table->string('Email');
            $table->string('Mobile')->nullable(); $table->string('Area_Work')->nullable(); $table->string('Status');
            $table->string('UserName'); $table->string('Password'); $table->string('Question')->nullable();
            $table->string('Answer')->nullable(); $table->string('Photo')->nullable();
        });
    }

    private function jobSeekerRegistration(): array
    {
        return [
            'account_type' => 'JobSeeker', 'name' => 'Test Seeker', 'username' => 'test.seeker',
            'email' => 'seeker@example.test', 'mobile' => '9876543210', 'password' => Hash::make('secret-pass'),
            'profile' => ['address' => '1 Test Road', 'city' => 'Pune', 'gender' => 'Other', 'birth_date' => '1995-01-01'],
            'professional' => ['qualification' => 'Graduate'],
        ];
    }

    private function employerRegistration(): array
    {
        return [
            'account_type' => 'Employer', 'name' => 'Example Company', 'username' => 'example.hr',
            'email' => 'hr@example.test', 'mobile' => '9876543210', 'password' => Hash::make('secret-pass'),
            'profile' => ['contact_person' => 'HR Contact', 'address' => '1 Test Road', 'city' => 'Pune', 'area_work' => 'Technology'],
            'professional' => ['completed' => true],
        ];
    }

    public function test_job_seeker_review_sends_otp_and_redirects_to_verify_with_registration_session(): void
    {
        Mail::fake();
        $registration = $this->jobSeekerRegistration();

        $this->withSession(['registration' => $registration])
            ->post(route('register.review.store'), ['confirm_details' => '1'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('registration.account_type', 'JobSeeker')
            ->assertSessionHas('registration.email', 'seeker@example.test')
            ->assertSessionHas('registration.otp');

        Mail::assertSent(RegistrationOtpMail::class, fn ($mail) => $mail->hasTo('seeker@example.test'));
        $this->get(route('register.verify'))->assertOk()->assertSee('Verify your email');
    }

    public function test_employer_review_sends_otp_and_redirects_to_verify_with_registration_session(): void
    {
        Mail::fake();
        $registration = $this->employerRegistration();

        $this->withSession(['registration' => $registration])
            ->post(route('register.review.store'), ['confirm_details' => '1'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('registration.account_type', 'Employer')
            ->assertSessionHas('registration.email', 'hr@example.test')
            ->assertSessionHas('registration.otp');

        Mail::assertSent(RegistrationOtpMail::class, fn ($mail) => $mail->hasTo('hr@example.test'));
        $this->get(route('register.verify'))->assertOk()->assertSee('Verify your email');
    }

    public function test_review_confirmation_validation_failure_does_not_send_otp(): void
    {
        Mail::fake();

        $this->withSession(['registration' => $this->jobSeekerRegistration()])
            ->from(route('register.review'))
            ->post(route('register.review.store'))
            ->assertRedirect(route('register.review'))
            ->assertSessionHasErrors('confirm_details')
            ->assertSessionMissing('registration.otp');

        Mail::assertNothingSent();
    }

    public function test_mail_failure_returns_to_review_without_storing_an_unsent_otp(): void
    {
        Mail::shouldReceive('to')->once()->with('seeker@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->withSession(['registration' => $this->jobSeekerRegistration()])
            ->from(route('register.review'))
            ->post(route('register.review.store'), ['confirm_details' => '1'])
            ->assertRedirect(route('register.review'))
            ->assertSessionHas('error', 'We could not send your verification code. Please try again.')
            ->assertSessionMissing('registration.otp');
    }

    public function test_correct_otp_creates_pending_jobseeker_with_hashed_password_once(): void
    {
        $session = ['registration' => $this->jobSeekerRegistration() + [
            'otp' => '123456', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0,
        ]];

        $this->withSession($session)->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('jobseeker_reg')->count());
        $account = DB::table('jobseeker_reg')->first();
        $this->assertSame('Pending', $account->Status);
        $this->assertTrue(Hash::check('secret-pass', $account->Password));

        $this->post(route('register.verify.store'), ['otp' => '123456']);
        $this->assertSame(1, DB::table('jobseeker_reg')->count());
    }

    public function test_invalid_or_expired_otp_does_not_create_an_account(): void
    {
        $session = ['registration' => $this->jobSeekerRegistration() + [
            'otp' => '123456', 'otp_expires_at' => now()->subMinute(), 'otp_attempts' => 0,
        ]];

        $this->withSession($session)->from(route('register.verify'))->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors('otp');

        $this->assertSame(0, DB::table('jobseeker_reg')->count());
    }

    public function test_correct_otp_creates_pending_employer_with_hashed_password(): void
    {
        $this->withSession(['registration' => $this->employerRegistration() + [
            'otp' => '654321', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0,
        ]])->post(route('register.verify.store'), ['otp' => '654321'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('employer_reg')->count());
        $account = DB::table('employer_reg')->first();
        $this->assertSame('Pending', $account->Status);
        $this->assertTrue(Hash::check('secret-pass', $account->Password));
    }

    public function test_duplicate_email_is_rejected_after_otp_without_creating_another_account(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Existing', 'Email' => 'seeker@example.test', 'Status' => 'Pending',
            'UserName' => 'someone.else', 'Password' => Hash::make('secret-pass'),
        ]);

        $this->withSession([
            'registration' => $this->jobSeekerRegistration() + [
                'otp' => '123456', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0,
            ],
        ])->from(route('register.verify'))->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors('otp');

        $this->assertSame(0, DB::table('jobseeker_reg')->count());
    }
}
