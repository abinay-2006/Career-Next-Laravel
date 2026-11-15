<?php

namespace App\Services;

/** Role-scoped, reviewed allowlist of Copilot functions. */
class CopilotCapabilityRegistry
{
    /** @return array<string, list<string>> */
    public function categories(): array
    {
        return [
            'read_only' => ['get_dashboard_statistics', 'get_registration_statistics', 'get_recent_login_activity', 'get_application_statistics', 'get_recent_jobs', 'get_pending_document_reviews', 'get_user_details'],
            'self_service' => ['get_employer_dashboard_statistics', 'get_my_jobs', 'get_employer_job_applicants', 'get_my_interviews', 'get_my_employer_profile', 'get_my_profile', 'get_my_applications', 'get_my_saved_jobs', 'get_my_resume_summary'],
            'sensitive_write' => ['suspend_user'],
            // Stages a change to the caller's own profile; Laravel applies it only after explicit confirmation.
            'confirmed_self_write' => ['request_profile_update'],
        ];
    }

    /** Declaration for the role's own-profile update tool; properties come from the service allowlist. */
    private function profileUpdateDeclaration(string $role, string $description): array
    {
        $properties = [];
        foreach (CopilotProfileUpdateService::editableFields($role) as $name => $label) {
            $properties[$name] = ['type' => 'STRING', 'description' => 'New '.mb_strtolower($label).($name === 'birth_date' ? ' (YYYY-MM-DD).' : ($name === 'gender' ? ' (Male, Female or Other).' : '.'))];
        }

        return $this->fn('request_profile_update', $description, $this->object($properties, []));
    }

    public function declarations(string $role): array
    {
        $common = ['type' => 'OBJECT'];
        return match ($role) {
            'admin' => [
                $this->fn('get_dashboard_statistics', 'Read aggregate Career Next totals and status counts.', $common),
                $this->fn('get_registration_statistics', 'Read new Job Seeker and Employer registrations for a bounded period.', $this->object(['days' => ['type' => 'INTEGER', 'description' => 'Number of days to inspect, from 1 to 90.']], ['days'])),
                $this->fn('get_recent_login_activity', 'Read successful login events recorded in the existing activity log.', $this->object(['days' => ['type' => 'INTEGER', 'description' => 'Number of past days, up to 30.'], 'limit' => ['type' => 'INTEGER', 'description' => 'Maximum event count, up to 50.']], [])),
                $this->fn('get_application_statistics', 'Read aggregate application totals and status counts.', $common),
                $this->fn('get_recent_jobs', 'Read recent job postings with safe summary fields.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 20.']], [])),
                $this->fn('get_pending_document_reviews', 'Read pending document review summaries without document contents.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_user_details', 'Find an exact Job Seeker or Employer by numeric account ID and return safe account fields.', $this->object(['account_type' => ['type' => 'STRING', 'enum' => ['jobseeker', 'employer']], 'account_id' => ['type' => 'INTEGER', 'description' => 'The positive numeric account ID.']], ['account_type', 'account_id'])),
                $this->fn('suspend_user', 'Request suspension of one Job Seeker or Employer account. Laravel will ask the Admin to confirm before any change.', $this->object(['account_type' => ['type' => 'STRING', 'enum' => ['jobseeker', 'employer']], 'account_id' => ['type' => 'INTEGER', 'description' => 'The positive numeric account ID.']], ['account_type', 'account_id'])),
            ],
            'employer' => [
                $this->fn('get_employer_dashboard_statistics', 'Read the existing recruitment dashboard statistics for the authenticated employer only.', $this->object(['range' => ['type' => 'STRING', 'enum' => ['7', '30', '90', 'all'], 'description' => 'Analytics window in days, or all.']], [])),
                $this->fn('get_my_jobs', 'Read only the authenticated employer own job postings.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_employer_job_applicants', 'Read safe application summaries only for a job owned by the authenticated employer.', $this->object(['job_id' => ['type' => 'INTEGER', 'description' => 'The positive ID of your job posting.'], 'limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], ['job_id'])),
                $this->fn('get_my_interviews', 'Read interview schedule summaries for applications to the authenticated employer own jobs.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_my_employer_profile', 'Read safe business profile fields for the authenticated employer.', $common),
                $this->profileUpdateDeclaration('employer', 'Propose changes to the authenticated employer own company profile. Include only the fields the user asked to change. Laravel validates the values, shows the exact changes, and applies nothing until the user confirms.'),
            ],
            'jobseeker' => [
                $this->fn('get_my_profile', 'Read a small safe summary of the authenticated Job Seeker own profile.', $common),
                $this->fn('get_my_applications', 'Read the authenticated Job Seeker own application statuses and job summaries.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_my_saved_jobs', 'Read jobs saved by the authenticated Job Seeker.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_my_interviews', 'Read interview schedule summaries attached only to the authenticated Job Seeker own applications.', $this->object(['limit' => ['type' => 'INTEGER', 'description' => 'Maximum result count, up to 30.']], [])),
                $this->fn('get_my_resume_summary', 'Read only the title, template, and updated time of the authenticated Job Seeker own saved resume. Resume contents are not sent.', $common),
                $this->profileUpdateDeclaration('jobseeker', 'Propose changes to the authenticated Job Seeker own profile. Include only the fields the user asked to change. Laravel validates the values, shows the exact changes, and applies nothing until the user confirms.'),
            ],
            default => [],
        };
    }

    public function names(string $role): array
    {
        return array_column($this->declarations($role), 'name');
    }

    public function instructions(string $role): string
    {
        return match ($role) {
            'admin' => 'You assist an authenticated Admin. Use functions for live data; do not infer platform facts. Account registration dates are not stored, so weekly or monthly registration counts cannot be provided. Sensitive operations are only proposed by a function and require explicit Laravel confirmation. Never claim an operation completed unless a tool result confirms it.',
            'employer' => 'You assist an authenticated Employer. Use functions for live data; they return only records owned by this employer. Never screen, approve, or reject applicants. '
                .'You can update this employer own company profile (company name, contact person, address, city, mobile number, business area): when asked, call request_profile_update with only the requested fields and new values; if values are missing, ask for them first. '
                .'Laravel shows the changes and the user must reply CONFIRM PROFILE UPDATE before anything is saved, so never claim a change was made. Email, username, password, logo and account status are changed on the Profile page, not by Copilot.',
            'jobseeker' => 'You assist an authenticated Job Seeker. Use functions for live personal data; they return only this user records. Never apply for jobs, alter statuses, or invent qualifications. '
                .'You can update this user own profile (full name, address, city, mobile number, qualification, gender, birth date): when asked, call request_profile_update with only the requested fields and the values the user gave; if values are missing, ask for them first. '
                .'Laravel shows the changes and the user must reply CONFIRM PROFILE UPDATE before anything is saved, so never claim a change was made. Email, username, password, photo, resume, education records and skills are changed on the Profile and Education pages, not by Copilot.',
            default => 'Use only available functions and do not claim access to application data.',
        };
    }

    private function fn(string $name, string $description, array $parameters): array
    {
        return ['name' => $name, 'description' => $description, 'parameters' => $parameters];
    }

    private function object(array $properties, array $required): array
    {
        return ['type' => 'OBJECT', 'properties' => $properties, 'required' => $required];
    }
}
