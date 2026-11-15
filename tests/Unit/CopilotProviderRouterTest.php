<?php

namespace Tests\Unit;

use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProviderModelResolver;
use App\Services\CopilotProviderRouter;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotException;
use App\Services\FreeLlmApiCopilotService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Provider routing (NavyAI -> OpenRouter in production, FreeLLMAPI locally) and dynamic model
 * discovery from the providers' catalogues. All provider traffic is faked; nothing leaves the process.
 */
class CopilotProviderRouterTest extends TestCase
{
    private const OR_MODELS = 'https://openrouter.ai/api/v1/models*';
    private const OR_CHAT = 'https://openrouter.ai/api/v1/chat/completions';
    private const NAVY_MODELS = 'https://api.navy/v1/models';
    private const NAVY_STATUS = 'https://api.navy/v1/models/status';
    private const NAVY_CHAT = 'https://api.navy/v1/chat/completions';
    private const LOCAL_CHAT = 'http://127.0.0.1:31415/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai.provider' => 'openrouter', 'services.ai.fallback_provider' => 'navyai',
            'services.ai.provider_fallback_enabled' => true, 'services.ai.total_timeout_seconds' => 24,
            'services.ai.model_catalog_cache_seconds' => 600, 'services.ai.model_catalog_stale_seconds' => 86400,
            'services.ai.providers.openrouter' => [
                'base_url' => 'https://openrouter.ai/api/v1', 'key' => 'or-test', 'enabled' => true,
            ],
            'services.ai.providers.navyai' => ['base_url' => 'https://api.navy/v1', 'key' => 'navy-test', 'enabled' => true, 'allowed_plans' => '', 'check_model_availability' => false],
        ]);
        Http::preventStrayRequests();
    }

    // ---------------------------------------------------------------- OpenRouter discovery

    public function test_openrouter_catalogue_is_fetched_with_its_key_and_cached(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/agent-pro', 50)]]),
            self::OR_CHAT => Http::response(self::answer('ok')),
        ]);

        $this->reply();
        $this->reply();

        $catalogue = $this->requestsTo('openrouter.ai/api/v1/models');
        $this->assertCount(1, $catalogue, 'the catalogue is cached between requests');
        $this->assertSame('Bearer or-test', $catalogue[0]->header('Authorization')[0]);
        $this->assertSame('https://openrouter.ai/api/v1/models?supported_parameters=tools', $catalogue[0]->url());
        $this->assertStringContainsString('supported_parameters=tools', $catalogue[0]->url());
    }

    public function test_openrouter_selects_eligible_models_from_explicit_tool_metadata_without_price_or_score_assumptions(): void
    {
        Http::fake([self::OR_MODELS => Http::response(['data' => [
            self::orModel('vendor/mid', 30),
            self::orModel('vendor/best', 55),
            self::orModel('vendor/unscored', null),
            self::orModel('vendor/good', 45),
        ]])]);

        $chain = app(CopilotProviderModelResolver::class)->resolve('openrouter', config('services.ai.providers.openrouter'), microtime(true) + 10);

        $this->assertSame(['vendor/best', 'vendor/good', 'vendor/mid'], $chain);
        $this->assertNotContains('openrouter/auto', $chain);
    }

    public function test_openrouter_rejects_models_without_tool_support_and_other_ineligible_entries(): void
    {
        Http::fake([self::OR_MODELS => Http::response(['data' => [
            self::orModel('vendor/no-tools', 90, ['supported_parameters' => ['max_tokens', 'temperature']]),
            self::orModel('vendor/no-tool-choice', 90, ['supported_parameters' => ['tools', 'max_tokens']]),
            self::orModel('vendor/image-out', 90, ['architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['image', 'text']]]),
            self::orModel('openrouter/auto', 90, ['pricing' => ['prompt' => '-1', 'completion' => '-1']]),
            self::orModel('vendor/too-expensive', 90, ['pricing' => ['prompt' => '1000', 'completion' => '1000']]),
            self::orModel('vendor/expired', 90, ['expiration_date' => '2020-01-01']),
            self::orModel('vendor/tiny-context', 90, ['context_length' => 4096]),
            self::orModel('vendor/no-chat-endpoint', 90, ['endpoints' => ['/api/v1/images']]),
            self::orModel('vendor/alias', 90, ['alias_target' => ['slug' => 'vendor/x']]),
            self::orModel('bad id with spaces', 90),
            'not-an-object',
            self::orModel('vendor/eligible', 10),
        ]])]);

        $chain = app(CopilotProviderModelResolver::class)->resolve('openrouter', config('services.ai.providers.openrouter'), microtime(true) + 10);

        $this->assertSame(['vendor/eligible', 'vendor/too-expensive'], $chain);
    }

    // ---------------------------------------------------------------- NavyAI discovery

    public function test_navyai_accepts_non_premium_tool_capable_models(): void
    {
        Http::fake([self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-free-tools', 65536, premium: false)]])]);

        $chain = app(CopilotProviderModelResolver::class)->resolve('navyai', config('services.ai.providers.navyai'), microtime(true) + 10);

        $this->assertSame(['navy-free-tools'], $chain);
    }

    public function test_navyai_handles_unknown_optional_metadata_and_rejects_ineligible_models(): void
    {
        $catalogue = ['data' => [
            self::navyModel('unknown-meta', 900000, overrides: ['metadata_status' => 'unknown']),
            self::navyModel('no-tools', 900000, overrides: ['supports_tools' => false, 'supports_function_calling' => false]),
            self::navyModel('embeddings', 900000, overrides: ['endpoint' => '/v1/embeddings']),
            self::navyModel('image-out', 900000, overrides: ['output_modalities' => ['text', 'image'], 'supports_image_output' => true]),
            self::navyModel('plus-only', 900000, overrides: ['required_plan' => 'Plus']),
            self::navyModel('function-calling-only', 1000, overrides: ['supports_tools' => false, 'supports_function_calling' => true]),
        ]];
        Http::fake([self::NAVY_MODELS => Http::response($catalogue)]);

        $resolver = app(CopilotProviderModelResolver::class);
        $this->assertSame(['function-calling-only', 'unknown-meta'], $resolver->resolve('navyai', config('services.ai.providers.navyai'), microtime(true) + 10));

        CopilotProviderModelResolver::forgetCatalog('navyai');
        $withPlan = array_merge(config('services.ai.providers.navyai'), ['allowed_plans' => 'plus']);
        $this->assertSame(['function-calling-only', 'plus-only', 'unknown-meta'], $resolver->resolve('navyai', $withPlan, microtime(true) + 10));
    }

    public function test_navyai_chain_does_not_use_premium_flags_as_an_access_or_ranking_assumption(): void
    {
        Http::fake([self::NAVY_MODELS => Http::response(['data' => [
            self::navyModel('premium-a', 128000, premium: true),
            self::navyModel('premium-b', 120000, premium: true),
            self::navyModel('premium-c', 110000, premium: true),
            self::navyModel('standard', 65536, premium: false),
        ]])]);

        $chain = app(CopilotProviderModelResolver::class)->resolve('navyai', config('services.ai.providers.navyai'), microtime(true) + 10);

        $this->assertSame(['premium-a', 'premium-b', 'premium-c'], $chain);
    }

    public function test_navyai_catalogue_plan_and_live_status_metadata_filter_explicitly_ineligible_models(): void
    {
        $block = array_merge(config('services.ai.providers.navyai'), ['check_model_availability' => true]);
        Http::fake([
            self::NAVY_MODELS => Http::response(['data' => [
                self::navyModel('navy/down', 128000),
                self::navyModel('navy/healthy', 128000),
                self::navyModel('navy/plan-locked', 128000, overrides: ['required_plan' => 'Max']),
            ]]),
            self::NAVY_STATUS => Http::response(['models' => [
                'navy/down' => ['lastStatus' => 'error'],
                'navy/healthy' => ['lastStatus' => 'ok'],
            ]]),
        ]);

        $chain = app(CopilotProviderModelResolver::class)->resolve('navyai', $block, microtime(true) + 10);

        $this->assertSame(['navy/healthy'], $chain);
        $statusRequest = $this->requestsTo('api.navy/v1/models/status')[0];
        $this->assertSame([], $statusRequest->header('Authorization'));
    }

    // ---------------------------------------------------------------- catalogue failures

    public function test_malformed_and_non_json_catalogues_are_invalid_responses(): void
    {
        Http::fake([self::NAVY_MODELS => Http::sequence()
            ->push(['models' => 'malformed'])
            ->push('<html>not json</html>', 200, ['Content-Type' => 'text/html'])
            ->push(['data' => ['id' => 'not-a-list']])]);

        foreach (range(1, 3) as $_) {
            $e = $this->resolveFailure('navyai');
            $this->assertSame(FreeLlmApiCopilotException::INVALID_RESPONSE, $e->reason);
            $this->assertSame(FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE, $e->getMessage());
        }
    }

    public function test_empty_catalogue_reports_no_available_model(): void
    {
        Http::fake([self::OR_MODELS => Http::response(['data' => []])]);

        $e = $this->resolveFailure('openrouter');

        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $e->reason);
        $this->assertSame(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, $e->getMessage());
    }

    public function test_catalogue_authentication_permission_and_edge_block_are_classified(): void
    {
        Http::fake([self::NAVY_MODELS => Http::sequence()
            ->push(['error' => ['code' => 'invalid_api_key']], 401)
            ->push(['error' => ['code' => 'forbidden']], 403)
            ->push('<!DOCTYPE html><title>Attention Required! | Cloudflare</title>', 403, ['Content-Type' => 'text/html; charset=UTF-8', 'Server' => 'cloudflare', 'CF-RAY' => 'abc'])]);
        Log::spy();

        $authentication = $this->resolveFailure('navyai');
        $this->assertSame(FreeLlmApiCopilotException::AUTHENTICATION, $authentication->reason);
        $this->assertSame(FreeLlmApiCopilotService::AUTHENTICATION_MESSAGE, $authentication->getMessage());
        $permission = $this->resolveFailure('navyai');
        $this->assertSame(FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED, $permission->reason);
        $this->assertSame(FreeLlmApiCopilotService::ACCOUNT_PERMISSION_MESSAGE, $permission->getMessage());
        $edge = $this->resolveFailure('navyai');
        $this->assertSame(FreeLlmApiCopilotException::UPSTREAM, $edge->reason, 'a CDN block is not a credential problem');
        $this->assertSame(403, $edge->providerStatus);

        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider catalogue returned an unsuccessful response.'
            && $c['provider'] === 'navyai' && $c['operation'] === 'model_discovery' && $c['failure_type'] === 'edge_blocked' && $c['edge'] === 'cloudflare');
    }

    public function test_catalogue_connection_timeout_and_refusal_are_classified(): void
    {
        Http::fake([self::OR_MODELS => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out after 6001 milliseconds')
            ->pushFailedConnection('cURL error 7: Failed to connect to openrouter.ai port 443')]);

        $this->assertSame(FreeLlmApiCopilotException::TIMEOUT, $this->resolveFailure('openrouter')->reason);
        $this->assertSame(FreeLlmApiCopilotException::CONNECTION, $this->resolveFailure('openrouter')->reason);
    }

    public function test_last_known_good_catalogue_is_used_when_a_refresh_fails(): void
    {
        config(['services.ai.model_catalog_cache_seconds' => 0]);
        Http::fake([self::NAVY_MODELS => Http::sequence()
            ->push(['data' => [self::navyModel('navy-strong', 128000)]])
            ->push('blocked', 403, ['Content-Type' => 'text/html'])]);
        $resolver = app(CopilotProviderModelResolver::class);

        $this->assertSame(['navy-strong'], $resolver->resolve('navyai', config('services.ai.providers.navyai'), microtime(true) + 10));
        $this->assertSame(['navy-strong'], $resolver->resolve('navyai', config('services.ai.providers.navyai'), microtime(true) + 10));
        $this->assertCount(2, $this->requestsTo('api.navy/v1/models'));
    }

    public function test_openrouter_catalogue_authorization_failure_does_not_use_a_stale_catalogue(): void
    {
        config(['services.ai.model_catalog_cache_seconds' => 0]);
        Http::fake([
            self::OR_MODELS => Http::sequence()
                ->push(['data' => [self::orModel('vendor/stale', 50)]])
                ->push(['error' => ['code' => 'invalid_api_key']], 401),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy/available', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy after catalog key failure')),
        ]);

        app(CopilotProviderModelResolver::class)->resolve('openrouter', config('services.ai.providers.openrouter'), microtime(true) + 10);
        $this->assertSame('Navy after catalog key failure', $this->reply());

        $this->assertCount(2, $this->requestsTo('openrouter.ai/api/v1/models'));
        $this->assertSame([], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame(['navy/available'], array_map(fn(Request $request) => $request['model'], $this->requestsTo('api.navy/v1/chat/completions')));
    }

    // ---------------------------------------------------------------- provider fallback

    public function test_openrouter_catalogue_failure_falls_back_to_navyai(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response('<html>Just a moment...</html>', 403, ['Content-Type' => 'text/html', 'cf-mitigated' => 'challenge']),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy success')),
        ]);
        Log::spy();

        $this->assertSame('Navy success', $this->reply());

        $this->assertSame([], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame('navy-strong', $this->requestsTo('api.navy/v1/chat/completions')[0]['model']);
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider attempt failed.'
            && $c['provider'] === 'openrouter' && $c['operation'] === 'model_discovery' && $c['fallback_provider'] === 'navyai');
        Log::shouldHaveReceived('info')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider fallback succeeded.'
            && $c['provider'] === 'navyai' && array_keys($c['failed_providers']) === ['openrouter']);
    }

    public function test_openrouter_completion_timeouts_fall_back_to_navyai(): void
    {
        $attempted = [];
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 50), self::orModel('vendor/b', 40)]]),
            self::OR_CHAT => function (Request $request) use (&$attempted) {
                $attempted[] = $request['model'];
                throw new ConnectionException('cURL error 28: Operation timed out after 9001 milliseconds');
            },
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy after timeout')),
        ]);

        $this->assertSame('Navy after timeout', $this->reply());
        $this->assertSame(['vendor/a', 'vendor/b'], $attempted);
    }

    public function test_openrouter_refused_model_tries_next_discovered_model_and_is_skipped_afterwards(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/gated', 60), self::orModel('vendor/open', 40)]]),
            self::OR_CHAT => function (Request $request) {
                return $request['model'] === 'vendor/gated'
                    ? Http::response(['error' => ['code' => 403, 'message' => 'Account cannot access model vendor/gated']], 403)
                    : Http::response(self::answer('Open model answer'));
            },
        ]);

        $this->assertSame('Open model answer', $this->reply());
        $this->assertSame('Open model answer', $this->reply());

        $models = array_map(fn(Request $r) => $r['model'], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame(['vendor/gated', 'vendor/open', 'vendor/open'], $models, 'a refused model is not retried on the next request');
        $this->assertSame([], $this->requestsTo('api.navy'));
    }

    public function test_invalid_api_key_is_classified_and_uses_the_other_provider_without_rotating_models(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 60), self::orModel('vendor/b', 40)]]),
            self::OR_CHAT => Http::response(['error' => ['code' => 'invalid_api_key']], 401),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy/a', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy after bad OpenRouter key')),
        ]);
        Log::spy();

        $this->assertSame('Navy after bad OpenRouter key', $this->reply());
        $openRouterRequests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertCount(1, $openRouterRequests);
        $this->assertSame(['Bearer or-test'], $openRouterRequests[0]->header('Authorization'));
        $this->assertSame(self::OR_CHAT, $openRouterRequests[0]->url());
        $this->assertSame('vendor/a', $openRouterRequests[0]['model']);
        $this->assertSame(['navy/a'], array_map(fn(Request $r) => $r['model'], $this->requestsTo('api.navy/v1/chat/completions')));
        Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context) =>
            $message === 'OpenRouter Copilot unsuccessful response.'
            && $context['provider'] === 'openrouter' && $context['operation'] === 'completion'
            && $context['http_status'] === 401 && $context['selected_model_id'] === 'vendor/a'
            && $context['provider_error_code'] === 'invalid_api_key'
            && $context['failure_type'] === FreeLlmApiCopilotException::INVALID_CREDENTIALS
        );
    }

    public function test_navyai_account_permission_failure_falls_back_to_openrouter_once(): void
    {
        config(['services.ai.provider' => 'navyai', 'services.ai.fallback_provider' => 'openrouter']);
        Http::fake([
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy/a', 128000), self::navyModel('navy/b', 64000)]]),
            self::NAVY_CHAT => Http::response(['error' => ['code' => 403, 'message' => 'The AI account does not have permission to use this service.']], 403),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup', 50)]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter backup answer')),
        ]);

        $this->assertSame('OpenRouter backup answer', $this->reply());
        $navyRequests = $this->requestsTo('api.navy/v1/chat/completions');
        $openRouterRequests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertCount(1, $navyRequests, 'account-wide denial must not rotate NavyAI models');
        $this->assertSame(['Bearer navy-test'], $navyRequests[0]->header('Authorization'));
        $this->assertCount(1, $openRouterRequests);
        $this->assertSame(['Bearer or-test'], $openRouterRequests[0]->header('Authorization'));
        $this->assertSame('vendor/backup', $openRouterRequests[0]['model']);
    }

    public function test_generic_http_403_is_account_level_and_falls_back_without_model_rotation(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 60), self::orModel('vendor/b', 40)]]),
            self::OR_CHAT => Http::response(['error' => ['code' => 'account_access_denied']], 403),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy/a', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy account fallback')),
        ]);
        Log::spy();

        $this->assertSame('Navy account fallback', $this->reply());
        $requests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertCount(1, $requests);
        $this->assertSame('vendor/a', $requests[0]['model']);
        Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context) =>
            $message === 'OpenRouter Copilot unsuccessful response.'
            && $context['http_status'] === 403 && $context['selected_model_id'] === 'vendor/a'
            && $context['provider_error_code'] === 'account_access_denied'
            && $context['failure_type'] === FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED
        );
    }

    public function test_account_level_401_and_403_on_navyai_do_not_rotate_models(): void
    {
        config(['services.ai.provider' => 'navyai', 'services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy/a', 128000), self::navyModel('navy/b', 64000)]]),
            self::NAVY_CHAT => Http::sequence()
                ->push(['error' => ['code' => 'invalid_api_key']], 401)
                ->push(['error' => ['code' => 'account_access_denied']], 403),
        ]);

        $invalid = $this->replyFailure();
        $forbidden = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::INVALID_CREDENTIALS, $invalid->reason);
        $this->assertSame(FreeLlmApiCopilotService::AUTHENTICATION_MESSAGE, $invalid->getMessage());
        $this->assertSame(FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED, $forbidden->reason);
        $this->assertSame(FreeLlmApiCopilotService::ACCOUNT_PERMISSION_MESSAGE, $forbidden->getMessage());
        $requests = $this->requestsTo('api.navy/v1/chat/completions');
        $this->assertSame(['navy/a', 'navy/a'], array_map(fn(Request $request) => $request['model'], $requests));
        foreach ($requests as $request) {
            $this->assertSame(['Bearer navy-test'], $request->header('Authorization'));
            $this->assertSame(self::NAVY_CHAT, $request->url());
        }
    }

    public function test_both_providers_rejecting_credentials_or_access_returns_an_aggregate_safe_error(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['error' => ['code' => 'invalid_api_key']], 401),
            self::NAVY_MODELS => Http::response(['error' => ['code' => 'account_access_denied']], 403),
        ]);

        $error = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::PROVIDERS_REJECTED_ACCESS, $error->reason);
        $this->assertSame(FreeLlmApiCopilotService::PROVIDERS_REJECTED_ACCESS_MESSAGE, $error->getMessage());
        $this->assertSame([], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame([], $this->requestsTo('api.navy/v1/chat/completions'));
    }

    public function test_model_specific_403_rotates_once_and_temporarily_excludes_refused_model(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a-denied', 60), self::orModel('vendor/z-allowed', 40)]]),
            self::OR_CHAT => function (Request $request) {
                return $request['model'] === 'vendor/a-denied'
                    ? Http::response(['error' => ['code' => 403, 'message' => 'Access denied for model vendor/a-denied']], 403)
                    : Http::response(self::answer('Alternate model answer'));
            },
        ]);
        Log::spy();

        $this->assertSame('Alternate model answer', $this->reply());
        $this->assertSame('Alternate model answer', $this->reply());
        $models = array_map(fn(Request $request) => $request['model'], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame(['vendor/a-denied', 'vendor/z-allowed', 'vendor/z-allowed'], $models);
        Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context) =>
            $message === 'OpenRouter Copilot unsuccessful response.'
            && $context['selected_model_id'] === 'vendor/a-denied'
            && $context['failure_type'] === FreeLlmApiCopilotException::MODEL_PERMISSION_DENIED
            && $context['provider_error_code'] === '403'
        );
    }

    public function test_model_404_is_classified_as_unavailable_and_rotates(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/missing', 60), self::orModel('vendor/ready', 40)]]),
            self::OR_CHAT => function (Request $request) {
                return $request['model'] === 'vendor/missing'
                    ? Http::response(['error' => ['code' => 'model_not_found']], 404)
                    : Http::response(self::answer('Available model answer'));
            },
        ]);
        Log::spy();

        $this->assertSame('Available model answer', $this->reply());
        Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context) =>
            $message === 'OpenRouter Copilot unsuccessful response.'
            && $context['http_status'] === 404 && $context['selected_model_id'] === 'vendor/missing'
            && $context['failure_type'] === FreeLlmApiCopilotException::MODEL_UNAVAILABLE
        );
    }

    public function test_explicit_unsupported_tool_calling_error_tries_next_model(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/no-tools', 60), self::orModel('vendor/tools', 40)]]),
            self::OR_CHAT => function (Request $request) {
                return $request['model'] === 'vendor/no-tools'
                    ? Http::response(['error' => ['code' => 400, 'message' => 'Tool calling is not supported for this model']], 400)
                    : Http::response(self::answer('Tool-compatible answer'));
            },
        ]);

        $this->assertSame('Tool-compatible answer', $this->reply());
        $models = array_map(fn(Request $request) => $request['model'], $this->requestsTo('openrouter.ai/api/v1/chat/completions'));
        $this->assertSame(['vendor/no-tools', 'vendor/tools'], $models);
    }

    public function test_openrouter_insufficient_credits_falls_back_and_pauses_openrouter(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 50), self::orModel('vendor/b', 40)]]),
            self::OR_CHAT => Http::response(['error' => ['code' => 402, 'message' => 'Insufficient credits']], 402),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
            self::NAVY_CHAT => Http::response(self::answer('Navy answer')),
        ]);
        Log::spy();

        $this->assertSame('Navy answer', $this->reply());
        $this->assertSame('Navy answer', $this->reply());

        $this->assertCount(1, $this->requestsTo('openrouter.ai/api/v1/chat/completions'), 'credits are account-wide: no second model, no request during the pause');
        Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context) =>
            $message === 'OpenRouter Copilot unsuccessful response.'
            && $context['provider'] === 'openrouter' && $context['operation'] === 'completion'
            && $context['http_status'] === 402 && $context['provider_error_code'] === '402'
            && $context['failure_type'] === FreeLlmApiCopilotException::QUOTA
            && $context['limit_scope'] === 'gateway'
        );
    }

    public function test_navyai_edge_block_on_completion_is_not_retried_per_model(): void
    {
        config(['services.ai.provider' => 'navyai', 'services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('a', 128000), self::navyModel('b', 64000)]]),
            self::NAVY_CHAT => Http::response('<html>blocked</html>', 403, ['Content-Type' => 'text/html', 'Server' => 'cloudflare']),
        ]);

        $e = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::UPSTREAM, $e->reason);
        $this->assertCount(1, $this->requestsTo('api.navy/v1/chat/completions'));
    }

    public function test_both_providers_unavailable_returns_a_classified_safe_error(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['error' => ['code' => 'server_error']], 503),
            self::NAVY_MODELS => Http::response('<html>Attention Required</html>', 403, ['Content-Type' => 'text/html', 'Server' => 'cloudflare']),
        ]);
        Log::spy();

        $e = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::UPSTREAM, $e->reason);
        $this->assertSame(FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE, $e->getMessage());
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot all AI providers failed.'
            && $c['failed_providers'] === ['openrouter' => 'model_discovery:provider_outage', 'navyai' => 'model_discovery:provider_outage']);
    }

    public function test_openrouter_gets_a_bounded_share_of_the_budget_when_a_fallback_exists(): void
    {
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 50)]]),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
        ]);
        $budgets = [];
        $completion = $this->mock(FreeLlmApiCopilotService::class);
        $completion->shouldReceive('reply')->twice()->andReturnUsing(function () use (&$budgets) {
            $budgets[config('services.freellmapi.provider_name')] = config('services.freellmapi.total_timeout_seconds');
            if (count($budgets) === 1) throw new FreeLlmApiCopilotException('slow', FreeLlmApiCopilotException::TIMEOUT);
            return 'ok';
        });

        $this->reply();

        $this->assertLessThanOrEqual(14.5, $budgets['openrouter'], 'about ten seconds are reserved for NavyAI');
        $this->assertGreaterThanOrEqual(13.0, $budgets['navyai']);
    }

    // ---------------------------------------------------------------- environment rules

    public function test_freellmapi_remains_the_local_development_provider(): void
    {
        config([
            'services.ai.provider' => 'freellmapi', 'services.ai.provider_fallback_enabled' => false,
            'services.freellmapi' => array_merge(config('services.freellmapi'), [
                'enabled' => true, 'base_url' => 'http://127.0.0.1:31415/v1', 'key' => 'local-key',
                'models' => ['primary' => 'local-primary', 'secondary' => 'local-secondary', 'tertiary' => ''],
            ]),
        ]);
        Http::fake([self::LOCAL_CHAT => Http::response(self::answer('Local answer'))]);

        $this->assertSame('Local answer', $this->reply());

        $requests = Http::recorded()->pluck(0)->values();
        $this->assertCount(1, $requests, 'no catalogue lookup for the curated local chain');
        $this->assertSame('local-primary', $requests[0]['model']);
        $this->assertArrayNotHasKey('reasoning', $requests[0]->data());
    }

    public function test_production_never_selects_freellmapi_or_a_local_gateway(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.ai.provider' => 'freellmapi', 'services.ai.fallback_provider' => 'freellmapi',
            'services.freellmapi' => array_merge(config('services.freellmapi'), ['enabled' => true, 'base_url' => 'http://127.0.0.1:31415/v1', 'key' => 'local-key']),
        ]);
        $this->assertSame(['navyai'], app(CopilotProviderRouter::class)->providers());

        config(['services.ai.provider' => 'navyai', 'services.ai.fallback_provider' => 'openrouter']);
        $this->assertSame(['navyai', 'openrouter'], app(CopilotProviderRouter::class)->providers());

        config(['services.ai.providers.navyai.base_url' => 'http://127.0.0.1:31415/v1']);
        $this->assertSame(['openrouter'], app(CopilotProviderRouter::class)->providers(), 'hosted providers must use public HTTPS hosts');

        config(['services.ai.providers.openrouter.enabled' => false, 'services.ai.providers.navyai.base_url' => 'https://api.navy/v1', 'services.ai.providers.navyai.key' => '']);
        $this->assertSame([], app(CopilotProviderRouter::class)->providers());
        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $this->replyFailure()->reason);
        Http::assertNothingSent();
    }

    public function test_navyai_is_the_production_primary_and_openrouter_is_the_fallback(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.ai.provider' => 'navyai', 'services.ai.fallback_provider' => 'openrouter',
            'services.ai.provider_fallback_enabled' => true,
        ]);

        $this->assertSame(['navyai', 'openrouter'], app(CopilotProviderRouter::class)->providers());
    }

    // ---------------------------------------------------------------- tool calling

    public function test_openrouter_tool_call_continues_on_the_same_discovered_model(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/agent', 50)]]),
            self::OR_CHAT => Http::sequence()->push(self::toolCalls([['call-1', 'get_my_profile']]))->push(self::answer('Finished')),
        ]);

        $this->assertSame('Finished', $this->reply('show my profile'));

        $requests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame(['Bearer or-test'], $request->header('Authorization'));
            $this->assertSame(self::OR_CHAT, $request->url());
            $this->assertSame('vendor/agent', $request['model']);
            $this->assertArrayNotHasKey('plugins', $request->data());
            $this->assertFalse($request['parallel_tool_calls']);
            $this->assertSame(['effort' => 'low'], $request['reasoning']);
            $this->assertSame('auto', $request['tool_choice']);
            $this->assertSame('function', $request['tools'][0]['type']);
        }
        $followup = $requests[1]['messages'];
        $this->assertSame('call-1', collect($followup)->firstWhere('role', 'assistant')['tool_calls'][0]['id']);
        $this->assertSame('call-1', collect($followup)->firstWhere('role', 'tool')['tool_call_id']);
    }

    public function test_navyai_tool_call_continues_on_the_resolved_model(): void
    {
        config(['services.ai.provider' => 'navyai', 'services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
            self::NAVY_CHAT => Http::sequence()->push(self::toolCalls([['navy-call-1', 'get_my_profile']]))->push(self::answer('Navy finished')),
        ]);

        $this->assertSame('Navy finished', $this->reply('show my profile'));

        $requests = $this->requestsTo('api.navy/v1/chat/completions');
        $this->assertSame(['navy-strong', 'navy-strong'], [$requests[0]['model'], $requests[1]['model']]);
        $catalogue = $this->requestsTo('api.navy/v1/models');
        $this->assertSame('https://api.navy/v1/models', $catalogue[0]->url());
        $this->assertSame([], $catalogue[0]->header('Authorization'), 'NavyAI documents its model catalogue as public');
        foreach ($requests as $request) {
            $this->assertSame(['Bearer navy-test'], $request->header('Authorization'));
            $this->assertSame(self::NAVY_CHAT, $request->url());
        }
        $this->assertArrayNotHasKey('parallel_tool_calls', $requests[0]->data());
        $this->assertSame('navy-call-1', collect($requests[1]['messages'])->firstWhere('role', 'tool')['tool_call_id']);
    }

    public function test_multiple_tool_calls_each_execute_once_and_each_get_a_result(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        $spy = $this->spyDispatcher();
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/agent', 50)]]),
            self::OR_CHAT => Http::sequence()
                ->push(self::toolCalls([['call-a', 'get_my_profile'], ['call-b', 'get_my_applications']]))
                ->push(self::answer('Both done')),
        ]);

        $this->assertSame('Both done', $this->reply());

        $this->assertSame(['get_my_profile', 'get_my_applications'], $spy->calls);
        $toolMessages = collect($this->requestsTo('openrouter.ai/api/v1/chat/completions')[1]['messages'])->where('role', 'tool')->values();
        $this->assertSame(['call-a', 'call-b'], $toolMessages->pluck('tool_call_id')->all());
    }

    public function test_only_one_change_is_staged_per_round_and_duplicate_ids_are_rejected(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        $spy = $this->spyDispatcher();
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/agent', 50), self::orModel('vendor/second', 40)]]),
            self::OR_CHAT => Http::sequence()
                // Duplicate tool-call IDs: rejected before anything executes; the next model is asked.
                ->push(self::toolCalls([['dup', 'get_my_profile'], ['dup', 'get_my_saved_jobs']]))
                ->push(self::toolCalls([['s1', 'request_profile_update', '{"changes":{"City":"Pune"}}'], ['s2', 'request_profile_update', '{"changes":{"City":"Goa"}}']]))
                ->push(self::answer('Staged')),
        ]);

        $this->reply();

        $this->assertSame(['request_profile_update'], $spy->calls, 'the second staging call is answered with an error, not executed');
        $requests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertSame(['vendor/agent', 'vendor/second', 'vendor/second'], array_map(fn(Request $r) => $r['model'], $requests));
        $results = collect($requests[2]['messages'])->where('role', 'tool')->values();
        $this->assertSame(['s1', 's2'], $results->pluck('tool_call_id')->all());
        $this->assertStringContainsString('Only one change', $results[1]['content']);
    }

    public function test_failure_after_tool_execution_never_fails_over_or_repeats_the_tool(): void
    {
        $spy = $this->spyDispatcher();
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/agent', 50), self::orModel('vendor/second', 40)]]),
            self::OR_CHAT => Http::sequence()
                ->push(self::toolCalls([['call-1', 'get_my_profile']]))
                ->push(['error' => ['code' => 'server_error']], 503),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
        ]);

        $e = $this->replyFailure();

        $this->assertFalse($e->fallbackSafe);
        $this->assertSame(['get_my_profile'], $spy->calls);
        $this->assertSame([], $this->requestsTo('api.navy'));
        $this->assertSame(['vendor/agent', 'vendor/agent'], array_map(fn(Request $r) => $r['model'], $this->requestsTo('openrouter.ai/api/v1/chat/completions')));
    }

    public function test_reasoning_only_reply_without_content_or_tool_call_is_not_an_answer(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/thinker', 50), self::orModel('vendor/plain', 40)]]),
            self::OR_CHAT => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'reasoning' => 'thinking...'], 'finish_reason' => 'length']]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Part answer']]]]]]),
        ]);

        $this->assertSame('Part answer', $this->reply());
    }

    public function test_runtime_configuration_is_restored_after_every_provider_attempt(): void
    {
        $original = config('services.freellmapi');
        Http::fake([
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/a', 50)]]),
            self::OR_CHAT => Http::response(['error' => ['code' => 'bad']], 500),
            self::NAVY_MODELS => Http::response(['data' => [self::navyModel('navy-strong', 128000)]]),
            self::NAVY_CHAT => Http::response(['error' => ['code' => 'bad']], 500),
        ]);

        $this->replyFailure();

        $this->assertSame($original, config('services.freellmapi'));
    }

    // ---------------------------------------------------------------- helpers

    private function reply(string $message = 'hello'): string
    {
        return app(CopilotProviderRouter::class)->reply('jobseeker', $message, null, app(CopilotCapabilityRegistry::class), app(CopilotToolDispatcher::class));
    }

    private function replyFailure(): FreeLlmApiCopilotException
    {
        try {
            $this->reply();
        } catch (FreeLlmApiCopilotException $e) {
            return $e;
        }
        $this->fail('Expected a provider failure.');
    }

    private function resolveFailure(string $provider): FreeLlmApiCopilotException
    {
        try {
            app(CopilotProviderModelResolver::class)->resolve($provider, config('services.ai.providers.' . $provider), microtime(true) + 10);
        } catch (FreeLlmApiCopilotException $e) {
            return $e;
        }
        $this->fail('Expected a model resolution failure.');
    }

    /** @return list<Request> */
    private function requestsTo(string $fragment): array
    {
        return Http::recorded()->pluck(0)->filter(fn(Request $r) => str_contains($r->url(), $fragment))->values()->all();
    }

    private function spyDispatcher(): object
    {
        $spy = new class(app(CopilotCapabilityRegistry::class), request()) extends CopilotToolDispatcher {
            public array $calls = [];
            public function dispatch(string $role, mixed $actor, string $name, array $args): array
            {
                $this->calls[] = $name;
                return ['ok' => true];
            }
        };
        $this->app->instance(CopilotToolDispatcher::class, $spy);
        return $spy;
    }

    private static function answer(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];
    }

    /** @param list<array{0: string, 1: string, 2?: string}> $calls [id, name, arguments] */
    private static function toolCalls(array $calls): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => array_map(
            fn(array $c) => ['id' => $c[0], 'type' => 'function', 'function' => ['name' => $c[1], 'arguments' => $c[2] ?? '{}']],
            $calls
        )], 'finish_reason' => 'tool_calls']]];
    }

    private static function orModel(string $id, ?float $agentic, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'context_length' => 200000,
            'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
            'pricing' => ['prompt' => '0.0000005', 'completion' => '0.000002'],
            'supported_parameters' => ['max_tokens', 'temperature', 'tool_choice', 'tools'],
            'endpoints' => ['/api/v1/chat/completions'],
            'expiration_date' => null,
            'benchmarks' => $agentic === null ? null : ['artificial_analysis' => ['agentic_index' => $agentic, 'intelligence_index' => $agentic / 2]],
        ], $overrides);
    }

    private static function navyModel(string $id, int $maxOutput, bool $premium = true, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'endpoint' => '/v1/chat/completions', 'premium' => $premium,
            'supports_tools' => true, 'supports_function_calling' => true,
            'supports_reasoning' => true, 'metadata_status' => 'known', 'output_modalities' => ['text'],
            'max_output_tokens' => $maxOutput, 'context_window' => 1000000, 'token_multiplier' => 2,
        ], $overrides);
    }
}
