<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Walkin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalkinController extends Controller
{
    /**
     * Display all walk-ins of the logged-in employer.
     */
    public function index()
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $walkins = Walkin::where('EmployerId', $employer->EmployerId)
            ->orderByDesc('WalkInId')
            ->get();

        return view('employer.walkins.index', compact('walkins'));
    }

    /**
     * Store a new walk-in.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'txtTitle' => 'required|string|max:50',
            'txtTotal' => 'required|integer|min:1',
            'txtQual'  => 'required|string|max:50',
            'txtDesc'  => 'required|string|max:100',
            'txtDate'  => 'required|date',
            'txtTime'  => 'required',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        Walkin::create([
            'EmployerId'       => $employer->EmployerId,
            'CompanyName'      => $employer->CompanyName,
            'JobTitle'         => $validated['txtTitle'],
            'Vacancy'          => $validated['txtTotal'],
            'MinQualification' => $validated['txtQual'],
            'Description'      => $validated['txtDesc'],
            'InterviewDate'    => $validated['txtDate'],
            'InterviewTime'    => $validated['txtTime'],
        ]);

        return redirect()
            ->route('employer.walkins.index')
            ->with('success', 'Walk-in created successfully.');
    }

    /**
     * Update a walk-in.
     */
    public function update(Request $request, Walkin $walkin)
    {
        $validated = $request->validate([
            'txtTitle' => 'required|string|max:50',
            'txtTotal' => 'required|integer|min:1',
            'txtQual'  => 'required|string|max:50',
            'txtDesc'  => 'required|string|max:100',
            'txtDate'  => 'required|date',
            'txtTime'  => 'required',
        ]);

        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        if ((int) $walkin->EmployerId !== (int) $employer->EmployerId) {
            abort(404);
        }

        $walkin->update([
            'JobTitle'         => $validated['txtTitle'],
            'Vacancy'          => $validated['txtTotal'],
            'MinQualification' => $validated['txtQual'],
            'Description'      => $validated['txtDesc'],
            'InterviewDate'    => $validated['txtDate'],
            'InterviewTime'    => $validated['txtTime'],
        ]);

        return back()->with('success', 'Walk-in updated successfully.');
    }

    /**
     * Delete a walk-in.
     */
    public function destroy(Walkin $walkin)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        if ((int) $walkin->EmployerId !== (int) $employer->EmployerId) {
            abort(404);
        }

        $walkin->delete();

        return back()->with('success', 'Walk-in deleted successfully.');
    }
}
