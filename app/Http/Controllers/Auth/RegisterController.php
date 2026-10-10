<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class RegisterController extends Controller
{
    /**
     * Step 1: Show account type selection page.
     */
    public function showRegistration()
    {
        return view('auth.register');
    }


    /**
     * Step 1: Store selected account type.
     */
    public function storeAccountType(Request $request)
    {
        $validated = $request->validate([
            'account_type' => [
                'required',
                'in:JobSeeker,Employer',
            ],
        ]);

        session([
            'registration.account_type' => $validated['account_type'],
        ]);

        return redirect()->route('register.details');
    }


    /**
     * Step 2: Show account details page.
     */
    public function showDetails()
    {
        $accountType = session('registration.account_type');

        /*
        |--------------------------------------------------------------------------
        | User must complete Step 1 first
        |--------------------------------------------------------------------------
        */

        if (!$accountType) {
            return redirect()->route('register.index');
        }

        return view('auth.register-details', [
            'accountType' => $accountType,
        ]);
    }


    /**
     * Step 2: Store account details temporarily.
     */
    public function storeDetails(Request $request)
    {
        $accountType = session('registration.account_type');

        /*
        |--------------------------------------------------------------------------
        | Make sure Step 1 was completed
        |--------------------------------------------------------------------------
        */

        if (!$accountType) {
            return redirect()->route('register.index');
        }


        /*
        |--------------------------------------------------------------------------
        | Validate account details
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'email' => [
                'required',
                'email',
                'max:150',
            ],

            'mobile' => [
                'required',
                'string',
                'max:20',
                ...($accountType === 'JobSeeker' ? ['regex:/^[1-9][0-9]{6,18}$/'] : []),
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

        ]);


        /*
        |--------------------------------------------------------------------------
        | Check username in existing accounts
        |--------------------------------------------------------------------------
        */

        $usernameExists =
            JobSeeker::where('UserName', $validated['username'])->exists()
            ||
            Employer::where('UserName', $validated['username'])->exists();

        if ($usernameExists) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'username' => 'This username is already taken. Please choose another one.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Check email in existing accounts
        |--------------------------------------------------------------------------
        */

        $emailExists =
            JobSeeker::where('Email', $validated['email'])->exists()
            ||
            Employer::where('Email', $validated['email'])->exists();

        if ($emailExists) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'This email address is already registered.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Store account details in session
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | The account is NOT created in jobseeker_reg or employer_reg yet.
        |
        */

        session([
            'registration.name' => $validated['name'],
            'registration.username' => $validated['username'],
            'registration.email' => $validated['email'],
            'registration.mobile' => $validated['mobile'],

            // Store hashed password temporarily.
            'registration.password' => Hash::make($validated['password']),
        ]);


        /*
        |--------------------------------------------------------------------------
        | Go to Step 3
        |--------------------------------------------------------------------------
        |
        | The route will be implemented in Part 3.
        |
        */

        return redirect()->route('register.profile');
    }

    /**
     * Step 3: Show personal/company details.
     */
    public function showProfile()
    {
        $accountType = session('registration.account_type');

        if (!$accountType) {
            return redirect()->route('register.index');
        }

        /*
    |--------------------------------------------------------------------------
    | Make sure Step 2 was completed
    |--------------------------------------------------------------------------
    */

        if (
            !session()->has('registration.name') ||
            !session()->has('registration.username') ||
            !session()->has('registration.email') ||
            !session()->has('registration.mobile') ||
            !session()->has('registration.password')
        ) {
            return redirect()->route('register.details');
        }

        return view('auth.register-profile', [
            'accountType' => $accountType,
            'profile' => session('registration.profile', []),
        ]);
    }


    /**
     * Step 3: Store personal/company details temporarily.
     */
    public function storeProfile(Request $request)
    {
        $accountType = session('registration.account_type');

        if (!$accountType) {
            return redirect()->route('register.index');
        }

        /*
    |--------------------------------------------------------------------------
    | Make sure Step 2 was completed
    |--------------------------------------------------------------------------
    */

        if (
            !session()->has('registration.name') ||
            !session()->has('registration.username') ||
            !session()->has('registration.email')
        ) {
            return redirect()->route('register.details');
        }


        /*
    |--------------------------------------------------------------------------
    | JOB SEEKER
    |--------------------------------------------------------------------------
    */

        if ($accountType === 'JobSeeker') {

            $validated = $request->validate([
                'address' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'city' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'gender' => [
                    'required',
                    'in:Male,Female,Other',
                ],

                'birth_date' => [
                    'required',
                    'date',
                    'before:today',
                ],
            ]);


            session([
                'registration.profile' => $validated,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | EMPLOYER
    |--------------------------------------------------------------------------
    */ elseif ($accountType === 'Employer') {

            $validated = $request->validate([
                'contact_person' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'address' => [
                    'required',
                    'string',
                    'max:500',
                ],

                'city' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'area_work' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ]);


            session([
                'registration.profile' => $validated,
            ]);
        }


        return redirect()->route('register.professional');
    }

    /**
     * Step 4: Show professional details.
     */
    public function showProfessional()
    {
        $accountType = session('registration.account_type');

        /*
    |--------------------------------------------------------------------------
    | Step 1 check
    |--------------------------------------------------------------------------
    */

        if (!$accountType) {
            return redirect()->route('register.index');
        }


        /*
    |--------------------------------------------------------------------------
    | Step 2 check
    |--------------------------------------------------------------------------
    */

        if (
            !session()->has('registration.name') ||
            !session()->has('registration.username') ||
            !session()->has('registration.email') ||
            !session()->has('registration.mobile') ||
            !session()->has('registration.password')
        ) {
            return redirect()->route('register.details');
        }


        /*
    |--------------------------------------------------------------------------
    | Step 3 check
    |--------------------------------------------------------------------------
    */

        if (!session()->has('registration.profile')) {
            return redirect()->route('register.profile');
        }


        return view('auth.register-professional', [
            'accountType' => $accountType,
            'professional' => session('registration.professional', []),
        ]);
    }


    /**
     * Step 4: Store professional details temporarily.
     */
    public function storeProfessional(Request $request)
    {
        $accountType = session('registration.account_type');


        /*
    |--------------------------------------------------------------------------
    | Make sure registration has reached Step 4
    |--------------------------------------------------------------------------
    */

        if (!$accountType) {
            return redirect()->route('register.index');
        }


        if (
            !session()->has('registration.name') ||
            !session()->has('registration.username') ||
            !session()->has('registration.email')
        ) {
            return redirect()->route('register.details');
        }


        if (!session()->has('registration.profile')) {
            return redirect()->route('register.profile');
        }


        /*
    |--------------------------------------------------------------------------
    | JOB SEEKER
    |--------------------------------------------------------------------------
    */

        if ($accountType === 'JobSeeker') {

            $validated = $request->validate([

                'qualification' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'resume' => [
                    'nullable',
                    'file',
                    'mimes:pdf,doc,docx',
                    'max:5120',
                ],

                'photo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],

            ]);


            /*
        |--------------------------------------------------------------------------
        | Store uploaded files temporarily
        |--------------------------------------------------------------------------
        |
        | We don't put them directly into the final account record yet.
        |
        */

            $professionalData = [
                'qualification' => $validated['qualification'],
            ];


            /*
        |--------------------------------------------------------------------------
        | Resume
        |--------------------------------------------------------------------------
        */

            if ($request->hasFile('resume')) {

                $resume = $request->file('resume');

                $resumePath = $resume->store(
                    'registration/resumes'
                );

                $professionalData['resume'] = $resumePath;
            }


            /*
        |--------------------------------------------------------------------------
        | Profile Photo
        |--------------------------------------------------------------------------
        */

            if ($request->hasFile('photo')) {

                $photo = $request->file('photo');

                $photoPath = $photo->store(
                    'registration/photos',
                    'public'
                );

                $professionalData['photo'] = $photoPath;
            }


            session([
                'registration.professional' => $professionalData,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | EMPLOYER
    |--------------------------------------------------------------------------
    */ elseif ($accountType === 'Employer') {

            /*
        |--------------------------------------------------------------------------
        | Area of Work was already collected in Step 3.
        |--------------------------------------------------------------------------
        |
        | We don't invent additional fields that don't exist in the
        | current employer_reg schema.
        |
        */

            session([
                'registration.professional' => [
                    'completed' => true,
                ],
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Continue to Review
    |--------------------------------------------------------------------------
    */

        return redirect()->route('register.review');
    }

    public function showReview()
    {
        $registration = session('registration');

        if (!$registration || !isset($registration['account_type'])) {
            return redirect()->route('register.index');
        }

        if (
            !isset($registration['name']) ||
            !isset($registration['username']) ||
            !isset($registration['email']) ||
            !isset($registration['mobile']) ||
            !isset($registration['password'])
        ) {
            return redirect()->route('register.details');
        }

        if (!isset($registration['profile'])) {
            return redirect()->route('register.profile');
        }

        if (!isset($registration['professional'])) {
            return redirect()->route('register.professional');
        }

        return view('auth.register-review', [
            'registration' => $registration,
            'accountType' => $registration['account_type'],
        ]);
    }

    public function storeReview(Request $request)
    {
        $registration = session('registration');

        if (!$registration || !isset($registration['account_type'])) {
            return redirect()->route('register.index');
        }

        if (
            !isset($registration['name']) ||
            !isset($registration['username']) ||
            !isset($registration['email']) ||
            !isset($registration['mobile']) ||
            !isset($registration['password'])
        ) {
            return redirect()->route('register.details');
        }

        if (!isset($registration['profile'])) {
            return redirect()->route('register.profile');
        }

        if (!isset($registration['professional'])) {
            return redirect()->route('register.professional');
        }

        $request->validate([
            'confirm_details' => ['accepted'],
        ], [
            'confirm_details.accepted' =>
            'Please confirm that your details are correct before continuing.',
        ]);

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        try {
            Mail::to($registration['email'])->send(
                new RegistrationOtpMail($otp, $registration['name'])
            );
        } catch (\Throwable $e) {
            Log::error('Registration OTP delivery failed.', [
                'exception' => $e::class,
            ]);

            return back()->with('error', 'We could not send your verification code. Please try again.');
        }

        // Store the code only after delivery succeeds, so the user never
        // reaches verification with a code that was not sent.
        session([
            'registration.otp' => $otp,
            'registration.otp_expires_at' => now()->addMinutes(10),
            'registration.otp_attempts' => 0,
            'registration.otp_last_sent_at' => now(),
        ]);

        return redirect()
            ->route('register.verify')
            ->with(
                'success',
                'A verification code has been sent to your email address.'
            );
    }
    public function showVerify()
    {
        $registration = session('registration');

        // Registration does not exist
        if (!$registration || !isset($registration['account_type'])) {
            return redirect()->route('register.index');
        }

        // Account details are missing
        if (
            !isset($registration['name']) ||
            !isset($registration['username']) ||
            !isset($registration['email']) ||
            !isset($registration['mobile']) ||
            !isset($registration['password'])
        ) {
            return redirect()->route('register.details');
        }

        // Profile details are missing
        if (!isset($registration['profile'])) {
            return redirect()->route('register.profile');
        }

        // Professional details are missing
        if (!isset($registration['professional'])) {
            return redirect()->route('register.professional');
        }

        // OTP has not been generated yet
        if (!session()->has('registration.otp')) {
            return redirect()->route('register.review');
        }

        return view('auth.register-verify', [
            'registration' => $registration,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $registration = session('registration');

        /*
    |--------------------------------------------------------------------------
    | Check Registration
    |--------------------------------------------------------------------------
    */

        if (!$registration || !isset($registration['account_type'])) {

            return redirect()
                ->route('register.index');
        }


        /*
    |--------------------------------------------------------------------------
    | Check OTP
    |--------------------------------------------------------------------------
    */

        if (!session()->has('registration.otp')) {

            return redirect()
                ->route('register.review');
        }


        /*
    |--------------------------------------------------------------------------
    | Validate Input
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'otp' => [
                'required',
                'digits:6',
            ],
        ]);


        $storedOtp =
            session('registration.otp');

        $expiresAt =
            session('registration.otp_expires_at');

        $attempts =
            (int) session('registration.otp_attempts', 0);


        /*
    |--------------------------------------------------------------------------
    | Maximum Attempts
    |--------------------------------------------------------------------------
    */

        if ($attempts >= 5) {

            session()->forget([
                'registration.otp',
                'registration.otp_expires_at',
                'registration.otp_attempts',
                'registration.otp_last_sent_at',
            ]);

            return redirect()
                ->route('register.review')
                ->withErrors([
                    'otp' =>
                    'Too many incorrect attempts. Please request a new verification code.',
                ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Check Expiration
    |--------------------------------------------------------------------------
    */

        if (
            !$expiresAt ||
            now()->greaterThan($expiresAt)
        ) {

            session()->forget([
                'registration.otp',
                'registration.otp_expires_at',
                'registration.otp_attempts',
            ]);

            return back()
                ->withErrors([
                    'otp' =>
                    'This verification code has expired. Please request a new code.',
                ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Compare OTP
    |--------------------------------------------------------------------------
    */

        if (
            !hash_equals(
                (string) $storedOtp,
                (string) $validated['otp']
            )
        ) {

            session([
                'registration.otp_attempts' =>
                $attempts + 1,
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'otp' =>
                    'The verification code is incorrect.',
                ]);
        }


        /*
    |--------------------------------------------------------------------------
    | OTP Correct
    | Create Account
    |--------------------------------------------------------------------------
    */

        try {

            /*
        |--------------------------------------------------------------------------
        | Job Seeker
        |--------------------------------------------------------------------------
        */

            DB::transaction(function () use ($registration): void {
            if (
                $registration['account_type'] === 'JobSeeker'
            ) {

                if (JobSeeker::where('UserName', $registration['username'])->orWhere('Email', $registration['email'])->exists()
                    || Employer::where('UserName', $registration['username'])->orWhere('Email', $registration['email'])->exists()) {
                    throw ValidationException::withMessages([
                        'otp' => 'An account with this username or email already exists. Please sign in or restart registration with different details.',
                    ]);
                }

                $profile =
                    $registration['profile'];

                $professional =
                    $registration['professional'];


                JobSeeker::create([

                    'JobSeekerName' =>
                    $registration['name'],

                    'Address' =>
                    $profile['address'],

                    'City' =>
                    $profile['city'],

                    'Email' =>
                    $registration['email'],

                    'Mobile' =>
                    $registration['mobile'],

                    'Qualification' =>
                    $professional['qualification'],

                    'Gender' =>
                    $profile['gender'],

                    'BirthDate' =>
                    $profile['birth_date'],

                    'Resume' =>
                    $professional['resume'] ?? null,

                    'Status' => 'Pending',

                    'UserName' =>
                    $registration['username'],

                    'Password' =>
                    $registration['password'],

                    'Question' =>
                    null,

                    'Answer' =>
                    null,

                    'Photo' =>
                    $professional['photo'] ?? null,

                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | Employer
        |--------------------------------------------------------------------------
            */ elseif (
                $registration['account_type'] === 'Employer'
            ) {

                if (JobSeeker::where('UserName', $registration['username'])->orWhere('Email', $registration['email'])->exists()
                    || Employer::where('UserName', $registration['username'])->orWhere('Email', $registration['email'])->exists()) {
                    throw ValidationException::withMessages([
                        'otp' => 'An account with this username or email already exists. Please sign in or restart registration with different details.',
                    ]);
                }

                $profile =
                    $registration['profile'];


                Employer::create([

                    'CompanyName' =>
                    $registration['name'],

                    'ContactPerson' =>
                    $profile['contact_person'],

                    'Address' =>
                    $profile['address'],

                    'City' =>
                    $profile['city'],

                    'Email' =>
                    $registration['email'],

                    'Mobile' =>
                    $registration['mobile'],

                    'Area_Work' =>
                    $profile['area_work'],

                    'Status' => 'Pending',

                    'UserName' =>
                    $registration['username'],

                    'Password' =>
                    $registration['password'],

                    'Question' =>
                    null,

                    'Answer' =>
                    null,

                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | Invalid Account Type
        |--------------------------------------------------------------------------
        */ else {
                throw ValidationException::withMessages([
                    'account_type' => 'Invalid account type.',
                ]);
            }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Registration account creation failed.', [
                'exception' => $e::class,
            ]);

            return back()->with('error', 'We could not create your account right now. Your details have not been submitted; please try again.');
        }


        /*
    |--------------------------------------------------------------------------
    | Registration Complete
    |--------------------------------------------------------------------------
    */

        session()->forget('registration');


        return redirect()
            ->route('login')
            ->with(
                'success',
                'Your account has been created successfully. You can now log in.'
            );
    }

    public function resendOtp()
    {
        $registration = session('registration');

        if (
            !$registration ||
            !isset($registration['email'])
        ) {
            return redirect()->route('register.index');
        }

        // Check when the OTP was last sent
        $lastSentAt = session('registration.otp_last_sent_at');

        if ($lastSentAt) {

            // Convert session value safely to Carbon
            if (!$lastSentAt instanceof Carbon) {
                $lastSentAt = Carbon::parse($lastSentAt);
            }

            // Calculate elapsed seconds
            $elapsedSeconds = (int) $lastSentAt->diffInSeconds(now());

            // 60-second cooldown
            if ($elapsedSeconds < 60) {

                $remaining = 60 - $elapsedSeconds;

                return back()->withErrors([
                    'otp' => "Please wait {$remaining} seconds before requesting another code.",
                ]);
            }
        }

        // Generate new 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        try {
            Mail::to($registration['email'])->send(
                new RegistrationOtpMail($otp, $registration['name'])
            );
        } catch (\Throwable $e) {
            Log::error('Registration OTP resend failed.', [
                'exception' => $e::class,
            ]);

            return back()->with('error', 'We could not send your verification code. Please try again.');
        }

        session([
            'registration.otp' => $otp,
            'registration.otp_expires_at' => now()->addMinutes(10),
            'registration.otp_attempts' => 0,
            'registration.otp_last_sent_at' => now(),
        ]);

        return back()->with(
            'success',
            'A new verification code has been sent to your email address.'
        );
    }
}
