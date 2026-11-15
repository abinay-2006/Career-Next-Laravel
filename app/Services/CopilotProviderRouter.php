<?php

namespace App\Services;

/** Selects the configured backend and permits one pre-action cross-provider failover. */
class CopilotProviderRouter
{
    public function __construct(
        private readonly FreeLlmApiCopilotService $completion,
        private readonly CopilotProviderModelResolver $modelResolver
    ) {}

    public function reply(string $role, string $message, mixed $actor, CopilotCapabilityRegistry $registry, CopilotToolDispatcher $dispatcher, array $history = []): string
    {
        $selected = (string) config('services.ai.provider', app()->environment('production') ? 'openrouter' : 'freellmapi');
        $providers = [$selected];
        $fallback = (string) config('services.ai.fallback_provider', $selected === 'openrouter' ? 'navyai' : '');
        if ((bool) config('services.ai.provider_fallback_enabled', true) && $fallback !== '' && $fallback !== $selected) $providers[] = $fallback;
        $providers = array_values(array_filter($providers, fn(string $name): bool => (bool) ($name === 'freellmapi' ? config('services.freellmapi.enabled', false) : config('services.ai.providers.'.$name.'.enabled', false))
            && is_string($name === 'freellmapi' ? config('services.freellmapi.key') : config('services.ai.providers.'.$name.'.key'))
            && ($name === 'freellmapi' ? config('services.freellmapi.key') : config('services.ai.providers.'.$name.'.key')) !== ''));
        if ($providers === []) throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);

        $started = microtime(true);
        $deadline = $started + max(1, (int) config('services.ai.total_timeout_seconds', 24));
        $last = null;
        foreach ($providers as $index => $provider) {
            $block = $provider === 'freellmapi' ? array_merge((array) config('services.ai.providers.freellmapi', []), (array) config('services.freellmapi', [])) : config('services.ai.providers.'.$provider);
            if (!is_array($block)) continue;
            $remaining = $deadline - microtime(true);
            if ($remaining < 1) throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::TIMEOUT_MESSAGE, FreeLlmApiCopilotException::TIMEOUT);
            $old = config('services.freellmapi');
            $localProvider = $provider === 'freellmapi';
            try {
                $model = match ($provider) {
                    'openrouter' => 'openrouter/auto',
                    'navyai' => $this->modelResolver->resolveNavyAi($block, $deadline),
                    default => $block['model'] ?? '',
                };
                $remaining = max(1, $deadline - microtime(true));
                config(['services.freellmapi' => array_merge($old, [
                    'base_url' => $block['base_url'] ?? '', 'key' => $block['key'] ?? '', 'enabled' => $block['enabled'] ?? false,
                    'provider_name' => $provider,
                    'models' => $localProvider ? ($old['models'] ?? ['primary' => $model]) : ['primary' => $model],
                    'model_fallback_enabled' => $localProvider ? ($old['model_fallback_enabled'] ?? true) : false,
                    'model_cooldown_default_seconds' => $block['cooldown_default_seconds'] ?? 60,
                    'model_cooldown_max_seconds' => $block['cooldown_max_seconds'] ?? 300,
                    'total_timeout_seconds' => $remaining,
                    'request_timeout_seconds' => $block['request_timeout_seconds'] ?? 9,
                    'cooldown_namespace' => $provider,
                    'auto_router_cost_tier' => $block['auto_router_cost_tier'] ?? 'max',
                ])]);
                return $this->completion->reply($role, $message, $actor, $registry, $dispatcher, $history);
            } catch (FreeLlmApiCopilotException $e) {
                $last = $e;
                $eligible = in_array($e->reason, [FreeLlmApiCopilotException::CONNECTION, FreeLlmApiCopilotException::TIMEOUT, FreeLlmApiCopilotException::UPSTREAM, FreeLlmApiCopilotException::RATE_LIMITED, FreeLlmApiCopilotException::QUOTA], true)
                    || in_array($e->reason, [FreeLlmApiCopilotException::AUTHENTICATION, FreeLlmApiCopilotException::PERMISSION], true)
                    || ($e->modelSpecific && in_array($e->reason, [FreeLlmApiCopilotException::INVALID_RESPONSE, FreeLlmApiCopilotException::EMPTY_COMPLETION, FreeLlmApiCopilotException::UNAVAILABLE], true));
                if ($index + 1 >= count($providers) || !$eligible || !$e->fallbackSafe) throw $e;
            } finally {
                config(['services.freellmapi' => $old]);
            }
        }
        throw $last ?? new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);
    }
}
