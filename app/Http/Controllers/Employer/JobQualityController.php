<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\JobQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Job Quality Checker endpoint.
 *
 * The browser sends the fields the employer has typed so far and nothing else.
 * Any "score" in the request is deliberately never read, so the number shown is
 * always the one JobQualityService calculated from the data on the server.
 */
class JobQualityController extends Controller
{
    /**
     * Field names a draft may carry. They are the job form's own input names,
     * which is why the checker can post FormData straight back.
     */
    private const DRAFT_FIELDS = [
        'txtTitle',
        'txtTotal',
        'txtSalaryFrom',
        'txtSalaryTo',
        'cmbQual',
        'txtOther',
        'cmbExperience',
        'cmbArrangement',
        'cmbEmployment',
        'txtDesc',
        'required_skills',
        'required_software',
    ];

    public function __construct(private readonly JobQualityService $quality)
    {
    }

    /**
     * Score a draft, or a saved job with the draft typed over it.
     */
    public function check(Request $request): JsonResponse
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            // The role middleware already blocks this; the shape matches it so a
            // fetch() never receives an HTML page where it parses JSON.
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // bootstrap/app.php renders exceptions as JSON for api/* only, so a failed
        // validation here has to answer with JSON itself - same as validateJob().
        $validator = Validator::make($request->all(), [
            'txtTitle' => 'nullable|string|max:500',
            'txtTotal' => 'nullable|string|max:20',
            'txtSalaryFrom' => 'nullable|string|max:50',
            'txtSalaryTo' => 'nullable|string|max:50',
            'cmbQual' => 'nullable|string|max:100',
            'txtOther' => 'nullable|string|max:100',
            'cmbExperience' => 'nullable|string|max:60',
            'cmbArrangement' => 'nullable|string|max:60',
            'cmbEmployment' => 'nullable|string|max:60',
            'txtDesc' => 'nullable|string|max:20000',
            'required_skills' => 'nullable|string|max:30000',
            'required_software' => 'nullable|string|max:30000',
            'JobId' => 'nullable|integer',
        ]);

        if ($validator->fails() && $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validate();

        $jobId = $validated['JobId'] ?? null;
        $fields = [];

        if ($jobId !== null) {
            // Ownership is the same lookup the update route uses, so an employer
            // can only ever score a listing they could also edit.
            $job = Job::where('JobId', $jobId)
                ->where('EmployerId', $employer->EmployerId)
                ->firstOrFail();

            $fields = $this->quality->fieldsFromJob($job);
        }

        foreach (self::DRAFT_FIELDS as $field) {
            if ($request->has($field)) {
                $fields[$field] = $validated[$field];
            }
        }

        // The saved row arrives as an already resolved qualification, but the
        // form sends the dropdown plus the "Other" box. Once either is sent the
        // draft pair wins, or re-picking the dropdown would not move the score.
        if ($request->has('cmbQual') || $request->has('txtOther')) {
            unset($fields['qualification']);
        }

        $report = $this->quality->evaluate($fields, $employer);

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'report' => $report,
        ]);
    }
}
