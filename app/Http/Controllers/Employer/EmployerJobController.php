<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;

class EmployerJobController extends Controller
{
    /**
     * Display all jobs posted by the logged-in employer.
     */
    public function index()
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $jobs = Job::where('CompanyName', $employer->CompanyName)
            ->orderByDesc('JobId')
            ->get();

        $totalRecords = $jobs->count();

        $degrees = [
            'B.C.A',
            'M.C.A',
            'B.Sc.I.T',
            'B.Sc.C.S',
            'M.Sc.I.T',
            'M.Sc.C.S',
            'B.E / B.Tech',
            'M.E / M.Tech',
            'B.Com',
            'M.Com',
            'B.A',
            'M.A',
            'M.B.A',
            'B.B.A',
            'B.Pharm',
            'M.Pharm',
            'MBBS',
            'LLB',
            'LLM',
            'Diploma',
            'Ph.D',
            'Other'
        ];

        return view('employer.jobs.index', compact(
            'jobs',
            'totalRecords',
            'degrees'
        ));
    }

    /**
     * Store a new job.
     */
    public function store(Request $request)
    {
        $request->validate([
            'txtTitle' => 'required',
            'txtTotal' => 'required|integer|min:1',
            'cmbQual'  => 'required',
            'txtDesc'  => 'required',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        Job::create([
            'CompanyName' => $employer->CompanyName,
            'JobTitle' => $request->txtTitle,
            'Vacancy' => $request->txtTotal,
            'SalaryFrom' => $request->txtSalaryFrom,
            'SalaryTo' => $request->txtSalaryTo,
            'MinQualification' => $request->cmbQual === 'Other'
                ? $request->txtOther
                : $request->cmbQual,
            'Description' => $request->txtDesc,
            'Status' => 'Pending',
        ]);

        return back()->with('success', 'Job Posted Successfully.');
    }

    /**
     * Update an existing job.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'txtTitle' => 'required',
            'txtTotal' => 'required|integer|min:1',
            'cmbQual'  => 'required',
            'txtDesc'  => 'required',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $job = Job::where('JobId', $id)
            ->where('CompanyName', $employer->CompanyName)
            ->firstOrFail();

        $job->update([
            'JobTitle' => $request->txtTitle,
            'Vacancy' => $request->txtTotal,
            'SalaryFrom' => $request->txtSalaryFrom,
            'SalaryTo' => $request->txtSalaryTo,
            'MinQualification' => $request->cmbQual === 'Other'
                ? $request->txtOther
                : $request->cmbQual,
            'Description' => $request->txtDesc,
        ]);

        return back()->with('success', 'Job Updated Successfully.');
    }

    /**
     * Delete an existing job.
     */
    public function destroy($id)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $job = Job::where('JobId', $id)
            ->where('CompanyName', $employer->CompanyName)
            ->firstOrFail();

        $job->delete();

        return redirect()
            ->route('employer.jobs.index')
            ->with('success', 'Job deleted successfully.');
    }
}
