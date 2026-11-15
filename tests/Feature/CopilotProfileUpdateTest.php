<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProfileUpdateService;
use App\Services\CopilotToolDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Copilot profile updates: staged by the model, applied by Laravel only after explicit confirmation.
 * The gateway is faked; every persistence assertion reads the database directly.
 */
class CopilotProfileUpdateTest extends TestCase
{
    private const ENDPOINT = 'http://127.0.0.1:31415/v1/chat/completions';
    private const CONFIRM = 'CONFIRM PROFILE UPDATE';

    /** Queued gateway responses; a single fake reads from it so tests can enqueue more at any time. */
    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.freellmapi.enabled' => true,
            'services.freellmapi.base_url' => 'http://127.0.0.1:31415/v1',
            'services.freellmapi.key' => 'test-key',
            'services.freellmapi.models' => ['primary' => 'gemini-3.5-flash', 'secondary' => null, 'tertiary' => null],
        ]);
        Http::preventStrayRequests();
        Http::fake([self::ENDPOINT => function () {
            $next = array_shift($this->responses);
            if ($next === null) {
                $this->fail('Unexpected gateway request.');
            }
            return Http::response($next);
        }]);
        Schema::create('jobseeker_reg', function (Blueprint $t): void {
            $t->increments('JobSeekId'); $t->string('JobSeekerName'); $t->string('Status'); $t->string('Qualification')->nullable();
            $t->string('City')->nullable(); $t->string('Password')->nullable(); $t->text('Address')->nullable(); $t->string('Email')->nullable();
            $t->string('Mobile')->nullable(); $t->string('Gender')->nullable(); $t->date('BirthDate')->nullable(); $t->string('UserName')->nullable();
            $t->string('Photo')->nullable(); $t->string('Resume')->nullable(); $t->string('Question')->nullable(); $t->string('Answer')->nullable();
        });
        Schema::create('employer_reg', function (Blueprint $t): void {
            $t->increments('EmployerId'); $t->string('CompanyName'); $t->string('Status'); $t->string('Password')->nullable(); $t->string('City')->nullable();
            $t->string('Area_Work')->nullable(); $t->string('ContactPerson')->nullable(); $t->text('Address')->nullable(); $t->string('Mobile')->nullable();
            $t->string('Email')->nullable(); $t->string('UserName')->nullable(); $t->string('Photo')->nullable(); $t->string('Question')->nullable(); $t->string('Answer')->nullable();
        });
        Schema::create('user_master', function (Blueprint $t): void { $t->increments('UserId'); $t->string('UserName'); $t->string('Password'); });
        Schema::create('account_identifiers', function (Blueprint $t): void {
            $t->id(); $t->string('identifier_type', 20); $t->string('normalized_value', 255); $t->string('account_type', 20); $t->unsignedBigInteger('account_id');
            $t->unique(['identifier_type', 'normalized_value']); $t->unique(['account_type', 'account_id', 'identifier_type']);
        });
    }

    private function seeker(array $attributes = []): JobSeeker
    {
        return JobSeeker::query()->create($attributes + [
            'JobSeekerName' => 'Asha Rao', 'Status' => 'Approved', 'City' => 'Mumbai', 'Email' => 'asha@example.test',
            'UserName' => 'asha', 'Password' => Hash::make('secret-pass'), 'Qualification' => 'B.Sc',
        ]);
    }

    private function employer(array $attributes = []): Employer
    {
        return Employer::query()->create($attributes + [
            'CompanyName' => 'Acme Hiring', 'Status' => 'Approved', 'City' => 'Pune', 'Area_Work' => 'Manufacturing',
            'Email' => 'hr@acme.test', 'UserName' => 'acmehr', 'Password' => Hash::make('secret-pass'),
        ]);
    }

    private static function toolCall(array $arguments, string $id = 'call-profile'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id' => $id, 'type' => 'function', 'function' => ['name' => 'request_profile_update', 'arguments' => json_encode($arguments)],
        ]]], 'finish_reason' => 'tool_calls']]];
    }

    private static function answer(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];
    }

    /** Model proposes the change, then (wrongly) claims success; Laravel's wording must win. */
    private function fakeProposal(array $arguments): void
    {
        array_push($this->responses, self::toolCall($arguments), self::answer('Done! I have updated your profile.'));
    }

    private function say(string $role, string $message): TestResponse
    {
        return $this->postJson(route($role.'.copilot.message'), ['message' => $message]);
    }

    private function row(string $table, string $key, int $id): object
    {
        return DB::table($table)->where($key, $id)->first();
    }

    // 1 + 8 + 9
    public function test_job_seeker_update_is_shown_then_applied_only_after_confirmation(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['city' => 'Bengaluru', 'mobile' => '9876543210']);

        $reply = $this->say('jobseeker', 'Can you update my city to Bengaluru and my mobile to 9876543210?')->assertOk()->json('reply');

        $this->assertStringContainsString('City: "Mumbai" → "Bengaluru"', $reply);
        $this->assertStringContainsString('Mobile number: (empty) → "9876543210"', $reply);
        $this->assertStringContainsString('reply exactly: '.self::CONFIRM, $reply);
        $this->assertStringNotContainsString('Done!', $reply); // the model cannot claim success
        $this->assertSame('Mumbai', $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->City); // nothing written yet

        $this->say('jobseeker', self::CONFIRM)->assertOk()->assertJsonPath('reply', 'Profile updated: City, Mobile number.');

        $row = $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId);
        $this->assertSame('Bengaluru', $row->City);
        $this->assertSame('9876543210', $row->Mobile);
        $this->assertSame('Asha Rao', $row->JobSeekerName); // untouched fields stay
        Http::assertSentCount(2); // the confirmation never went to the model
    }

    public function test_job_seeker_can_update_name_qualification_gender_and_birth_date(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['full_name' => 'Asha R. Rao', 'qualification' => 'M.Sc Data Science', 'gender' => 'female', 'birth_date' => '1999-04-12', 'address' => '12 Lake Road']);

        $this->say('jobseeker', 'Update my details')->assertOk();
        $this->say('jobseeker', 'confirm profile update')->assertOk()->assertJsonPath('reply', fn (string $r) => str_starts_with($r, 'Profile updated:'));

        $row = $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId);
        $this->assertSame('Asha R. Rao', $row->JobSeekerName);
        $this->assertSame('M.Sc Data Science', $row->Qualification);
        $this->assertSame('Female', $row->Gender); // normalised to the profile page's option
        $this->assertSame('1999-04-12', substr((string) $row->BirthDate, 0, 10));
        $this->assertSame('12 Lake Road', $row->Address);
    }

    // 2
    public function test_employer_updates_company_fields_after_confirmation(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer, 'employer');
        $this->fakeProposal(['company_name' => 'Acme Talent Pvt Ltd', 'business_area' => 'Information Technology', 'contact_person' => 'R. Mehta']);

        $reply = $this->say('employer', 'Please update our company profile')->assertOk()->json('reply');
        $this->assertStringContainsString('Company name: "Acme Hiring" → "Acme Talent Pvt Ltd"', $reply);
        $this->assertSame('Acme Hiring', $this->row('employer_reg', 'EmployerId', $employer->EmployerId)->CompanyName);

        $this->say('employer', self::CONFIRM)->assertOk()->assertJsonPath('reply', 'Profile updated: Company name, Business area, Contact person.');

        $row = $this->row('employer_reg', 'EmployerId', $employer->EmployerId);
        $this->assertSame('Acme Talent Pvt Ltd', $row->CompanyName);
        $this->assertSame('Information Technology', $row->Area_Work);
        $this->assertSame('R. Mehta', $row->ContactPerson);
        $this->assertSame('Approved', $row->Status);
    }

    // 3
    public function test_admin_has_no_profile_update_capability(): void
    {
        DB::table('user_master')->insert(['UserName' => 'admin', 'Password' => 'hash']);
        $admin = Admin::query()->firstOrFail();
        $seeker = $this->seeker();
        $this->actingAs($admin, 'admin');

        $this->assertNotContains('request_profile_update', app(CopilotCapabilityRegistry::class)->names('admin'));
        $this->assertSame(['error' => 'This function is not available for your account.'],
            app(CopilotToolDispatcher::class)->dispatch('admin', $admin, 'request_profile_update', ['city' => 'X']));

        // Even if a model proposes it for an admin, the tool is not dispatched and nothing changes.
        array_push($this->responses, self::toolCall(['city' => 'Nowhere']), self::answer('That is not something I can change.'));
        $this->say('admin', 'Change that job seeker city to Nowhere')->assertOk()->assertJsonPath('reply', 'That is not something I can change.');
        $this->assertSame('Mumbai', $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->City);
    }

    // 4
    public function test_wrong_role_or_unauthenticated_actor_cannot_update(): void
    {
        $seeker = $this->seeker();
        $employer = $this->employer();
        $dispatcher = app(CopilotToolDispatcher::class);

        // Employer guard with a Job Seeker actor (or no matching session) is refused.
        Auth::guard('employer')->login($employer);
        $this->assertSame(['error' => 'This function is not available for your account.'], $dispatcher->dispatch('employer', $seeker, 'request_profile_update', ['city' => 'X']));
        Auth::guard('employer')->logout();
        $this->assertSame(['error' => 'This function is not available for your account.'], $dispatcher->dispatch('jobseeker', $seeker, 'request_profile_update', ['city' => 'X']));

        // An employer cannot use Job Seeker fields.
        $this->actingAs($employer, 'employer');
        $this->fakeProposal(['full_name' => 'Hijack', 'qualification' => 'PhD']);
        $reply = $this->say('employer', 'Change my name')->assertOk()->json('reply');
        $this->assertStringContainsString('cannot be changed through Copilot: full_name, qualification', $reply);
        $this->say('employer', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');
        $this->assertSame('Acme Hiring', $this->row('employer_reg', 'EmployerId', $employer->EmployerId)->CompanyName);
    }

    // 5
    public function test_user_cannot_update_another_users_profile(): void
    {
        $victim = $this->seeker(['JobSeekerName' => 'Victim', 'City' => 'Chennai', 'Email' => 'v@example.test', 'UserName' => 'victim']);
        $attacker = $this->seeker(['JobSeekerName' => 'Attacker', 'Email' => 'a@example.test', 'UserName' => 'attacker']);

        // A model-supplied target ID is not an accepted field.
        $this->actingAs($attacker, 'jobseeker');
        $this->fakeProposal(['account_id' => $victim->JobSeekId, 'city' => 'Hacked']);
        $this->assertStringContainsString('cannot be changed through Copilot: account_id', $this->say('jobseeker', 'update user '.$victim->JobSeekId)->json('reply'));

        // The dispatcher refuses an actor that is not the authenticated user.
        $this->assertSame(['error' => 'This function is not available for your account.'],
            app(CopilotToolDispatcher::class)->dispatch('jobseeker', $victim, 'request_profile_update', ['city' => 'Hacked']));

        // A pending change staged by the victim cannot be confirmed by someone else in that session.
        Auth::guard('jobseeker')->logout();
        $this->actingAs($victim, 'jobseeker');
        $this->fakeProposal(['city' => 'Madurai']);
        $this->say('jobseeker', 'Change my city to Madurai')->assertOk();
        Auth::guard('jobseeker')->logout();
        $this->actingAs($attacker, 'jobseeker');
        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');

        $this->assertSame('Chennai', $this->row('jobseeker_reg', 'JobSeekId', $victim->JobSeekId)->City);
        $this->assertSame('Mumbai', $this->row('jobseeker_reg', 'JobSeekId', $attacker->JobSeekId)->City);
    }

    // 6
    public function test_protected_fields_are_rejected_and_nothing_is_staged(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['city' => 'Goa', 'email' => 'new@example.test', 'password' => 'hunter22', 'Status' => 'Approved', 'user_name' => 'boss', 'Photo' => 'x.png']);

        $reply = $this->say('jobseeker', 'Update everything')->assertOk()->json('reply');
        $this->assertStringContainsString('cannot be changed through Copilot: email, password, Status, user_name, Photo', $reply);
        $this->assertStringContainsString('No change was made.', $reply);
        $this->assertStringNotContainsString('hunter22', $reply);

        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');
        $row = $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId);
        $this->assertSame(['Mumbai', 'asha@example.test', 'asha', 'Approved'], [$row->City, $row->Email, $row->UserName, $row->Status]);
        $this->assertTrue(Hash::check('secret-pass', $row->Password));
    }

    // 7
    public function test_invalid_values_are_rejected_with_clear_messages(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['mobile' => str_repeat('9', 25), 'birth_date' => 'not a date', 'gender' => 'Robot', 'full_name' => '   ']);

        $reply = $this->say('jobseeker', 'Update my profile')->assertOk()->json('reply');
        $this->assertStringContainsString("I couldn't prepare that profile update:", $reply);
        $this->assertStringContainsString('- Mobile number:', $reply);
        $this->assertStringContainsString('- Birth date:', $reply);
        $this->assertStringContainsString('- Gender: must be Male, Female or Other.', $reply);
        $this->assertStringContainsString('- Full name: cannot be empty.', $reply);

        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');
        $this->assertNull($this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->Mobile);
    }

    public function test_future_birth_date_and_overlong_company_name_are_rejected(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['birth_date' => now()->addYear()->toDateString()]);
        $this->assertStringContainsString('- Birth date: cannot be in the future.', $this->say('jobseeker', 'x')->json('reply'));

        Auth::guard('jobseeker')->logout();
        $employer = $this->employer();
        $this->actingAs($employer, 'employer');
        $this->fakeProposal(['company_name' => str_repeat('A', 256)]);
        $this->assertStringContainsString('- Company name:', $this->say('employer', 'x')->json('reply'));
        $this->assertSame('Acme Hiring', $this->row('employer_reg', 'EmployerId', $employer->EmployerId)->CompanyName);
    }

    // 8
    public function test_cancel_expiry_and_other_messages_discard_a_pending_change(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');

        $this->fakeProposal(['city' => 'Goa']);
        $this->say('jobseeker', 'city Goa');
        $this->say('jobseeker', 'cancel')->assertJsonPath('reply', 'Profile update cancelled. No changes were made.');

        $this->fakeProposal(['city' => 'Goa']);
        $this->say('jobseeker', 'city Goa');
        $this->travel(301)->seconds();
        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'That profile update confirmation has expired. No change was made. Please ask again.');

        $this->fakeProposal(['city' => 'Goa']);
        $this->say('jobseeker', 'city Goa');
        $this->responses[] = self::answer('Sure, here is something else.');
        $this->say('jobseeker', 'Actually, what jobs suit me?')->assertJsonPath('reply', 'Sure, here is something else.');
        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');

        $this->assertSame('Mumbai', $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->City);
    }

    public function test_new_conversation_discards_pending_change(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['city' => 'Goa']);
        $this->say('jobseeker', 'city Goa');
        $this->postJson(route('jobseeker.copilot.reset'))->assertOk();
        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', 'There is no pending profile update to confirm. No change was made.');
        $this->assertSame('Mumbai', $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->City);
    }

    public function test_profile_changed_after_summary_is_not_overwritten(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');
        $this->fakeProposal(['city' => 'Goa']);
        $this->say('jobseeker', 'city Goa');

        DB::table('jobseeker_reg')->where('JobSeekId', $seeker->JobSeekId)->update(['City' => 'Delhi']); // edited on the profile page meanwhile

        $this->say('jobseeker', self::CONFIRM)->assertJsonPath('reply', fn (string $r) => str_contains($r, 'nothing was applied'));
        $this->assertSame('Delhi', $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId)->City);
    }

    public function test_unchanged_values_need_no_confirmation(): void
    {
        $this->actingAs($this->seeker(), 'jobseeker');
        $this->fakeProposal(['city' => 'mumbai ']);
        $this->say('jobseeker', 'city Mumbai')->assertJsonPath('reply', fn (string $r) => str_contains($r, 'confirm') === false);
    }

    public function test_tool_declares_only_allowlisted_fields_per_role(): void
    {
        $registry = app(CopilotCapabilityRegistry::class);
        $tool = fn (string $role) => collect($registry->declarations($role))->firstWhere('name', 'request_profile_update');

        $this->assertSame(['full_name', 'address', 'city', 'mobile', 'qualification', 'gender', 'birth_date'], array_keys($tool('jobseeker')['parameters']['properties']));
        $this->assertSame(['company_name', 'contact_person', 'address', 'city', 'mobile', 'business_area'], array_keys($tool('employer')['parameters']['properties']));
        foreach (['jobseeker', 'employer'] as $role) {
            $this->assertStringContainsString('CONFIRM PROFILE UPDATE', $registry->instructions($role));
            $this->assertEmpty(array_intersect(['email', 'password', 'status', 'user_name', 'photo'], array_keys($tool($role)['parameters']['properties'])));
        }
        $this->assertSame(array_keys(CopilotProfileUpdateService::editableFields('jobseeker')), array_keys($tool('jobseeker')['parameters']['properties']));
    }

    // 10: the profile pages that now share their validation rules behave as before.
    public function test_job_seeker_profile_page_update_still_validates_and_persists(): void
    {
        $seeker = $this->seeker();
        $this->actingAs($seeker, 'jobseeker');

        $this->from(route('jobseeker.profile.index'))->put(route('jobseeker.profile.update'), [
            'JobSeekerName' => 'Asha Rao', 'Email' => 'asha@example.test', 'UserName' => 'asha', 'Mobile' => str_repeat('1', 21),
        ])->assertSessionHasErrors('Mobile');

        $this->put(route('jobseeker.profile.update'), [
            'JobSeekerName' => 'Asha Rao', 'Email' => 'asha@example.test', 'UserName' => 'asha', 'City' => 'Kochi', 'Mobile' => '12345', 'Gender' => 'Female',
        ])->assertRedirect(route('jobseeker.profile.index'))->assertSessionHas('success');

        $row = $this->row('jobseeker_reg', 'JobSeekId', $seeker->JobSeekId);
        $this->assertSame(['Kochi', '12345', 'Female'], [$row->City, $row->Mobile, $row->Gender]);
        $this->assertTrue(Hash::check('secret-pass', $row->Password));
    }

    public function test_employer_profile_page_update_still_validates_and_persists(): void
    {
        $employer = $this->employer();
        $this->actingAs($employer, 'employer');

        $this->from(route('employer.profile.index'))->put(route('employer.profile.update'), [
            'Email' => 'hr@acme.test', 'UserName' => 'acmehr',
        ])->assertSessionHasErrors('CompanyName');

        $this->put(route('employer.profile.update'), [
            'CompanyName' => 'Acme Hiring 2', 'Email' => 'hr@acme.test', 'UserName' => 'acmehr', 'Area_Work' => 'Retail', 'ContactPerson' => 'S. Iyer',
        ])->assertRedirect(route('employer.profile.index'))->assertSessionHas('success');

        $row = $this->row('employer_reg', 'EmployerId', $employer->EmployerId);
        $this->assertSame(['Acme Hiring 2', 'Retail', 'S. Iyer'], [$row->CompanyName, $row->Area_Work, $row->ContactPerson]);
    }
}
