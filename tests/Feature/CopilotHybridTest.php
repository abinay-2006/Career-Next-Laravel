<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotToolDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CopilotHybridTest extends TestCase
{
    private const ENDPOINT = 'http://127.0.0.1:31415/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.freellmapi.enabled' => true, 'services.freellmapi.base_url' => 'http://127.0.0.1:31415/v1', 'services.freellmapi.key' => 'test-key',
            // Single-model chain: these tests cover per-response classification; fallback has its own suite.
            'services.freellmapi.models' => ['primary' => 'gemini-3.5-flash', 'secondary' => null, 'tertiary' => null]]);
        Http::preventStrayRequests();
        Schema::create('jobseeker_reg', function (Blueprint $t): void { $t->increments('JobSeekId'); $t->string('JobSeekerName'); $t->string('Status'); $t->string('Qualification')->nullable(); $t->string('City')->nullable(); $t->string('Password')->nullable(); });
        Schema::create('employer_reg', function (Blueprint $t): void { $t->increments('EmployerId'); $t->string('CompanyName'); $t->string('Status'); $t->string('Password')->nullable(); $t->string('City')->nullable(); $t->string('Area_Work')->nullable(); });
        Schema::create('user_master', function (Blueprint $t): void { $t->increments('UserId'); $t->string('UserName'); $t->string('Password'); });
        Schema::create('job_master', function (Blueprint $t): void { $t->increments('JobId'); $t->integer('EmployerId'); $t->string('CompanyName'); $t->string('JobTitle'); $t->string('Status')->nullable(); $t->integer('Vacancy')->nullable(); });
        Schema::create('application_master', function (Blueprint $t): void { $t->increments('ApplicationId'); $t->integer('JobSeekId'); $t->integer('JobId'); $t->string('Status'); $t->dateTime('ApplicationDate')->nullable(); });
        Schema::create('saved_jobs', function (Blueprint $t): void { $t->increments('SavedJobId'); $t->integer('JobSeekId'); $t->integer('JobId'); });
        Schema::create('logs', function (Blueprint $t): void { $t->increments('Id'); $t->integer('JobId')->nullable(); $t->string('action'); $t->dateTime('cdate'); $t->string('actor_type')->nullable(); $t->integer('actor_id')->nullable(); $t->string('target_type')->nullable(); $t->integer('target_id')->nullable(); $t->text('metadata')->nullable(); });
    }

    private function actor(string $role): Admin|Employer|JobSeeker
    {
        return match ($role) {
            'admin' => $this->adminActor(),
            'employer' => Employer::query()->create(['CompanyName' => 'Employer', 'Status' => 'Approved']),
            default => JobSeeker::query()->create(['JobSeekerName' => 'Seeker', 'Status' => 'Approved']),
        };
    }

    private function adminActor(): Admin
    {
        DB::table('user_master')->insert(['UserName' => 'admin', 'Password' => 'hash']);
        return Admin::query()->firstOrFail();
    }

    private function answer(string $text = 'Helpful answer'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];
    }

    public function test_all_roles_use_the_same_configured_free_llm_api_provider(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()->push($this->answer('Admin reply'))->push($this->answer('Employer reply'))->push($this->answer('Seeker reply'))]);
        foreach (['admin', 'employer', 'jobseeker'] as $role) {
            foreach (['admin', 'employer', 'jobseeker'] as $guard) Auth::guard($guard)->logout();
            $this->actingAs($this->actor($role), $role)->postJson(route($role.'.copilot.message'), ['message' => 'Hello'])->assertOk()->assertJsonStructure(['reply']);
        }
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === self::ENDPOINT && $request->hasHeader('Authorization', 'Bearer test-key') && $request['model'] === 'gemini-3.5-flash');
    }

    public function test_job_seeker_tool_round_trip_uses_role_allowlist_and_matching_call_id(): void
    {
        $seeker = $this->actor('jobseeker');
        config(['services.freellmapi.enabled' => true]);
        $call = ['choices' => [[
            'message' => [
                'role' => 'assistant', 'content' => null,
                'tool_calls' => [['id' => 'call-1', 'type' => 'function', 'function' => ['name' => 'get_my_profile', 'arguments' => '{}']]],
                'provider_meta' => ['kept' => true],
            ],
        ]]];
        Http::fake([self::ENDPOINT => Http::sequence()->push($call, 200)->push($this->answer('Your profile is available.'), 200)]);
        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.copilot.message'), ['message' => 'Show my profile'])->assertOk();
        $requests = Http::recorded()->pluck(0);
        $second = $requests[1]->data()['messages'];
        $this->assertSame(true, collect($second)->firstWhere('role', 'assistant')['provider_meta']['kept']);
        $tool = collect($second)->firstWhere('role', 'tool');
        $this->assertSame('call-1', $tool['tool_call_id']);
        $this->assertArrayHasKey('profile', json_decode($tool['content'], true)['result']);
    }

    public function test_admin_and_employer_operations_use_only_their_allowed_read_functions(): void
    {
        $admin = $this->actor('admin');
        $owner = $this->actor('employer');
        DB::table('job_master')->insert(['EmployerId' => $owner->EmployerId, 'CompanyName' => 'Employer', 'JobTitle' => 'Analyst', 'Status' => 'approved', 'Vacancy' => 1]);
        $tool = fn (string $id, string $name) => ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => $id, 'function' => ['name' => $name, 'arguments' => '{}']]]]]]];
        Http::fake([self::ENDPOINT => Http::sequence()->push($tool('admin-call', 'get_dashboard_statistics'), 200)->push($this->answer('Platform totals.'), 200)->push($tool('employer-call', 'get_my_jobs'), 200)->push($this->answer('Your job is listed.'), 200)]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.copilot.message'), ['message' => 'Summarize the platform'])->assertOk();
        Auth::guard('admin')->logout();
        $this->actingAs($owner, 'employer')->postJson(route('employer.copilot.message'), ['message' => 'Show my jobs'])->assertOk();
        $requests = Http::recorded()->pluck(0);
        $adminToolResult = json_decode(collect($requests[1]->data()['messages'])->firstWhere('role', 'tool')['content'], true)['result'];
        $this->assertTrue($adminToolResult['available']);
        $employerToolResult = json_decode(collect($requests[3]->data()['messages'])->firstWhere('role', 'tool')['content'], true)['result'];
        $this->assertSame('Analyst', $employerToolResult['jobs'][0]['title']);
    }

    public function test_admin_suspension_tool_still_requires_explicit_confirmation(): void
    {
        $admin = $this->actor('admin');
        $seeker = $this->actor('jobseeker');
        $toolCall = ['choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'suspend-call',
                    'function' => ['name' => 'suspend_user', 'arguments' => json_encode(['account_type' => 'jobseeker', 'account_id' => $seeker->JobSeekId])],
                ]],
            ],
        ]]];
        Http::fake([self::ENDPOINT => Http::sequence()->push($toolCall, 200)->push($this->answer('I cannot confirm a change.'), 200)]);
        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.copilot.message'), ['message' => 'Suspend this account']);
        $this->assertSame(200, $response->status(), $response->getContent());
        $response->assertJsonPath('reply', fn (string $reply) => str_contains($reply, 'CONFIRM SUSPENSION'));
        $this->assertSame('Approved', DB::table('jobseeker_reg')->where('JobSeekId', $seeker->JobSeekId)->value('Status'));
        $this->postJson(route('admin.copilot.message'), ['message' => 'CONFIRM SUSPENSION'])->assertOk()->assertJsonPath('reply', fn (string $reply) => str_contains($reply, 'Suspension completed'));
        $this->assertSame('Suspended', DB::table('jobseeker_reg')->where('JobSeekId', $seeker->JobSeekId)->value('Status'));
        Http::assertSentCount(2);
    }

    public function test_unknown_function_is_not_dispatched_and_round_limit_is_enforced(): void
    {
        $seeker = $this->actor('jobseeker');
        $dispatcher = app(CopilotToolDispatcher::class);
        $this->assertSame(['error' => 'This function is not available for your account.'], $dispatcher->dispatch('jobseeker', $seeker, 'get_dashboard_statistics', []));
        $round = fn (string $id) => ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => $id, 'function' => ['name' => 'get_my_profile', 'arguments' => '{}']]]]]]];
        Http::fake([self::ENDPOINT => Http::sequence()->push($round('x'), 200)->push($round('y'), 200)->push($round('z'), 200)->push($round('w'), 200)]);
        $this->actingAs($seeker, 'jobseeker')->postJson(route('jobseeker.copilot.message'), ['message' => 'show profile'])->assertStatus(502);
        Http::assertSentCount(4);
    }

    public function test_dispatcher_rejects_invalid_arguments_and_actor_mismatch(): void
    {
        $authenticated = $this->actor('jobseeker');
        $other = $this->actor('jobseeker');
        Auth::guard('jobseeker')->login($authenticated);
        $dispatcher = app(CopilotToolDispatcher::class);
        $this->assertSame(['error' => 'The requested function arguments were invalid.'], $dispatcher->dispatch('jobseeker', $authenticated, 'get_my_profile', ['extra' => true]));
        $this->assertSame(['error' => 'This function is not available for your account.'], $dispatcher->dispatch('jobseeker', $other, 'get_my_profile', []));
        $this->assertContains('get_my_profile', app(CopilotCapabilityRegistry::class)->names('jobseeker'));
        $this->assertNotContains('get_my_profile', app(CopilotCapabilityRegistry::class)->names('employer'));
    }

    public static function providerErrors(): array
    {
        return [[401, 502], [403, 502], [429, 503], [503, 503], [504, 504]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerErrors')]
    public function test_provider_status_errors_are_safe_and_never_fall_back(int $providerStatus, int $expectedStatus): void
    {
        $this->actingAs($this->actor('jobseeker'), 'jobseeker');
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'sensitive test-key upstream detail']], $providerStatus)]);
        $response = $this->postJson(route('jobseeker.copilot.message'), ['message' => 'hello'])->assertStatus($expectedStatus)->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('test-key', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_disabled_configuration_and_empty_completion_fail_safely(): void
    {
        config(['services.freellmapi.enabled' => false]);
        $this->actingAs($this->actor('jobseeker'), 'jobseeker')->postJson(route('jobseeker.copilot.message'), ['message' => 'hello'])->assertStatus(503);
        config(['services.freellmapi.enabled' => true]);
        Http::fake([self::ENDPOINT => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => '  ']]]], 200)]);
        $this->postJson(route('jobseeker.copilot.message'), ['message' => 'hello'])->assertStatus(502);
    }

    public function test_invalid_json_and_connection_failure_are_controlled(): void
    {
        $this->actingAs($this->actor('jobseeker'), 'jobseeker');
        Http::fake([self::ENDPOINT => Http::response('not valid JSON', 200)]);
        $this->postJson(route('jobseeker.copilot.message'), ['message' => 'hello'])->assertStatus(502);
        Http::fake([self::ENDPOINT => function () { throw new \Illuminate\Http\Client\ConnectionException('Connection refused'); }]);
        $this->postJson(route('jobseeker.copilot.message'), ['message' => 'again'])->assertStatus(503);
    }

    public function test_role_history_is_separate_and_reset_clears_it(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()->push($this->answer('First'))->push($this->answer('Second'))]);
        $actor = $this->actor('jobseeker');
        $this->actingAs($actor, 'jobseeker')->withSession([])->postJson(route('jobseeker.copilot.message'), ['message' => 'one'])->assertOk();
        $this->postJson(route('jobseeker.copilot.message'), ['message' => 'two'])->assertOk();
        $requests = Http::recorded()->pluck(0);
        $this->assertSame(['system', 'user'], array_column($requests[0]->data()['messages'], 'role'));
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($requests[1]->data()['messages'], 'role'));
        $key = 'career_next_copilot_jobseeker_'.$actor->getAuthIdentifier().'_freellmapi';
        $this->postJson(route('jobseeker.copilot.reset'))->assertOk();
        $this->assertFalse(session()->has($key));
    }
}
