<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\JobSeekerDocument;
use App\Support\ProfilePhoto;
use App\Support\UploadStorage;

class JobSeekerProfileController extends Controller
{
    /**
     * Display Job Seeker Profile
     */
    public function index(Request $request)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        /*
    |--------------------------------------------------------------------------
    | Profile Initials
    |--------------------------------------------------------------------------
    */

        $initials = collect(
            preg_split('/\s+/', trim($jobSeeker->JobSeekerName ?? '')))
            ->filter()
            ->map(fn($name) => strtoupper(substr($name, 0, 1)))
            ->take(2)
            ->implode('');


        /*
    |--------------------------------------------------------------------------
    | Resume
    |--------------------------------------------------------------------------
    | Resume is now taken from jobseeker_documents.
    |
    | We use DocumentType = resume.
    |--------------------------------------------------------------------------
    */

        $resume = $jobSeeker->documents()
            ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
            ->latest('DocId')
            ->first();


        /*
    |--------------------------------------------------------------------------
    | PROFILE COMPLETION
    |--------------------------------------------------------------------------
    */

        $completionItems = [

            'name' =>
            !empty(trim((string) $jobSeeker->JobSeekerName)),

            'email' =>
            !empty(trim((string) $jobSeeker->Email)),

            'mobile' =>
            !empty(trim((string) $jobSeeker->Mobile)),

            'city' =>
            !empty(trim((string) $jobSeeker->City)),

            'address' =>
            !empty(trim((string) $jobSeeker->Address)),

            'gender' =>
            !empty(trim((string) $jobSeeker->Gender)),

            'birth_date' =>
            !empty($jobSeeker->BirthDate),

            /*
        |--------------------------------------------------------------------------
        | Education
        |--------------------------------------------------------------------------
        */

            'education' =>
            $jobSeeker->education()->exists()
                ||
                $jobSeeker->schoolEducation()->exists(),

            /*
        |--------------------------------------------------------------------------
        | Resume
        |--------------------------------------------------------------------------
        */

            'resume' =>
            $resume !== null,

            /*
        |--------------------------------------------------------------------------
        | Profile Photo
        |--------------------------------------------------------------------------
        */

            'photo' =>
            !empty(trim((string) $jobSeeker->Photo)),
        ];


        /*
    |--------------------------------------------------------------------------
    | Calculate Completion
    |--------------------------------------------------------------------------
    */

        $completedItems = count(
            array_filter($completionItems)
        );

        $totalItems = count($completionItems);

        $profileCompletion = $totalItems > 0
            ? (int) round(
                ($completedItems / $totalItems) * 100
            )
            : 0;


        /*
    |--------------------------------------------------------------------------
    | Completion Labels
    |--------------------------------------------------------------------------
    */

        $profileCompletionLabels = [

            'name' => 'Full Name',

            'email' => 'Email',

            'mobile' => 'Mobile Number',

            'city' => 'City',

            'address' => 'Address',

            'gender' => 'Gender',

            'birth_date' => 'Date of Birth',

            'education' => 'Education',

            'resume' => 'Resume',

            'photo' => 'Profile Photo',
        ];


        /*
    |--------------------------------------------------------------------------
    | Missing Profile Items
    |--------------------------------------------------------------------------
    */

        $missingProfileItems = [];

        foreach ($completionItems as $key => $completed) {

            if (!$completed) {

                $missingProfileItems[] =
                    $profileCompletionLabels[$key];
            }

        }



        /*
    |--------------------------------------------------------------------------
    | Deep Link Section
    |--------------------------------------------------------------------------
    |
    | Allows dashboard buttons to open the appropriate
    | profile section/modal.
    |--------------------------------------------------------------------------
    */

        $section = $request->query('section');


        /*
    |--------------------------------------------------------------------------
    | Return Profile View
    |--------------------------------------------------------------------------
    */

        return view(
            'jobseeker.profile.index',
            compact(
                'jobSeeker',
                'initials',
                'resume',
                'completionItems',
                'completedItems',
                'totalItems',
                'profileCompletion',
                'missingProfileItems',
                'section'
            )
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

        // Handle profile photo upload using configurable storage (public locally, s3 in production)
        if ($request->hasFile('Photo')) {
            try {
                $photo = $request->file('Photo');
                $diskName = UploadStorage::publicDiskName();

                $fileName = 'profile_' . $jobSeeker->JobSeekId . '_' . \Illuminate\Support\Str::uuid() . '.' . $photo->getClientOriginalExtension();
                $oldPath = ProfilePhoto::path($jobSeeker->Photo);
                $storedPath = UploadStorage::storeAs($photo, 'photos', $fileName, $diskName);
                $jobSeeker->Photo = $storedPath;

                try {
                    DB::transaction(function () use ($jobSeeker): void {
                        if (!$jobSeeker->save()) {
                            throw new \RuntimeException('Profile update was not persisted.');
                        }
                    });
                } catch (\Throwable $e) {
                    if (!$oldPath || $oldPath !== $storedPath) {
                        UploadStorage::deleteBestEffort($diskName, $storedPath, 'failed job seeker photo update cleanup');
                    }
                    throw $e;
                }

                if ($oldPath && $oldPath !== $storedPath) {
                    UploadStorage::deleteBestEffort($diskName, $oldPath, 'replaced job seeker photo cleanup');
                }
            }
 catch (\Exception $e) {
                Log::error('JobSeeker profile photo upload failed.', ['exception' => $e::class]);
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Unable to upload profile photo. Please try again later.'], 500);
                }
                return back()->withErrors(['Photo' => 'Unable to upload profile photo. Please try again later.']);
            }

        }


