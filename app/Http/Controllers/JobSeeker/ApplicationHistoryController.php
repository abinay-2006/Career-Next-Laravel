<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationHistoryController extends Controller
{
    public function show(Application $application)
    {
        abort_unless((int) $application->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);
        $application->load('job');
        return view('jobseeker.applications.show', compact('application'));
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:Pending,Accepted,Rejected'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $seekerId = Auth::guard('jobseeker')->id();
        $applications = Application::query()->with('job')
            ->where('JobSeekId', $seekerId)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('Status', $status))
            ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $search = '%' . trim($filters['search']) . '%';
                $query->whereHas('job', fn ($jobs) => $jobs->where('JobTitle', 'like', $search)->orWhere('CompanyName', 'like', $search));
            })
            ->orderByDesc('ApplicationId')
            ->paginate(10)->withQueryString();

        return view('jobseeker.applications.index', compact('applications'));
    }
}
