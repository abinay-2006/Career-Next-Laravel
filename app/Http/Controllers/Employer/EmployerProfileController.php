<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

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
            // Required
            'CompanyName' => ['required', 'string', 'max:255'],
            'Email'       => ['required', 'email', 'max:255'],
            'UserName'    => ['required', 'string', 'max:100'],

            // Optional
            'ContactPerson' => ['nullable', 'string', 'max:255'],
            'Address'       => ['nullable', 'string', 'max:1000'],
            'City'          => ['nullable', 'string', 'max:100'],
            'Mobile'        => ['nullable', 'string', 'max:20'],
            'Area_Work'     => ['nullable', 'string', 'max:255'],

            // Optional password
            'Password'      => ['nullable', 'string', 'min:6'],
            'Photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Upload Company Logo
    |--------------------------------------------------------------------------
    */

        if ($request->hasFile('Photo')) {

            $file = $request->file('Photo');

            $uploadPath =
                public_path('upload/photos');

            /*
        | Create folder if it doesn't exist
        */

            if (!File::exists($uploadPath)) {
                File::makeDirectory(
                    $uploadPath,
                    0755,
                    true
                );
            }

            /*
        | Delete old logo
        */

            if (
                !empty($employer->Photo) &&
                File::exists(
                    $uploadPath . '/' . $employer->Photo
                )
            ) {

                File::delete(
                    $uploadPath . '/' . $employer->Photo
                );
            }

            /*
        | Generate unique filename
        */

            $filename =
                'company_' .
                $employer->EmployerId .
                '_' .
                time() .
                '.' .
                $file->getClientOriginalExtension();

            /*
        | Move new logo
        */

            $file->move(
                $uploadPath,
                $filename
            );

            $validated['Photo'] = $filename;
        }

        /*
    |--------------------------------------------------------------------------
    | Update Employer
    |--------------------------------------------------------------------------
    */

        $employer->update($validated);

        return redirect()
            ->route('employer.profile.index')
            ->with(
                'success',
                'Company profile updated successfully.'
            );

        /*
    |--------------------------------------------------------------------------
    | Update profile fields
    |--------------------------------------------------------------------------
    */

        $employer->CompanyName   = $validated['CompanyName'];
        $employer->Email         = $validated['Email'];
        $employer->UserName      = $validated['UserName'];

        // Optional fields
        $employer->ContactPerson = $validated['ContactPerson'] ?? null;
        $employer->Address       = $validated['Address'] ?? null;
        $employer->City          = $validated['City'] ?? null;
        $employer->Mobile        = $validated['Mobile'] ?? null;
        $employer->Area_Work     = $validated['Area_Work'] ?? null;

        /*
    |--------------------------------------------------------------------------
    | Update password only if entered
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['Password'])) {
            $employer->Password = Hash::make($validated['Password']);
        }

        /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

        $employer->save();

        return redirect()
            ->route('employer.profile.index')
            ->with('success', 'Profile updated successfully.');
    }
}
