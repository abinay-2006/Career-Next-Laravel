<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class EmployerProfileController extends Controller
{
    /**
     * Display employer profile.
     */
    public function index()
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            return redirect()->route('login');
        }

        return view('employer.profile.index', compact('employer'));
    }

    /**
     * Update employer profile.
     */
    public function update(Request $request)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            // Required fields
            'CompanyName' => ['required', 'string', 'max:255'],
            'Email' => ['required', 'email', 'max:255'],
            'UserName' => ['required', 'string', 'max:100'],

            // Optional fields
            'ContactPerson' => ['nullable', 'string', 'max:255'],
            'Address' => ['nullable', 'string', 'max:1000'],
            'City' => ['nullable', 'string', 'max:100'],
            'Mobile' => ['nullable', 'string', 'max:20'],
            'Area_Work' => ['nullable', 'string', 'max:255'],

            // Optional password
            'Password' => ['nullable', 'string', 'min:6'],

            // Optional company photo
            'Photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Company Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('Photo')) {

            $file = $request->file('Photo');

            $uploadPath = public_path('upload/photos');

            // Create upload directory if it doesn't exist.
            if (!File::exists($uploadPath)) {
                File::makeDirectory(
                    $uploadPath,
                    0755,
                    true
                );
            }

            // Delete old company logo.
            if (
                !empty($employer->Photo) &&
                File::exists($uploadPath . '/' . $employer->Photo)
            ) {
                File::delete(
                    $uploadPath . '/' . $employer->Photo
                );
            }

            // Generate unique filename.
            $filename =
                'company_' .
                $employer->EmployerId .
                '_' .
                time() .
                '.' .
                $file->getClientOriginalExtension();

            // Move new logo.
            $file->move(
                $uploadPath,
                $filename
            );

            $validated['Photo'] = $filename;
        }

        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        */

        if (!empty($validated['Password'])) {
            $validated['Password'] = Hash::make(
                $validated['Password']
            );
        } else {
            // Do not overwrite the existing password
            // when the password field is left empty.
            unset($validated['Password']);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Employer
        |--------------------------------------------------------------------------
        */

        $employer->update($validated);

        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('employer.profile.index')
            ->with(
                'success',
                'Company profile updated successfully.'
            );
    }
}
