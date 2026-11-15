<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proves that one model of a hosted provider honours the OpenAI-compatible tool-calling contract the
 * Copilot depends on, for providers whose catalogue publishes no reliable capability metadata
 * (NaraRouter documents neither a /models schema nor a tool-support flag).
 *
 * The probe is a two-step conversation with a single synthetic function that does nothing:
 *   1. tools + tool_choice "auto" must produce a tool_call with an ID, the probe function's name and
 *      JSON-string arguments carrying the requested value;
 *   2. after the tool result message is returned, the model must give a non-empty text answer.
 *
 * The probe function is never part of the Copilot registry and nothing is dispatched: no Career Next
 * data or action is touched. Only identifiers, statuses and timings are logged, never keys or bodies.
 */
class CopilotToolCallingVerifier
{
    public const VERIFIED = 'verified';
    /** The model answered but cannot honour the tool contract (or the provider rejected the request shape). */
    public const UNSUPPORTED = 'unsupported';
    /** The provider refused this model for this account (HTTP 403). */
    public const FORBIDDEN = 'forbidden';
    /** Temporary model-side failure (5xx, timeout): no conclusion, nothing is cached. */
    public const TRANSIENT = 'transient';

    public const PROBE_FUNCTION = 'career_next_capability_probe';
    private const PROBE_VALUE = 'ok';

    private const STEP_TIMEOUT_SECONDS = 6.0;
    private const CONNECT_TIMEOUT_SECONDS = 3.0;
    /** Never start a probe step with less time than this left before the deadline. */
    public const MIN_STEP_SECONDS = 2.0;

    /**
     * @param array $extraPayload Provider parameters the runtime completion will also send for this model.
     * @return self::VERIFIED|self::UNSUPPORTED|self::FORBIDDEN|self::TRANSIENT
     * @throws FreeLlmApiCopilotException for provider-wide failures (credentials, quota, rate limit,
     *         unreachable provider, edge block), so no further model is probed.
     */
    public function verify(string $provider, string $baseUrl, string $key, string $model, array $extraPayload, float $deadline): string
    {
        $startedAt = microtime(true);
        $endpoint = rtrim($baseUrl, '/') . '/chat/completions';
        $tools = [[
            'type' => 'function',
            'function' => [
                'name' => self::PROBE_FUNCTION,
                'description' => 'Connectivity check. Records the given status. Has no side effects.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['status' => ['type' => 'string', 'enum' => [self::PROBE_VALUE]]],
                    'required' => ['status'],
                ],
            ],
        ]];
        $messages = [
            ['role' => 'system', 'content' => 'This is an automated function-calling compatibility check. Follow the instructions exactly.'],
            ['role' => 'user', 'content' => 'Call the function ' . self::PROBE_FUNCTION . ' with status "' . self::PROBE_VALUE . '". After you receive its result, reply with one short sentence.'],
        ];

        // Step 1: the model must request the probe function with well-formed arguments.
        $first = $this->send($provider, $endpoint, $key, $model, $messages, $tools, $extraPayload, $deadline);
        if (is_string($first)) return $this->finish($provider, $model, $first, 'tool_call', $startedAt);

        $assistant = data_get($first->json(), 'choices.0.message');
        $toolCalls = is_array($assistant) ? ($assistant['tool_calls'] ?? null) : null;
        if (!is_array($toolCalls) || $toolCalls === [] || !array_is_list($toolCalls) || !$this->validProbeCall($toolCalls[0])) {
            return $this->finish($provider, $model, self::UNSUPPORTED, 'tool_call', $startedAt, $first->status());
        }

