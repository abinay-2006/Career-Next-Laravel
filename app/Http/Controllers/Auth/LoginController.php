<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Employer;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeeker;


class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'txtUser' => 'required',
            'txtPass' => 'required',
            'cmbUser' => 'required'
        ]);

        $username = $request->txtUser;
        $password = $request->txtPass;
        $type = $request->cmbUser;

        switch ($type) {

            case 'Administrator':

                $user = DB::table('user_master')
                    ->where('UserName', $username)
                    ->first();

                if ($user && Hash::check($password, $user->Password)) {

                    session([
                        'UserType' => 'Administrator',
                        'Name' => $user->UserName
                    ]);

                    return redirect()->route('admin.index');
                }

                break;

            case 'Employer':

                $employer = Employer::where(function ($query) use ($username) {
                    $query->where('UserName', $username)
                        ->orWhere('Email', $username);
                })
                    ->first();

                if (
                    $employer &&
                    Hash::check($password, $employer->Password) &&
                    in_array($employer->Status, ['Confirm', 'Confirmed', 'Approved'])
                ) {
                    Auth::guard('employer')->login($employer);

                    session([
                        'UserType' => 'Employer',
                        'ID' => $employer->EmployerId,
                        'Name' => $employer->CompanyName,
                    ]);

                    return redirect()->route('employer.dashboard');
                }

                break;

            case 'JobSeeker':

                $jobSeeker = JobSeeker::where(function ($query) use ($username) {
                    $query->where('UserName', $username)
                        ->orWhere('Email', $username);
                })->first();
                
                if (

                    $jobSeeker &&
                    Hash::check($password, $jobSeeker->Password) &&
                    in_array($jobSeeker->Status, ['Confirm', 'Confirmed', 'Approved'])
                ) {
                    Auth::guard('jobseeker')->login($jobSeeker);
                    
                    session([
                        'UserType' => 'JobSeeker',
                        'ID' => $jobSeeker->JobSeekId,
                        'Name' => $jobSeeker->JobSeekerName,
                    ]);

                    return redirect()->route('jobseeker.index');
                }

                break;
        }

        return back()
            ->withInput()
            ->with('error', 'Wrong username or password.');
    }
    public function logout()
    {
        session()->flush();

        return redirect()->route('login');
    }
}
