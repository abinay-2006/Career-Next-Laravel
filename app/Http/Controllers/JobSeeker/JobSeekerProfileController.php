<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\JobSeeker;
use Illuminate\Support\Facades\File;
use App\Models\JobSeekerDocument;

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
            preg_split('/\s+/', trim($jobSeeker->JobSeekerName ?? ''))
        )
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

            /*
        |--------------------------------------------------------------------------
        | Resume directory
        |--------------------------------------------------------------------------
        */

            $uploadDirectory = public_path('upload');

            if (!File::exists($uploadDirectory)) {
                File::makeDirectory($uploadDirectory, 0755, true);
            }

            /*
        |--------------------------------------------------------------------------
        | Generate unique filename
        |--------------------------------------------------------------------------
        */

            $filename =
                time() .
                '_' .
                $jobSeeker->JobSeekId .
                '_' .
                preg_replace(
                    '/[^A-Za-z0-9._-]/',
                    '_',
                    $file->getClientOriginalName()
                );

            /*
        |--------------------------------------------------------------------------
        | Store file
        |--------------------------------------------------------------------------
        */

            $file->move(
                $uploadDirectory,
                $filename
            );

            /*
        |--------------------------------------------------------------------------
        | Delete old physical resume
        |--------------------------------------------------------------------------
        */

            if (
                !empty($oldResumeFile) &&
                File::exists(
                    public_path('upload/' . $oldResumeFile)
                )
            ) {
                File::delete(
                    public_path('upload/' . $oldResumeFile)
                );
            }

            /*
        |--------------------------------------------------------------------------
        | Update legacy Resume field
        |--------------------------------------------------------------------------
        |
        | Keep this because your existing profile page currently
        | uses jobseeker_reg.Resume.
        |
        */

            $jobSeeker->Resume = $filename;
            $jobSeeker->save();

            /*
        |--------------------------------------------------------------------------
        | Update jobseeker_documents
        |--------------------------------------------------------------------------
        */

            $document = $jobSeeker->documents()
                ->whereRaw(
                    'LOWER(DocumentType) = ?',
                    ['resume']
                )
                ->first();

            if ($document) {

                /*
            | Replace existing resume document
            */

                $document->FileName = $filename;

                $document->OriginalFileName =
                    $file->getClientOriginalName();

                $document->UploadDate = now();

                $document->VerificationStatus = 'pending';

                $document->AdminNotes = null;

                $document->save();
            } else {

                /*
            | Create first resume document
            */

                $jobSeeker->documents()->create([

                    'DocumentType' => 'resume',

                    'FileName' => $filename,

                    'OriginalFileName' =>
                    $file->getClientOriginalName(),

                    'UploadDate' => now(),

                    'VerificationStatus' => 'pending',

                    'AdminNotes' => null,

                ]);
            }
        }

        return redirect()
            ->route('jobseeker.profile.index')
            ->with(
                'success',
                'Resume uploaded successfully.'
            );
    }

    /**
     * Remove uploaded resume
     */
    public function removeResume()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        /*
    |--------------------------------------------------------------------------
    | Find resume document
    |--------------------------------------------------------------------------
    */

        $resumeDocument = $jobSeeker->documents()
            ->whereRaw(
                'LOWER(DocumentType) = ?',
                ['resume']
            )
            ->first();

        /*
    |--------------------------------------------------------------------------
    | Delete physical file
    |--------------------------------------------------------------------------
    */

        if ($resumeDocument && !empty($resumeDocument->FileName)) {

            $resumePath = public_path(
                'upload/' . $resumeDocument->FileName
            );

            if (File::exists($resumePath)) {
                File::delete($resumePath);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Fallback:
    | Delete using legacy Resume field if document record
    | does not exist.
    |--------------------------------------------------------------------------
    */

        if (
            !empty($jobSeeker->Resume) &&
            File::exists(
                public_path('upload/' . $jobSeeker->Resume)
            )
        ) {

            File::delete(
                public_path('upload/' . $jobSeeker->Resume)
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Remove resume document record
    |--------------------------------------------------------------------------
    */

        if ($resumeDocument) {
            $resumeDocument->delete();
        }

        /*
    |--------------------------------------------------------------------------
    | Clear legacy Resume field
    |--------------------------------------------------------------------------
    */

        $jobSeeker->Resume = null;

        $jobSeeker->save();

        /*
    |--------------------------------------------------------------------------
    | Return to profile
    |--------------------------------------------------------------------------
    */

        return redirect()
            ->route('jobseeker.profile.index')
            ->with(
                'success',
                'Resume removed successfully.'
            );
    }
}
