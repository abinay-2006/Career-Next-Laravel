<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/** Strictly validates and dispatches only the functions advertised to this authenticated role. */
class CopilotToolDispatcher
{
    public function __construct(private readonly CopilotCapabilityRegistry $registry, private readonly Request $request) {}

    public function dispatch(string $role, mixed $actor, string $name, array $args): array
    {
        if (!in_array($name, $this->registry->names($role), true)) return ['error' => 'This function is not available for your account.'];
        if (!$this->validArguments($name, $args)) return ['error' => 'The requested function arguments were invalid.'];
        if ($role === 'admin' && !$actor instanceof Admin) return ['error' => 'This function is not available for your account.'];
        if ($role === 'employer' && !$actor instanceof Employer) return ['error' => 'This function is not available for your account.'];
        if ($role === 'jobseeker' && !$actor instanceof JobSeeker) return ['error' => 'This function is not available for your account.'];
        $authenticated = Auth::guard($role)->user();
        if (!$authenticated || (int) $authenticated->getAuthIdentifier() !== (int) $actor->getAuthIdentifier()) return ['error' => 'This function is not available for your account.'];

        return match ($name) {
            'get_dashboard_statistics' => $this->adminStats(),
            'get_registration_statistics' => ['available' => false, 'reason' => 'Registration dates are not stored in the current account tables.'],
            'get_recent_login_activity' => $this->loginActivity($args),
            'get_application_statistics' => $this->applicationStats(),
            'get_recent_jobs' => $this->recentJobs($args),
            'get_pending_document_reviews' => $this->pendingDocuments($args),
            'get_user_details' => $this->userDetails($args),
            'suspend_user' => $this->suspendUser($actor, $args),
            'get_employer_dashboard_statistics' => $this->employerDashboard($actor, $args),
            'get_my_jobs' => $this->employerJobs($actor, $args),
            'get_employer_job_applicants' => $this->employerApplicants($actor, $args),
            'get_my_interviews' => $this->interviews($role, $actor, $args),
            'get_my_employer_profile' => ['profile' => ['company_name' => $actor->CompanyName, 'city' => $actor->City, 'business_area' => $actor->Area_Work]],
            'get_my_profile' => $this->seekerProfile($actor),
            'get_my_applications' => $this->seekerApplications($actor, $args),
            'get_my_saved_jobs' => $this->savedJobs($actor, $args),
            'get_my_resume_summary' => $this->resumeSummary($actor),
            // Stages only; the authenticated actor (never a model-supplied ID) is the target.
            'request_profile_update' => app(CopilotProfileUpdateService::class)->stage($this->request, $role, $actor, $args),
            default => ['error' => 'This function is not available.'],
        };
    }

