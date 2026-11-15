<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotService as Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FreeLLMAPI error classification, Retry-After handling and cooldown behaviour.
 * All provider traffic is faked; no live gateway is contacted and nothing sleeps.
 */
class FreeLlmApiRateLimitTest extends TestCase
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
        Schema::create('job_master', function (Blueprint $t): void { $t->increments('JobId'); $t->integer('EmployerId'); $t->string('CompanyName'); $t->string('JobTitle'); $t->string('Status')->nullable(); $t->integer('Vacancy')->nullable(); });
        Schema::create('application_master', function (Blueprint $t): void { $t->increments('ApplicationId'); $t->integer('JobSeekId'); $t->integer('JobId'); $t->string('Status'); $t->dateTime('ApplicationDate')->nullable(); });
        Schema::create('saved_jobs', function (Blueprint $t): void { $t->increments('SavedJobId'); $t->integer('JobSeekId'); $t->integer('JobId'); });
        Schema::create('logs', function (Blueprint $t): void { $t->increments('Id'); $t->integer('JobId')->nullable(); $t->string('action'); $t->dateTime('cdate'); $t->string('actor_type')->nullable(); $t->integer('actor_id')->nullable(); $t->string('target_type')->nullable(); $t->integer('target_id')->nullable(); $t->text('metadata')->nullable(); });
        $this->actingAs(JobSeeker::query()->create(['JobSeekerName' => 'Seeker', 'Status' => 'Approved']), 'jobseeker');
    }

    private function ask(string $message = 'hello'): TestResponse
    {
        return $this->postJson(route('jobseeker.copilot.message'), ['message' => $message]);
    }

    private function rateLimited(array $headers = [], array $error = ['type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded', 'message' => 'Rate limit exceeded']): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => $error], 429, $headers);
    }

    private function answer(string $text = 'Helpful answer'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];
    }

    private function assertSafe(TestResponse $response): void
    {
        $response->assertHeader('X-Copilot-Safe-Error', '1')->assertJsonStructure(['message']);
        $body = $response->getContent();
        $this->assertStringNotContainsString('test-key', $body);
        $this->assertStringNotContainsString('sensitive', $body);
        $this->assertStringNotContainsString('Rate limit exceeded', $body);
    }

    public function test_429_with_integer_retry_after_and_conflicting_remaining_count_is_a_rate_limit(): void
    {
        Log::spy();
        // Mirrors the real gateway response: 429 even though its own request limiter shows 119/120 left.
        Http::fake([self::ENDPOINT => $this->rateLimited(['Retry-After' => '2862', 'X-RateLimit-Limit' => '120', 'X-RateLimit-Remaining' => '119', 'X-RateLimit-Reset' => (string) (time() + 30)])]);

        $response = $this->ask()->assertStatus(503)
            ->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in about 48 minutes'))
            ->assertJsonPath('retry_after', 2862)
            ->assertHeader('Retry-After', '2862');
        $this->assertSafe($response);
        $this->assertStringNotContainsString('usage limit', $response->json('message'));
        $this->assertStringNotContainsString('quota', $response->json('message'));
        Http::assertSentCount(1); // never retried inside the request

        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx) => $msg === 'FreeLLMAPI Copilot unsuccessful response.'
            && $ctx['http_status'] === 429 && $ctx['failure_type'] === 'rate_limited' && $ctx['provider_error_code'] === 'rate_limit_exceeded'
            && $ctx['retry_after_seconds'] === 2862 && !str_contains(json_encode($ctx), 'test-key'));
    }

    public function test_429_with_http_date_retry_after(): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited(['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 600)])]);

        $response = $this->ask()->assertStatus(503)->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in about 10 minutes'));
        $this->assertGreaterThanOrEqual(595, $response->json('retry_after'));
        $this->assertLessThanOrEqual(600, $response->json('retry_after'));
        $this->assertSafe($response);
    }

    public function test_429_without_retry_after_uses_generic_wait_and_no_header(): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited()]);

        $response = $this->ask()->assertStatus(503)
            ->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in a few minutes'))
            ->assertJsonMissingPath('retry_after')
            ->assertHeaderMissing('Retry-After');
        $this->assertSafe($response);
    }

    public static function invalidRetryAfterValues(): array
    {
        return [
            'text' => ['soon'], 'negative' => ['-5'], 'zero' => ['0'], 'decimal' => ['1.5'],
            'absurdly large' => ['99999999'], 'past http-date' => ['Thu, 01 Jan 2015 00:00:00 GMT'],
            'impossible http-date' => ['Mon, 32 Foo 2026 25:61:00 GMT'], 'non-GMT date' => ['2026-11-15T10:00:00+05:30'],
        ];
    }

    #[DataProvider('invalidRetryAfterValues')]
    public function test_invalid_retry_after_values_are_ignored(string $value): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited(['Retry-After' => $value, 'X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => '120'])]);

        $this->ask()->assertStatus(503)
            ->assertJsonPath('message', sprintf(Service::RATE_LIMIT_MESSAGE, 'in a few minutes'))
            ->assertJsonMissingPath('retry_after')
            ->assertHeaderMissing('Retry-After');
    }

    public function test_reset_header_is_used_only_when_that_limiter_is_exhausted(): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited(['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) (time() + 120)])]);
        $retry = $this->ask()->assertStatus(503)->json('retry_after');
        $this->assertGreaterThanOrEqual(115, $retry);
        $this->assertLessThanOrEqual(120, $retry);
    }

    public function test_reset_header_is_ignored_when_remaining_count_is_not_zero(): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited(['X-RateLimit-Remaining' => '119', 'X-RateLimit-Reset' => (string) (time() + 120)])]);
        $this->ask()->assertStatus(503)->assertJsonMissingPath('retry_after');
    }

    public function test_quota_is_reported_only_when_the_gateway_says_so(): void
    {
        Http::fake([self::ENDPOINT => $this->rateLimited(['Retry-After' => '7200'], ['type' => 'insufficient_quota', 'code' => 'insufficient_quota', 'message' => 'sensitive detail'])]);

        $response = $this->ask()->assertStatus(503)->assertJsonPath('message', sprintf(Service::QUOTA_MESSAGE, 'in about 2 hours'));
        $this->assertSafe($response);
    }

    public function test_no_request_is_sent_while_cooling_down_and_one_probe_is_allowed_afterwards(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()->push(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '2862'])->push($this->answer('Back online'))]);

        $this->ask('first')->assertStatus(503);
        $second = $this->ask('second')->assertStatus(503)->assertHeader('X-Copilot-Safe-Error', '1');
        $this->assertStringContainsString('Please try again in about', $second->json('message'));
        $this->assertLessThanOrEqual(2862, $second->json('retry_after'));
        Http::assertSentCount(1);

        // Local cooldown is capped at five minutes so a gateway that recovers early is noticed.
        $this->travel(301)->seconds();
        $this->ask('third')->assertOk()->assertJsonPath('reply', 'Back online');
        Http::assertSentCount(2);
    }

    public static function statusClassifications(): array
    {
        return [
            '401 authentication' => [401, 502, Service::AUTHENTICATION_MESSAGE],
            '403 account permission' => [403, 502, Service::ACCOUNT_PERMISSION_MESSAGE],
            '408 timeout' => [408, 504, Service::TIMEOUT_MESSAGE],
            '500 upstream' => [500, 503, Service::UPSTREAM_MESSAGE],
            '502 upstream' => [502, 503, Service::UPSTREAM_MESSAGE],
            '503 upstream' => [503, 503, Service::UPSTREAM_MESSAGE],
            '504 gateway timeout' => [504, 504, Service::TIMEOUT_MESSAGE],
        ];
    }

    #[DataProvider('statusClassifications')]
    public function test_provider_status_is_classified_with_an_accurate_message(int $providerStatus, int $expectedStatus, string $message): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => ['message' => 'sensitive test-key quota detail', 'code' => 'insufficient_quota']], $providerStatus)
            ->push($this->answer())]);

        $response = $this->ask()->assertStatus($expectedStatus)->assertExactJson(['message' => $message]);
        $this->assertSafe($response);
        Http::assertSentCount(1);

        // Non-429 errors do not start a cooldown: the next user message reaches the gateway.
        $this->ask('again')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_connection_failure_and_timeout_are_distinguished(): void
    {
        $attempt = 0;
        Http::fake([self::ENDPOINT => function () use (&$attempt) {
            throw new ConnectionException(++$attempt === 1
                ? 'cURL error 7: Failed to connect to 127.0.0.1 port 31415: Connection refused'
                : 'cURL error 28: Operation timed out after 9001 milliseconds');
        }]);

        $this->assertSafe($this->ask()->assertStatus(503)->assertExactJson(['message' => Service::CONNECTION_MESSAGE]));
        $this->assertSafe($this->ask('again')->assertStatus(504)->assertExactJson(['message' => Service::TIMEOUT_MESSAGE]));
        $this->assertSame(2, $attempt);
    }

    public function test_empty_completion_and_malformed_json_are_distinguished(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => '']]]])
            ->push('<html>not json</html>', 200)]);

        $this->assertSafe($this->ask()->assertStatus(502)->assertExactJson(['message' => Service::EMPTY_COMPLETION_MESSAGE]));
        $this->assertSafe($this->ask('again')->assertStatus(502)->assertExactJson(['message' => Service::INVALID_RESPONSE_MESSAGE]));
    }

    public function test_success_response_shape_is_unchanged(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->answer('Hello there'))]);
        $this->ask()->assertOk()->assertExactJson(['reply' => 'Hello there']);
    }

    public static function failuresAfterToolCall(): array
    {
        return [
            'rate limited' => [fn () => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '60']), 503],
            'timed out' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out'), 504],
        ];
    }

    #[DataProvider('failuresAfterToolCall')]
    public function test_tool_is_not_executed_again_when_the_follow_up_request_fails(\Closure $failure, int $expectedStatus): void
    {
        $spy = new class(app(CopilotCapabilityRegistry::class), request()) extends CopilotToolDispatcher {
            public int $calls = 0;
            public function dispatch(string $role, mixed $actor, string $name, array $args): array
            {
                $this->calls++;
                return parent::dispatch($role, $actor, $name, $args);
            }
        };
        $this->app->instance(CopilotToolDispatcher::class, $spy);

        $toolCall = ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call-1', 'type' => 'function', 'function' => ['name' => 'get_my_profile', 'arguments' => '{}']]]]]]];
        $calls = 0;
        $followUp = null;
        Http::fake([self::ENDPOINT => function ($request) use (&$calls, &$followUp, $toolCall, $failure) {
            if (++$calls === 1) {
                return Http::response($toolCall);
            }
            $followUp = $request->data()['messages'];
            return $failure();
        }]);

        $this->ask('Show my profile')->assertStatus($expectedStatus);

        $this->assertSame(1, $spy->calls);
        $this->assertSame(2, $calls); // initial completion + one follow-up, no retry
        $this->assertSame('call-1', collect($followUp)->firstWhere('role', 'tool')['tool_call_id']);
    }
}
