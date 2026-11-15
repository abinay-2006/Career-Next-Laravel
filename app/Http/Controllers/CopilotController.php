<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\CopilotActionService;
use App\Services\CopilotCapabilityRegistry;
use App\Services\CopilotProfileUpdateService;
use App\Services\CopilotToolDispatcher;
use App\Services\FreeLlmApiCopilotException;
use App\Services\CopilotProviderRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CopilotController extends Controller
{
    public function message(
        Request $request,
        string $role,
        CopilotProviderRouter $providerRouter,
        CopilotCapabilityRegistry $registry,
        CopilotToolDispatcher $dispatcher,
        CopilotActionService $actions,
        CopilotProfileUpdateService $profileUpdates
    ): JsonResponse {
        $account = Auth::guard($role)->user();
        abort_unless($account, 401);

        $validator = Validator::make($request->all(), ['message' => ['required', 'string', 'max:2000']]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please enter a message of up to 2,000 characters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = $validator->validated()['message'];
        $key = 'career_next_copilot_' . $role . '_' . $account->getAuthIdentifier() . '_freellmapi';
        $history = $request->session()->get($key, []);
        $history = is_array($history) ? array_slice($history, -10) : [];

        try {
            $pendingConfirmation = $role === 'admin'
                && $account instanceof Admin
                && $actions->hasPendingConfirmation($request, $account);
            $reply = $pendingConfirmation
                ? $actions->handleAdminMessage($request, $account, $message)
                : null;

            // Profile-update confirmations/cancellations are decided by Laravel and never reach the model.
            if ($reply === null) {
                $reply = $profileUpdates->handleMessage($request, $role, $account, $message);
            }

            if ($reply === null) {
                $reply = $providerRouter->reply($role, $message, $account, $registry, $dispatcher, $history);
            }
        } catch (FreeLlmApiCopilotException $e) {
            // Provider 429s are returned as 503, never 429: the frontend reserves 429 for this
            // app's own per-user throttle ("You are sending messages too quickly").
            $status = match ($e->reason) {
                FreeLlmApiCopilotException::TIMEOUT => 504,
                FreeLlmApiCopilotException::AUTHENTICATION,
                FreeLlmApiCopilotException::PERMISSION,
                FreeLlmApiCopilotException::INVALID_RESPONSE,
                FreeLlmApiCopilotException::EMPTY_COMPLETION => 502,
                default => 503,
            };

            $body = ['message' => $e->getMessage()];
            if ($e->retryAfterSeconds !== null) {
                $body['retry_after'] = $e->retryAfterSeconds;
            }

            $response = response()->json($body, $status)->header('X-Copilot-Safe-Error', '1');

            return $e->retryAfterSeconds !== null
                ? $response->header('Retry-After', (string) $e->retryAfterSeconds)
                : $response;
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'The AI assistant ran into an unexpected problem. Please try again.',
            ], 500)->header('X-Copilot-Safe-Error', '1');
        }

        $history[] = ['role' => 'user', 'content' => $message];
        $history[] = ['role' => 'assistant', 'content' => $reply];
        $request->session()->put($key, array_slice($history, -12));

        return response()->json(['reply' => $reply]);
    }

    public function reset(Request $request, string $role): JsonResponse
    {
        $account = Auth::guard($role)->user();
        abort_unless($account, 401);
        $base = 'career_next_copilot_' . $role . '_' . $account->getAuthIdentifier();
        // Starting a new conversation also discards any unconfirmed profile update.
        $request->session()->forget([$base, $base . '_freellmapi', 'career_next_copilot_profile_update_' . $role . '_' . $account->getAuthIdentifier()]);
        return response()->json(['ok' => true]);
    }
}
