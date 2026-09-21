<?php

namespace App\Services;

use App\Models\JobSeeker;
use App\Models\Employer;
use Illuminate\Support\Facades\DB;

class ProfileCompletionService
{
    /*
    |--------------------------------------------------------------------------
    | Job Seeker Profile Completion
    |--------------------------------------------------------------------------
    */

    public function jobSeeker(JobSeeker $jobSeeker): array
    {
        $sections = [

            'basic' => [
                'label' => 'Basic Information',
                'weight' => 20,
                'complete' => $this->filled([
                    $jobSeeker->JobSeekerName,
                    $jobSeeker->Email,
                    $jobSeeker->Mobile,
                    $jobSeeker->City,
                    $jobSeeker->BirthDate,
                    $jobSeeker->Gender,
                ]),
            ],

            'education' => [
                'label' => 'Education',
                'weight' => 20,
                'complete' => $this->hasEducation($jobSeeker),
            ],

            'skills' => [
                'label' => 'Skills',
                'weight' => 15,
                'complete' => false,
            ],

            'resume' => [
                'label' => 'Resume',
                'weight' => 15,
                'complete' => $this->hasResume($jobSeeker),
            ],

            'experience' => [
                'label' => 'Work Experience',
                'weight' => 10,
                'complete' => false,
            ],

            'projects' => [
                'label' => 'Projects',
                'weight' => 5,
                'complete' => false,
            ],

            'certifications' => [
                'label' => 'Certifications',
                'weight' => 5,
                'complete' => false,
            ],

            'additional' => [
                'label' => 'Additional Information',
                'weight' => 10,
                'complete' => $this->filled([
                    $jobSeeker->Address,
                    $jobSeeker->Qualification,
                ]),
            ],
        ];

        return $this->calculate($sections);
    }


    /*
    |--------------------------------------------------------------------------
    | Employer Profile Completion
    |--------------------------------------------------------------------------
    */

    public function employer(Employer $employer): array
    {
        $sections = [

            'company' => [
                'label' => 'Company Information',
                'weight' => 25,
                'complete' => $this->filled([
                    $employer->CompanyName,
                    $employer->ContactPerson,
                    $employer->City,
                ]),
            ],

            'contact' => [
                'label' => 'Contact Information',
                'weight' => 15,
                'complete' => $this->filled([
                    $employer->Email,
                    $employer->Mobile,
                ]),
            ],

            'address' => [
                'label' => 'Company Address',
                'weight' => 10,
                'complete' => $this->filled([
                    $employer->Address,
                ]),
            ],

            'industry' => [
                'label' => 'Industry & Company Details',
                'weight' => 15,
                'complete' => false,
            ],

            'description' => [
                'label' => 'Company Description',
                'weight' => 10,
                'complete' => false,
            ],

            'logo' => [
                'label' => 'Company Logo',
                'weight' => 10,
                'complete' => false,
            ],

            'website' => [
                'label' => 'Website & Social Links',
                'weight' => 5,
                'complete' => false,
            ],

            'hiring' => [
                'label' => 'Hiring Information',
                'weight' => 10,
                'complete' => false,
            ],
        ];

        return $this->calculate($sections);
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Percentage
    |--------------------------------------------------------------------------
    */

    private function calculate(array $sections): array
    {
        $percentage = 0;
        $completed = 0;
        $total = count($sections);

        foreach ($sections as $section) {

            if ($section['complete']) {
                $percentage += $section['weight'];
                $completed++;
            }
        }

        $percentage = min(100, $percentage);

        return [
            'percentage' => $percentage,
            'completed' => $completed,
            'total' => $total,
            'sections' => $sections,
            'status' => $this->getStatus($percentage),
            'message' => $this->getMessage($percentage),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Check Filled Values
    |--------------------------------------------------------------------------
    */

    private function filled(array $values): bool
    {
        foreach ($values as $value) {

            if (
                $value === null ||
                trim((string) $value) === '' ||
                trim((string) $value) === '-'
            ) {
                return false;
            }
        }

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Job Seeker Education
    |--------------------------------------------------------------------------
    */

    private function hasEducation(JobSeeker $jobSeeker): bool
    {
        $higherEducationExists = DB::table('jobseeker_education')
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();

        $schoolEducationExists = DB::table('jobseeker_school_education')
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();

        return $higherEducationExists || $schoolEducationExists;
    }

    /*
|--------------------------------------------------------------------------
| Job Seeker Resume / Documents
|--------------------------------------------------------------------------
*/

    private function hasResume(JobSeeker $jobSeeker): bool
    {
        return DB::table('jobseeker_documents')
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Status
    |--------------------------------------------------------------------------
    */

    private function getStatus(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'Complete';
        }

        if ($percentage >= 80) {
            return 'Almost Complete';
        }

        if ($percentage >= 50) {
            return 'Good Progress';
        }

        if ($percentage >= 25) {
            return 'Getting Started';
        }

        return 'Just Started';
    }


    /*
    |--------------------------------------------------------------------------
    | Profile Message
    |--------------------------------------------------------------------------
    */

    private function getMessage(int $percentage): string
    {
        if ($percentage >= 100) {
            return 'Your profile is complete.';
        }

        if ($percentage >= 80) {
            return 'You are almost there. Complete the remaining sections.';
        }

        if ($percentage >= 50) {
            return 'Your profile is taking shape. Add a few more details.';
        }

        if ($percentage >= 25) {
            return 'Keep going to make your profile more complete.';
        }

        return 'Start completing your profile to get better opportunities.';
    }
}
