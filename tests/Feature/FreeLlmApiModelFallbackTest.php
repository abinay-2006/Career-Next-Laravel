<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotService as Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Curated multi-model fallback inside the single FreeLLMAPI gateway.
 * Provider traffic is faked per model; nothing sleeps and no live gateway is used.
 */
class FreeLlmApiModelFallbackTest extends TestCase
{
    private const ENDPOINT = 'http://127.0.0.1:31415/v1/chat/completions';
    private const P = 'primary-model';
    private const S = 'secondary-model';
    private const T = 'tertiary-model';

    /** @var list<array{model: string, payload: array}> every request, including ones that throw */
    private array $sent = [];
    /** @var array<string, list<\Closure|array>> */
    private array $queues = [];
    private JobSeeker $seeker;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.freellmapi.enabled' => true,
            'services.freellmapi.base_url' => 'http://127.0.0.1:31415/v1',
            'services.freellmapi.key' => 'test-key',
            'services.freellmapi.models' => ['primary' => self::P, 'secondary' => self::S, 'tertiary' => self::T],
            'services.freellmapi.model_fallback_enabled' => true,
            'services.freellmapi.max_model_attempts' => 3,
        ]);
        Http::preventStrayRequests();
        Schema::create('jobseeker_reg', function (Blueprint $t): void { $t->increments('JobSeekId'); $t->string('JobSeekerName'); $t->string('Status'); $t->string('Qualification')->nullable(); $t->string('City')->nullable(); $t->string('Password')->nullable(); });
        Schema::create('job_master', function (Blueprint $t): void { $t->increments('JobId'); $t->integer('EmployerId'); $t->string('CompanyName'); $t->string('JobTitle'); $t->string('Status')->nullable(); $t->integer('Vacancy')->nullable(); });
        Schema::create('application_master', function (Blueprint $t): void { $t->increments('ApplicationId'); $t->integer('JobSeekId'); $t->integer('JobId'); $t->string('Status'); $t->dateTime('ApplicationDate')->nullable(); });
        Schema::create('saved_jobs', function (Blueprint $t): void { $t->increments('SavedJobId'); $t->integer('JobSeekId'); $t->integer('JobId'); });
        Schema::create('logs', function (Blueprint $t): void { $t->increments('Id'); $t->integer('JobId')->nullable(); $t->string('action'); $t->dateTime('cdate'); $t->string('actor_type')->nullable(); $t->integer('actor_id')->nullable(); $t->string('target_type')->nullable(); $t->integer('target_id')->nullable(); $t->text('metadata')->nullable(); });
        $this->seeker = JobSeeker::query()->create(['JobSeekerName' => 'Seeker', 'Status' => 'Approved']);
        $this->actingAs($this->seeker, 'jobseeker');

        Http::fake([self::ENDPOINT => function (Request $request) {
            $model = (string) $request['model'];
            $this->sent[] = ['model' => $model, 'payload' => $request->data()];
            $next = array_shift($this->queues[$model]);
            if ($next === null) {
                $this->fail("Unexpected request to {$model}");
            }
            return $next instanceof \Closure ? $next() : Http::response(...$next);
        }]);
    }

    /** Queue responses per model: each item is [body, status, headers] or a Closure. */
    private function queue(string $model, array|\Closure ...$responses): void
    {
        $this->queues[$model] = array_merge($this->queues[$model] ?? [], $responses);
    }

    private function models(): array
    {
        return array_column($this->sent, 'model');
    }

    private function ask(string $message = 'hello'): TestResponse
    {
        return $this->postJson(route('jobseeker.copilot.message'), ['message' => $message]);
    }

    private static function answer(string $text = 'Helpful answer'): array
    {
        return [['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]], 200];
    }

    private static function toolCall(string $id = 'call-1', string $name = 'get_my_profile'): array
    {
        return [['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => '{}']]]], 'finish_reason' => 'tool_calls']]], 200];
    }

    private static function failWith(int $status, array $headers = [], string $code = 'rate_limit_exceeded'): array
    {
        return [['error' => ['code' => $code, 'message' => 'sensitive test-key upstream detail']], $status, $headers];
    }

    private static function timeout(): \Closure
    {
        return fn () => throw new ConnectionException('cURL error 28: Operation timed out after 9001 milliseconds');
    }

    private function spyDispatcher(): object
    {
        $spy = new class(app(CopilotCapabilityRegistry::class), request()) extends CopilotToolDispatcher {
            public array $calls = [];
            public function dispatch(string $role, mixed $actor, string $name, array $args): array
            {
                $this->calls[] = $name;
                return parent::dispatch($role, $actor, $name, $args);
            }
        };
        $this->app->instance(CopilotToolDispatcher::class, $spy);
        return $spy;
    }

    // 1
    public function test_primary_success_uses_no_fallback(): void
    {
        $this->queue(self::P, self::answer('From primary'));
        $this->ask()->assertOk()->assertExactJson(['reply' => 'From primary']);
        $this->assertSame([self::P], $this->models());
    }

    // 2
    public function test_primary_rate_limit_falls_back_to_secondary(): void
    {
        Log::spy();
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '120']));
        $this->queue(self::S, self::answer('From secondary'));

        $this->ask()->assertOk()->assertExactJson(['reply' => 'From secondary']);
        $this->assertSame([self::P, self::S], $this->models());

        Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $m === 'FreeLLMAPI Copilot model attempt failed.'
            && $c['model'] === self::P && $c['failure_type'] === 'rate_limited' && $c['http_status'] === 429
            && $c['retry_after_seconds'] === 120 && $c['next_model'] === self::S && !str_contains(json_encode($c), 'test-key'));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $c) => $m === 'FreeLLMAPI Copilot model cooldown started.' && $c['model'] === self::P && $c['cooldown_seconds'] === 120);
        Log::shouldHaveReceived('info')->withArgs(fn (string $m, array $c) => $m === 'FreeLLMAPI Copilot reply completed.' && $c['model'] === self::S && $c['fallback_used'] === true);
    }

    // 3
    public function test_primary_upstream_503_falls_back_to_secondary(): void
    {
        $this->queue(self::P, self::failWith(503, [], 'upstream_error'));
        $this->queue(self::S, self::answer('From secondary'));
        $this->ask()->assertOk()->assertJsonPath('reply', 'From secondary');
        $this->assertSame([self::P, self::S], $this->models());
    }

    // 4
    public function test_primary_and_secondary_fail_then_tertiary_succeeds(): void
    {
        $this->queue(self::P, self::failWith(429));
        $this->queue(self::S, self::timeout());
        $this->queue(self::T, self::answer('From tertiary'));
        $this->ask()->assertOk()->assertJsonPath('reply', 'From tertiary');
        $this->assertSame([self::P, self::S, self::T], $this->models());
    }

    // 5
    public function test_all_models_failing_for_different_reasons_returns_generic_message(): void
    {
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '600']));
        $this->queue(self::S, self::failWith(503, [], 'upstream_error'));
        $this->queue(self::T, self::timeout());

        $response = $this->ask()->assertStatus(503)
            ->assertExactJson(['message' => sprintf(Service::MODELS_UNAVAILABLE_MESSAGE, 'shortly')])
            ->assertHeader('X-Copilot-Safe-Error', '1')
            ->assertHeaderMissing('Retry-After');
        $this->assertStringNotContainsString('sensitive', $response->getContent());
        $this->assertStringNotContainsString(self::S, $response->getContent()); // no internal config exposed
        $this->assertSame([self::P, self::S, self::T], $this->models());
    }

    public function test_all_models_rate_limited_reports_soonest_retry(): void
    {
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '600']));
        $this->queue(self::S, self::failWith(429, ['Retry-After' => '120']));
        $this->queue(self::T, self::failWith(429, ['Retry-After' => '300']));

        $this->ask()->assertStatus(503)
            ->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in about 2 minutes'))
            ->assertJsonPath('retry_after', 120)
            ->assertHeader('Retry-After', '120');

        // Every model is now cooling down: the next message sends nothing and keeps the estimate.
        $this->ask('again')->assertStatus(503)->assertJsonPath('retry_after', 120);
        $this->assertCount(3, $this->sent);
    }

    // 6 + 7
    public function test_cooldown_skips_only_the_limited_model_and_respects_retry_after(): void
    {
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '600']));
        $this->queue(self::S, self::answer('one'), self::answer('two'));

        $this->ask('first')->assertOk()->assertJsonPath('reply', 'one');
        $this->ask('second')->assertOk()->assertJsonPath('reply', 'two');
        // Primary was not contacted again while cooling down; healthy secondary was not blocked.
        $this->assertSame([self::P, self::S, self::S], $this->models());

        // The local cooldown is capped (300 s) so a recovered model is probed again later.
        $this->travel(301)->seconds();
        $this->queue(self::P, self::answer('primary back'));
        $this->ask('third')->assertOk()->assertJsonPath('reply', 'primary back');
        $this->assertSame([self::P, self::S, self::S, self::P], $this->models());
    }

    public function test_gateway_wide_limit_does_not_fall_back_and_pauses_all_models(): void
    {
        // X-RateLimit-Remaining: 0 is the gateway's own client limiter: every model would be refused.
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '30', 'X-RateLimit-Remaining' => '0']));

        $this->ask()->assertStatus(503)->assertJsonPath('retry_after', 30);
        $this->ask('again')->assertStatus(503);
        $this->assertSame([self::P], $this->models());
    }

    // 8
    public function test_authentication_and_permission_failures_do_not_fall_back(): void
    {
        $this->queue(self::P, self::failWith(401, [], 'invalid_api_key'), self::failWith(403, [], 'forbidden'));

        $this->ask()->assertStatus(502)->assertExactJson(['message' => Service::AUTHENTICATION_MESSAGE]);
        $this->ask('again')->assertStatus(502)->assertExactJson(['message' => Service::PERMISSION_MESSAGE]);
        $this->assertSame([self::P, self::P], $this->models());
    }

    // 9
    public function test_invalid_request_and_unknown_model_do_not_fall_back(): void
    {
        $this->queue(self::P, self::failWith(400, [], 'invalid_request_error'), self::failWith(404, [], 'model_not_found'));

        $this->ask()->assertStatus(503)->assertExactJson(['message' => Service::UNAVAILABLE_MESSAGE]);
        $this->ask('again')->assertStatus(503)->assertExactJson(['message' => Service::UNAVAILABLE_MESSAGE]);
        $this->assertSame([self::P, self::P], $this->models());
    }

    public function test_unreachable_gateway_does_not_fall_back(): void
    {
        $this->queue(self::P, fn () => throw new ConnectionException('cURL error 7: Failed to connect: Connection refused'));
        $this->ask()->assertStatus(503)->assertExactJson(['message' => Service::CONNECTION_MESSAGE]);
        $this->assertSame([self::P], $this->models());
    }

    // 10
    public function test_fallback_disabled_uses_only_the_primary(): void
    {
        config(['services.freellmapi.model_fallback_enabled' => false]);
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '60']));
        $this->ask()->assertStatus(503)->assertJsonPath('retry_after', 60);
        $this->assertSame([self::P], $this->models());
    }

    public function test_max_model_attempts_limits_the_chain(): void
    {
        config(['services.freellmapi.max_model_attempts' => 2]);
        $this->queue(self::P, self::failWith(503, [], 'upstream_error'));
        $this->queue(self::S, self::failWith(503, [], 'upstream_error'));
        $this->ask()->assertStatus(503)->assertExactJson(['message' => Service::UPSTREAM_MESSAGE]);
        $this->assertSame([self::P, self::S], $this->models());
    }

    // 11 + 12
    public function test_function_calling_works_on_fallback_model_with_identical_tools(): void
    {
        $spy = $this->spyDispatcher();
        $this->queue(self::P, self::failWith(429));
        $this->queue(self::S, self::toolCall('call-s1'), self::answer('Your profile is ready.'));

        $this->ask('Show my profile')->assertOk()->assertJsonPath('reply', 'Your profile is ready.');

        $this->assertSame([self::P, self::S, self::S], $this->models());
        $this->assertSame(['get_my_profile'], $spy->calls);
        // The exact same allowed tool definitions and tool_choice go to every model.
        $this->assertSame($this->sent[0]['payload']['tools'], $this->sent[1]['payload']['tools']);
        $this->assertSame($this->sent[0]['payload']['tool_choice'], $this->sent[1]['payload']['tool_choice']);
        $this->assertSame(app(CopilotCapabilityRegistry::class)->names('jobseeker'), array_column(array_column($this->sent[1]['payload']['tools'], 'function'), 'name'));
        // Tool-call ID and the full assistant tool-call message are preserved for the follow-up.
        $followUp = $this->sent[2]['payload']['messages'];
        $this->assertSame('call-s1', collect($followUp)->firstWhere('role', 'assistant')['tool_calls'][0]['id']);
        $this->assertSame('call-s1', collect($followUp)->firstWhere('role', 'tool')['tool_call_id']);
    }

    // 13
    public function test_failure_after_tool_execution_never_switches_model_or_repeats_the_tool(): void
    {
        $spy = $this->spyDispatcher();
        $this->queue(self::P, self::toolCall('call-p1'), self::failWith(503, [], 'upstream_error'));

        $this->ask('Show my profile')->assertStatus(503)->assertExactJson(['message' => Service::UPSTREAM_MESSAGE]);

        $this->assertSame(['get_my_profile'], $spy->calls);
        $this->assertSame([self::P, self::P], $this->models()); // secondary never asked
    }

    // 14
    public function test_ambiguous_timeout_after_tool_execution_is_not_replayed(): void
    {
        $spy = $this->spyDispatcher();
        $this->queue(self::P, self::toolCall('call-p1'), self::timeout());

        $this->ask('Show my profile')->assertStatus(504)->assertExactJson(['message' => Service::TIMEOUT_MESSAGE]);

        $this->assertSame(['get_my_profile'], $spy->calls);
        $this->assertSame([self::P, self::P], $this->models());
    }

    public function test_timeout_before_any_tool_execution_may_fall_back(): void
    {
        $spy = $this->spyDispatcher();
        $this->queue(self::P, self::timeout());
        $this->queue(self::S, self::answer('Answered by secondary'));

        $this->ask()->assertOk()->assertJsonPath('reply', 'Answered by secondary');
        $this->assertSame([], $spy->calls);
        $this->assertSame([self::P, self::S], $this->models());
    }

    public function test_broken_tool_call_is_not_executed_and_next_model_is_used(): void
    {
        $spy = $this->spyDispatcher();
        // Missing tool-call ID: this model cannot honour the contract, so it is never dispatched.
        $this->queue(self::P, [['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['type' => 'function', 'function' => ['name' => 'get_my_profile', 'arguments' => '{}']]]]]]], 200]);
        $this->queue(self::S, self::answer('Clean answer'));

        $this->ask()->assertOk()->assertJsonPath('reply', 'Clean answer');
        $this->assertSame([], $spy->calls);
        $this->assertSame([self::P, self::S], $this->models());
    }

    public function test_empty_completion_falls_back_but_never_becomes_an_answer(): void
    {
        $this->queue(self::P, [['choices' => [['message' => ['role' => 'assistant', 'content' => '  ']]]], 200]);
        $this->queue(self::S, [['choices' => [['message' => ['role' => 'assistant', 'content' => '']]]], 200]);
        $this->queue(self::T, [['choices' => [['message' => ['role' => 'assistant', 'content' => null]]]], 200]);

        $this->ask()->assertStatus(502)->assertExactJson(['message' => Service::EMPTY_COMPLETION_MESSAGE]);
        $this->assertSame([self::P, self::S, self::T], $this->models());
    }

    // 15
    public function test_conversation_context_is_preserved_when_switching_models(): void
    {
        $key = 'career_next_copilot_jobseeker_'.$this->seeker->getAuthIdentifier().'_freellmapi';
        $this->queue(self::P, self::failWith(503, [], 'upstream_error'));
        $this->queue(self::S, self::answer('Continuing'));

        $this->withSession([$key => [['role' => 'user', 'content' => 'Earlier question'], ['role' => 'assistant', 'content' => 'Earlier answer']]])
            ->ask('Follow-up')->assertOk();

        $first = $this->sent[0]['payload']['messages'];
        $this->assertSame($first, $this->sent[1]['payload']['messages']);
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($first, 'role'));
        $this->assertSame('Follow-up', end($first)['content']);
    }

    // 16
    public function test_missing_or_invalid_model_configuration_fails_safely_without_requests(): void
    {
        config(['services.freellmapi.models' => ['primary' => 'bad model!', 'secondary' => '', 'tertiary' => ['x']]]);
        $this->ask()->assertStatus(503)->assertExactJson(['message' => Service::UNAVAILABLE_MESSAGE]);

        config(['services.freellmapi.models' => 'not-an-array']);
        $this->ask('again')->assertStatus(503)->assertExactJson(['message' => Service::UNAVAILABLE_MESSAGE]);

        $this->assertSame([], $this->sent);
    }

    public function test_invalid_and_duplicate_entries_are_dropped_from_the_chain(): void
    {
        config(['services.freellmapi.models' => ['primary' => '../etc passwd', 'secondary' => self::S, 'tertiary' => self::S]]);
        $this->queue(self::S, self::failWith(503, [], 'upstream_error'));

        $this->ask()->assertStatus(503)->assertExactJson(['message' => Service::UPSTREAM_MESSAGE]);
        $this->assertSame([self::S], $this->models());
    }

    public function test_missing_model_block_uses_the_existing_default_model(): void
    {
        config(['services.freellmapi.models' => null]);
        $this->queue(Service::MODEL, self::answer('Default model'));
        $this->ask()->assertOk()->assertJsonPath('reply', 'Default model');
        $this->assertSame([Service::MODEL], $this->models());
    }

    // ---- Regression: per-round failure classification and pinned follow-up timeout ----

    /** @return \ArrayObject<int, array{level: string, message: string, context: array}> */
    private function captureLogs(): \ArrayObject
    {
        $logs = new \ArrayObject();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($event) use ($logs) {
            if (str_contains($event->message, 'FreeLLMAPI')) {
                $logs->append(['level' => $event->level, 'message' => $event->message, 'context' => $event->context]);
            }
        });
        return $logs;
    }

    private function logged(\ArrayObject $logs, string $message): array
    {
        return array_values(array_filter($logs->getArrayCopy(), fn (array $log) => $log['message'] === $message));
    }

    /** Puts the primary into a model cooldown through the real flow (429 + successful fallback). */
    private function coolDownPrimary(): void
    {
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '600']));
        $this->queue(self::S, self::answer('warm-up'));
        $this->ask('warm-up')->assertOk();
        $this->sent = [];
    }

    public function test_incident_primary_cooldown_then_secondary_tool_rounds_then_follow_up_timeout_returns_timeout(): void
    {
        $this->coolDownPrimary();
        $spy = $this->spyDispatcher();
        $logs = $this->captureLogs();
        $this->queue(self::S, self::toolCall('call-a', 'get_my_profile'), self::toolCall('call-b', 'get_my_applications'), self::timeout());

        $response = $this->ask('Show my profile and applications')->assertStatus(504)
            ->assertExactJson(['message' => Service::TIMEOUT_MESSAGE])
            ->assertHeader('X-Copilot-Safe-Error', '1')
            ->assertHeaderMissing('Retry-After');
        $this->assertStringNotContainsString('temporarily unavailable', $response->getContent());

        // Primary skipped by cooldown (no request), secondary served every round, tertiary never asked.
        $this->assertSame([self::S, self::S, self::S], $this->models());
        // Each tool ran exactly once; nothing was replayed after the timeout.
        $this->assertSame(['get_my_profile', 'get_my_applications'], $spy->calls);
        // Both tool-call IDs and results were carried into the final (timed-out) follow-up.
        $final = collect($this->sent[2]['payload']['messages']);
        $this->assertSame(['call-a', 'call-b'], $final->where('role', 'tool')->pluck('tool_call_id')->values()->all());

        // The resolved cooldown skip from round 1 no longer turns the timeout into a generic error.
        $this->assertSame([], $this->logged($logs, 'FreeLLMAPI Copilot all configured models failed.'));
        $this->assertSame([], $this->logged($logs, 'FreeLLMAPI Copilot all configured models are rate limited.'));
        $failed = $this->logged($logs, 'FreeLLMAPI Copilot model attempt failed.');
        $this->assertSame('tool_already_executed', end($failed)['context']['fallback_blocked_reason']);
        $this->assertSame(15.0, (float) $this->logged($logs, 'FreeLLMAPI Copilot connection failure.')[0]['context']['timeout_seconds']);
    }

    public function test_initial_attempts_keep_nine_second_cap_and_pinned_follow_ups_use_fifteen(): void
    {
        $logs = $this->captureLogs();
        // Initial, fallback-eligible attempt: 9 s cap.
        $this->queue(self::P, self::timeout());
        $this->queue(self::S, self::toolCall('call-1'), self::timeout());

        $this->ask('Show my profile')->assertStatus(504)->assertExactJson(['message' => Service::TIMEOUT_MESSAGE]);

        $timeouts = $this->logged($logs, 'FreeLLMAPI Copilot connection failure.');
        $this->assertSame([self::P, self::S], array_map(fn ($l) => $l['context']['model'], $timeouts));
        $this->assertSame(9.0, (float) $timeouts[0]['context']['timeout_seconds']);  // before any tool
        $this->assertSame(15.0, (float) $timeouts[1]['context']['timeout_seconds']); // pinned after tool
        $this->assertSame([self::P, self::S, self::S], $this->models());
    }

    public function test_resolved_fallback_failure_does_not_contaminate_a_later_pinned_failure(): void
    {
        $spy = $this->spyDispatcher();
        $this->queue(self::P, self::failWith(429, ['Retry-After' => '120']));
        $this->queue(self::S, self::toolCall('call-1'), self::failWith(503, [], 'upstream_error'));

        // Previously: [rate_limited, upstream] mixed into the generic message. Now: the real 503 error.
        $this->ask('Show my profile')->assertStatus(503)->assertExactJson(['message' => Service::UPSTREAM_MESSAGE]);
        $this->assertSame(['get_my_profile'], $spy->calls);
        $this->assertSame([self::P, self::S, self::S], $this->models());
    }

    public function test_models_attempted_counts_only_real_requests_not_cooldown_skips(): void
    {
        $this->coolDownPrimary();
        $logs = $this->captureLogs();
        $this->queue(self::S, self::failWith(503, [], 'upstream_error'));
        $this->queue(self::T, self::timeout());

        // One unresolved round with differing reasons across all eligible models stays generic.
        $this->ask()->assertStatus(503)->assertExactJson(['message' => sprintf(Service::MODELS_UNAVAILABLE_MESSAGE, 'shortly')]);

        $summary = $this->logged($logs, 'FreeLLMAPI Copilot all configured models failed.');
        $this->assertCount(1, $summary);
        $this->assertSame(2, $summary[0]['context']['models_attempted']); // primary was skipped, not requested
        $this->assertSame([self::S, self::T], $this->models());
    }

    public function test_rate_limited_round_with_cooldown_skip_counts_only_requests(): void
    {
        $this->coolDownPrimary();
        $logs = $this->captureLogs();
        $this->queue(self::S, self::failWith(429, ['Retry-After' => '90']));
        $this->queue(self::T, self::failWith(429, ['Retry-After' => '240']));

        $this->ask()->assertStatus(503)->assertJsonPath('retry_after', 90)
            ->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in about 2 minutes'));

        $summary = $this->logged($logs, 'FreeLLMAPI Copilot all configured models are rate limited.');
        $this->assertSame(2, $summary[0]['context']['models_attempted']);
    }
}
