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

        $jobs = Job::where('EmployerId', $employer->EmployerId)
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
        $validated = $request->validate([
            'txtTitle' => 'required|string|max:50',
            'txtTotal' => 'required|integer|min:1',
            'cmbQual'  => 'required|string|max:50',
            'txtOther' => 'nullable|required_if:cmbQual,Other|string|max:50',
            'txtDesc'  => 'required|string|max:200',
            'txtSalaryFrom' => 'nullable|string|max:50',
            'txtSalaryTo' => 'nullable|string|max:50',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        Job::create([
            'EmployerId' => $employer->EmployerId,
            'CompanyName' => $employer->CompanyName,
            'JobTitle' => $validated['txtTitle'],
            'Vacancy' => $validated['txtTotal'],
            'SalaryFrom' => $validated['txtSalaryFrom'] ?? null,
            'SalaryTo' => $validated['txtSalaryTo'] ?? null,
            'MinQualification' => $validated['cmbQual'] === 'Other'
                ? $validated['txtOther']
                : $validated['cmbQual'],
            'Description' => $validated['txtDesc'],
            'Status' => 'pending',
        ]);

        return back()->with('success', 'Job Posted Successfully.');
    }

    /**
     * Update an existing job.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'txtTitle' => 'required|string|max:50',
            'txtTotal' => 'required|integer|min:1',
            'cmbQual'  => 'required|string|max:50',
            'txtOther' => 'nullable|required_if:cmbQual,Other|string|max:50',
            'txtDesc'  => 'required|string|max:200',
            'txtSalaryFrom' => 'nullable|string|max:50',
            'txtSalaryTo' => 'nullable|string|max:50',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $job = Job::where('JobId', $id)
            ->where('EmployerId', $employer->EmployerId)
            ->firstOrFail();

        $job->update([
            'JobTitle' => $validated['txtTitle'],
            'Vacancy' => $validated['txtTotal'],
            'SalaryFrom' => $validated['txtSalaryFrom'] ?? null,
            'SalaryTo' => $validated['txtSalaryTo'] ?? null,
            'MinQualification' => $validated['cmbQual'] === 'Other'
                ? $validated['txtOther']
                : $validated['cmbQual'],
            'Description' => $validated['txtDesc'],
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
            ->where('EmployerId', $employer->EmployerId)
            ->firstOrFail();

        $job->delete();

        return redirect()
            ->route('employer.jobs.index')
            ->with('success', 'Job deleted successfully.');
    }
}
