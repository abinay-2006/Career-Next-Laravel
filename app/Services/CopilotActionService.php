<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Http\Request;
use Throwable;

/** Deterministic, Laravel-owned Copilot operations with explicit confirmation for sensitive writes. */
class CopilotActionService
{
    private const PENDING_TTL_SECONDS = 300;

    public function hasPendingConfirmation(Request $request, Admin $admin): bool
    {
        return is_array($request->session()->get('career_next_copilot_admin_pending_suspend_'.$admin->getAuthIdentifier()));
    }

    public function handleAdminMessage(Request $request, Admin $admin, string $message): ?string
    {
        $key = 'career_next_copilot_admin_pending_suspend_'.$admin->getAuthIdentifier();
        $pending = $request->session()->get($key);

        if (is_array($pending)) {
            if ((int) ($pending['expires_at'] ?? 0) <= now()->getTimestamp()
                || (int) ($pending['admin_id'] ?? 0) !== (int) $admin->getAuthIdentifier()) {
                $request->session()->forget($key);
                if ($this->isConfirmation($message)) {
                    return 'That suspension confirmation has expired. No change was made. Please ask again to start a new request.';
                }
            } elseif (preg_match('/^\s*(?:cancel|no|never mind)\s*[.!]?\s*$/i', $message)) {
                $request->session()->forget($key);

                return 'Suspension cancelled. No account was changed.';
            } elseif ($this->isConfirmation($message)) {
                $request->session()->forget($key);

                return $this->confirmSuspension($admin, $pending);
            } else {
                // Any other message ends the pending confirmation; it cannot be reused later.
                $request->session()->forget($key);
            }
        }

        if (preg_match('/\b(?:suspend|suspending)\b/i', $message)) {
            return $this->requestSuspension($request, $admin, $key, $message);
        }

        return $this->unsupportedAdminWrite($message);
    }

    private function requestSuspension(Request $request, Admin $admin, string $key, string $message): string
    {
        $tail = preg_replace('/^.*?\b(?:suspend|suspending)\b/isu', '', $message) ?? '';
        $type = null;
        if (preg_match('/\b(job\s*seeker|jobseeker|candidate|employer)\b/i', $tail, $match)) {
            $type = match (mb_strtolower(preg_replace('/\s+/', '', $match[1]))) {
                'jobseeker', 'candidate' => 'jobseeker',
                'employer' => 'employer',
            };
            $tail = preg_replace('/\b(?:job\s*seeker|jobseeker|candidate|employer)\b/i', ' ', $tail, 1) ?? $tail;
        }

        $identifier = trim($tail);
        $identifier = preg_replace('/^(?:(?:the|a|an)\s+)?(?:user|account)\s+(?:named|called)\s+/i', '', $identifier) ?? $identifier;
        $identifier = preg_replace('/^(?:(?:the|a|an)\s+)?(?:user|account)\s+(?:of\s+)?/i', '', $identifier) ?? $identifier;
        $identifier = preg_replace('/^(?:named|called)\s+/i', '', $identifier) ?? $identifier;
        $identifier = preg_replace('/(?:^|\s+)(?:with\s+)?(?:id|number)\s*#?(\d+)\s*$/i', ' $1', $identifier) ?? $identifier;
        $identifier = trim($identifier, " \t\n\r\0\x0B.,!?;:#");

        if ($identifier === '') {
            return 'I can request an account suspension. Please provide the exact account name, username, email, or numeric ID, and specify job seeker or employer if the ID could match both.';
        }

        $targets = app(AdminAccountManagementService::class)->findExactTargets($admin, $identifier, $type);
        if ($targets === []) {
            return 'I could not find an exact matching account. No change was made. Please check the account name, username, email, or ID and try again.';
        }
        if (count($targets) > 1) {
            $choices = collect($targets)->take(4)->map(fn (array $target): string => $this->label($target))->implode('; ');

            return 'That identifier matches more than one account. No change was made. Specify one of these exact accounts: '.$choices.'.';
        }

        $target = $targets[0];
        if (strtolower(trim($target['status'])) === 'suspended') {
            return 'No change was needed: '.$this->label($target).' is already suspended.';
        }

        $request->session()->put($key, [
            'admin_id' => (int) $admin->getAuthIdentifier(),
            'account_type' => $target['account_type'],
            'account_id' => $target['id'],
            'expires_at' => now()->getTimestamp() + self::PENDING_TTL_SECONDS,
        ]);

        return 'I found '.$this->label($target).' (current status: '.($target['status'] ?: 'unknown').'). No change has been made. To proceed within five minutes, reply exactly: CONFIRM SUSPENSION.';
    }

    /** @param array{admin_id: int, account_type: string, account_id: int, expires_at: int} $pending */
    private function confirmSuspension(Admin $admin, array $pending): string
    {
        try {
            $result = app(AdminAccountManagementService::class)->suspend(
                $admin,
                (string) ($pending['account_type'] ?? ''),
                (int) ($pending['account_id'] ?? 0),
            );
        } catch (Throwable $exception) {
            report($exception);

            return 'I could not verify that the suspension completed. Please check Admin → Manage Users before retrying.';
        }

        if (!$result['success']) {
            return 'Suspension was not completed: '.$result['message'].' No change was confirmed.';
        }

        $target = app(AdminAccountManagementService::class)->findExactTargets(
            $admin,
            (string) ($pending['account_id'] ?? ''),
            (string) ($pending['account_type'] ?? ''),
        )[0] ?? null;
        $label = $target === null
            ? ucfirst((string) $pending['account_type']).' account (ID '.(int) $pending['account_id'].')'
            : $this->label($target);

        return ($result['changed'] ?? false)
            ? 'Suspension completed. '.$label.' is now suspended.'
            : 'No change was needed. '.$label.' was already suspended.';
    }

    private function isConfirmation(string $message): bool
    {
        return preg_match('/^\s*confirm\s+suspension\s*[.!]?\s*$/i', $message) === 1;
    }

    /** @param array{account_type: string, id: int, name: string, status: string} $target */
    private function label(array $target): string
    {
        $role = $target['account_type'] === 'jobseeker' ? 'Job Seeker' : 'Employer';
        $name = mb_substr(trim(strip_tags($target['name'])), 0, 100);

        return $role.' '.$name.' (ID '.$target['id'].')';
    }

    private function unsupportedAdminWrite(string $message): ?string
    {
        if (preg_match('/\b(?:delete|remove)\b.*\b(?:user|account)\b/i', $message)) {
            return 'Account deletion is available in Admin → Manage Users, but Copilot does not execute deletions. No change was made.';
        }
        if (preg_match('/\b(?:approve|reject)\b.*\bjob\b|\bjob\b.*\b(?:approve|reject)\b/i', $message)) {
            return 'Job approval and rejection are available in Admin → Manage Jobs, but Copilot does not execute those changes. No change was made.';
        }
        if (preg_match('/\b(?:approve|reject)\b.*\bdocument\b|\bdocument\b.*\b(?:approve|reject)\b/i', $message)) {
            return 'Document approval and rejection are available in Admin → Manage Documents, but Copilot does not execute those changes. No change was made.';
        }

        return null;
    }
}
