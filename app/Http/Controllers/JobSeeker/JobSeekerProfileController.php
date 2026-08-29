<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\File;

class JobSeekerProfileController extends Controller
{
    /**
     * Display Job Seeker Profile
     */
    public function index()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $initials = collect(
            preg_split('/\s+/', trim($jobSeeker->JobSeekerName ?? ''))
        )
            ->filter()
            ->map(fn($name) => strtoupper(substr($name, 0, 1)))
            ->take(2)
            ->implode('');

        return view(
            'jobseeker.profile.index',
            compact('jobSeeker', 'initials')
        );
    }

    /**
     * Update Job Seeker Profile
     */
    public function update(Request $request)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $validated = $request->validate([
            'JobSeekerName' => 'required|string|max:255',

            'Address' => 'nullable|string',

            'City' => 'nullable|string|max:100',

            'Email' => 'required|email|max:255',

            'Mobile' => 'nullable|string|max:20',

            'Qualification' => 'nullable|string|max:255',

            'Gender' => 'nullable|string|max:20',

            'BirthDate' => 'nullable|date',

            'UserName' => 'required|string|max:100',

            'Password' => 'nullable|string|min:6',

            'Photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $jobSeeker->JobSeekerName =
            $validated['JobSeekerName'];

        $jobSeeker->Address =
            $validated['Address'] ?? null;

        $jobSeeker->City =
            $validated['City'] ?? null;

        $jobSeeker->Email =
            $validated['Email'];

        $jobSeeker->Mobile =
            $validated['Mobile'] ?? null;

        $jobSeeker->Qualification =
            $validated['Qualification'] ?? null;

        $jobSeeker->Gender =
            $validated['Gender'] ?? null;

        $jobSeeker->BirthDate =
            $validated['BirthDate'] ?? null;

        $jobSeeker->UserName =
            $validated['UserName'];

        /*
         * Only change password when
         * the user entered a new password.
         */
        if (!empty($validated['Password'])) {
            $jobSeeker->Password =
                Hash::make($validated['Password']);
        }
        /*
|--------------------------------------------------------------------------
| Profile Photo
|--------------------------------------------------------------------------
*/

        if ($request->hasFile('Photo')) {

            $photo = $request->file('Photo');

            $photoDirectory = public_path('upload/photos');

            // Create directory if it doesn't exist
            if (!File::exists($photoDirectory)) {
                File::makeDirectory($photoDirectory, 0755, true);
            }

            // Delete old photo
            if (!empty($jobSeeker->Photo)) {

                $oldPhoto = $photoDirectory . '/' . $jobSeeker->Photo;

                if (File::exists($oldPhoto)) {
                    File::delete($oldPhoto);
                }
            }

            // Generate unique filename
            $fileName = 'profile_' .
                $jobSeeker->JobSeekId . '_' .
                time() . '.' .
                $photo->getClientOriginalExtension();

            // Store photo
            $photo->move($photoDirectory, $fileName);

            // Save filename
            $jobSeeker->Photo = $fileName;
        }
        $jobSeeker->save();

        return redirect()
            ->route('jobseeker.profile.index')
            ->with(
                'success',
                'Profile updated successfully.'
            );
    }

    /**
     * Upload / Update Resume
     */
    public function updateResume(Request $request)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $validated = $request->validate([
            'Resume' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('Resume')) {

            $file = $request->file('Resume');

            // Generate unique filename
            $filename = time() . '_' . $jobSeeker->JobSeekId . '_' . $file->getClientOriginalName();

            // Store inside public/upload
            $file->move(
                public_path('upload'),
                $filename
            );

            // Delete old resume if it exists
            if (
                !empty($jobSeeker->Resume) &&
                file_exists(public_path('upload/' . $jobSeeker->Resume))
            ) {
                unlink(public_path('upload/' . $jobSeeker->Resume));
            }

            // Save filename to database
            $jobSeeker->Resume = $filename;

            $jobSeeker->save();
        }

        return redirect()
            ->route('jobseeker.profile.index')
            ->with('success', 'Resume uploaded successfully.');
    }
    /**
     * Remove uploaded resume
     */
    public function removeResume()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Delete physical file
        if (
            !empty($jobSeeker->Resume) &&
            file_exists(public_path('upload/' . $jobSeeker->Resume))
        ) {
            unlink(public_path('upload/' . $jobSeeker->Resume));
        }

        // Remove resume reference from database
        $jobSeeker->Resume = null;
        $jobSeeker->save();

        return redirect()
            ->route('jobseeker.profile.index')
            ->with('success', 'Resume removed successfully.');
    }
}