    private function validArguments(string $name, array $args): bool
    {
        // Field allowlisting, value validation and clear per-field messages happen in
        // CopilotProfileUpdateService; here only the shape is checked.
        if ($name === 'request_profile_update') {
            $keys = array_keys($args);
            return count($keys) <= 12 && $keys === array_values(array_filter($keys, 'is_string'));
        }

        $allowed = match ($name) {
            'get_dashboard_statistics', 'get_application_statistics', 'get_my_profile', 'get_my_employer_profile', 'get_my_resume_summary' => [],
            'get_employer_dashboard_statistics' => ['range'],
            'get_registration_statistics' => ['days'],
            'get_recent_login_activity' => ['days', 'limit'],
            'get_recent_jobs', 'get_pending_document_reviews', 'get_my_jobs', 'get_my_applications', 'get_my_saved_jobs', 'get_my_interviews' => ['limit'],
            'get_user_details', 'suspend_user' => ['account_type', 'account_id'],
            'get_employer_job_applicants' => ['job_id', 'limit'],
            default => null,
        };
        if ($allowed === null || array_diff(array_keys($args), $allowed) !== []) return false;
        $required = match ($name) {
            'get_registration_statistics' => ['days'],
            'get_user_details', 'suspend_user' => ['account_type', 'account_id'],
            'get_employer_job_applicants' => ['job_id'],
            default => [],
        };
        if (array_diff($required, array_keys($args)) !== []) return false;
        foreach (['days' => [1, 90], 'limit' => [1, 50], 'account_id' => [1, PHP_INT_MAX], 'job_id' => [1, PHP_INT_MAX]] as $key => [$min, $max]) {
            if (array_key_exists($key, $args) && (!is_int($args[$key]) || $args[$key] < $min || $args[$key] > $max)) return false;
        }
        if (isset($args['days']) && $args['days'] > ($name === 'get_registration_statistics' ? 90 : 30)) return false;
        $maxLimit = match ($name) {
            'get_recent_jobs' => 20,
            'get_pending_document_reviews', 'get_my_jobs', 'get_employer_job_applicants', 'get_my_applications', 'get_my_saved_jobs', 'get_my_interviews' => 30,
            default => 50,
        };
        if (isset($args['limit']) && $args['limit'] > $maxLimit) return false;
        if (in_array($name, ['get_user_details', 'suspend_user'], true) && !in_array($args['account_type'] ?? null, ['jobseeker', 'employer'], true)) return false;
        if (in_array($name, ['get_user_details', 'suspend_user'], true) && !is_int($args['account_id'] ?? null)) return false;
        if ($name === 'get_employer_job_applicants' && !is_int($args['job_id'] ?? null)) return false;
        if (isset($args['range']) && !in_array($args['range'], ['7', '30', '90', 'all'], true)) return false;
        return true;
    }

    private function adminStats(): array
    {
        $totals = [];
        foreach (['jobseeker_reg' => 'job_seekers', 'employer_reg' => 'employers', 'job_master' => 'jobs', 'application_master' => 'applications'] as $table => $label) {
            if (Schema::hasTable($table)) $totals[$label] = DB::table($table)->count();
        }
        if (Schema::hasTable('job_master') && Schema::hasColumn('job_master', 'Status')) $totals['jobs_by_status'] = DB::table('job_master')->select('Status')->selectRaw('COUNT(*) as total')->groupBy('Status')->get()->toArray();
        return ['available' => true, 'totals' => $totals];
    }

    private function applicationStats(): array
    {
        if (!Schema::hasTable('application_master')) return ['available' => false, 'reason' => 'Application records are unavailable.'];
        $query = DB::table('application_master');
        return ['available' => true, 'total' => $query->count(), 'by_status' => Schema::hasColumn('application_master', 'Status') ? DB::table('application_master')->select('Status')->selectRaw('COUNT(*) as total')->groupBy('Status')->get()->toArray() : []];
    }

    private function loginActivity(array $args): array
    {
        $required = ['action', 'cdate', 'actor_type', 'actor_id'];
        if (!Schema::hasTable('logs') || collect($required)->contains(fn ($column) => !Schema::hasColumn('logs', $column))) return ['available' => false, 'reason' => 'The existing activity log does not contain trusted login identity fields.'];
        $days = $args['days'] ?? 1;
        $rows = DB::table('logs')->where('action', 'login_succeeded')->whereIn('actor_type', ['admin', 'employer', 'jobseeker'])->whereNotNull('actor_id')->where('cdate', '>=', now()->subDays($days))->where('cdate', '<=', now())->orderByDesc('cdate')->limit($args['limit'] ?? 20)->get(['cdate', 'actor_type', 'actor_id']);
        return ['available' => true, 'events' => $rows->map(fn ($row) => ['timestamp' => Carbon::parse($row->cdate)->toIso8601String(), 'role' => $row->actor_type, 'account_id' => (int) $row->actor_id])->all()];
    }

