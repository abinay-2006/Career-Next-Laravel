<?php

namespace Tests\Unit;

use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProviderRouter;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotException;
use App\Services\FreeLlmApiCopilotService;
use Tests\TestCase;

class CopilotProviderRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai.provider' => 'openrouter', 'services.ai.fallback_provider' => 'navyai',
            'services.ai.provider_fallback_enabled' => true, 'services.ai.total_timeout_seconds' => 24,
            'services.ai.providers.openrouter' => ['base_url' => 'https://openrouter.ai/api/v1', 'key' => 'or-test', 'enabled' => true, 'model' => 'google/gemini-4-carbon'],
            'services.ai.providers.navyai' => ['base_url' => 'https://api.navy/v1', 'key' => 'navy-test', 'enabled' => true, 'model' => 'gpt-6-astra'],
        ]);
    }

    public function test_switches_provider_only_for_eligible_pre_action_failure(): void
    {
        $completion = $this->mock(FreeLlmApiCopilotService::class);
        $completion->shouldReceive('reply')->twice()->andReturnUsing(function () {
            static $attempt = 0;
            if (++$attempt === 1) {
                $this->assertSame('https://openrouter.ai/api/v1', config('services.freellmapi.base_url'));
                throw new FreeLlmApiCopilotException('unavailable', FreeLlmApiCopilotException::CONNECTION);
            }
            $this->assertSame('https://api.navy/v1', config('services.freellmapi.base_url'));
            $this->assertSame('gpt-6-astra', config('services.freellmapi.models.primary'));
            return 'NavyAI answer';
        });
        $this->app->instance(FreeLlmApiCopilotService::class, $completion);
        $reply = app(CopilotProviderRouter::class)->reply('jobseeker', 'hello', null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
        $this->assertSame('NavyAI answer', $reply);
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
}
