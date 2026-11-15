<?php

namespace App\Services;

/** Role-scoped descriptions of verified application behavior and Copilot boundaries. */
class CopilotCapabilityCatalog
{
    public function instructions(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin application capabilities: review dashboard totals; search and review Job Seeker and Employer accounts; approve, suspend, or delete those accounts; approve or reject jobs; approve or reject uploaded documents; manage news; review feedback; and inspect activity logs, including the recent successful login records supplied in Career Next data. Copilot can execute only account suspension through its Laravel confirmation flow. Account lookup and authorization are performed by Laravel; ask for an exact match and explicit confirmation. For every other change, explain the relevant Admin page and do not claim the change was performed. Never suspend, approve, reject, or delete from model-generated text.',
            'employer' => 'Employer application capabilities: manage only the employer account’s own job postings; use job quality checks; review applicants to those own jobs and set application status to Pending, Accepted, or Rejected; schedule, revise, cancel, and complete interviews for eligible own applications; use application chat; review delivered follow-up messages; view analytics; and update the employer profile. Copilot’s only write operation for employers is a confirmed update of the employer’s own company profile fields (company name, contact person, address, city, mobile, business area); it is applied only after the employer replies CONFIRM PROFILE UPDATE. For everything else, explain the relevant page and rules, and never claim to have applied a change or access another employer’s data.',
            'jobseeker' => 'Job Seeker application capabilities: browse approved jobs, apply, review own application history, save jobs, compare jobs, and use job matching and suitability guidance; manage own profile, education, schools, documents, resume, skills, and career preferences; confirm, decline, or request a reschedule for own interviews; use the follow-up coach, record own follow-up activity, deliberately send an edited follow-up message, and use application chat. Copilot’s only write operation for Job Seekers is a confirmed update of the seeker’s own profile fields (full name, address, city, mobile, qualification, gender, birth date); it is applied only after the seeker replies CONFIRM PROFILE UPDATE. For everything else, explain the relevant page and rules, and never claim to have applied a change or access another seeker’s data.',
            default => 'No application capabilities are available for this role. Do not suggest that an operation is supported.',
        };
    }
}
