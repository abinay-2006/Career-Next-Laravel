<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!in_array($role, ['admin', 'employer', 'jobseeker'], true)) {
            abort(403);
        }

        $authenticatedRoles = array_values(array_filter(
            ['admin', 'employer', 'jobseeker'],
            fn (string $guard): bool => Auth::guard($guard)->check()
        ));

        if ($authenticatedRoles === []) {
            return redirect()->guest(route('login'));
        }

        if (count($authenticatedRoles) !== 1 || $authenticatedRoles[0] !== $role) {
            abort(403, 'This browser session has an inconsistent account identity. Sign out and sign in again.');
        }

        if ($role !== 'admin') {
            $account = Auth::guard($role)->user()?->fresh();
            $status = strtolower(trim((string) ($account?->Status ?? '')));

            if (!in_array($status, ['confirm', 'confirmed', 'approved'], true)) {
                Auth::guard($role)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with(
                    'error',
                    $status === 'suspended'
                        ? 'Your account has been suspended. Please contact the administrator.'
                        : 'Your account is no longer active. Please contact the administrator.'
                );
            }
        }

        return $next($request);
    }
}
