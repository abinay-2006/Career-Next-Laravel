<?php

namespace Tests\Unit;

use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProviderModelResolver;
use App\Services\CopilotProviderRouter;
use App\Services\CopilotToolCallingVerifier;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotException;
use App\Services\FreeLlmApiCopilotService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * NaraRouter as the production primary (OpenRouter fallback): authenticated discovery, the
 * tool-calling probe, error classification, failover and duplicate-execution safety.
 * All provider traffic is faked; no real credentials are needed and nothing leaves the process.
 */
class CopilotNaraRouterProviderTest extends TestCase
{
    private const NARA_MODELS = 'https://router.bynara.id/v1/models';
    private const NARA_CHAT = 'https://router.bynara.id/v1/chat/completions';
    private const OR_MODELS = 'https://openrouter.ai/api/v1/models*';
    private const OR_CHAT = 'https://openrouter.ai/api/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai.provider' => 'nararouter', 'services.ai.fallback_provider' => 'openrouter',
            'services.ai.provider_fallback_enabled' => true, 'services.ai.total_timeout_seconds' => 24,
            'services.ai.model_catalog_cache_seconds' => 600, 'services.ai.model_catalog_stale_seconds' => 86400,
            'services.ai.providers.nararouter' => [
                'base_url' => 'https://router.bynara.id/v1', 'key' => 'nara-test', 'enabled' => true,
                'tool_verification_cache_seconds' => 21600, 'tool_verification_failure_cache_seconds' => 3600,
            ],
            'services.ai.providers.openrouter' => ['base_url' => 'https://openrouter.ai/api/v1', 'key' => 'or-test', 'enabled' => true],
        ]);
        Http::preventStrayRequests();
    }

    // ---------------------------------------------------------------- configuration & selection

    public function test_config_defaults_use_the_documented_base_url_and_no_model_setting(): void
    {
        $config = require base_path('config/services.php');
        $nara = $config['ai']['providers']['nararouter'];

        $this->assertSame('https://router.bynara.id/v1', $nara['base_url']);
        $this->assertArrayNotHasKey('model', $nara);
        $this->assertArrayNotHasKey('models', $nara);
        $this->assertArrayNotHasKey('model', $config['ai']['providers']['openrouter']);
    }

    public function test_production_selects_nararouter_then_openrouter(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame(['nararouter', 'openrouter'], app(CopilotProviderRouter::class)->providers());

        config(['services.ai.provider' => null]);
        $this->assertSame(['nararouter', 'openrouter'], app(CopilotProviderRouter::class)->providers(), 'NaraRouter is the production default');
    }

    public function test_production_never_selects_freellmapi_even_when_its_variables_are_present(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.ai.provider' => 'freellmapi', 'services.ai.fallback_provider' => 'freellmapi',
            'services.freellmapi' => array_merge(config('services.freellmapi'), ['enabled' => true, 'base_url' => 'http://127.0.0.1:31415/v1', 'key' => 'local-key']),
            'services.ai.providers.freellmapi' => ['enabled' => true, 'base_url' => 'http://127.0.0.1:31415/v1', 'key' => 'local-key'],
        ]);

        $this->assertSame(['nararouter'], app(CopilotProviderRouter::class)->providers());
    }

    public function test_missing_or_unsafe_nararouter_configuration_is_skipped(): void
    {
        $this->app['env'] = 'production';

        config(['services.ai.providers.nararouter.key' => '']);
        $this->assertSame(['openrouter'], app(CopilotProviderRouter::class)->providers(), 'missing NARAROUTER_API_KEY');

        config(['services.ai.providers.nararouter.key' => 'nara-test', 'services.ai.providers.nararouter.enabled' => false]);
        $this->assertSame(['openrouter'], app(CopilotProviderRouter::class)->providers(), 'NARAROUTER_ENABLED=false');

        config(['services.ai.providers.nararouter.enabled' => true, 'services.ai.providers.nararouter.base_url' => 'http://localhost:8080/v1']);
        $this->assertSame(['openrouter'], app(CopilotProviderRouter::class)->providers(), 'a local/plain-HTTP base URL is rejected');

        config(['services.ai.providers.nararouter.base_url' => '', 'services.ai.providers.openrouter.key' => null]);
        $this->assertSame([], app(CopilotProviderRouter::class)->providers());
        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $this->replyFailure()->reason);
        Http::assertNothingSent();
    }

    public function test_no_hardcoded_model_ids_or_routing_aliases_in_provider_code_or_config(): void
    {
        $files = array_merge(glob(app_path('Services/Copilot*.php')), [app_path('Services/FreeLlmApiCopilotService.php'), config_path('services.php')]);
        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/openrouter\/auto|auto\/naraya|naraya\.ai|\bgpt-[0-9o]|gemini-|deepseek-|claude-|llama-|qwen|mistral-|kimi-/i', $source, basename($file));
        }
    }

    // ---------------------------------------------------------------- discovery

    public function test_catalogue_and_completions_use_bearer_auth_with_only_the_nararouter_key(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['object' => 'list', 'data' => [['id' => 'nara-chat-a', 'object' => 'model']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('Nara answer'))),
        ]);

        $this->assertSame('Nara answer', $this->reply());

        $catalogue = $this->requestsTo('router.bynara.id/v1/models');
        $this->assertCount(1, $catalogue);
        $this->assertSame(self::NARA_MODELS, $catalogue[0]->url());
        $this->assertSame(['Bearer nara-test'], $catalogue[0]->header('Authorization'));
        foreach ($this->requestsTo('router.bynara.id') as $request) {
            $this->assertSame(['Bearer nara-test'], $request->header('Authorization'), 'the OpenRouter key is never sent to NaraRouter');
        }
        $this->assertSame([], $this->requestsTo('openrouter.ai'));
        $this->assertSame('nara-chat-a', $this->copilotRequests()[0]['model']);
    }

    public function test_alternative_catalogue_shapes_are_parsed_safely(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::sequence()
                ->push(['models' => [['id' => 'models-key-model']]])
                ->push(['bare-list-model']),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('ok'))),
        ]);

        $this->assertSame(['models-key-model'], $this->resolve());
        CopilotProviderModelResolver::forgetCatalog('nararouter');
        $this->assertSame(['bare-list-model'], $this->resolve());
    }

    public function test_empty_and_malformed_catalogues_fail_safely_without_probing(): void
    {
        Http::fake([self::NARA_MODELS => Http::sequence()
            ->push(['object' => 'list', 'data' => []])
            ->push(['unexpected' => 'shape'])
            ->push('<html>oops</html>', 200, ['Content-Type' => 'text/html'])]);

        $empty = $this->resolveFailure();
        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $empty->reason);
        $this->assertSame(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, $empty->getMessage());

        CopilotProviderModelResolver::forgetCatalog('nararouter');
        $this->assertSame(FreeLlmApiCopilotException::INVALID_RESPONSE, $this->resolveFailure()->reason);
        CopilotProviderModelResolver::forgetCatalog('nararouter');
        $this->assertSame(FreeLlmApiCopilotException::INVALID_RESPONSE, $this->resolveFailure()->reason);
        $this->assertSame([], $this->requestsTo('chat/completions'));
    }

    public function test_explicitly_ineligible_and_unavailable_catalogue_entries_are_never_probed(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [
                ['id' => 'embedder', 'type' => 'embedding'],
                ['id' => 'painter', 'output_modalities' => ['image']],
                ['id' => 'listener', 'input_modalities' => ['audio']],
                ['id' => 'no-tools', 'supports_tools' => false],
                ['id' => 'no-functions', 'capabilities' => ['function_calling' => false]],
                ['id' => 'no-tool-param', 'supported_parameters' => ['temperature']],
                ['id' => 'switched-off', 'available' => false],
                ['id' => 'retired-model', 'status' => 'deprecated'],
                ['id' => 'embed-endpoint', 'endpoint' => '/v1/embeddings'],
                ['id' => 'tiny', 'context_length' => 2048],
                ['id' => 'auto/router-alias'],
                ['id' => 'auto'],
                ['id' => 'web-model:online'],
                ['id' => 'bad id with spaces'],
                ['id' => 'vendor/ctx-large', 'context_length' => 200000],
                ['id' => 'reasoner', 'reasoning' => true, 'context_length' => 128000],
                ['id' => 'bare-object'],
                'plain-string',
            ]]),
            // Every probe fails, so the probed order reveals the complete eligible set.
            self::NARA_CHAT => Http::response(self::answer('I will not call tools.')),
        ]);

        $this->resolveFailure();
        $this->resolveFailure();

        $probed = array_map(fn(Request $r) => $r['model'], $this->probeRequests());
        $this->assertSame(['vendor/ctx-large', 'reasoner', 'bare-object', 'plain-string'], $probed);
        $reasoner = collect($this->probeRequests())->first(fn(Request $r) => $r['model'] === 'reasoner');
        $this->assertSame('low', $reasoner['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning_effort', $this->probeRequests()[0]->data());
    }

    // ---------------------------------------------------------------- tool-calling verification

    public function test_only_models_that_pass_the_two_step_tool_probe_are_selected(): void
    {
        $spy = $this->spyDispatcher();
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a-text-only'], ['id' => 'b-tool-capable']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('Served by verified model')), ['a-text-only' => 'text']),
        ]);

        $this->assertSame('Served by verified model', $this->reply());

        $this->assertSame(['b-tool-capable'], array_map(fn(Request $r) => $r['model'], $this->copilotRequests()));
        $this->assertSame([], $spy->calls, 'the probe never reaches the Copilot dispatcher');

        $probes = $this->probeRequests();
        $this->assertSame(['a-text-only', 'b-tool-capable', 'b-tool-capable'], array_map(fn(Request $r) => $r['model'], $probes));
        foreach ($probes as $probe) {
            $this->assertSame('auto', $probe['tool_choice']);
            $this->assertCount(1, $probe['tools']);
            $this->assertSame('function', $probe['tools'][0]['type']);
            $this->assertNotContains(CopilotToolCallingVerifier::PROBE_FUNCTION, app(CopilotCapabilityRegistry::class)->names('admin'));
        }
        $continuation = collect($probes[2]['messages']);
        $this->assertSame('probe-call-1', $continuation->firstWhere('role', 'assistant')['tool_calls'][0]['id']);
        $this->assertSame('probe-call-1', $continuation->firstWhere('role', 'tool')['tool_call_id']);
    }

    public function test_models_failing_argument_or_continuation_checks_are_excluded(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a-bad-arguments'], ['id' => 'b-loops'], ['id' => 'c-empty-followup']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('unused')), [
                'a-bad-arguments' => 'bad_arguments', 'b-loops' => 'loop', 'c-empty-followup' => 'empty_followup',
            ]),
        ]);

        $e = $this->resolveFailure();

        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $e->reason);
        $this->assertSame(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, $e->getMessage());
        $this->assertSame([], $this->copilotRequests());
    }

    public function test_probe_verdicts_are_cached_and_can_be_invalidated(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'nara-a']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('ok'))),
        ]);

        $this->reply();
        $this->reply();
        $this->assertCount(2, $this->probeRequests(), 'one two-step probe for two Copilot requests');
        $this->assertCount(1, $this->requestsTo('router.bynara.id/v1/models'));

        CopilotProviderModelResolver::forgetCatalog('nararouter');
        $this->reply();
        $this->assertCount(4, $this->probeRequests(), 'invalidation drops cached verdicts');
        $this->assertCount(2, $this->requestsTo('router.bynara.id/v1/models'));
    }

    public function test_cold_cache_probes_at_most_three_models_and_stops_at_the_first_success(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => array_map(fn($i) => ['id' => 'model-' . $i], range(1, 6))]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('ok')), ['model-1' => 'text', 'model-2' => 'text', 'model-3' => 'text', 'model-4' => 'text']),
        ]);

        $this->assertSame(FreeLlmApiCopilotException::UNAVAILABLE, $this->resolveFailure()->reason);
        $this->assertSame(['model-1', 'model-2', 'model-3'], array_map(fn(Request $r) => $r['model'], $this->probeRequests()));

        $this->assertSame(['model-5'], $this->resolve(), 'rejected verdicts are cached; probing resumes with the next models');
    }

    public function test_invalid_key_during_probe_stops_probing_and_falls_back_to_openrouter(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']]]),
            self::NARA_CHAT => Http::response(['error' => ['type' => 'unauthorized', 'message' => 'Invalid API key', 'request_id' => 'req_abc123']], 401),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);
        Log::spy();

        $this->assertSame('OpenRouter answer', $this->reply());

        $this->assertCount(1, $this->requestsTo('router.bynara.id/v1/chat/completions'), 'an invalid key is provider-wide: no model rotation');
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider tool-calling verification stopped by a provider-wide failure.'
            && $c['failure_type'] === FreeLlmApiCopilotException::INVALID_CREDENTIALS && $c['provider_request_id'] === 'req_abc123');
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider attempt failed.'
            && $c['provider'] === 'nararouter' && $c['fallback_provider'] === 'openrouter');
    }

    public function test_all_probes_forbidden_is_an_account_restriction_not_an_invalid_key(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a'], ['id' => 'b']]]),
            self::NARA_CHAT => Http::response(['error' => ['type' => 'forbidden', 'message' => 'Account suspended']], 403),
        ]);

        $e = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED, $e->reason);
        $this->assertSame(FreeLlmApiCopilotService::ACCOUNT_PERMISSION_MESSAGE, $e->getMessage());
    }

    // ---------------------------------------------------------------- completion errors & failover

    public function test_catalogue_invalid_key_falls_back_to_openrouter_without_probing(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['error' => ['type' => 'unauthorized', 'message' => 'Missing or invalid API key']], 401),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);

        $this->assertSame('OpenRouter answer', $this->reply());
        $this->assertSame([], $this->requestsTo('router.bynara.id/v1/chat/completions'));
        $orRequests = $this->requestsTo('openrouter.ai/api/v1/chat/completions');
        $this->assertSame(['Bearer or-test'], $orRequests[0]->header('Authorization'), 'the NaraRouter key is never sent to OpenRouter');
        $this->assertSame('vendor/backup', $orRequests[0]['model']);
    }

    public function test_plan_entitlement_403_is_model_scoped_and_never_classified_as_invalid_key(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a-premium'], ['id' => 'b-included']]]),
            self::NARA_CHAT => $this->naraChat(fn(Request $r) => $r['model'] === 'a-premium'
                ? Http::response(['error' => ['type' => 'forbidden', 'message' => 'Your plan does not include this model.', 'request_id' => 'req_plan1']], 403)
                : Http::response(self::answer('Included model answer'))),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);
        Log::spy();

        $this->assertSame('OpenRouter answer', $this->reply(), 'the only verified model was refused: safe provider fallback');
        $this->assertSame('Included model answer', $this->reply(), 'the refused model is excluded and the next model is verified');

        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'NaraRouter Copilot unsuccessful response.'
            && $c['provider'] === 'nararouter' && $c['http_status'] === 403 && $c['selected_model_id'] === 'a-premium'
            && $c['failure_type'] === FreeLlmApiCopilotException::MODEL_PERMISSION_DENIED
            && $c['provider_error_code'] === 'forbidden' && $c['provider_request_id'] === 'req_plan1');
        Log::shouldNotHaveReceived('warning', [\Mockery::any(), \Mockery::on(fn($c) => ($c['failure_type'] ?? null) === FreeLlmApiCopilotException::INVALID_CREDENTIALS)]);
    }

    public function test_suspended_account_403_on_completion_is_account_level(): void
    {
        config(['services.ai.provider_fallback_enabled' => false]);
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(['error' => ['type' => 'forbidden', 'message' => 'Your account is suspended.']], 403)),
        ]);

        $e = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED, $e->reason);
        $this->assertNotSame(FreeLlmApiCopilotException::INVALID_CREDENTIALS, $e->reason);
    }

    public function test_completion_invalid_key_is_classified_and_falls_back(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(['error' => ['type' => 'unauthorized', 'message' => 'Invalid key']], 401)),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);
        Log::spy();

        $this->assertSame('OpenRouter answer', $this->reply());
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'NaraRouter Copilot unsuccessful response.'
            && $c['failure_type'] === FreeLlmApiCopilotException::INVALID_CREDENTIALS && $c['http_status'] === 401);
    }

    public function test_rate_limit_fails_over_to_openrouter_and_pauses_nararouter(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a'], ['id' => 'b']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(['error' => ['type' => 'rate_limited', 'message' => 'Rate limit exceeded. Please retry later.']], 429)),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);

        $this->assertSame('OpenRouter answer', $this->reply());
        $this->assertSame('OpenRouter answer', $this->reply());

        $this->assertCount(1, $this->copilotRequests(), 'a key-wide 429: no model rotation and no request during the cooldown');
    }

    public function test_probe_rate_limit_falls_back_without_caching_a_verdict(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a'], ['id' => 'b']]]),
            self::NARA_CHAT => Http::sequence()
                ->push(['error' => ['type' => 'rate_limited']], 429)
                ->push(self::toolCalls([['probe-call-1', CopilotToolCallingVerifier::PROBE_FUNCTION, '{"status":"ok"}']]))
                ->push(self::answer('Verified.'))
                ->push(self::answer('Nara recovered')),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);

        $this->assertSame('OpenRouter answer', $this->reply());
        $this->assertSame('Nara recovered', $this->reply());
        $this->assertSame(['a', 'a', 'a', 'a'], array_map(fn(Request $r) => $r['model'], $this->requestsTo('router.bynara.id/v1/chat/completions')));
    }

    public function test_completion_timeout_and_outage_fall_back_to_openrouter(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 9001 milliseconds');
            }),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter after timeout')),
        ]);

        $this->assertSame('OpenRouter after timeout', $this->reply());
    }

    public function test_catalogue_outage_falls_back_to_openrouter(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['error' => ['type' => 'service_unavailable']], 503),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
            self::OR_CHAT => Http::response(self::answer('OpenRouter answer')),
        ]);

        $this->assertSame('OpenRouter answer', $this->reply());
    }

    public function test_validation_error_is_not_rotated_or_failed_over(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(['error' => ['type' => 'validation_error', 'message' => 'Request blocked.']], 400)),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
        ]);

        $e = $this->replyFailure();

        $this->assertSame(FreeLlmApiCopilotException::INVALID_REQUEST, $e->reason);
        $this->assertSame(FreeLlmApiCopilotService::INVALID_REQUEST_MESSAGE, $e->getMessage());
        $this->assertSame([], $this->requestsTo('openrouter.ai'));
    }

    public function test_successful_response_never_fails_over(): void
    {
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(fn() => Http::response(self::answer('Nara answer'))),
        ]);

        $this->assertSame('Nara answer', $this->reply());
        $this->assertSame([], $this->requestsTo('openrouter.ai'));
    }

    // ---------------------------------------------------------------- tool calling through the Copilot

    public function test_tool_call_round_trip_executes_each_tool_once_and_continues_on_the_same_model(): void
    {
        $spy = $this->spyDispatcher();
        $copilot = 0;
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'nara-reasoner', 'reasoning' => true]]]),
            self::NARA_CHAT => $this->naraChat(function () use (&$copilot) {
                return ++$copilot === 1
                    ? Http::response(self::toolCalls([['call-a', 'get_my_profile'], ['call-b', 'get_my_applications', '{"limit":3}']]))
                    : Http::response(self::answer('Both done'));
            }),
        ]);

        $this->assertSame('Both done', $this->reply('show my profile and applications'));

        $this->assertSame(['get_my_profile', 'get_my_applications'], $spy->calls);
        $this->assertSame([[], ['limit' => 3]], $spy->arguments);
        $requests = $this->copilotRequests();
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame('nara-reasoner', $request['model']);
            $this->assertSame('auto', $request['tool_choice']);
            $this->assertSame('function', $request['tools'][0]['type']);
            $this->assertSame('low', $request['reasoning_effort'], 'the request shape the probe verified');
            $this->assertArrayNotHasKey('parallel_tool_calls', $request->data());
            $this->assertArrayNotHasKey('reasoning', $request->data());
        }
        $tools = collect($requests[1]['messages'])->where('role', 'tool')->values();
        $this->assertSame(['call-a', 'call-b'], $tools->pluck('tool_call_id')->all());
        $this->assertSame([], $this->requestsTo('openrouter.ai'));
    }

    public function test_failure_after_tool_execution_never_fails_over_or_repeats_the_tool(): void
    {
        $spy = $this->spyDispatcher();
        $copilot = 0;
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(function () use (&$copilot) {
                return ++$copilot === 1
                    ? Http::response(self::toolCalls([['call-1', 'get_my_profile']]))
                    : Http::response(['error' => ['type' => 'service_unavailable']], 503);
            }),
            self::OR_MODELS => Http::response(['data' => [self::orModel('vendor/backup')]]),
        ]);
        Log::spy();

        $e = $this->replyFailure();

        $this->assertFalse($e->fallbackSafe);
        $this->assertSame(['get_my_profile'], $spy->calls, 'the tool ran exactly once');
        $this->assertSame([], $this->requestsTo('openrouter.ai'), 'no failover after a tool executed');
        Log::shouldHaveReceived('warning')->withArgs(fn(string $m, array $c) => $m === 'Copilot provider attempt failed.'
            && $c['provider'] === 'nararouter' && $c['fallback_blocked_reason'] === 'tool_already_executed');
    }

    public function test_model_cannot_call_tools_outside_its_role(): void
    {
        $spy = $this->spyDispatcher();
        $copilot = 0;
        Http::fake([
            self::NARA_MODELS => Http::response(['data' => [['id' => 'a']]]),
            self::NARA_CHAT => $this->naraChat(function () use (&$copilot) {
                return ++$copilot === 1
                    ? Http::response(self::toolCalls([['call-1', 'suspend_user', '{"user_id":1}'], ['call-2', CopilotToolCallingVerifier::PROBE_FUNCTION, '{"status":"ok"}']]))
                    : Http::response(self::answer('Not allowed'));
            }),
        ]);

        $this->assertSame('Not allowed', $this->reply());

        $this->assertSame([], $spy->calls, 'admin-only and probe tools are never dispatched for a job seeker');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Fakes NaraRouter chat completions: requests carrying the probe function are answered per the
     * model's behaviour (default: a valid probe round trip); Copilot requests go to $copilot.
     */
    private function naraChat(callable $copilot, array $probeBehaviour = []): \Closure
    {
        return function (Request $request) use ($copilot, $probeBehaviour) {
            if (!self::isProbe($request)) return $copilot($request);

            $continuation = collect($request['messages'])->contains('role', 'tool');
            $behaviour = $probeBehaviour[$request['model']] ?? 'valid';
            $call = fn(string $arguments) => Http::response(self::toolCalls([['probe-call-1', CopilotToolCallingVerifier::PROBE_FUNCTION, $arguments]]));

            return match ($behaviour) {
                'text' => Http::response(self::answer('I cannot call functions.')),
                'bad_arguments' => $call('not json'),
                'loop' => $call('{"status":"ok"}'),
                'empty_followup' => $continuation ? Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => '']]]]) : $call('{"status":"ok"}'),
                default => $continuation ? Http::response(self::answer('Verified.')) : $call('{"status":"ok"}'),
            };
        };
    }

    private static function isProbe(Request $request): bool
    {
        return ($request['tools'][0]['function']['name'] ?? null) === CopilotToolCallingVerifier::PROBE_FUNCTION;
    }

    /** @return list<Request> */
    private function probeRequests(): array
    {
        return array_values(array_filter($this->requestsTo('router.bynara.id/v1/chat/completions'), fn(Request $r) => self::isProbe($r)));
    }

    /** @return list<Request> */
    private function copilotRequests(): array
    {
        return array_values(array_filter($this->requestsTo('router.bynara.id/v1/chat/completions'), fn(Request $r) => !self::isProbe($r)));
    }

    private function resolve(): array
    {
        return app(CopilotProviderModelResolver::class)->resolve('nararouter', config('services.ai.providers.nararouter'), microtime(true) + 20);
    }

    private function resolveFailure(): FreeLlmApiCopilotException
    {
        try {
            $this->resolve();
        } catch (FreeLlmApiCopilotException $e) {
            return $e;
        }
        $this->fail('Expected a model resolution failure.');
    }

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

    /** @return list<Request> */
    private function requestsTo(string $fragment): array
    {
        return Http::recorded()->pluck(0)->filter(fn(Request $r) => str_contains($r->url(), $fragment))->values()->all();
    }

    private function spyDispatcher(): object
    {
        $spy = new class(app(CopilotCapabilityRegistry::class), request()) extends CopilotToolDispatcher {
            public array $calls = [];
            public array $arguments = [];
            public function dispatch(string $role, mixed $actor, string $name, array $args): array
            {
                $this->calls[] = $name;
                $this->arguments[] = $args;
                return ['ok' => true];
            }
        };
        $this->app->instance(CopilotToolDispatcher::class, $spy);
        return $spy;
    }

    private static function answer(string $text): array
    {
        return ['id' => 'chatcmpl-test', 'object' => 'chat.completion', 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']]];
    }

    /** @param list<array{0: string, 1: string, 2?: string}> $calls [id, name, arguments] */
    private static function toolCalls(array $calls): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => array_map(
            fn(array $c) => ['id' => $c[0], 'type' => 'function', 'function' => ['name' => $c[1], 'arguments' => $c[2] ?? '{}']],
            $calls
        )], 'finish_reason' => 'tool_calls']]];
    }

    private static function orModel(string $id): array
    {
        return [
            'id' => $id, 'context_length' => 200000,
            'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
            'supported_parameters' => ['max_tokens', 'temperature', 'tool_choice', 'tools'],
        ];
    }
}
