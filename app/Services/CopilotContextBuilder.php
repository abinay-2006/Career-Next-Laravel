<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Job;
use App\Models\JobSeekerResume;
use App\Models\JobSeekerSkill;
use App\Models\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CopilotContextBuilder
{
    /** Supplies only static, role-specific guidance for ordinary conversation. */
    public function generalContext(string $role): string
    {
        return match ($role) {
            'admin' => 'Career Next is a recruitment platform with separate Admin, Employer, and Job Seeker areas. For live platform information, use the application data assistant.',
            'employer' => 'Career Next helps employers manage recruitment. For current jobs or applicants, use the application data assistant.',
            'jobseeker' => 'Career Next helps job seekers manage applications and career materials. For current applications or saved jobs, use the application data assistant.',
            default => '',
        };
    }

    public function build(string $role, mixed $account, string $message): string
    {
        return match ($role) {
            'jobseeker' => $this->forJobSeeker($account, $message),
            'employer' => $this->forEmployer($account, $message),
            'admin' => $this->forAdmin(),
            default => '',
        };
    }

    private function forJobSeeker(mixed $seeker, string $message): string
    {
        $id = (int) $seeker->JobSeekId;
        $facts = ['Profile: '.json_encode(['qualification' => $seeker->Qualification, 'city' => $seeker->City], JSON_UNESCAPED_UNICODE)];
        $skills = JobSeekerSkill::findFor($id);
        if ($skills) $facts[] = 'Self-reported skills: '.implode(', ', array_merge($skills->names('skills'), $skills->names('software')));
        if (Schema::hasTable('jobseeker_resumes')) {
            $resume = JobSeekerResume::query()->where('JobSeekId', $id)->latest('updated_at')->first();
            if ($resume) $facts[] = 'Resume Builder content: '.mb_substr(json_encode($resume->content, JSON_UNESCAPED_UNICODE) ?: '', 0, 5000);
        }
        if (Schema::hasTable('application_master')) {
            $apps = Application::query()->with('job:JobId,JobTitle,CompanyName,Status')->where('JobSeekId', $id)->latest('ApplicationId')->limit(10)->get();
            $facts[] = 'Own applications: '.json_encode($apps->map(fn ($app) => ['job' => $app->job?->JobTitle, 'company' => $app->job?->CompanyName, 'status' => $app->Status, 'date' => $app->ApplicationDate])->values(), JSON_UNESCAPED_UNICODE);
        }
        if (Schema::hasTable('job_master')) {
            $terms = collect(preg_split('/\W+/u', mb_strtolower($message)))->filter(fn ($term) => mb_strlen($term) > 3)->take(5);
            $jobs = Job::query()->whereRaw('LOWER(Status) = ?', ['approved'])
                ->when($terms->isNotEmpty(), fn ($q) => $q->where(function ($sub) use ($terms) {
                    foreach ($terms as $term) $sub->orWhere('JobTitle', 'like', '%'.$term.'%')->orWhere('Description', 'like', '%'.$term.'%')->orWhere('MinQualification', 'like', '%'.$term.'%');
                }))->latest('JobId')->limit(8)->get(['JobId', 'JobTitle', 'CompanyName', 'MinQualification', 'Description', 'SalaryFrom', 'SalaryTo']);
            $facts[] = 'Approved jobs possibly relevant to the request (only these may be recommended): '.json_encode($jobs->map(fn ($job) => ['title' => $job->JobTitle, 'company' => $job->CompanyName, 'qualification' => $job->MinQualification, 'description' => $job->Description, 'salary_range' => [$job->SalaryFrom, $job->SalaryTo], 'url' => route('jobseeker.jobs.show', $job->JobId)])->values(), JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $facts);
    }

    private function forEmployer(mixed $employer, string $message): string
    {
        $jobs = Job::query()->where('EmployerId', $employer->EmployerId)->latest('JobId')->limit(30)->get();
        $facts = ['Own job postings: '.json_encode($jobs->map(fn ($job) => ['id' => $job->JobId, 'title' => $job->JobTitle, 'status' => $job->Status, 'vacancies' => $job->Vacancy, 'requirements' => $job->MinQualification, 'description' => $job->Description])->values(), JSON_UNESCAPED_UNICODE)];
        if (Schema::hasTable('application_master') && Schema::hasTable('jobseeker_reg')) {
            $applications = DB::table('application_master as a')->join('job_master as j', 'j.JobId', '=', 'a.JobId')
                ->join('jobseeker_reg as s', 's.JobSeekId', '=', 'a.JobSeekId')
                ->where('j.EmployerId', $employer->EmployerId)->latest('a.ApplicationId')->limit(30)
                ->get(['a.ApplicationId', 'a.Status', 'j.JobTitle', 's.Qualification', 's.City']);
            $facts[] = 'Application summaries for own jobs only; application IDs are temporary reference labels, qualifications and city are candidate-provided, and no contact or document data is included: '.json_encode($applications, JSON_UNESCAPED_UNICODE);
            $facts[] = 'Own application counts by status: '.json_encode(DB::table('application_master as a')->join('job_master as j', 'j.JobId', '=', 'a.JobId')->where('j.EmployerId', $employer->EmployerId)->select('a.Status', DB::raw('COUNT(*) as total'))->groupBy('a.Status')->get(), JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $facts);
    }

    private function forAdmin(): string
    {
        $facts = [];
        foreach (['jobseeker_reg' => 'job seekers', 'employer_reg' => 'employers', 'job_master' => 'job postings', 'application_master' => 'applications', 'jobseeker_documents' => 'uploaded documents'] as $table => $label) {
            if (!Schema::hasTable($table)) continue;
            $total = DB::table($table)->count();
            $facts[] = "{$label}: {$total} total";
            if ($table === 'job_master' && Schema::hasColumn($table, 'Status')) $facts[] = 'Jobs by status: '.json_encode(DB::table($table)->select('Status', DB::raw('COUNT(*) as total'))->groupBy('Status')->get());
            if ($table === 'jobseeker_documents' && Schema::hasColumn($table, 'VerificationStatus')) $facts[] = 'Pending document verifications: '.DB::table($table)->whereRaw('LOWER(VerificationStatus) = ?', ['pending'])->count();
            if ($table === 'application_master' && Schema::hasColumn($table, 'Status')) $facts[] = 'Applications by status: '.json_encode(DB::table($table)->select('Status', DB::raw('COUNT(*) as total'))->groupBy('Status')->get());
        }

        $loginActivity = $this->recentSuccessfulLogins();
        $facts[] = 'Successful login activity from the last 30 days (up to 50 newest records). Each identifier is the account role and internal numeric ID; timestamps use the application timezone. Summarize only these recorded events and state when no records are present: '.json_encode($loginActivity, JSON_UNESCAPED_UNICODE);

        return implode("\n", $facts);
    }

    /** @return list<array{timestamp: string, account_role: string, account_id: int}> */
    private function recentSuccessfulLogins(): array
    {
        if (!Schema::hasTable('logs') || !Schema::hasColumn('logs', 'action')
            || !Schema::hasColumn('logs', 'cdate') || !Schema::hasColumn('logs', 'actor_type')
            || !Schema::hasColumn('logs', 'actor_id')) {
            return [];
        }

        return Log::query()
            ->where('action', 'login_succeeded')
            ->whereIn('actor_type', ['admin', 'employer', 'jobseeker'])
            ->whereNotNull('actor_id')
            ->where('cdate', '>=', now()->subDays(30))
            ->where('cdate', '<=', now())
            ->orderByDesc('cdate')
            ->orderByDesc('Id')
            ->limit(50)
            ->get(['cdate', 'actor_type', 'actor_id'])
            ->map(fn (Log $log): array => [
                'timestamp' => Carbon::parse($log->cdate)->toIso8601String(),
                'account_role' => (string) $log->actor_type,
                'account_id' => (int) $log->actor_id,
            ])
            ->values()
            ->all();
    }
}
