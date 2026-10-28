<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Support\ProfilePhoto;
use App\Support\UploadStorage;

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
                $diskName = UploadStorage::publicDiskName();

                $filename =
                    'company_' .
                    $employer->EmployerId .
                    '_' .
                    \Illuminate\Support\Str::uuid() .
                    '.' .
                    $file->getClientOriginalExtension();

                $oldPath = ProfilePhoto::path($employer->Photo);
                $storedPath = UploadStorage::storeAs($file, 'photos', $filename, $diskName);

                $validated['Photo'] = $storedPath;

                $uploadPath = $storedPath;
            } catch (\Exception $e) {
                Log::error('Employer profile photo upload failed.', ['exception' => $e::class]);
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Unable to upload company photo. Please try again later.'], 500);
                }
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

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($employer, $validated): void {
                if (!$employer->update($validated)) {
                    throw new \RuntimeException('Employer profile update was not persisted.');
                }
            });
        } catch (\Throwable $e) {
            if (isset($uploadPath) && (!$oldPath || $oldPath !== $uploadPath)) {
                UploadStorage::deleteBestEffort(UploadStorage::publicDiskName(), $uploadPath, 'failed employer photo update cleanup');
            }
            Log::error('Employer profile save failed.', ['exception' => $e::class]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unable to save profile. Please try again later.'], 500);
            }
            return back()->withErrors(['profile' => 'Unable to save profile. Please try again later.']);
        }

        if (isset($uploadPath) && $oldPath && $oldPath !== $uploadPath) {
            UploadStorage::deleteBestEffort(UploadStorage::publicDiskName(), $oldPath, 'replaced employer photo cleanup');
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Company profile updated successfully.', 'refresh' => true]);
        }

        return redirect()
            ->route('employer.profile.index')
            ->with(
                'success',
                'Company profile updated successfully.'
            );
    }
}
