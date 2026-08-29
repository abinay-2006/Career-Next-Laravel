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
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'cmbUser' => 'required|in:JobSeeker,Employer',

            'txtName' => 'required|string|max:255',

            'txtUser' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/'
            ],

            'txtEmail' => 'required|email|max:255',

            'txtPass' => 'required|string|min:8',

            'txtConfirm' => 'required|same:txtPass',

            'terms' => 'accepted',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check Username
        |--------------------------------------------------------------------------
        */

        $usernameExists =
            JobSeeker::where('UserName', $request->txtUser)->exists()
            ||
            Employer::where('UserName', $request->txtUser)->exists();

        if ($usernameExists) {
            return response()->json([
                'success' => false,
                'message' => 'That username is already taken. Please choose another one.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Email
        |--------------------------------------------------------------------------
        */

        $emailExists =
            JobSeeker::where('Email', $request->txtEmail)->exists()
            ||
            Employer::where('Email', $request->txtEmail)->exists();

        if ($emailExists) {
            return response()->json([
                'success' => false,
                'message' => 'That email address is already registered. Please sign in or use another email.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Job Seeker
        |--------------------------------------------------------------------------
        */

        if ($request->cmbUser === 'JobSeeker') {

            $jobSeeker = JobSeeker::create([
                // Basic registration information
                'JobSeekerName' => $request->txtName,
                'UserName'      => $request->txtUser,
                'Email'         => $request->txtEmail,
                'Password'      => Hash::make($request->txtPass),

                // Default profile information (to be completed later)
                'Address'       => ' ', // Provide a non-empty string for NOT NULL fields
                'City'          => ' ', // Provide a non-empty string for NOT NULL fields
                'Mobile'        => ' ', // Provide a non-empty string for NOT NULL fields
                'Qualification' => ' ', // Provide a non-empty string for NOT NULL fields
                'Gender'        => ' ', // Provide a non-empty string for NOT NULL fields
                'BirthDate'     => '1970-01-01', // Provide a default valid date for NOT NULL date fields
                'Resume'        => ' ', // Provide a non-empty string for NOT NULL fields
                'Status'        => 'Pending', // Set account status
                'Question'      => ' ', // Default security question
                'Answer'        => ' ', // Default security answer
                'Photo'         => ' ', // Default photo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Job seeker account created successfully.',
                'type'    => 'JobSeeker',
                'id'      => $jobSeeker->JobSeekId,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Employer
        |--------------------------------------------------------------------------
        */

        if ($request->cmbUser === 'Employer') {

            $employer = Employer::create([
                // Basic registration information
                'CompanyName'   => $request->txtName,
                'UserName'      => $request->txtUser,
                'Email'         => $request->txtEmail,
                'Password'      => Hash::make($request->txtPass),

                // Default profile information (to be completed later)
                'ContactPerson' => ' ', // Provide a non-empty string for NOT NULL fields
                'Address'       => ' ', // Provide a non-empty string for NOT NULL fields
                'City'          => ' ', // Provide a non-empty string for NOT NULL fields
                'Mobile'        => ' ', // Provide a non-empty string for NOT NULL fields
                'Area_Work'     => ' ', // Provide a non-empty string for NOT NULL fields
                'Status'        => 'Pending', // Set account status
                'Question'      => ' ', // Default security question
                'Answer'        => ' ', // Default security answer
                'Photo'         => ' ', // Default photo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employer account created successfully.',
                'type'    => 'Employer',
                'id'      => $employer->EmployerId,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Invalid Account Type
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => false,
            'message' => 'Invalid account type.'
        ], 422);
    }

}
