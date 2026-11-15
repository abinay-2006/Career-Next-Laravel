<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Resolves a currently listed NavyAI chat model suitable for Copilot tool calling. */
class CopilotProviderModelResolver
{
    public function resolveNavyAi(array $provider, float $deadline): string
    {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
        $remaining = $deadline - microtime(true);
        if ($baseUrl === '' || $remaining < 1) {
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::TIMEOUT_MESSAGE, FreeLlmApiCopilotException::TIMEOUT);
        }

        try {
            // NavyAI documents this model catalog as public; do not send the inference API key here.
            $response = Http::acceptJson()->timeout(min(5, $remaining))->connectTimeout(min(3, $remaining))->get($baseUrl . '/models');
        } catch (ConnectionException $e) {
            Log::warning('Copilot provider model catalog request failed.', ['provider' => 'navyai', 'failure_type' => 'connection', 'exception' => $e::class]);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE, $e, null, null, true);
        }

        if (!$response->successful()) {
            Log::warning('Copilot provider model catalog returned an unsuccessful response.', ['provider' => 'navyai', 'http_status' => $response->status()]);
            throw new FreeLlmApiCopilotException(
                FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE,
                $response->status() === 401 || $response->status() === 403 ? FreeLlmApiCopilotException::AUTHENTICATION : FreeLlmApiCopilotException::UNAVAILABLE,
                null,
                null,
                $response->status(),
                true
            );
        }

        $items = $response->json('data');
        if (!is_array($items)) {
            Log::warning('Copilot provider model catalog had an invalid structure.', ['provider' => 'navyai']);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::MODEL_CATALOG_MESSAGE, FreeLlmApiCopilotException::INVALID_RESPONSE, null, null, $response->status(), true);
        }

        $candidates = array_values(array_filter($items, function (mixed $model): bool {
            if (!is_array($model)) return false;
            $supportsTools = ($model['supports_tools'] ?? false) === true || ($model['supports_function_calling'] ?? false) === true;
            $chatEndpoint = in_array($model['endpoint'] ?? null, ['/v1/chat/completions', 'chat/completions'], true);
            return is_string($model['id'] ?? null)
                && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/@+-]{0,191}$/', $model['id']) === 1
                && $supportsTools && $chatEndpoint
                && ($model['supports_reasoning'] ?? false) === true
                && ($model['metadata_status'] ?? null) === 'known';
        }));

        if ($candidates === []) {
            Log::warning('Copilot provider model catalog contains no eligible tool-capable model.', ['provider' => 'navyai']);
            throw new FreeLlmApiCopilotException(FreeLlmApiCopilotService::MODEL_UNAVAILABLE_MESSAGE, FreeLlmApiCopilotException::UNAVAILABLE, null, null, $response->status(), true);
        }

        // Restrict selection to known reasoning models with explicit tool support, then
        // prefer greater published output/context capacity and use the ID only for stable ties.
        usort($candidates, function (array $a, array $b): int {
            $capacityA = [(int) ($a['max_output_tokens'] ?? 0), (int) ($a['context_window'] ?? 0)];
            $capacityB = [(int) ($b['max_output_tokens'] ?? 0), (int) ($b['context_window'] ?? 0)];
            return ($capacityB <=> $capacityA) ?: strcmp((string) $a['id'], (string) $b['id']);
        });

        return $candidates[0]['id'];
    }
}
