<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordRecoveryMail;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Services\ActivityLogger;

class PasswordResetController extends Controller
{
    public function show()
    {
        return view('auth.forgot-password');
    }

    public function showOtp(Request $request)
    {
        if (!$request->session()->has('password_recovery_email')) {
            return redirect()->route('forgot');
        }

        return view('auth.forgot-password', ['step' => 'otp']);
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'account_type' => ['nullable', 'in:employer,jobseeker'],
        ]);
        $email = Str::lower(trim($data['email']));
        $matches = collect([
            ...JobSeeker::query()->whereRaw('LOWER(Email) = ?', [$email])->get(),
            ...Employer::query()->whereRaw('LOWER(Email) = ?', [$email])->get(),
        ]);

        $request->session()->forget('password_recovery_email');
        $request->session()->put('password_recovery_email', $email);

        // Send a distinct code for each matching account. This avoids asking the
        // user to guess the account type when an email is shared across accounts.
        if ($matches->isNotEmpty()) {
            foreach ($matches as $account) {
                $type = $account instanceof JobSeeker ? 'jobseeker' : 'employer';
                $otp = (string) random_int(100000, 999999);
                DB::table('password_recovery_tokens')->insert([
                    'account_type' => $type,
                    'account_id' => $account->getKey(),
                    'email' => $email,
                    'token_hash' => hash_hmac('sha256', $otp, (string) config('app.key')),
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                try {
                    if (config('mail.default') === 'log') {
                        throw new \RuntimeException('The configured log mailer does not deliver recovery email.');
                    }
                    Mail::to($email)->send(new PasswordRecoveryMail($otp));
                } catch (\Throwable $exception) {
                    DB::table('password_recovery_tokens')->where('token_hash', hash_hmac('sha256', $otp, (string) config('app.key')))->delete();
                    Log::warning('Password recovery email could not be sent', ['exception' => $exception::class]);
                    return redirect()->route('forgot')->withInput($request->only('email'))->with('error', 'We could not send the verification email. Please try again later or contact support.');
                }

                // A logging failure must not invalidate a code that has already been emailed.
                try {
                    app(ActivityLogger::class)->record('password_recovery_requested', $type, $account->getKey());
                } catch (\Throwable $exception) {
                    Log::warning('Password recovery request could not be logged', ['exception' => $exception::class]);
                }
            }
        }

        return redirect()->route('forgot.verify')->with('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);
        $email = $request->session()->get('password_recovery_email');
        if (!$email) {
            return redirect()->route('forgot');
        }

        $limiterKey = 'password-recovery-otp:' . hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return back()->withErrors(['otp' => 'Too many attempts. Request a new verification code.']);
        }

        $recovery = DB::table('password_recovery_tokens')
            ->where('email', $email)
            ->where('token_hash', hash_hmac('sha256', $validated['otp'], (string) config('app.key')))
            ->where('expires_at', '>', now())
            ->first();

        if (!$recovery) {
            RateLimiter::hit($limiterKey, 600);
            return back()->withErrors(['otp' => 'That code is invalid or expired. Please request a new code.']);
        }

        RateLimiter::clear($limiterKey);

        $token = Str::random(64);
        DB::table('password_recovery_tokens')->where('id', $recovery->id)->update([
            'token_hash' => hash_hmac('sha256', $token, (string) config('app.key')),
            'expires_at' => now()->addMinutes(10),
            'updated_at' => now(),
        ]);

        return redirect()->route('forgot.token', $token);
    }

    public function showReset(string $token)
    {
        if (!$this->validToken($token)) {
            return redirect()->route('forgot')->withErrors(['email' => 'That recovery link is invalid or expired. Request a new link.']);
        }

        return view('auth.forgot-password', ['token' => $token]);
    }

    public function resetPassword(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $hash = hash_hmac('sha256', $token, (string) config('app.key'));

        $completed = DB::transaction(function () use ($hash, $validated): array|false {
            $recovery = DB::table('password_recovery_tokens')->where('token_hash', $hash)->lockForUpdate()->first();
            if (!$recovery) {
                return false;
            }

            // Treat the expiry instant itself as expired. Parse the database
            // timestamp with Laravel's configured timezone before comparing.
            if (now()->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($recovery->expires_at))) {
                DB::table('password_recovery_tokens')->where('id', $recovery->id)->delete();
                return false;
            }

            // Verify the token belongs to the correct account type and email
            $account = match ($recovery->account_type) {
                'jobseeker' => JobSeeker::query()->whereKey($recovery->account_id)->whereRaw('LOWER(Email) = ?', [$recovery->email])->first(),
                'employer' => Employer::query()->whereKey($recovery->account_id)->whereRaw('LOWER(Email) = ?', [$recovery->email])->first(),
                default => null,
            };
            if (!$account) {
                DB::table('password_recovery_tokens')->where('id', $recovery->id)->delete();
                return false;
            }

            // Check if the account type matches the token's account type
            if ($account instanceof JobSeeker && $recovery->account_type !== 'jobseeker') {
                return false;
            }
            if ($account instanceof Employer && $recovery->account_type !== 'employer') {
                return false;
            }

            $account->Password = Hash::make($validated['password']);
            $account->save();
            DB::table('password_recovery_tokens')->where('account_type', $recovery->account_type)->where('account_id', $recovery->account_id)->delete();
            return ['type' => $recovery->account_type, 'id' => (int) $recovery->account_id];
        });

        if ($completed === false) {
            return redirect()->route('forgot')->withErrors(['email' => 'That recovery link is invalid or expired. Request a new link.']);
        }

        app(ActivityLogger::class)->record('password_recovery_completed', $completed['type'], $completed['id']);

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }

    private function validToken(string $token): bool
    {
        return DB::table('password_recovery_tokens')->where('token_hash', hash_hmac('sha256', $token, (string) config('app.key')))->where('expires_at', '>', now())->exists();
    }
}
