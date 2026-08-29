<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Walkin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalkinController extends Controller
{
    /**
     * Display all walkins of logged employer.
     */
    public function index()
    {
        $company = auth('employer')->user()->CompanyName;

        $walkins = Walkin::where('CompanyName', $company)
            ->orderByDesc('WalkInId')
            ->get();

        return view('employer.walkins.index', compact('walkins'));
    }

    /**
     * Store new walkin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'txtTitle' => 'required|max:255',
            'txtTotal' => 'required|integer|min:1',
            'txtQual'  => 'required',
            'txtDesc'  => 'required',
            'txtDate'  => 'required|date',
            'txtTime'  => 'required',
        ]);

        Walkin::create([
            'CompanyName'      => auth('employer')->user()->CompanyName,
            'JobTitle'         => $request->txtTitle,
            'Vacancy'          => $request->txtTotal,
            'MinQualification' => $request->txtQual,
            'Description'      => $request->txtDesc,
            'InterviewDate'    => $request->txtDate,
            'InterviewTime'    => $request->txtTime,
        ]);

        return redirect()
            ->route('employer.walkins.index')
            ->with('success', 'Walk-in created successfully.');
    }

    /**
     * Update walkin.
     */
    public function update(Request $request, Walkin $walkin) // Already correct for model binding
    {
        $request->validate([
            'txtTitle' => 'required|max:255',
            'txtTotal' => 'required|integer|min:1',
            'txtQual'  => 'required',
            'txtDesc'  => 'required',
            'txtDate'  => 'required|date',
            'txtTime'  => 'required',
        ]);

        $walkin->update([
            'JobTitle'         => $request->txtTitle,
            'Vacancy'          => $request->txtTotal,
            'MinQualification' => $request->txtQual,
            'Description'      => $request->txtDesc,
            'InterviewDate'    => $request->txtDate,
            'InterviewTime'    => $request->txtTime,
        ]);

        return back()->with('success', 'Walk-in updated successfully.');
    }

    /**
     * Delete walkin.
     */
    public function destroy(Walkin $walkin) // Already correct for model binding
    {
        $walkin->delete();

        return back()->with('success', 'Walk-in deleted successfully.');
    }
}
