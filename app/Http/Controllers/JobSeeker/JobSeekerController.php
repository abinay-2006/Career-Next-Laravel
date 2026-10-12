<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class JobSeekerController extends Controller
{
    public function index()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        if (!$jobSeeker) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'login' => 'Please log in to access your Job Seeker dashboard.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PROFILE COMPLETION
        |--------------------------------------------------------------------------
        */

        $completionItems = [
            'name' => !empty(trim((string) $jobSeeker->JobSeekerName)),

            'email' => !empty(trim((string) $jobSeeker->Email)),

            'mobile' => !empty(trim((string) $jobSeeker->Mobile)),

            'city' => !empty(trim((string) $jobSeeker->City)),

            'address' => !empty(trim((string) $jobSeeker->Address)),

            'gender' => !empty(trim((string) $jobSeeker->Gender)),

            'birth_date' => !empty($jobSeeker->BirthDate),

            /*
            |--------------------------------------------------------------------------
            | EDUCATION
            |--------------------------------------------------------------------------
            | Check the actual education tables.
            */
            'education' =>
            $jobSeeker->education()->exists()
                ||
                $jobSeeker->schoolEducation()->exists(),

            /*
            |--------------------------------------------------------------------------
            | RESUME
            |--------------------------------------------------------------------------
            | Check the actual jobseeker_documents table.
            |
            | We use DocumentType = resume.
            */
            'resume' => $jobSeeker->documents()
                ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
                ->exists(),

            /*
            |--------------------------------------------------------------------------
            | PROFILE PHOTO
            |--------------------------------------------------------------------------
            */
            'photo' => !empty(trim((string) $jobSeeker->Photo)),
        ];

        /*
        |--------------------------------------------------------------------------
        | CALCULATE PERCENTAGE
        |--------------------------------------------------------------------------
        */

        $completedItems = count(
            array_filter($completionItems)
        );

        $totalItems = count($completionItems);

        $profileCompletion = $totalItems > 0
            ? (int) round(($completedItems / $totalItems) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | MISSING ITEMS
        |--------------------------------------------------------------------------
        */

        $profileCompletionLabels = [
            'name' => [
                'label' => 'Full Name',
                'route' => 'jobseeker.profile.index',
            ],

            'email' => [
                'label' => 'Email',
                'route' => 'jobseeker.profile.index',
            ],

            'mobile' => [
                'label' => 'Mobile Number',
                'route' => 'jobseeker.profile.index',
            ],

            'city' => [
                'label' => 'City',
                'route' => 'jobseeker.profile.index',
            ],

            'address' => [
                'label' => 'Address',
                'route' => 'jobseeker.profile.index',
            ],

            'gender' => [
                'label' => 'Gender',
                'route' => 'jobseeker.profile.index',
            ],

            'birth_date' => [
                'label' => 'Date of Birth',
                'route' => 'jobseeker.profile.index',
            ],

            'education' => [
                'label' => 'Education',
                'route' => 'jobseeker.education',
            ],

            'resume' => [
                'label' => 'Resume',
                'route' => 'jobseeker.profile.index',
            ],

            'photo' => [
                'label' => 'Profile Photo',
                'route' => 'jobseeker.profile.index',
            ],
        ];

        $missingProfileItems = [];

        foreach ($completionItems as $key => $completed) {

            if (!$completed) {

                $missingProfileItems[] = [
                    'key' => $key,
                    'label' => $profileCompletionLabels[$key]['label'],
                    'route' => $profileCompletionLabels[$key]['route'],
                ];
            }
        }

        return view('jobseeker.index', compact(
            'jobSeeker',
            'completionItems',
            'completedItems',
            'totalItems',
            'profileCompletion',
            'missingProfileItems'
        ));
    }

    public function education()
    {
        return view('jobseeker.education.index');
    }

    public function jobs()
    {
        return view('jobseeker.jobs.index');
    }

    public function walkins()
    {
        return view('jobseeker.walkins.index');
    }
}
