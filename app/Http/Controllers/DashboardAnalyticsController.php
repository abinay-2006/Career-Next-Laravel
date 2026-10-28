<?php

namespace App\Http\Controllers;

use App\Models\JobSeeker;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DashboardAnalyticsController extends Controller
{
    public function employer(Request $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        if ($filters instanceof JsonResponse) return $filters;
        $employer = Auth::guard('employer')->user();
        abort_unless($employer, 403);

        return response()->json($analytics->employer((int) $employer->EmployerId, $filters['range']));
    }

    public function jobSeeker(Request $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        if ($filters instanceof JsonResponse) return $filters;
        $jobSeeker = Auth::guard('jobseeker')->user();
        abort_unless($jobSeeker instanceof JobSeeker, 403);

        return response()->json($analytics->jobSeeker($jobSeeker, $filters['range']));
    }

    private function validatedFilters(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->query(), ['range' => ['nullable', 'in:7,30,90,all']]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'The selected analytics filters are invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return ['range' => $validator->validated()['range'] ?? '30'];
    }
}
