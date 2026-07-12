<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageUsersController extends Controller
{
    public function index(Request $request)
    {
        $filterStatus = $request->get('status', 'all');
        $filterType   = $request->get('type', 'all');
        $search       = trim($request->get('search', ''));

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
            $seekersQuery->where('Status', $filterStatus);
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
            $employersQuery->where('Status', $filterStatus);
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
            DB::table('jobseeker_reg')->where('Status', 'Confirm')->count() +
                DB::table('employer_reg')->where('Status', 'Confirm')->count(),

            'pending' =>
            DB::table('jobseeker_reg')->where('Status', 'Pending')->count() +
                DB::table('employer_reg')->where('Status', 'Pending')->count(),

            'suspended' =>
            DB::table('jobseeker_reg')->where('Status', 'Suspended')->count() +
                DB::table('employer_reg')->where('Status', 'Suspended')->count(),
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

        // Determine the table based on the type
        $table = $type === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
        $key   = $type === 'jobseeker' ? 'JobSeekId' : 'EmployerId';

        DB::table($table)
            ->where($key, $id)
            ->update([
                'Status' => 'Confirm'
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Account approved successfully.'
        ]);
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

        // Determine the table based on the type
        $table = $type === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
        $key   = $type === 'jobseeker' ? 'JobSeekId' : 'EmployerId';

        // Update the status to "Suspended"
        DB::table($table)
            ->where($key, $id)
            ->update([
                'Status' => 'Suspended'
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Account suspended.'
        ]);
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

        // Determine the table based on the type
        $table = $type === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
        $key   = $type === 'jobseeker' ? 'JobSeekId' : 'EmployerId';


        // Delete the user
        DB::table($table)
            ->where($key, $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.'
        ]);
    }
}
