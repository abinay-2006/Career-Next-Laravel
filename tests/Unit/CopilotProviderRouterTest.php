<?php

namespace Tests\Unit;

use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProviderRouter;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotException;
use App\Services\FreeLlmApiCopilotService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CopilotProviderRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai.provider' => 'openrouter', 'services.ai.fallback_provider' => 'navyai',
            'services.ai.provider_fallback_enabled' => true, 'services.ai.total_timeout_seconds' => 24,
            'services.ai.providers.openrouter' => ['base_url' => 'https://openrouter.ai/api/v1', 'key' => 'or-test', 'enabled' => true],
            'services.ai.providers.navyai' => ['base_url' => 'https://api.navy/v1', 'key' => 'navy-test', 'enabled' => true],
        ]);
        Http::preventStrayRequests();
    }

    public function test_switches_provider_only_for_eligible_pre_action_failure(): void
    {
        Http::fake(['https://api.navy/v1/models' => Http::response(['data' => [self::navyModel('navy-small', 32), self::navyModel('navy-strong', 128)]])]);
        $completion = $this->mock(FreeLlmApiCopilotService::class);
        $completion->shouldReceive('reply')->twice()->andReturnUsing(function () {
            static $attempt = 0;
            if (++$attempt === 1) {
                $this->assertSame('https://openrouter.ai/api/v1', config('services.freellmapi.base_url'));
                throw new FreeLlmApiCopilotException('unavailable', FreeLlmApiCopilotException::CONNECTION);
            }
            $this->assertSame('https://api.navy/v1', config('services.freellmapi.base_url'));
            $this->assertSame('navy-strong', config('services.freellmapi.models.primary'));
            return 'NavyAI answer';
        });
        $this->app->instance(FreeLlmApiCopilotService::class, $completion);
        $reply = app(CopilotProviderRouter::class)->reply('jobseeker', 'hello', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
        $this->assertSame('NavyAI answer', $reply);
    }

    public function test_openrouter_uses_its_auto_router_and_max_cost_tier(): void
    {
        $completion = $this->mock(FreeLlmApiCopilotService::class);
        $completion->shouldReceive('reply')->once()->andReturnUsing(function () {
            $this->assertSame('openrouter/auto', config('services.freellmapi.models.primary'));
            $this->assertSame('max', config('services.freellmapi.auto_router_cost_tier'));
            return 'Auto answer';
        });
        $this->app->instance(FreeLlmApiCopilotService::class, $completion);
        $this->assertSame('Auto answer', app(CopilotProviderRouter::class)->reply('jobseeker', 'hello', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class)));
    }

    public function test_navy_catalog_resolution_rejects_invalid_or_ineligible_catalogs(): void
    {
        Http::fake(['https://api.navy/v1/models' => Http::response(['data' => [self::navyModel('no-tools', 256, false)]])]);
        try {
            app(\App\Services\CopilotProviderModelResolver::class)->resolveNavyAi(config('services.ai.providers.navyai'), microtime(true) + 5);
            $this->fail('Expected no eligible model error');
        } catch (FreeLlmApiCopilotException $e) {
            $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $e->reason);
            $this->assertSame(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, $e->getMessage());
        }
    }

    public function test_navy_catalog_resolution_rejects_malformed_catalogs(): void
    {
        Http::fake(['https://api.navy/v1/models' => Http::response(['models' => 'malformed'])]);
        try {
            app(\App\Services\CopilotProviderModelResolver::class)->resolveNavyAi(config('services.ai.providers.navyai'), microtime(true) + 5);
            $this->fail('Expected invalid catalog error');
        } catch (FreeLlmApiCopilotException $e) {
            $this->assertSame(FreeLlmApiCopilotException::INVALID_RESPONSE, $e->reason);
        }
    }

    public function test_openrouter_404_is_model_specific_and_falls_back_before_tool_execution(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['code' => 'model_not_found']], 404),
            'https://api.navy/v1/models' => Http::response(['data' => [self::navyModel('navy-strong', 128)]]),
            'https://api.navy/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Navy success']]]]),
        ]);
        $reply = app(CopilotProviderRouter::class)->reply('jobseeker', 'hello', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
        $this->assertSame('Navy success', $reply);
        Http::assertSent(fn(Request $request) => str_contains($request->url(), 'openrouter.ai') && $request['model'] === 'openrouter/auto');
        Http::assertSent(fn(Request $request) => str_contains($request->url(), 'api.navy/v1/chat/completions') && $request['model'] === 'navy-strong');
    }

    public function test_openrouter_tool_call_and_tool_result_continue_on_same_provider(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                'id' => 'call-1', 'type' => 'function', 'function' => ['name' => 'get_my_profile', 'arguments' => '{}'],
            ]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Finished']]]])]);
        $reply = app(CopilotProviderRouter::class)->reply('jobseeker', 'show my profile', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
        $this->assertSame('Finished', $reply);
        $requests = Http::recorded()->pluck(0)->values();
        $this->assertCount(2, $requests);
        $this->assertSame('openrouter/auto', $requests[0]->data()['model']);
        $this->assertSame('max', $requests[0]->data()['plugins'][0]['cost_tier']);
        $followup = $requests[1]->data()['messages'];
        $this->assertSame('call-1', collect($followup)->firstWhere('role', 'assistant')['tool_calls'][0]['id']);
        $this->assertSame('call-1', collect($followup)->firstWhere('role', 'tool')['tool_call_id']);
        $this->assertSame('openrouter/auto', $requests[1]->data()['model']);
    }

    public function test_navyai_tool_call_and_tool_result_continue_on_resolved_model(): void
    {
        config(['services.ai.provider' => 'navyai', 'services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            'https://api.navy/v1/models' => Http::response(['data' => [self::navyModel('navy-strong', 128)]]),
            'https://api.navy/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                    'id' => 'navy-call-1', 'type' => 'function', 'function' => ['name' => 'get_my_profile', 'arguments' => '{}'],
                ]]], 'finish_reason' => 'tool_calls']]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Navy finished']]]]),
        ]);
        $reply = app(CopilotProviderRouter::class)->reply('jobseeker', 'show my profile', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
        $this->assertSame('Navy finished', $reply);
        $requests = Http::recorded()->pluck(0)->filter(fn(Request $request) => str_contains($request->url(), '/chat/completions'))->values();
        $this->assertCount(2, $requests);
        $this->assertSame('navy-strong', $requests[0]->data()['model']);
        $followup = $requests[1]->data()['messages'];
        $this->assertSame('navy-call-1', collect($followup)->firstWhere('role', 'assistant')['tool_calls'][0]['id']);
        $this->assertSame('navy-call-1', collect($followup)->firstWhere('role', 'tool')['tool_call_id']);
        $this->assertSame('navy-strong', $requests[1]->data()['model']);
    }

    public function test_never_switches_provider_after_tool_execution(): void
    {
        $completion = $this->mock(FreeLlmApiCopilotService::class);
        $completion->shouldReceive('reply')->once()->andThrow(new FreeLlmApiCopilotException('failed after action', FreeLlmApiCopilotException::UPSTREAM, null, null, 503, true, false));
        $this->app->instance(FreeLlmApiCopilotService::class, $completion);
        try {
            app(CopilotProviderRouter::class)->reply('jobseeker', 'do it', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
            $this->fail('Expected provider failure');
        } catch (FreeLlmApiCopilotException $e) {
            $this->assertFalse($e->fallbackSafe);
        }
    }

    private static function navyModel(string $id, int $maxOutput, bool $supportsTools = true): array
    {
        return [
            'id' => $id, 'endpoint' => '/v1/chat/completions', 'premium' => true,
            'supports_tools' => $supportsTools, 'supports_function_calling' => false,
            'supports_reasoning' => true, 'metadata_status' => 'known',
            'max_output_tokens' => $maxOutput, 'context_window' => 100000, 'token_multiplier' => 5,
        ];
    }
}