        try {
            if (!$request->hasFile('Photo')) {
                if (!$jobSeeker->save()) {
                    throw new \RuntimeException('Resume record was not persisted.');
                }
            }
        }
 catch (\Exception $e) {
            Log::error('JobSeeker profile save failed.', ['exception' => $e::class]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unable to save profile. Please try again later.'], 500);
            }
            return back()->withErrors(['profile' => 'Unable to save profile. Please try again later.']);
        }


        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Profile updated successfully.', 'refresh' => true]);
        }

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

        if (!$request->hasFile('Resume')) {
            return redirect()
                ->route('jobseeker.profile.index')
                ->withErrors([
                    'Resume' => 'Please select a resume file.'
                ]);
        }


        $file = $request->file('Resume');

        /*
    |--------------------------------------------------------------------------
    | Find existing resume document BEFORE replacing it
    |--------------------------------------------------------------------------
    */

        $existingDocument = $jobSeeker->documents()
            ->whereRaw(
                'LOWER(DocumentType) = ?',
                ['resume']
            )
            ->first();

        $oldResumeFile = $existingDocument?->FileName;
        $legacyResumeFile = $jobSeeker->Resume;

        /*
    |--------------------------------------------------------------------------
    | Resume directory
    |--------------------------------------------------------------------------
    */

        /*
    |--------------------------------------------------------------------------
    | Generate unique filename
    |--------------------------------------------------------------------------
    */

        $filename = 'resume_' . $jobSeeker->JobSeekId . '_' . \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();

        /*
    |--------------------------------------------------------------------------
    | Store new resume
    |--------------------------------------------------------------------------
    */

        $storedPath = null;
        try {
            $storedPath = UploadStorage::storeAs($file, 'documents', $filename, UploadStorage::privateDiskName());
        } catch (\Throwable $e) {
            Log::error('Resume upload storage failed.', ['exception' => $e::class]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'The resume could not be saved. Please try again.'], 500);
            }
            return back()->with('error', 'The resume could not be saved. Please try again.');
        }


        /*
    |--------------------------------------------------------------------------
    | Delete previous physical resume
    |--------------------------------------------------------------------------
    */

        try {
            DB::transaction(function () use ($jobSeeker, $existingDocument, $storedPath, $file): void {
                $jobSeeker->Resume = $storedPath;
                $jobSeeker->save();

                $values = [
                    'FileName' => $storedPath,
                    'OriginalFileName' => $file->getClientOriginalName(),
                    'UploadDate' => now(),
                    'DocumentType' => 'resume'
                ];

                if ($existingDocument) {
                    if (!$existingDocument->update($values)) {
                        throw new \RuntimeException('Resume document record was not persisted.');
                    }
                }
 else {
                    $created = $jobSeeker->documents()->create($values);
                    if (!$created->exists) {
                        throw new \RuntimeException('Resume document record was not persisted.');
                    }
                }

            });

            // If transaction succeeds, delete old file
            UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $oldResumeFile, 'replaced resume cleanup');

            if ($legacyResumeFile && $legacyResumeFile !== $storedPath) {
                UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $legacyResumeFile, 'legacy resume cleanup');
            }


            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Resume uploaded successfully.', 'refresh' => true]);
            }

            return redirect()
                ->route('jobseeker.profile.index')
                ->with('success', 'Resume uploaded successfully.');
        }
 catch (\Exception $e) {
            Log::error('Resume upload failed.', ['exception' => $e::class]);
            // Attempt to clean up the newly uploaded file on failure
            UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $storedPath, 'failed resume update cleanup');
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update resume record. Please try again.'], 500);
            }
            return back()->with('error', 'Failed to update resume record. Please try again.');
        }

    }


    /**
     * Delete Resume
     */
    public function removeResume(Request $request)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        // Find the resume document (DocumentType = resume)
        $resumeDoc = $jobSeeker->documents()
            ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
            ->first();

        // If no resume exists, just redirect with a message
        if (!$resumeDoc) {
            // Ensure legacy column cleared
            if (!empty($jobSeeker->Resume)) {
                $jobSeeker->Resume = null;
                $jobSeeker->save();
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'No resume to delete.', 'refresh' => true]);
            }

            return redirect()
                ->route('jobseeker.profile.index')
                ->with('success', 'No resume to delete.');
        }


        // Store filenames before deletion
        $storedPath = $resumeDoc->FileName;
        $legacyPath = $jobSeeker->Resume;

        // Delete the document record and clear legacy column within a transaction
        DB::transaction(function () use ($jobSeeker, $resumeDoc) {
            $resumeDoc->delete();
            $jobSeeker->Resume = null;
            $jobSeeker->save();
        });

        // Delete physical file from configured storage
        UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $storedPath, 'removed resume cleanup');

        // Delete legacy file if it exists and differs from the stored path
        if ($legacyPath && $legacyPath !== $storedPath) {
            UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $legacyPath, 'removed legacy resume cleanup');
        }


        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Resume removed successfully.', 'refresh' => true]);
        }

        return redirect()
            ->route('jobseeker.profile.index')
            ->with('success', 'Resume removed successfully.');
    }

}
