<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\DashboardAnalyticsService;

class EmployerController extends Controller
{
    public function index(DashboardAnalyticsService $analytics)
    {
        $employer = Auth::guard('employer')->user();
        abort_unless($employer, 403);

        return view('employer.index', [
            'analytics' => $analytics->employer((int) $employer->EmployerId),
        ]);
    }
}
