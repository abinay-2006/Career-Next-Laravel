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
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = Str::lower(trim($data['email']));
        $matches = collect([
            ...JobSeeker::query()->whereRaw('LOWER(Email) = ?', [$email])->get(),
            ...Employer::query()->whereRaw('LOWER(Email) = ?', [$email])->get(),
        ]);

        // An address shared by several accounts cannot safely identify one account.
        if ($matches->count() === 1) {
            $account = $matches->first();
            $type = $account instanceof JobSeeker ? 'jobseeker' : 'employer';
            $otp = (string) random_int(100000, 999999);
            DB::table('password_recovery_tokens')->where('email', $email)->delete();
            DB::table('password_recovery_tokens')->insert([
                'account_type' => $type,
                'account_id' => $account->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $otp),
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
                DB::table('password_recovery_tokens')->where('token_hash', hash('sha256', $otp))->delete();
                Log::warning('Password recovery email could not be sent', ['exception' => $exception::class]);
                return back()->with('status', 'We could not send a code right now. Please try again shortly.');
            }

            // A logging failure must not invalidate a link that has already been emailed.
            try {
                app(ActivityLogger::class)->record('password_recovery_requested', $type, $account->getKey());
            } catch (\Throwable $exception) {
                Log::warning('Password recovery request could not be logged', ['exception' => $exception::class]);
            }
        }

        if ($matches->count() === 1) {
            $request->session()->put('password_recovery_email', $email);
            return redirect()->route('forgot.verify')->with('status', 'A verification code has been sent to your email address.');
        }

        return back()->with('status', 'If an eligible account matches that email, a verification code will arrive shortly.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);
        $email = $request->session()->get('password_recovery_email');
        if (!$email) {
            return redirect()->route('forgot');
        }

        $recovery = DB::table('password_recovery_tokens')
            ->where('email', $email)
            ->where('token_hash', hash('sha256', $validated['otp']))
            ->where('expires_at', '>', now())
            ->first();

        if (!$recovery) {
            return back()->withErrors(['otp' => 'That code is invalid or expired. Please request a new code.']);
        }

        $token = Str::random(64);
        DB::table('password_recovery_tokens')->where('id', $recovery->id)->update([
            'token_hash' => hash('sha256', $token),
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
        $hash = hash('sha256', $token);

        $completed = DB::transaction(function () use ($hash, $validated): array|false {
            $recovery = DB::table('password_recovery_tokens')->where('token_hash', $hash)->lockForUpdate()->first();
            if (!$recovery || now()->greaterThan($recovery->expires_at)) {
                return false;
            }

            $account = match ($recovery->account_type) {
                'jobseeker' => JobSeeker::query()->whereKey($recovery->account_id)->whereRaw('LOWER(Email) = ?', [$recovery->email])->first(),
                'employer' => Employer::query()->whereKey($recovery->account_id)->whereRaw('LOWER(Email) = ?', [$recovery->email])->first(),
                default => null,
            };
            if (!$account) {
                DB::table('password_recovery_tokens')->where('id', $recovery->id)->delete();
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
        return DB::table('password_recovery_tokens')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists();
    }
}
