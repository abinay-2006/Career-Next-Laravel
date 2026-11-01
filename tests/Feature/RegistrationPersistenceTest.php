<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        Schema::create('account_identifiers', function (Blueprint $table) {
            $table->id(); $table->string('identifier_type', 20); $table->string('normalized_value', 255);
            $table->string('account_type', 20); $table->unsignedBigInteger('account_id');
            $table->unique(['identifier_type', 'normalized_value']);
            $table->unique(['account_type', 'account_id', 'identifier_type']);
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
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);
        $registration = $this->jobSeekerRegistration();

        $this->withSession(['registration' => $registration])
            ->post(route('register.review.store'), ['confirm_details' => '1'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('registration.account_type', 'JobSeeker')
            ->assertSessionHas('registration.email', 'seeker@example.test')
            ->assertSessionHas('registration.otp_hash')
            ->assertSessionMissing('registration.otp');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.brevo.com/v3/smtp/email') && $request['to'][0]['email'] === 'seeker@example.test');
        $this->get(route('register.verify'))->assertOk()->assertSee('Verify your email');
    }

    public function test_employer_review_sends_otp_and_redirects_to_verify_with_registration_session(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);
        $registration = $this->employerRegistration();

        $this->withSession(['registration' => $registration])
            ->post(route('register.review.store'), ['confirm_details' => '1'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('registration.account_type', 'Employer')
            ->assertSessionHas('registration.email', 'hr@example.test')
            ->assertSessionHas('registration.otp_hash')
            ->assertSessionMissing('registration.otp');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.brevo.com/v3/smtp/email') && $request['to'][0]['email'] === 'hr@example.test');
        $this->get(route('register.verify'))->assertOk()->assertSee('Verify your email');
    }

    public function test_review_confirmation_validation_failure_does_not_send_otp(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);

        $this->withSession(['registration' => $this->jobSeekerRegistration()])
            ->from(route('register.review'))
            ->post(route('register.review.store'))
            ->assertRedirect(route('register.review'))
            ->assertSessionHasErrors('confirm_details')
            ->assertSessionMissing('registration.otp');

        Http::assertNothingSent();
    }

    public function test_mail_failure_returns_to_review_without_storing_an_unsent_otp(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'delivery failed'], 500)]);

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

    public function test_duplicate_username_is_rejected_across_account_types_case_insensitively(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Existing', 'Email' => 'other@example.test', 'Status' => 'Pending',
            'UserName' => 'TEST.SEEKER', 'Password' => Hash::make('secret-pass'),
        ]);
        $session = $this->jobSeekerRegistration() + ['otp' => '123456', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0];

        $this->withSession(['registration' => $session])->from(route('register.verify'))
            ->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('register.verify'))->assertSessionHasErrors();
        $this->assertSame(0, DB::table('jobseeker_reg')->count());
    }

    public function test_duplicate_email_is_rejected_across_account_types_case_insensitively(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Existing', 'Email' => 'SEEKER@EXAMPLE.TEST', 'Status' => 'Pending',
            'UserName' => 'different.user', 'Password' => Hash::make('secret-pass'),
        ]);
        $session = $this->jobSeekerRegistration() + ['otp' => '123456', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0];

        $this->withSession(['registration' => $session])->from(route('register.verify'))
            ->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('register.verify'))->assertSessionHasErrors('otp');
        $this->assertSame(0, DB::table('jobseeker_reg')->count());
    }

    public function test_partial_identifier_reservation_rolls_back_with_account_creation(): void
    {
        DB::table('account_identifiers')->insert([
            'identifier_type' => 'email', 'normalized_value' => 'seeker@example.test',
            'account_type' => 'employer', 'account_id' => 999,
        ]);
        $session = $this->jobSeekerRegistration() + ['otp' => '123456', 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0];

        $this->withSession(['registration' => $session])->from(route('register.verify'))
            ->post(route('register.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('register.verify'))->assertSessionHasErrors();

        $this->assertSame(0, DB::table('jobseeker_reg')->count());
        $this->assertSame(0, DB::table('account_identifiers')->where('normalized_value', 'test.seeker')->count());
    }

    public function test_registry_database_constraint_rejects_a_second_reservation(): void
    {
        DB::table('account_identifiers')->insert([
            'identifier_type' => 'username', 'normalized_value' => 'reserved-name', 'account_type' => 'employer', 'account_id' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('account_identifiers')->insert([
            'identifier_type' => 'username', 'normalized_value' => 'reserved-name', 'account_type' => 'jobseeker', 'account_id' => 2,
        ]);
    }

    public function test_registration_accepts_and_normalizes_an_email_style_username(): void
    {
        $this->withSession(['registration' => ['account_type' => 'JobSeeker']])
            ->post(route('register.details.store'), [
                'name' => 'Email Username Seeker', 'username' => 'ABC@Example.test', 'email' => 'seeker@example.test',
                'mobile' => '9876543210', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect(route('register.profile'))
            ->assertSessionHas('registration.username', 'abc@example.test');
    }

    public function test_registration_rejects_username_matching_an_existing_email(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Owner', 'Email' => 'owner@example.test', 'Status' => 'Pending',
            'UserName' => 'taken@example.test', 'Password' => Hash::make('secret-pass'),
        ]);

        $this->withSession(['registration' => ['account_type' => 'JobSeeker']])
            ->post(route('register.details.store'), [
                'name' => 'Candidate', 'username' => '  TAKEN@example.test ', 'email' => 'new@example.test',
                'mobile' => '9876543210', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect()->assertSessionHasErrors('username');

        $this->assertSame('taken@example.test', DB::table('employer_reg')->value('UserName'));
        $this->assertTrue(\App\Services\AccountIdentifierRegistry::existsInAccounts(' TAKEN@EXAMPLE.TEST '));
    }

    public function test_registration_rejects_email_matching_an_existing_username(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Owner', 'Email' => 'owner@example.test', 'Status' => 'Pending',
            'UserName' => 'contact@example.test', 'Password' => Hash::make('secret-pass'),
        ]);

        $this->withSession(['registration' => ['account_type' => 'JobSeeker']])
            ->post(route('register.details.store'), [
                'name' => 'Candidate', 'username' => 'new-candidate', 'email' => 'CONTACT@example.test',
                'mobile' => '9876543210', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect()->assertSessionHasErrors('email');

        $this->assertSame('contact@example.test', DB::table('employer_reg')->value('UserName'));
    }

    public function test_same_account_can_use_one_email_style_value_for_username_and_email(): void
    {
        $value = 'same@example.test';
        $this->withSession(['registration' => ['account_type' => 'JobSeeker']])
            ->post(route('register.details.store'), [
                'name' => 'Candidate', 'username' => strtoupper($value), 'email' => $value,
                'mobile' => '9876543210', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
            ])
            ->assertRedirect(route('register.profile'))
            ->assertSessionHas('registration.username', $value)
            ->assertSessionHas('registration.email', $value);
    }

    public function test_registry_allows_one_account_to_own_the_same_value_in_both_fields(): void
    {
        $value = 'same-owner@example.test';
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Same Owner', 'Address' => 'Address', 'City' => 'City', 'Email' => $value,
            'Mobile' => 9876543210, 'Qualification' => 'Degree', 'Gender' => 'Other', 'BirthDate' => '1990-01-01',
            'Status' => 'Pending', 'UserName' => $value, 'Password' => Hash::make('secret-pass'),
        ], 'JobSeekId');

        app(\App\Services\AccountIdentifierRegistry::class)->sync('jobseeker', $id, $value, $value);

        $this->assertSame(2, DB::table('account_identifiers')
            ->where('account_type', 'jobseeker')->where('account_id', $id)
            ->where('normalized_value', $value)->count());
    }

    public function test_collision_report_is_read_only_and_does_not_disclose_normalized_value(): void
    {
        DB::table('employer_reg')->insert([
            'CompanyName' => 'Owner', 'Email' => 'first@example.test', 'Status' => 'Pending',
            'UserName' => 'shared@example.test', 'Password' => Hash::make('secret-pass'),
        ]);
        DB::table('jobseeker_reg')->insert([
            'JobSeekerName' => 'Other', 'Address' => 'Address', 'City' => 'City', 'Email' => 'shared@example.test',
            'Mobile' => 9876543210, 'Qualification' => 'Degree', 'Gender' => 'Other', 'BirthDate' => '1990-01-01',
            'Status' => 'Pending', 'UserName' => 'other', 'Password' => Hash::make('secret-pass'),
        ]);

        $before = [DB::table('employer_reg')->first(), DB::table('jobseeker_reg')->first()];
        $report = app(\App\Services\AccountIdentifierRegistry::class)->reportCrossFieldCollisions();

        $this->assertCount(1, $report);
        $this->assertSame(hash('sha256', 'shared@example.test'), $report[0]['identifier_hash']);
        $this->assertEquals($before[0], DB::table('employer_reg')->first());
        $this->assertEquals($before[1], DB::table('jobseeker_reg')->first());
        $this->assertStringNotContainsString('shared@example.test', json_encode($report));
    }

    public function test_identifier_changes_replace_old_reservations_and_deletion_releases_them(): void
    {
        $id = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Registry Test', 'City' => 'Pune', 'Email' => 'old@example.test', 'Mobile' => 9876543210,
            'Qualification' => 'Graduate', 'Gender' => 'Other', 'BirthDate' => '1995-01-01', 'Status' => 'Pending',
            'UserName' => 'old-name', 'Password' => Hash::make('secret-pass'),
        ], 'JobSeekId');
        $registry = app(\App\Services\AccountIdentifierRegistry::class);
        $registry->sync('jobseeker', $id, 'old-name', 'old@example.test');

        DB::table('jobseeker_reg')->where('JobSeekId', $id)->update(['UserName' => 'new-name', 'Email' => 'new@example.test']);
        $registry->sync('jobseeker', $id, 'new-name', 'new@example.test');
        $this->assertDatabaseMissing('account_identifiers', ['normalized_value' => 'old-name']);
        $this->assertDatabaseHas('account_identifiers', ['normalized_value' => 'new-name']);

        DB::table('jobseeker_reg')->where('JobSeekId', $id)->delete();
        $registry->release('jobseeker', $id);
        $this->assertDatabaseMissing('account_identifiers', ['account_type' => 'jobseeker', 'account_id' => $id]);
    }
}
