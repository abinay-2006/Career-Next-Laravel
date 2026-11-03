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
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            abort(403);
        }

        $authenticatedRoles = array_values(array_filter(
            ['admin', 'employer', 'jobseeker'],
            fn (string $guard): bool => Auth::guard($guard)->check()
        ));

        if ($authenticatedRoles === []) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->guest(route('login'));
        }

        if (count($authenticatedRoles) !== 1 || $authenticatedRoles[0] !== $role) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'This browser session has an inconsistent account identity. Sign out and sign in again.'], 403);
            }
            abort(403, 'This browser session has an inconsistent account identity. Sign out and sign in again.');
        }

        if ($role !== 'admin') {
            $account = Auth::guard($role)->user()?->fresh();
            $status = strtolower(trim((string) ($account?->Status ?? '')));

            if (!in_array($status, ['confirm', 'confirmed', 'approved'], true)) {
                Auth::guard($role)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => $status === 'suspended'
                            ? 'Your account has been suspended. Please contact the administrator.'
                            : 'Your account is no longer active. Please contact the administrator.',
                    ], 403);
                }

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
