<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

                $user = DB::table('employer_reg')
                    ->where('UserName', $username)
                    ->orWhere('Email', $username)
                    ->first();

                if (
                    $user &&
                    in_array($user->Status, ['Confirm', 'Confirmed', 'Approved']) &&
                    Hash::check($password, $user->Password)
                ) {

                    session([
                        'UserType' => 'Employer',
                        'ID' => $user->EmployerId,
                        'Name' => $user->CompanyName
                    ]);

                    return redirect('/employer');
                }

                break;

            case 'JobSeeker':

                $user = DB::table('jobseeker_reg')
                    ->where('UserName', $username)
                    ->orWhere('Email', $username)
                    ->first();

                if (
                    $user &&
                    in_array($user->Status, ['Confirm', 'Confirmed', 'Approved']) &&
                    Hash::check($password, $user->Password)
                ) {

                    session([
                        'UserType' => 'JobSeeker',
                        'ID' => $user->JobSeekId,
                        'Name' => $user->JobSeekerName
                    ]);

                    return redirect('/jobseeker');
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
