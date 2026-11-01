<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $normalizedEmail = static fn ($email): string => mb_strtolower(trim((string) $email));
        $ipKey = static fn (Request $request): string => hash('sha256', (string) ($request->ip() ?? 'unknown'));

        // Recovery sending: 3 requests per normalized email and 10 per client IP each minute.
        RateLimiter::for('password-recovery-send', function (Request $request) use ($normalizedEmail, $ipKey) {
            return [
                Limit::perMinute(3)->by('email:'.hash('sha256', $normalizedEmail($request->input('email')))),
                Limit::perMinute(10)->by('ip:'.$ipKey($request)),
            ];
        });

        // OTP verification has separate route limits; wrong-code attempts are additionally
        // bounded by the controller's 5-per-10-minute counter.
        RateLimiter::for('password-recovery-verify', function (Request $request) use ($normalizedEmail, $ipKey) {
            $email = $request->session()->get('password_recovery_email', '');

            return [
                Limit::perMinute(10)->by('email:'.hash('sha256', $normalizedEmail($email))),
                Limit::perMinute(30)->by('ip:'.$ipKey($request)),
            ];
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
