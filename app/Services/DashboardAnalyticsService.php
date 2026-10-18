<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Support\Carbon;

class DashboardAnalyticsService
{
    public function employer(string $companyName, string $range = '30'): array
    {
        [$start, $end] = $this->rangeBounds($range);
        $jobs = Job::query()->where('CompanyName', $companyName);

        $jobStatuses = $this->normalizeStatuses(
            (clone $jobs)->selectRaw("LOWER(TRIM(COALESCE(Status, ''))) as status_key, COUNT(*) as total")
                ->groupBy('status_key')->pluck('total', 'status_key')->all()
        );

        $jobIds = (clone $jobs)->select('JobId');
        $applications = Application::query()->whereIn('JobId', $jobIds);
        $totalJobs = (clone $jobs)->count();
        $totalApplications = (clone $applications)->count();
        $rangeApplications = $range === 'all' ? $totalApplications : (clone $applications)->whereBetween('ApplicationDate', [$start, $end])->count();
        $applicationStatuses = $this->applicationStatuses(clone $applications, $range, $start, $end);

        $recentJobs = (clone $jobs)->select('JobId', 'JobTitle', 'Status')
            ->orderByDesc('JobId')->limit(5)->get()
            ->map(fn (Job $job) => ['id' => $job->JobId, 'title' => $job->JobTitle, 'status' => $job->Status ?: 'Unknown'])
            ->all();

        $recentApplications = Application::query()
            ->join('job_master', 'application_master.JobId', '=', 'job_master.JobId')
            ->where('job_master.CompanyName', $companyName)
            ->select('application_master.ApplicationId', 'application_master.Status', 'application_master.ApplicationDate', 'job_master.JobTitle')
            ->orderByDesc('application_master.ApplicationDate')->orderByDesc('application_master.ApplicationId')
            ->limit(5)->get()
            ->map(fn ($application) => [
                'id' => $application->ApplicationId,
                'title' => $application->JobTitle,
                'status' => $application->Status ?: 'Unknown',
                'date' => $application->ApplicationDate,
            ])->all();

        $applicationsByJob = Job::query()
            ->leftJoin('application_master', 'job_master.JobId', '=', 'application_master.JobId')
            ->where('job_master.CompanyName', $companyName)
            ->select('job_master.JobId', 'job_master.JobTitle')
            ->selectRaw('COUNT(application_master.ApplicationId) as application_count')
            ->groupBy('job_master.JobId', 'job_master.JobTitle')
            ->orderByDesc('application_count')->orderBy('job_master.JobTitle')
            ->limit(6)->get();

        return [
            'range' => $range,
            'updatedAt' => now()->toIso8601String(),
            'cards' => [
                ['label' => 'Jobs posted', 'value' => $totalJobs, 'detail' => 'All time'],
                ['label' => 'Active jobs', 'value' => ($jobStatuses['approved'] ?? 0) + ($jobStatuses['active'] ?? 0), 'detail' => 'Approved or active'],
                ['label' => 'Applications', 'value' => $totalApplications, 'detail' => 'All time'],
                ['label' => 'New applications', 'value' => $rangeApplications, 'detail' => $this->rangeLabel($range)],
            ],
            'jobStatuses' => $jobStatuses,
            'applicationStatuses' => $applicationStatuses,
            'applicationsByJob' => [
                'labels' => $applicationsByJob->pluck('JobTitle')->all(),
                'values' => $applicationsByJob->pluck('application_count')->map(fn ($count) => (int) $count)->all(),
            ],
            'trend' => $this->trend($applications, $range, $start, $end),
            'recentJobs' => $recentJobs,
            'recentApplications' => $recentApplications,
        ];
    }

