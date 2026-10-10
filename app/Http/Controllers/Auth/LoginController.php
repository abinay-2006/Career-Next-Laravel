<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Employer;
use App\Models\JobSeeker;
use App\Models\Admin;

class LoginController extends Controller
{
    /**
     * Display login page.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login.
     */

    public function login(Request $request)
    {
        $request->validate([
            'txtUser' => 'required',
            'txtPass' => 'required',
            'cmbUser' => 'required',
        ]);

        $username = $request->txtUser;
        $password = $request->txtPass;
        $type = $request->cmbUser;

        switch ($type) {

            /*
        |--------------------------------------------------------------------------
        | Administrator
        |--------------------------------------------------------------------------
        */
            case 'Administrator':

                $user = Admin::query()
                    ->where('UserName', $username)
                    ->first();

                if ($user && Hash::check($password, $user->getAuthPassword())) {

                    Auth::guard('admin')->login($user);
                    $request->session()->regenerate();

                    session([
                        'UserType' => 'Administrator',
                        'Name' => $user->UserName,
                    ]);

                    return redirect()->route('admin.index')->with('success', 'You have signed in successfully.');
                }

                break;


            /*
        |--------------------------------------------------------------------------
        | Employer
        |--------------------------------------------------------------------------
        */
            case 'Employer':

                $employer = Employer::where(function ($query) use ($username) {
                    $query->where('UserName', $username)
                        ->orWhere('Email', $username);
                })->first();

                if ($employer && Hash::check($password, $employer->Password)) {

                    $status = strtolower(trim($employer->Status ?? ''));

                    if ($status === 'pending') {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account is awaiting admin approval.');
                    }

                    if ($status === 'suspended') {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account has been suspended. Please contact the administrator.');
                    }

                    if (!in_array($status, ['confirm', 'confirmed', 'approved'])) {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account is not active. Please contact the administrator.');
                    }

                    Auth::guard('employer')->login($employer);

                    $request->session()->regenerate();

                    session([
                        'UserType' => 'Employer',
                        'ID' => $employer->EmployerId,
                        'Name' => $employer->CompanyName,
                    ]);

                    return redirect()->route('employer.dashboard')->with('success', 'You have signed in successfully.');
                }

                break;


            /*
        |--------------------------------------------------------------------------
        | Job Seeker
        |--------------------------------------------------------------------------
        */
            case 'JobSeeker':

                $jobSeeker = JobSeeker::where(function ($query) use ($username) {
                    $query->where('UserName', $username)
                        ->orWhere('Email', $username);
                })->first();

                if ($jobSeeker && Hash::check($password, $jobSeeker->Password)) {

                    $status = strtolower(trim($jobSeeker->Status ?? ''));

                    if ($status === 'pending') {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account is awaiting admin approval.');
                    }

                    if ($status === 'suspended') {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account has been suspended. Please contact the administrator.');
                    }

                    if (!in_array($status, ['confirm', 'confirmed', 'approved'])) {
                        return back()
                            ->withInput($request->only('txtUser', 'cmbUser'))
                            ->with('error', 'Your account is not active. Please contact the administrator.');
                    }

                    Auth::guard('jobseeker')->login($jobSeeker);

                    $request->session()->regenerate();

                    session([
                        'UserType' => 'JobSeeker',
                        'ID' => $jobSeeker->JobSeekId,
                        'Name' => $jobSeeker->JobSeekerName,
                    ]);

                    return redirect()->route('jobseeker.index')->with('success', 'You have signed in successfully.');
                }

                break;
        }

        return back()
            ->withInput($request->only('txtUser', 'cmbUser'))
            ->with('error', 'Wrong username or password.');
    }

    /**
     * Logout the currently authenticated user.
     */
    public function logout(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Logout from Laravel guards
        |--------------------------------------------------------------------------
        */

        Auth::guard('admin')->logout();
        Auth::guard('employer')->logout();
        Auth::guard('jobseeker')->logout();

        /*
        |--------------------------------------------------------------------------
        | Logout Administrator session
        |--------------------------------------------------------------------------
        |
        | Administrator currently uses the session-based authentication
        | system, so clear the administrator session as well.
        |
        */

        $request->session()->forget([
            'UserType',
            'ID',
            'Name',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Invalidate session
        |--------------------------------------------------------------------------
        */

        $request->session()->invalidate();

        /*
        |--------------------------------------------------------------------------
        | Regenerate CSRF token
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
