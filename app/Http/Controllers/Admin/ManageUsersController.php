<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ManageUsersController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:all,confirm,confirmed,approved,pending,suspended,Confirm,Confirmed,Approved,Pending,Suspended'],
            'type' => ['sometimes', 'in:all,jobseeker,employer'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);
        $filterStatus = strtolower($filters['status'] ?? 'all');
        $filterType = $filters['type'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        /*
    |--------------------------------------------------------------------------
    | Job Seekers
    |--------------------------------------------------------------------------
    */

        $seekersQuery = DB::table('jobseeker_reg')
            ->select(
                'JobSeekId as id',
                'JobSeekerName as name',
                'UserName',
                'Email',
                'Mobile',
                'City',
                'Qualification',
                'Gender',
                'Status'
            );

        if ($filterStatus != 'all') {
            in_array($filterStatus, ['confirm', 'confirmed', 'approved'], true)
                ? $seekersQuery->whereIn('Status', ['confirm', 'confirmed', 'approved'])
                : $seekersQuery->where('Status', $filterStatus);
        }

        if ($search != '') {
            $seekersQuery->where(function ($q) use ($search) {
                $q->where('JobSeekerName', 'like', "%{$search}%")
                    ->orWhere('UserName', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%");
            });
        }

        $seekers = ($filterType == 'all' || $filterType == 'jobseeker')
            ? $seekersQuery->get()
            : collect();



        /*
    |--------------------------------------------------------------------------
    | Employers
    |--------------------------------------------------------------------------
    */

        $employersQuery = DB::table('employer_reg')
            ->select(
                'EmployerId as id',
                'CompanyName as name',
                'UserName',
                'Email',
                'Mobile',
                'City',
                'Area_Work',
                'ContactPerson',
                'Status'
            );

        if ($filterStatus != 'all') {
            in_array($filterStatus, ['confirm', 'confirmed', 'approved'], true)
                ? $employersQuery->whereIn('Status', ['confirm', 'confirmed', 'approved'])
                : $employersQuery->where('Status', $filterStatus);
        }

        if ($search != '') {
            $employersQuery->where(function ($q) use ($search) {
                $q->where('CompanyName', 'like', "%{$search}%")
                    ->orWhere('UserName', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%");
            });
        }

        $employers = ($filterType == 'all' || $filterType == 'employer')
            ? $employersQuery->get()
            : collect();



        /*
    |--------------------------------------------------------------------------
    | Summary Counts
    |--------------------------------------------------------------------------
    */

        $counts = [
            'total' =>
            DB::table('jobseeker_reg')->count() +
                DB::table('employer_reg')->count(),

            'confirmed' =>
            DB::table('jobseeker_reg')->whereIn('Status', ['confirm', 'confirmed', 'approved'])->count() +
                DB::table('employer_reg')->whereIn('Status', ['confirm', 'confirmed', 'approved'])->count(),

            'pending' =>
            DB::table('jobseeker_reg')->where('Status', 'pending')->count() +
                DB::table('employer_reg')->where('Status', 'pending')->count(),

            'suspended' =>
            DB::table('jobseeker_reg')->where('Status', 'suspended')->count() +
                DB::table('employer_reg')->where('Status', 'suspended')->count(),
        ];

        return view('admin.manage_users', compact(
            'seekers',
            'employers',
            'filterStatus',
            'filterType',
            'search',
            'counts'
        ));
    }

    public function confirm(Request $request, $type, $id)
    {
        // Validate the type
        if (!in_array($type, ['jobseeker', 'employer'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid user type.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Invalid user type.');
        }

        return $this->updateStatus($request, $type, $id, 'Confirm', 'Account approved successfully.');
    }


    public function suspend(Request $request, $type, $id)
    {
        // Validate the type
        if (!in_array($type, ['jobseeker', 'employer'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid user type.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Invalid user type.');
        }

        return $this->updateStatus($request, $type, $id, 'Suspended', 'Account suspended.');
    }

    public function destroy(Request $request, $type, $id)
    {
        // Validate the type
        if (!in_array($type, ['jobseeker', 'employer'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid user type.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Invalid user type.');
        }

        $table = $type === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
        $key = $type === 'jobseeker' ? 'JobSeekId' : 'EmployerId';

        try {
            $deleted = DB::transaction(function () use ($table, $key, $id): bool {
                $record = DB::table($table)->where($key, $id)->lockForUpdate()->first();
                if (!$record) {
                    return false;
                }

                DB::table($table)->where($key, $id)->delete();
                return true;
            });
        } catch (QueryException $e) {
            Log::warning('Admin account deletion blocked by related data.', [
                'account_type' => $type,
                'account_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This account has related records that prevent deletion. Suspend it instead or resolve the related records first.',
            ], 409);
        }

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Account deleted.']);
    }

    private function updateStatus(Request $request, string $type, int|string $id, string $status, string $message)
    {
        if (!in_array($type, ['jobseeker', 'employer'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid user type.'], 422);
        }

        $table = $type === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
        $key = $type === 'jobseeker' ? 'JobSeekId' : 'EmployerId';
        $updated = DB::transaction(function () use ($table, $key, $id, $status): bool {
            $exists = DB::table($table)->where($key, $id)->lockForUpdate()->exists();
            if (!$exists) {
                return false;
            }

            DB::table($table)->where($key, $id)->update(['Status' => $status]);
            return true;
        });

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        app(ActivityLogger::class)->record(strtolower($status) === 'confirm' ? 'account_approved' : 'account_suspended', $type, (int) $id);

        return response()->json(['success' => true, 'message' => $message]);
    }
}
