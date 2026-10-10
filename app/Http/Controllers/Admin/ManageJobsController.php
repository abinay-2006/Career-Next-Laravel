<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageJobsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:all,pending,approved,rejected'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);
        $filterStatus = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $jobs = DB::table('job_master')
            ->when($filterStatus !== 'all', function ($query) use ($filterStatus) {
                $query->where('Status', ucfirst(strtolower($filterStatus)));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('CompanyName', 'like', "%{$search}%")
                        ->orWhere('JobTitle', 'like', "%{$search}%")
                        ->orWhere('JobId', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('JobId')
            ->get();

        $counts = [
            'total' => DB::table('job_master')->count(),

            'pending' => DB::table('job_master')
                ->where('Status', 'Pending')
                ->count(),

            'approved' => DB::table('job_master')
                ->where('Status', 'Approved')
                ->count(),

            'rejected' => DB::table('job_master')
                ->where('Status', 'Rejected')
                ->count(),
        ];

        return view('admin.manage_jobs', [
            'rows'          => $jobs,
            'jobs'          => $jobs,
            'records'       => $jobs->count(),

            'counts'        => $counts,
            'filterStatus'  => $filterStatus,
            'search'        => $search,

            'message' => session('success') ?? session('error'),
            'msgType' => session('success')
                ? 'success'
                : (session('error') ? 'error' : ''),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $exists = DB::table('job_master')->where('JobId', $id)->exists();
        if ($exists) {
            DB::table('job_master')->where('JobId', $id)->update(['Status' => 'Approved']);
        }

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.'
            ], 404);
        }

        return redirect()
            ->route('admin.manage.jobs')
            ->with('success', 'Job approved successfully.');
    }

    public function reject(Request $request, $id)
    {
        $exists = DB::table('job_master')->where('JobId', $id)->exists();
        if ($exists) {
            DB::table('job_master')->where('JobId', $id)->update(['Status' => 'Rejected']);
        }

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.'
            ], 404);
        }

        return redirect()
            ->route('admin.manage.jobs')
            ->with('success', 'Job rejected successfully.');
    }
}