        // Step 2: every tool call receives exactly one result; the model must then answer in text.
        $messages[] = $assistant;
        foreach ($toolCalls as $call) {
            if (!is_array($call) || !is_string($call['id'] ?? null) || $call['id'] === '') {
                return $this->finish($provider, $model, self::UNSUPPORTED, 'tool_call', $startedAt, $first->status());
            }
            $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode(['result' => ['recorded' => true]])];
        }

        $second = $this->send($provider, $endpoint, $key, $model, $messages, $tools, $extraPayload, $deadline);
        if (is_string($second)) return $this->finish($provider, $model, $second, 'tool_result_continuation', $startedAt);

        $final = data_get($second->json(), 'choices.0.message');
        $content = is_array($final) ? ($final['content'] ?? null) : null;
        if (is_array($content) && array_is_list($content)) {
            $content = implode('', array_map(fn($p) => is_array($p) && ($p['type'] ?? null) === 'text' && is_string($p['text'] ?? null) ? $p['text'] : '', $content));
        }
        $moreCalls = is_array($final) && is_array($final['tool_calls'] ?? null) && $final['tool_calls'] !== [];
        $ok = is_string($content) && trim($content) !== '' && !$moreCalls;

        return $this->finish($provider, $model, $ok ? self::VERIFIED : self::UNSUPPORTED, 'tool_result_continuation', $startedAt, $second->status());
    }

    private function validProbeCall(mixed $call): bool
    {
        if (!is_array($call) || !is_string($call['id'] ?? null) || $call['id'] === '') return false;
        if (($call['function']['name'] ?? null) !== self::PROBE_FUNCTION) return false;
        // The Copilot only accepts JSON-string arguments (the OpenAI contract), so the probe does too.
        $arguments = $call['function']['arguments'] ?? null;
        if (!is_string($arguments)) return false;
        try {
            $decoded = json_decode($arguments, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        return is_array($decoded) && ($decoded['status'] ?? null) === self::PROBE_VALUE;
    }

    /** @return Response|string a successful response, or a model-level outcome constant */
    private function send(string $provider, string $endpoint, string $key, string $model, array $messages, array $tools, array $extraPayload, float $deadline): Response|string
    {
        $remaining = $deadline - microtime(true);
        if ($remaining < self::MIN_STEP_SECONDS) return self::TRANSIENT;

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0,
            'max_tokens' => 300,
            'stream' => false,
        ], $extraPayload);

        try {
            $response = Http::acceptJson()->asJson()->withToken($key)
                ->timeout(min(self::STEP_TIMEOUT_SECONDS, $remaining))
                ->connectTimeout(min(self::CONNECT_TIMEOUT_SECONDS, $remaining))
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            $text = strtolower($e->getMessage());
            if (str_contains($text, 'curl error 28') || str_contains($text, 'timed out') || str_contains($text, 'timeout')) {
                return self::TRANSIENT; // a slow model; another model may answer in time
            }
            $this->logFailure($provider, $model, null, 'connection', null);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::CONNECTION_MESSAGE, FreeLlmApiCopilotException::CONNECTION, $e);
        }

        if ($response->successful()) return $response;

        $status = $response->status();
        $requestId = self::requestId($response);

        if (CopilotProviderModelResolver::isEdgeBlock($response)) {
            $this->logFailure($provider, $model, $status, 'edge_blocked', $requestId);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::UPSTREAM_MESSAGE, FreeLlmApiCopilotException::UPSTREAM, null, null, $status);
        }

        return match (true) {
            $status === 401 => $this->providerFailure($provider, $model, $status, $requestId, FreeLlmApiCopilotException::INVALID_CREDENTIALS, FreeLlmApiCopilotService::AUTHENTICATION_MESSAGE),
            $status === 402 => $this->providerFailure($provider, $model, $status, $requestId, FreeLlmApiCopilotException::QUOTA, sprintf(FreeLlmApiCopilotService::QUOTA_MESSAGE, 'in a few minutes')),
            $status === 429 => $this->providerFailure($provider, $model, $status, $requestId, FreeLlmApiCopilotException::RATE_LIMITED, sprintf(FreeLlmApiCopilotService::RATE_LIMIT_MESSAGE, 'in a few minutes')),
            $status === 403 => self::FORBIDDEN,
            $status === 408, $status >= 500 => self::TRANSIENT,
            default => self::UNSUPPORTED, // 400/404/413/415/422: the model rejects this request shape
        };
    }

    private function providerFailure(string $provider, string $model, int $status, ?string $requestId, string $reason, string $message): never
    {
        $this->logFailure($provider, $model, $status, $reason, $requestId);
        throw new FreeLlmApiCopilotException($message, $reason, null, null, $status);
    }

    private function logFailure(string $provider, string $model, ?int $status, string $failureType, ?string $requestId): void
    {
        Log::warning('Copilot provider tool-calling verification stopped by a provider-wide failure.', [
            'provider' => $provider, 'operation' => 'tool_verification', 'model' => $model,
            'http_status' => $status, 'failure_type' => $failureType, 'provider_request_id' => $requestId,
        ]);
    }

    private function finish(string $provider, string $model, string $outcome, string $stage, float $startedAt, ?int $status = null): string
    {
        Log::info('Copilot provider tool-calling verification finished.', [
            'provider' => $provider, 'operation' => 'tool_verification', 'model' => $model,
            'outcome' => $outcome, 'stage' => $stage, 'http_status' => $status,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        return $outcome;
    }

    /** Provider request ID from the structured error body or a request-ID header, if it is a safe token. */
    public static function requestId(Response $response): ?string
    {
        $id = null;
        try {
            $json = json_decode($response->body(), true, 8, JSON_THROW_ON_ERROR);
            $id = is_array($json) && is_array($json['error'] ?? null) ? ($json['error']['request_id'] ?? null) : null;
        } catch (\JsonException) {
        }
        $id ??= trim($response->header('X-Request-Id')) ?: null;
        return is_string($id) && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $id) === 1 ? $id : null;
    }
}
