<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Support\ProfilePhoto;

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

        // Handle company photo upload using configurable storage (public locally, s3 in production)
        if ($request->hasFile('Photo')) {
            try {
                $file = $request->file('Photo');
                $disk = Storage::disk('public');

                // Generate a unique filename
                $filename =
                    'company_' .
                    $employer->EmployerId .
                    '_' .
                    time() .
                    '.' .
                    $file->getClientOriginalExtension();

                // Delete old photo if it exists
                $oldPath = ProfilePhoto::path($employer->Photo);
                if ($oldPath && $disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }

                // Store the new photo
                $storedPath = $file->storeAs('photos', $filename, 'public');
                if (!$storedPath) {
                    throw new \RuntimeException('The company photo could not be written to the public disk.');
                }

                // Save filename in DB
                $validated['Photo'] = $storedPath;
            } catch (\Exception $e) {
                Log::error('Employer profile photo upload failed: ' . $e->getMessage());
                return back()->withErrors(['Photo' => 'Unable to upload company photo. Please try again later.']);
            }
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
