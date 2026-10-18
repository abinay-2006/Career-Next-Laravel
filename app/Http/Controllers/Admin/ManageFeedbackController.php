<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class ManageFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['account' => ['nullable', 'in:all,jobseeker,employer']]);
        $feedback = Feedback::query()
            ->when(($filters['account'] ?? 'all') === 'jobseeker', fn ($q) => $q->whereNotNull('JobSeekId'))
            ->when(($filters['account'] ?? 'all') === 'employer', fn ($q) => $q->whereNotNull('EmployerId'))
            ->orderByDesc('FeedbackId')->paginate(20)->withQueryString();
        return view('admin.feedback.index', compact('feedback'));
    }
}
