<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Discovers tool-capable chat models from a hosted provider's live catalogue (OpenRouter, NavyAI).
 *
 * No model ID is configured or hard-coded: every request resolves a short ranked chain (at most
 * three IDs) from the provider's published capability metadata. Catalogues are cached briefly; a
 * longer-lived "last known good" copy is used only when a fresh catalogue cannot be fetched.
 * Cache entries hold model IDs and ranking flags only, never keys or raw provider bodies.
 */
class CopilotProviderModelResolver
{
    public const MAX_CANDIDATES = FreeLlmApiCopilotService::MAX_CONFIGURED_MODELS;

    public const HOSTED_PROVIDERS = ['openrouter', 'navyai'];

    /** Same allowlist the completion service enforces, so every resolved ID is also accepted there. */
    private const MODEL_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/@+-]{0,127}$/';

    private const CATALOG_TIMEOUT_SECONDS = 6;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    /** The Copilot sends a system prompt, tool schemas and up to ten history turns. */
    private const MIN_CONTEXT_TOKENS = 16384;
    private const DEFAULT_DENIAL_SECONDS = 300;

    /** @return list<string> ranked model IDs, best first */
    public function resolve(string $provider, array $block, float $deadline): array
    {
        if (!in_array($provider, self::HOSTED_PROVIDERS, true)) {
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);
        }

        $baseUrl = rtrim((string) ($block['base_url'] ?? ''), '/');
        if (!self::isSafeHostedBaseUrl($baseUrl)) {
            Log::warning('Copilot provider has an unsafe or missing base URL.', ['provider' => $provider, 'operation' => 'model_discovery', 'failure_type' => 'invalid_configuration']);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE);
        }

        $candidates = $this->candidates($provider, $baseUrl, (string) ($block['key'] ?? ''), $block, $deadline);
        if ($provider === 'navyai' && ($block['check_model_availability'] ?? true)) {
            $candidates = $this->filterNavyAvailability($candidates, $baseUrl, $deadline);
        }
        $available = array_values(array_filter($candidates, fn(array $c): bool => !$this->isDenied($provider, $c['id'])));

        if ($available === []) {
            Log::warning('Copilot provider catalogue contains no eligible tool-capable model.', [
                'provider' => $provider, 'operation' => 'model_discovery', 'failure_type' => 'no_eligible_model',
                'eligible_models' => count($candidates), 'temporarily_denied' => count($candidates) - count($available),
            ]);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE, null, null, null, true);
        }

        // Catalogues cannot prove that an API account can invoke every listed model. Keep this
        // ordered chain capability-based; runtime authorization failures prune a model separately.
        $chain = array_slice(array_column($available, 'id'), 0, self::MAX_CANDIDATES);
        Log::info('Copilot provider models resolved.', ['provider' => $provider, 'operation' => 'model_discovery', 'models' => $chain, 'eligible_models' => count($available)]);

        return $chain;
    }

    /** Backwards-compatible single-model resolution for NavyAI. */
    public function resolveNavyAi(array $provider, float $deadline): string
    {
        return $this->resolve('navyai', $provider, $deadline)[0];
    }

    /**
     * Remembers, for a short time, that a provider refused one specific model (for example a model
     * gated behind a plan the account does not have), so later requests resolve a different model.
     */
    public static function markDenied(string $provider, string $model, ?int $seconds = null): void
    {
        try {
            Cache::put(self::denialKey($provider, $model), true, max(1, min(3600, $seconds ?? self::DEFAULT_DENIAL_SECONDS)));
        } catch (\Throwable) {
            // A cache failure must never turn a provider error into a server error.
        }
    }

    public static function forgetCatalog(string $provider): void
    {
        try {
            Cache::forget(self::catalogKey($provider, 'fresh'));
            Cache::forget(self::catalogKey($provider, 'stale'));
        } catch (\Throwable) {
        }
    }

    /** Hosted providers must be reached over HTTPS on a public host name, never a local gateway. */
    public static function isSafeHostedBaseUrl(string $baseUrl): bool
    {
        if ($baseUrl === '' || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') return false;
        $host = parse_url($baseUrl, PHP_URL_HOST);
        return is_string($host) && $host !== '' && !self::isLocalHost($host);
    }

    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return true;
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }
        return false;
    }

    /** @return list<array{id: string}> ranked eligible candidates */
    private function candidates(string $provider, string $baseUrl, string $key, array $block, float $deadline): array
    {
        $fingerprint = hash('sha256', json_encode([
            $baseUrl, hash('sha256', $key), self::eligibilitySettings($provider, $block),
            (bool) ($block['check_model_availability'] ?? true),
        ]));
        $fresh = $this->cacheGet(self::catalogKey($provider, 'fresh'));
        if (is_array($fresh) && ($fresh['fingerprint'] ?? null) === $fingerprint && is_array($fresh['candidates'] ?? null)) {
            return $fresh['candidates'];
        }

        try {
            $candidates = $this->fetchCandidates($provider, $baseUrl, $key, $block, $deadline);
        } catch (FreeLlmApiCopilotException $e) {
            $stale = $this->cacheGet(self::catalogKey($provider, 'stale'));
            $authorizationFailure = in_array($e->reason, [FreeLlmApiCopilotException::AUTHENTICATION, FreeLlmApiCopilotException::PERMISSION], true);
            if (!$authorizationFailure && is_array($stale) && ($stale['fingerprint'] ?? null) === $fingerprint && is_array($stale['candidates'] ?? null) && $stale['candidates'] !== []) {
                Log::warning('Copilot provider catalogue unavailable; using last known good catalogue.', [
                    'provider' => $provider, 'operation' => 'model_discovery', 'failure_type' => $e->reason,
                    'http_status' => $e->providerStatus, 'catalogue_age_seconds' => max(0, time() - (int) ($stale['fetched_at'] ?? time())),
                ]);
                return $stale['candidates'];
            }
            throw $e;
        }

        $entry = ['fingerprint' => $fingerprint, 'candidates' => $candidates, 'fetched_at' => time()];
        $this->cachePut(self::catalogKey($provider, 'fresh'), $entry, max(0, (int) config('services.ai.model_catalog_cache_seconds', 600)));
        if ($candidates !== []) {
            $this->cachePut(self::catalogKey($provider, 'stale'), $entry, max(0, (int) config('services.ai.model_catalog_stale_seconds', 86400)));
        }

        return $candidates;
    }

    /** @return list<array{id: string}> */
    private function fetchCandidates(string $provider, string $baseUrl, string $key, array $block, float $deadline): array
    {
        $remaining = $deadline - microtime(true);
        if ($remaining < 1) {
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::TIMEOUT_MESSAGE, FreeLlmApiCopilotException::TIMEOUT);
        }

        $startedAt = microtime(true);
        // OpenRouter filters its catalogue server side; eligibility is still verified below.
        $query = $provider === 'openrouter' ? ['supported_parameters' => 'tools'] : [];
        // OpenRouter's user catalog applies account-level provider preferences and guardrails.
        // It still cannot guarantee upstream model access, which is handled on completion errors.
        $catalogPath = $provider === 'openrouter' ? '/models/user' : '/models';

        try {
            $request = Http::acceptJson()->timeout(min(self::CATALOG_TIMEOUT_SECONDS, $remaining))->connectTimeout(min(self::CONNECT_TIMEOUT_SECONDS, $remaining));
            // The key only ever goes to the provider's own HTTPS host (checked in isSafeHostedBaseUrl).
            if ($key !== '') $request = $request->withToken($key);
            $response = $request->get($baseUrl . $catalogPath, $query);
        } catch (ConnectionException $e) {
            $text = strtolower($e->getMessage());
            $isTimeout = str_contains($text, 'curl error 28') || str_contains($text, 'timed out') || str_contains($text, 'timeout');
            $tls = str_contains($text, 'ssl') || str_contains($text, 'certificate') || str_contains($text, 'curl error 60');
            $dns = str_contains($text, 'could not resolve') || str_contains($text, 'curl error 6:');
            Log::warning('Copilot provider catalogue request failed.', [
                'provider' => $provider, 'operation' => 'model_discovery',
                'failure_type' => $isTimeout ? 'timeout' : ($tls ? 'tls' : ($dns ? 'dns' : 'connection')),
                'exception' => $e::class, 'elapsed_ms' => self::elapsedMs($startedAt),
            ]);
            throw new FreeLlmApiCopilotException(
                $isTimeout ? FreeLlmApiCopilotService::TIMEOUT_MESSAGE : FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE,
                $isTimeout ? FreeLlmApiCopilotException::TIMEOUT : FreeLlmApiCopilotException::CONNECTION,
                $e
            );
        }

        if (!$response->successful()) {
            $status = $response->status();
            $edgeBlocked = self::isEdgeBlock($response);
            $reason = match (true) {
                $edgeBlocked => FreeLlmApiCopilotException::UPSTREAM,
                $status === 401 => FreeLlmApiCopilotException::AUTHENTICATION,
                $status === 403 => FreeLlmApiCopilotException::PERMISSION,
                $status === 429 => FreeLlmApiCopilotException::RATE_LIMITED,
                $status >= 500 => FreeLlmApiCopilotException::UPSTREAM,
                default => FreeLlmApiCopilotException::UNAVAILABLE,
            };
            Log::warning('Copilot provider catalogue returned an unsuccessful response.', [
                'provider' => $provider, 'operation' => 'model_discovery', 'http_status' => $status,
                'failure_type' => $edgeBlocked ? 'edge_blocked' : $reason, 'provider_error_code' => self::safeErrorCode($response), 'content_type' => self::contentType($response),
                'edge' => self::edgeName($response), 'elapsed_ms' => self::elapsedMs($startedAt),
            ]);
            $message = match ($reason) {
                FreeLlmApiCopilotException::AUTHENTICATION => FreeLlmApiCopilotService::AUTHENTICATION_MESSAGE,
                FreeLlmApiCopilotException::PERMISSION => FreeLlmApiCopilotService::ACCOUNT_PERMISSION_MESSAGE,
                default => FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE,
            };
            throw new FreeLlmApiCopilotException($message, $reason, null, null, $status);
        }

        $items = $response->json('data');
        if (!is_array($items) || !array_is_list($items)) {
            Log::warning('Copilot provider catalogue had an invalid structure.', [
                'provider' => $provider, 'operation' => 'model_discovery', 'http_status' => $response->status(),
                'failure_type' => 'invalid_catalogue', 'content_type' => self::contentType($response),
                'edge' => self::edgeName($response), 'elapsed_ms' => self::elapsedMs($startedAt),
            ]);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE, FreeLlmApiCopilotException::INVALID_RESPONSE, null, null, $response->status());
        }

        $candidates = $provider === 'openrouter' ? $this->rankOpenRouter($items, $block) : $this->rankNavyAi($items, $block);

        Log::info('Copilot provider catalogue fetched.', [
            'provider' => $provider, 'operation' => 'model_discovery', 'listed_models' => count($items),
            'eligible_models' => count($candidates), 'elapsed_ms' => self::elapsedMs($startedAt),
        ]);

        return $candidates;
    }

    /** Apply NavyAI's public health metadata only where it explicitly reports a model is unhealthy. */
    private function filterNavyAvailability(array $candidates, string $baseUrl, float $deadline): array
    {
        $remaining = $deadline - microtime(true);
        if ($remaining < 1) return $candidates;

        try {
            $request = Http::acceptJson()->timeout(min(2, $remaining))->connectTimeout(min(1, $remaining));
            // The documented status endpoint is public; do not send credentials to it.
            $response = $request->get($baseUrl . '/models/status');
        } catch (ConnectionException $e) {
            Log::notice('NavyAI model availability metadata could not be fetched.', [
                'provider' => 'navyai', 'operation' => 'model_availability', 'failure_type' => 'connection',
            ]);
            return $candidates;
        }

        $models = $response->successful() ? $response->json('models') : null;
        if (!is_array($models)) {
            Log::notice('NavyAI model availability metadata is unavailable or malformed.', [
                'provider' => 'navyai', 'operation' => 'model_availability',
                'http_status' => $response->status(), 'failure_type' => 'availability_metadata_unavailable',
                'provider_error_code' => self::safeErrorCode($response),
            ]);
            return $candidates;
        }

        $filtered = array_values(array_filter($candidates, static function (array $candidate) use ($models): bool {
            $status = $models[$candidate['id']]['lastStatus'] ?? null;
            return !is_string($status) || strtolower($status) === 'ok';
        }));

        Log::info('NavyAI model availability metadata applied.', [
            'provider' => 'navyai', 'operation' => 'model_availability',
            'listed_candidates' => count($candidates), 'available_candidates' => count($filtered),
        ]);

        return $filtered;
    }

    private static function safeErrorCode(Response $response): ?string
    {
        try {
            $json = json_decode($response->body(), true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        $error = is_array($json) && is_array($json['error'] ?? null) ? $json['error'] : [];
        $metadata = is_array($error['metadata'] ?? null) ? $error['metadata'] : [];
        $numericCode = null;
        foreach ([$error['code'] ?? null, $error['type'] ?? null, $metadata['code'] ?? null, $metadata['type'] ?? null] as $code) {
            if (is_int($code) && $code >= 0 && $code <= 9999) $code = (string) $code;
            if (!is_string($code) || preg_match('/^[A-Za-z0-9_.:\/@+-]{1,96}$/', $code) !== 1) continue;
            if (!ctype_digit($code)) return strtolower($code);
            $numericCode ??= $code;
        }
        return $numericCode;
    }

    /** Select only on explicit chat/tool compatibility metadata, never price or inferred account access. */
    private function rankOpenRouter(array $items, array $block): array
    {
        $eligible = [];

        foreach ($items as $model) {
            if (!is_array($model) || !self::validId($model['id'] ?? null)) continue;
            $id = $model['id'];
            if (str_starts_with($id, 'openrouter/') || !empty($model['alias_target'])) continue;

            $parameters = $model['supported_parameters'] ?? null;
            if (!is_array($parameters) || !in_array('tools', $parameters, true) || !in_array('tool_choice', $parameters, true)) continue;

            $input = $model['architecture']['input_modalities'] ?? null;
            $output = $model['architecture']['output_modalities'] ?? null;
            if (!is_array($input) || !in_array('text', $input, true) || $output !== ['text']) continue;

            $endpoints = $model['endpoints'] ?? null;
            if (is_array($endpoints) && !in_array('/api/v1/chat/completions', $endpoints, true)) continue;

            $expires = $model['expiration_date'] ?? null;
            if (is_string($expires) && $expires !== '' && (strtotime($expires) === false || strtotime($expires) <= time())) continue;

            $context = $model['context_length'] ?? null;
            if (!is_int($context) || $context < self::MIN_CONTEXT_TOKENS) continue;
            $eligible[] = [
                'id' => $id,
                'rank' => [$context ?? 0],
            ];
        }

        return self::sorted($eligible);
    }

    /**
     * NavyAI: use explicit capability, plan, endpoint and current availability metadata only.
     *
     * @return list<array{id: string}>
     */
    private function rankNavyAi(array $items, array $block): array
    {
        $settings = self::eligibilitySettings('navyai', $block);
        $eligible = [];

        foreach ($items as $model) {
            if (!is_array($model) || !self::validId($model['id'] ?? null)) continue;
            if (!in_array($model['endpoint'] ?? null, ['/v1/chat/completions', '/chat/completions', 'chat/completions'], true)) continue;
            if (($model['supports_tools'] ?? null) !== true && ($model['supports_function_calling'] ?? null) !== true) continue;
            // Unknown metadata is treated conservatively: its capability flags are not trusted.
            if (($model['metadata_status'] ?? null) !== 'known') continue;

            $output = $model['output_modalities'] ?? null;
            if ((is_array($output) && $output !== ['text']) || ($model['supports_image_output'] ?? false) === true) continue;

            $plan = $model['required_plan'] ?? null;
            if ($plan !== null && (!is_string($plan) || !in_array(strtolower($plan), $settings['allowed_plans'], true))) continue;

            $context = $model['context_window'] ?? null;
            if (is_int($context) && $context < self::MIN_CONTEXT_TOKENS) continue;

            $eligible[] = ['id' => $model['id'], 'rank' => [$context ?? 0]];
        }

        return self::sorted($eligible);
    }

    /** @return list<array{id: string}> */
    private static function sorted(array $eligible): array
    {
        usort($eligible, fn(array $a, array $b): int => ($b['rank'] <=> $a['rank']) ?: strcmp($a['id'], $b['id']));
        return array_map(fn(array $c): array => ['id' => $c['id']], $eligible);
    }

    private static function eligibilitySettings(string $provider, array $block): array
    {
        if ($provider === 'openrouter') {
            return [];
        }
        $plans = array_filter(array_map(fn($p) => strtolower(trim((string) $p)), explode(',', (string) ($block['allowed_plans'] ?? ''))));
        sort($plans);
        return ['allowed_plans' => array_values($plans)];
    }

    /**
     * A refusal by a CDN or bot-protection layer in front of the provider (an HTML/text body instead
     * of the provider's JSON API error), as happens when traffic from a hosting network is blocked.
     */
    public static function isEdgeBlock(Response $response): bool
    {
        if (!in_array($response->status(), [403, 429, 503], true)) return false;
        if (trim($response->header('cf-mitigated')) !== '') return true;
        $type = self::contentType($response);
        return $type !== null && !str_contains($type, 'json');
    }

    public static function edgeName(Response $response): ?string
    {
        return trim($response->header('CF-RAY')) !== '' || strtolower(trim($response->header('Server'))) === 'cloudflare' ? 'cloudflare' : null;
    }

    private static function contentType(Response $response): ?string
    {
        $type = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        return preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/', $type) === 1 ? $type : null;
    }

    private static function validId(mixed $id): bool
    {
        return is_string($id) && preg_match(self::MODEL_ID_PATTERN, $id) === 1;
    }

    private function isDenied(string $provider, string $model): bool
    {
        return $this->cacheGet(self::denialKey($provider, $model)) === true;
    }

    private static function denialKey(string $provider, string $model): string
    {
        return 'copilot_provider_' . $provider . '_model_denied:' . hash('sha256', $model);
    }

    private static function catalogKey(string $provider, string $kind): string
    {
        return 'copilot_provider_' . $provider . '_catalogue_' . $kind;
    }

    private function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cachePut(string $key, array $value, int $seconds): void
    {
        if ($seconds < 1) return;
        try {
            Cache::put($key, $value, $seconds);
        } catch (\Throwable) {
        }
    }

    private static function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
