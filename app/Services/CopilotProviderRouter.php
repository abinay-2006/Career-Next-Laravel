<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Selects the Copilot AI provider and permits cross-provider failover only before any tool ran.
 *
 * Production: OpenRouter -> NavyAI (both with models discovered from their live catalogues).
 * Local development: the FreeLLMAPI gateway with its curated model chain. FreeLLMAPI is a local
 * gateway and is never contacted when APP_ENV=production.
 */
class CopilotProviderRouter
{
    public const PROVIDERS = ['openrouter', 'navyai', 'freellmapi'];

    /** Seconds kept back for the next provider while an earlier provider is still being tried. */
    private const FALLBACK_RESERVE_SECONDS = 10.0;
    /** Never reserve more than this share of the remaining budget for later providers. */
    private const MAX_RESERVE_SHARE = 0.45;

    public function __construct(
        private readonly FreeLlmApiCopilotService $completion,
        private readonly CopilotProviderModelResolver $modelResolver
    ) {}

    public function reply(string $role, string $message, mixed $actor, CopilotCapabilityRegistry $registry, CopilotToolDispatcher $dispatcher, array $history = []): string
    {
        $providers = $this->providers();
        if ($providers === []) {
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);
        }

        $hardDeadline = microtime(true) + max(1, (int) config('services.ai.total_timeout_seconds', 24));
        $failures = [];
        $last = null;

        foreach ($providers as $index => $provider) {
            $next = $providers[$index + 1] ?? null;
            $remaining = $hardDeadline - microtime(true);
            if ($remaining < 1) {
                throw $last ?? new FreeLlmApiCopilotException(FreeLlmApiCopilotService::TIMEOUT_MESSAGE, FreeLlmApiCopilotException::TIMEOUT);
            }

            // Before any tool runs, a provider may use only its share of the budget, so a slow or
            // failing first provider cannot leave the fallback provider without time.
            $reserve = $next === null ? 0.0 : min(self::FALLBACK_RESERVE_SECONDS, $remaining * self::MAX_RESERVE_SHARE);
            $softDeadline = microtime(true) + $remaining - $reserve;

            $block = $this->block($provider);
            $old = config('services.freellmapi');
            $stage = 'model_discovery';
            $attemptStarted = microtime(true);

            try {
                $models = $provider === 'freellmapi'
                    ? null
                    : $this->modelResolver->resolve($provider, $block, $softDeadline);

                $stage = 'completion';
                config(['services.freellmapi' => array_merge(is_array($old) ? $old : [], [
                    'base_url' => $block['base_url'] ?? '',
                    'key' => $block['key'] ?? '',
                    'enabled' => $block['enabled'] ?? false,
                    'provider_name' => $provider,
                    'models' => $models === null
                        ? ($old['models'] ?? ['primary' => $block['model'] ?? ''])
                        : array_combine(array_slice(['primary', 'secondary', 'tertiary'], 0, count($models)), $models),
                    'model_fallback_enabled' => $models === null ? ($old['model_fallback_enabled'] ?? true) : true,
                    'max_model_attempts' => $models === null ? ($old['max_model_attempts'] ?? FreeLlmApiCopilotService::MAX_CONFIGURED_MODELS) : count($models),
                    'model_cooldown_default_seconds' => $block['cooldown_default_seconds'] ?? 60,
                    'model_cooldown_max_seconds' => $block['cooldown_max_seconds'] ?? 300,
                    'total_timeout_seconds' => max(1.0, $softDeadline - microtime(true)),
                    // After a tool executes the provider is pinned and may use the full remaining budget.
                    'hard_deadline' => $hardDeadline,
                    'request_timeout_seconds' => $block['request_timeout_seconds'] ?? 9,
                    'cooldown_namespace' => $provider,
                ])]);

                $reply = $this->completion->reply($role, $message, $actor, $registry, $dispatcher, $history);

                if ($failures !== []) {
                    Log::info('Copilot provider fallback succeeded.', [
                        'provider' => $provider,
                        'failed_providers' => $failures,
                        'elapsed_ms' => self::elapsedMs($attemptStarted),
                    ]);
                }

                return $reply;
            } catch (FreeLlmApiCopilotException $e) {
                $last = $e;
                $failures[$provider] = $stage . ':' . $e->reason;

                // Every failure before a tool executed is safe to retry elsewhere: nothing has changed
                // and the next provider is a different service. After a tool executed the exception is
                // marked fallback-unsafe, so the operation is never replayed on another provider.
                $blockedReason = match (true) {
                    !$e->fallbackSafe => 'tool_already_executed',
                    $next === null => 'no_more_providers',
                    $hardDeadline - microtime(true) < 1 => 'time_budget_exhausted',
                    default => null,
                };

                Log::warning('Copilot provider attempt failed.', [
                    'provider' => $provider,
                    'operation' => $stage,
                    'failure_type' => $e->reason,
                    'http_status' => $e->providerStatus,
                    'elapsed_ms' => self::elapsedMs($attemptStarted),
                    'fallback_provider' => $blockedReason === null ? $next : null,
                    'fallback_blocked_reason' => $blockedReason,
                ]);

                if ($blockedReason !== null) {
                    if (count($failures) > 1) {
                        Log::warning('Copilot all AI providers failed.', ['failed_providers' => $failures]);

                        $failureReasons = array_map(static fn(string $failure): string => (string) substr(strrchr($failure, ':'), 1), array_values($failures));
                        $authorizationReasons = [
                            FreeLlmApiCopilotException::INVALID_CREDENTIALS,
                            FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED,
                        ];
                        if (count(array_diff($failureReasons, $authorizationReasons)) === 0) {
                            $distinctReasons = array_values(array_unique($failureReasons));
                            $reason = count($distinctReasons) === 1
                                ? $distinctReasons[0]
                                : FreeLlmApiCopilotException::PROVIDERS_REJECTED_ACCESS;
                            $message = match ($reason) {
                                FreeLlmApiCopilotException::INVALID_CREDENTIALS => FreeLlmApiCopilotService::AUTHENTICATION_MESSAGE,
                                FreeLlmApiCopilotException::ACCOUNT_PERMISSION_DENIED => FreeLlmApiCopilotService::ACCOUNT_PERMISSION_MESSAGE,
                                default => FreeLlmApiCopilotService::PROVIDERS_REJECTED_ACCESS_MESSAGE,
                            };

                            throw new FreeLlmApiCopilotException($message, $reason, $e, null, $e->providerStatus);
                        }
                    }
                    throw $e;
                }
            } finally {
                config(['services.freellmapi' => $old]);
            }
        }

