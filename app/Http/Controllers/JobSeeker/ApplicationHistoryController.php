<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationHistoryController extends Controller
{
    public function show(Application $application, ApplicationChatService $chat)
    {
        abort_unless((int) $application->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);
        $application->load('job');

        // The employer opening this application is what starts a chat. Until then there
        // is no thread and no button, and nothing here can move the application status.
        $chatThread = $chat->openFor($application);
        $chatOpen = $chatThread !== null
            && $chat->stateFor($application->Status) === ApplicationChatService::STATE_OPEN;

        return view('jobseeker.applications.show', compact('application', 'chatThread', 'chatOpen'));
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
