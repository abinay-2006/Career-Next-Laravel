<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ActivityLogger;

class FeedbackController extends Controller
{
    public function create()
    {
        return view('feedback.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['Feedback' => ['required', 'string', 'max:200']]);
        $role = Auth::guard('jobseeker')->check() ? 'jobseeker' : 'employer';
        $account = Auth::guard($role)->user();

        Feedback::create([
            'JobSeekId' => $role === 'jobseeker' ? $account->JobSeekId : null,
            'EmployerId' => $role === 'employer' ? $account->EmployerId : null,
            'Feedback' => trim($validated['Feedback']),
            'FeedbakDate' => now()->toDateString(),
        ]);
        app(ActivityLogger::class)->record('feedback_submitted', 'feedback');

        $message = 'Thank you. Your feedback has been submitted.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
