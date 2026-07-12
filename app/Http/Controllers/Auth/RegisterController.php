<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'cmbUser' => 'required',
            'txtName' => 'required|string|max:255',
            'txtUser' => 'required|string|max:100',
            'txtEmail' => 'required|email|max:255',
            'txtPass' => 'required|min:6',
        ]);

        // Username exists?
        if (
            JobSeeker::where('UserName', $request->txtUser)->exists() ||
            Employer::where('UserName', $request->txtUser)->exists()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'register' => 'Username is already taken.'
                ]);
        }

        if ($request->cmbUser == 'JobSeeker') {

            if (JobSeeker::where('Email', $request->txtEmail)->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'register' => 'Email already exists.'
                    ]);
            }

            JobSeeker::create([
                'JobSeekerName' => $request->txtName,
                'UserName'      => $request->txtUser,
                'Email'         => $request->txtEmail,
                'Password'      => Hash::make($request->txtPass),
                'Status'        => 'Pending',
            ]);
        } elseif ($request->cmbUser == 'Employer') {

            if (Employer::where('Email', $request->txtEmail)->exists()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'register' => 'Email already exists.'
                    ]);
            }

            Employer::create([
                'CompanyName' => $request->txtName,
                'UserName'    => $request->txtUser,
                'Email'       => $request->txtEmail,
                'Password'    => Hash::make($request->txtPass),
                'Status'      => 'Pending',
            ]);
        } else {

            return back()
                ->withInput()
                ->withErrors([
                    'register' => 'Invalid account type.'
                ]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Registration successful. Awaiting admin approval.');
    }
}