    private function recentJobs(array $args): array
    {
        if (!Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Job records are unavailable.'];
        return ['available' => true, 'jobs' => Job::query()->latest('JobId')->limit($args['limit'] ?? 10)->get(['JobId', 'CompanyName', 'JobTitle', 'Status'])->map(fn (Job $job) => ['id' => $job->JobId, 'company' => $job->CompanyName, 'title' => $job->JobTitle, 'status' => $job->Status])->all()];
    }

    private function pendingDocuments(array $args): array
    {
        if (!Schema::hasTable('jobseeker_documents') || !Schema::hasTable('jobseeker_reg')) return ['available' => false, 'reason' => 'Document review records are unavailable.'];
        $rows = DB::table('jobseeker_documents as d')->join('jobseeker_reg as s', 's.JobSeekId', '=', 'd.JobSeekId')->where('d.VerificationStatus', 'pending')->orderBy('d.UploadDate')->limit($args['limit'] ?? 20)->get(['d.DocId', 'd.DocumentType', 'd.UploadDate', 's.JobSeekId']);
        return ['available' => true, 'reviews' => $rows->map(fn ($row) => ['document_id' => (int) $row->DocId, 'document_type' => $row->DocumentType, 'uploaded_at' => $row->UploadDate, 'job_seeker_id' => (int) $row->JobSeekId])->all()];
    }

    private function userDetails(array $args): array
    {
        $definition = $args['account_type'] === 'jobseeker' ? ['table' => 'jobseeker_reg', 'id' => 'JobSeekId', 'name' => 'JobSeekerName'] : ['table' => 'employer_reg', 'id' => 'EmployerId', 'name' => 'CompanyName'];
        if (!Schema::hasTable($definition['table'])) return ['available' => false, 'reason' => 'Account records are unavailable.'];
        $row = DB::table($definition['table'])->where($definition['id'], $args['account_id'])->first([$definition['id'], $definition['name'], 'Status']);
        return $row ? ['found' => true, 'account_type' => $args['account_type'], 'id' => (int) $row->{$definition['id']}, 'name' => $row->{$definition['name']}, 'status' => $row->Status] : ['found' => false];
    }

    private function suspendUser(Admin $actor, array $args): array
    {
        $message = 'suspend '.$args['account_type'].' id '.$args['account_id'];
        $reply = app(CopilotActionService::class)->handleAdminMessage($this->request, $actor, $message);
        return ['confirmation_required' => str_contains($reply, 'reply exactly: CONFIRM SUSPENSION'), 'message' => $reply, 'changed' => false];
    }

    private function employerJobs(Employer $actor, array $args): array
    {
        if (!Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Job records are unavailable.'];
        $jobs = Job::query()->where('EmployerId', $actor->EmployerId)->latest('JobId')->limit($args['limit'] ?? 20)->get(['JobId', 'JobTitle', 'Status', 'Vacancy']);
        return ['available' => true, 'jobs' => $jobs->map(fn (Job $job) => ['id' => (int) $job->JobId, 'title' => $job->JobTitle, 'status' => $job->Status, 'vacancies' => $job->Vacancy])->all()];
    }

    private function employerDashboard(Employer $actor, array $args): array
    {
        $report = app(DashboardAnalyticsService::class)->employer((int) $actor->EmployerId, $args['range'] ?? '30');
        return [
            'range' => $report['range'],
            'cards' => $report['cards'],
            'job_statuses' => $report['jobStatuses'],
            'application_statuses' => $report['applicationStatuses'],
            'recent_jobs' => $report['recentJobs'],
            'recent_applications' => $report['recentApplications'],
        ];
    }

    private function employerApplicants(Employer $actor, array $args): array
    {
        if (!Schema::hasTable('application_master') || !Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Application records are unavailable.'];
        $job = Job::query()->where('JobId', $args['job_id'])->where('EmployerId', $actor->EmployerId)->first(['JobId', 'JobTitle']);
        if (!$job) return ['found' => false, 'message' => 'That job was not found among your own postings.'];
        $rows = DB::table('application_master as a')->where('a.JobId', $job->JobId)->orderByDesc('a.ApplicationId')->limit($args['limit'] ?? 20)->get(['a.ApplicationId', 'a.Status', 'a.ApplicationDate']);
        return ['found' => true, 'job' => ['id' => (int) $job->JobId, 'title' => $job->JobTitle], 'applicants' => $rows->map(fn ($row) => ['application_reference' => (int) $row->ApplicationId, 'status' => $row->Status, 'applied_at' => $row->ApplicationDate])->all()];
    }

    private function seekerProfile(JobSeeker $actor): array
    {
        return ['profile' => ['name' => $actor->JobSeekerName, 'qualification' => $actor->Qualification, 'city' => $actor->City]];
    }

    private function seekerApplications(JobSeeker $actor, array $args): array
    {
        if (!Schema::hasTable('application_master') || !Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Application records are unavailable.'];
        $rows = DB::table('application_master as a')->leftJoin('job_master as j', 'j.JobId', '=', 'a.JobId')->where('a.JobSeekId', $actor->JobSeekId)->orderByDesc('a.ApplicationId')->limit($args['limit'] ?? 20)->get(['a.ApplicationId', 'a.Status', 'a.ApplicationDate', 'j.JobTitle', 'j.CompanyName']);
        return ['available' => true, 'applications' => $rows->map(fn ($row) => ['application_reference' => (int) $row->ApplicationId, 'job' => $row->JobTitle, 'company' => $row->CompanyName, 'status' => $row->Status, 'applied_at' => $row->ApplicationDate])->all()];
    }

    private function savedJobs(JobSeeker $actor, array $args): array
    {
        if (!Schema::hasTable('saved_jobs') || !Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Saved job records are unavailable.'];
        $rows = DB::table('saved_jobs as s')->join('job_master as j', 'j.JobId', '=', 's.JobId')->where('s.JobSeekId', $actor->JobSeekId)->orderByDesc('s.SavedJobId')->limit($args['limit'] ?? 20)->get(['j.JobId', 'j.JobTitle', 'j.CompanyName', 'j.Status']);
        return ['available' => true, 'jobs' => $rows->map(fn ($row) => ['id' => (int) $row->JobId, 'title' => $row->JobTitle, 'company' => $row->CompanyName, 'status' => $row->Status])->all()];
    }

    private function interviews(string $role, Admin|Employer|JobSeeker $actor, array $args): array
    {
        if (!Schema::hasTable('application_interviews') || !Schema::hasTable('application_master') || !Schema::hasTable('job_master')) return ['available' => false, 'reason' => 'Interview records are unavailable.'];
        $query = DB::table('application_interviews as i')
            ->join('application_master as a', 'a.ApplicationId', '=', 'i.ApplicationId')
            ->join('job_master as j', 'j.JobId', '=', 'a.JobId');
        if ($role === 'employer' && $actor instanceof Employer) $query->where('j.EmployerId', $actor->EmployerId)->where('i.created_by_employer_id', $actor->EmployerId);
        elseif ($role === 'jobseeker' && $actor instanceof JobSeeker) $query->where('a.JobSeekId', $actor->JobSeekId);
        else return ['available' => false, 'reason' => 'Interview records are not available for this role.'];
        $rows = $query->orderBy('i.scheduled_at')->limit($args['limit'] ?? 20)->get(['i.id', 'i.ApplicationId', 'i.round', 'i.scheduled_at', 'i.timezone', 'i.interview_type', 'i.status', 'j.JobTitle']);
        return ['available' => true, 'interviews' => $rows->map(fn ($row) => ['interview_reference' => (int) $row->id, 'application_reference' => (int) $row->ApplicationId, 'job' => $row->JobTitle, 'round' => (int) $row->round, 'scheduled_at' => $row->scheduled_at, 'timezone' => $row->timezone, 'type' => $row->interview_type, 'status' => $row->status])->all()];
    }

    private function resumeSummary(JobSeeker $actor): array
    {
        if (!Schema::hasTable('jobseeker_resumes')) return ['available' => false, 'reason' => 'Saved resume records are unavailable.'];
        $resumes = DB::table('jobseeker_resumes')->where('JobSeekId', $actor->JobSeekId)->orderByDesc('updated_at')->limit(10)->get(['id', 'title', 'template', 'updated_at']);
        return ['available' => true, 'resumes' => $resumes->map(fn ($resume) => ['id' => (int) $resume->id, 'title' => $resume->title, 'template' => $resume->template, 'updated_at' => $resume->updated_at])->all()];
    }
}
