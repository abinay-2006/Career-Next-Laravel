<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const SESSION_KEY = 'password_reset';

    public function show()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = Str::lower(trim($validated['email']));
        $matches = $this->accountsForEmail($email);

        if ($matches->isEmpty()) {
            return back()->withErrors(['email' => 'No account was found with that email address.'])->withInput();
        }

        $otp = (string) random_int(100000, 999999);
        try {
            Mail::to($email)->send(new PasswordResetOtpMail($otp));
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['email' => 'We could not send the verification code. Please try again.'])->withInput();
        }

        $request->session()->put(self::SESSION_KEY, [
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
            'verified' => false,
        ]);

        return redirect()->route('forgot')->with('status', 'A verification code was sent to your email.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);
        $reset = $request->session()->get(self::SESSION_KEY);

        if (!$reset || now()->timestamp > $reset['expires_at'] || $reset['attempts'] >= 5) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('forgot')->withErrors(['otp' => 'The code expired or the attempt limit was reached. Request a new code.']);
        }

        if (!Hash::check($validated['otp'], $reset['otp_hash'])) {
            $reset['attempts']++;
            $request->session()->put(self::SESSION_KEY, $reset);
            return back()->withErrors(['otp' => 'That verification code is incorrect.']);
        }

        $reset['verified'] = true;
        $request->session()->put(self::SESSION_KEY, $reset);
        return redirect()->route('forgot')->with('status', 'Email verified. Choose a new password.');
    }

    public function resetPassword(Request $request)
    {
        $reset = $request->session()->get(self::SESSION_KEY);
        if (!$reset || !$reset['verified'] || now()->timestamp > $reset['expires_at']) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('forgot')->withErrors(['email' => 'Verify your email with a new code before resetting your password.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        foreach ($this->accountsForEmail($reset['email']) as $account) {
            $account->Password = Hash::make($validated['password']);
            $account->save();
        }

        $request->session()->forget(self::SESSION_KEY);
        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }

    private function accountsForEmail(string $email)
    {
        return collect([
            ...JobSeeker::whereRaw('LOWER(Email) = ?', [$email])->get(),
            ...Employer::whereRaw('LOWER(Email) = ?', [$email])->get(),
        ]);
    }
}