        throw $last ?? new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);
    }

    /**
     * Ordered, de-duplicated providers that are enabled, have a key and a usable base URL.
     *
     * @return list<string>
     */
    public function providers(): array
    {
        $production = app()->environment('production');
        $selected = strtolower(trim((string) config('services.ai.provider', $production ? 'openrouter' : 'freellmapi')));
        $fallback = strtolower(trim((string) config('services.ai.fallback_provider', '')));

        if ($production && $selected === 'freellmapi') {
            Log::warning('Copilot ignored FreeLLMAPI as the primary provider in production.', ['provider' => 'freellmapi', 'replacement' => 'openrouter']);
            $selected = 'openrouter';
        }

        $order = [$selected];
        if (filter_var(config('services.ai.provider_fallback_enabled', false), FILTER_VALIDATE_BOOL) && $fallback !== '') {
            $order[] = $fallback;
        }

        $usable = [];
        foreach (array_unique($order) as $provider) {
            $skip = $this->skipReason($provider, $production);
            if ($skip !== null) {
                Log::warning('Copilot AI provider skipped.', ['provider' => in_array($provider, self::PROVIDERS, true) ? $provider : 'unknown', 'reason' => $skip]);
                continue;
            }
            $usable[] = $provider;
        }

        if ($usable === []) {
            Log::warning('Copilot has no usable AI provider configured.', ['environment' => $production ? 'production' : 'non_production']);
        }

        return $usable;
    }

    private function skipReason(string $provider, bool $production): ?string
    {
        if (!in_array($provider, self::PROVIDERS, true)) return 'unknown_provider';
        if ($provider === 'freellmapi' && $production) return 'local_gateway_disabled_in_production';

        $block = $this->block($provider);
        if (!filter_var($block['enabled'] ?? false, FILTER_VALIDATE_BOOL)) return 'not_enabled';
        if (!is_string($block['key'] ?? null) || trim($block['key']) === '') return 'missing_api_key';

        $baseUrl = rtrim((string) ($block['base_url'] ?? ''), '/');
        if ($provider === 'freellmapi') {
            return in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true) && parse_url($baseUrl, PHP_URL_HOST) !== null
                ? null : 'invalid_base_url';
        }

        return CopilotProviderModelResolver::isSafeHostedBaseUrl($baseUrl) ? null : 'invalid_base_url';
    }

    private function block(string $provider): array
    {
        $block = $provider === 'freellmapi'
            ? array_merge((array) config('services.ai.providers.freellmapi', []), (array) config('services.freellmapi', []))
            : config('services.ai.providers.' . $provider);

        return is_array($block) ? $block : [];
    }

    private static function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