    public function jobSeeker(JobSeeker $jobSeeker, string $range = '30'): array
    {
        [$start, $end] = $this->rangeBounds($range);
        $applications = Application::query()->where('JobSeekId', $jobSeeker->getKey());
        $totalApplications = (clone $applications)->count();
        $rangeApplications = $range === 'all' ? $totalApplications : (clone $applications)->whereBetween('ApplicationDate', [$start, $end])->count();
        $applicationStatuses = $this->applicationStatuses(clone $applications, $range, $start, $end);

        $recentApplications = (clone $applications)->with(['job' => fn ($query) => $query->select('JobId', 'JobTitle', 'CompanyName')])
            ->select('ApplicationId', 'JobId', 'Status', 'ApplicationDate')
            ->orderByDesc('ApplicationDate')->orderByDesc('ApplicationId')->limit(5)->get()
            ->map(fn (Application $application) => [
                'id' => $application->ApplicationId,
                'title' => $application->job?->JobTitle ?? 'Job no longer available',
                'company' => $application->job?->CompanyName ?? '—',
                'status' => $application->Status ?: 'Unknown',
                'date' => $application->ApplicationDate,
            ])->all();

        return [
            'range' => $range,
            'updatedAt' => now()->toIso8601String(),
            'cards' => [
                ['label' => 'Applications sent', 'value' => $totalApplications, 'detail' => 'All time'],
                ['label' => 'In progress', 'value' => ($applicationStatuses['pending'] ?? 0), 'detail' => $this->rangeLabel($range)],
                ['label' => 'Accepted', 'value' => ($applicationStatuses['accepted'] ?? 0), 'detail' => $this->rangeLabel($range)],
                ['label' => 'Recent applications', 'value' => $rangeApplications, 'detail' => $this->rangeLabel($range)],
            ],
            'applicationStatuses' => $applicationStatuses,
            'trend' => $this->trend($applications, $range, $start, $end),
            'recentApplications' => $recentApplications,
        ];
    }

    private function applicationStatuses($query, string $range, Carbon $start, Carbon $end): array
    {
        if ($range !== 'all') {
            $query->whereBetween('ApplicationDate', [$start, $end]);
        }
        return $this->normalizeStatuses(
            $query->selectRaw("LOWER(TRIM(COALESCE(Status, ''))) as status_key, COUNT(*) as total")
                ->groupBy('status_key')->pluck('total', 'status_key')->all()
        );
    }

    private function normalizeStatuses(array $statuses): array
    {
        $normalized = [];
        foreach ($statuses as $status => $total) {
            $key = trim((string) $status);
            $key = $key === '' ? 'unknown' : $key;
            $normalized[$key] = ($normalized[$key] ?? 0) + (int) $total;
        }
        ksort($normalized);
        return $normalized;
    }

    private function trend($query, string $range, Carbon $start, Carbon $end): array
    {
        $query->whereNotNull('ApplicationDate');
        if ($range !== 'all') {
            $query->whereBetween('ApplicationDate', [$start, $end]);
        }
        $byDate = $query
            ->selectRaw('DATE(ApplicationDate) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')->orderBy('activity_date')
            ->pluck('total', 'activity_date');

        if ($range === 'all') {
            $byMonth = [];
            foreach ($byDate as $date => $total) {
                $month = Carbon::parse($date)->format('Y-m');
                $byMonth[$month] = ($byMonth[$month] ?? 0) + (int) $total;
            }
            $labels = array_map(fn ($month) => Carbon::createFromFormat('Y-m', $month)->format('M Y'), array_keys($byMonth));
            return ['labels' => $labels, 'values' => array_values($byMonth)];
        }

        $labels = [];
        $values = [];
        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('M j');
            $values[] = (int) ($byDate[$key] ?? 0);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    private function rangeBounds(string $range): array
    {
        $end = now()->endOfDay();
        $start = $range === 'all'
            ? Carbon::create(1970, 1, 1)->startOfDay()
            : now()->startOfDay()->subDays((int) $range - 1);
        return [$start, $end];
    }

    private function rangeLabel(string $range): string
    {
        return $range === 'all' ? 'All time' : 'Last ' . $range . ' days';
    }
}
