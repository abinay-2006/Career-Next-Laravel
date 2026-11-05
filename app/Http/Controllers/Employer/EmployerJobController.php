<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Job;

class EmployerJobController extends Controller
{
    private const REQUIREMENT_TYPES = ['mandatory', 'preferred'];

    private const REQUIREMENT_PROFICIENCIES = ['basic', 'intermediate', 'advanced', 'expert'];

    private const REQUIREMENT_LIMIT = 30;
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

        return view('employer.jobs.index', [
            'jobs' => $jobs,
            'totalRecords' => $totalRecords,
            'degrees' => $degrees,
            'experienceLevels' => Job::EXPERIENCE_LEVELS,
            'workArrangements' => Job::WORK_ARRANGEMENTS,
            'employmentTypes' => Job::EMPLOYMENT_TYPES,
        ]);
    }

    /**
     * Store a new job.
     */
    public function store(Request $request)
    {
        $validated = $this->validateJob($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

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
            ...$this->classificationPayload($request),
            ...$this->requirementPayload($request),
            'Status' => 'pending',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Job posted successfully.', 'refresh' => true]);
        }

        return back()->with('success', 'Job Posted Successfully.');
    }

    /**
     * Update an existing job.
     */
    public function update(Request $request, $id)
    {
        $validated = $this->validateJob($request);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

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
            ...$this->classificationPayload($request),
            ...$this->requirementPayload($request),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Job updated successfully.', 'refresh' => true]);
        }

        return back()->with('success', 'Job Updated Successfully.');
    }

    /**
     * Delete an existing job.
     */
    public function destroy(Request $request, $id)
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            abort(403, 'Unauthorized');
        }

        $job = Job::where('JobId', $id)
            ->where('EmployerId', $employer->EmployerId)
            ->firstOrFail();

        $job->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Job deleted successfully.', 'refresh' => true]);
        }

        return redirect()
            ->route('employer.jobs.index')
            ->with('success', 'Job deleted successfully.');
    }

    /**
     * The application renders validation errors as redirects, which the AJAX job
     * form cannot show inline, so a JSON submit gets the 422 payload app.js
     * expects. A full-page submit keeps the normal redirect with every typed
     * value, including the requirement JSON.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function validateJob(Request $request): array|JsonResponse
    {
        $validator = Validator::make($this->blankOptionsToNull($request), $this->jobRules());

        if ($validator->fails() && $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return $validator->validate();
    }

    /**
     * A select left on its placeholder posts an empty string, which means "not
     * specified" rather than an invalid choice.
     *
     * @return array<string, mixed>
     */
    private function blankOptionsToNull(Request $request): array
    {
        $data = $request->all();

        foreach (['cmbExperience', 'cmbArrangement', 'cmbEmployment'] as $field) {
            if (array_key_exists($field, $data) && (!is_string($data[$field]) || trim($data[$field]) === '')) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Classification columns are written only when the form sends them, so a
     * request from an older page never clears what an employer already chose.
     *
     * @return array<string, string|null>
     */
    private function classificationPayload(Request $request): array
    {
        $columns = [
            'cmbExperience' => 'ExperienceLevel',
            'cmbArrangement' => 'WorkArrangement',
            'cmbEmployment' => 'EmploymentType',
        ];

        $payload = [];

        foreach ($columns as $field => $column) {
            if ($request->has($field)) {
                $value = is_string($request->input($field)) ? trim($request->input($field)) : '';
                $payload[$column] = $value === '' ? null : $value;
            }
        }

        return $payload;
    }

    /**
     * Requirement columns are written only when the form actually sends them,
     * so a partial update never wipes requirements the request did not carry.
     *
     * @return array<string, array<int, array{name: string, type: string, proficiency: string|null}>|null>
     */
    private function requirementPayload(Request $request): array
    {
        $payload = [];

        foreach (['required_skills' => 'RequiredSkills', 'required_software' => 'RequiredSoftware'] as $field => $column) {
            if ($request->has($field)) {
                $payload[$column] = $this->normalizeRequirements($request->input($field));
            }
        }

        return $payload;
    }

    private function jobRules(): array
    {
        $requirementRule = $this->requirementRule();

        return [
            'txtTitle' => 'required|string|max:50',
            'txtTotal' => 'required|integer|min:1',
            'cmbQual'  => 'required|string|max:50',
            'txtOther' => 'nullable|required_if:cmbQual,Other|string|max:50',
            'txtDesc'  => 'required|string|max:200',
            'txtSalaryFrom' => 'nullable|string|max:50',
            'txtSalaryTo' => 'nullable|string|max:50',
            'cmbExperience' => ['nullable', Rule::in(array_keys(Job::EXPERIENCE_LEVELS))],
            'cmbArrangement' => ['nullable', Rule::in(Job::WORK_ARRANGEMENTS)],
            'cmbEmployment' => ['nullable', Rule::in(Job::EMPLOYMENT_TYPES)],
            'required_skills' => ['nullable', 'string', 'max:30000', $requirementRule],
            'required_software' => ['nullable', 'string', 'max:30000', $requirementRule],
        ];
    }

    /**
     * Shape check for the JSON the requirement pickers submit, reported under
     * the field itself so a rejected listing keeps every typed value.
     */
    private function requirementRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (!is_string($value)) {
                return;
            }

            $value = trim($value);

            if ($value === '' || $value === '[]') {
                return;
            }

            $decoded = json_decode($value, true);

            if (!is_array($decoded) || array_keys($decoded) !== range(0, count($decoded) - 1)) {
                $fail('Requirements must be a JSON list of entries.');

                return;
            }

            if (count($decoded) > self::REQUIREMENT_LIMIT) {
                $fail('A maximum of '.self::REQUIREMENT_LIMIT.' entries is allowed.');

                return;
            }

            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    $fail('Every requirement must be an object with a name, type and proficiency.');

                    return;
                }

                $name = is_string($entry['name'] ?? null)
                    ? trim(preg_replace('/\s+/u', ' ', $entry['name']))
                    : '';

                if ($name === '' || mb_strlen($name) > 100) {
                    $fail('Every requirement needs a name of 1-100 characters.');

                    return;
                }

                if (!in_array($entry['type'] ?? 'mandatory', self::REQUIREMENT_TYPES, true)) {
                    $fail('Requirement type must be "mandatory" or "preferred".');

                    return;
                }

                $proficiency = $entry['proficiency'] ?? null;

                if ($proficiency !== null && !in_array($proficiency, self::REQUIREMENT_PROFICIENCIES, true)) {
                    $fail('Proficiency must be basic, intermediate, advanced or expert.');

                    return;
                }
            }
        };
    }

    /**
     * Rebuild the stored list from a payload that passed requirementRule():
     * trimmed names, case-insensitive de-duplication and safe defaults.
     *
     * @return array<int, array{name: string, type: string, proficiency: string|null}>|null
     */
    private function normalizeRequirements(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode(trim($raw), true);

        if (!is_array($decoded)) {
            return null;
        }

        $seen = [];
        $clean = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry) || !is_string($entry['name'] ?? null)) {
                continue;
            }

            $name = trim(preg_replace('/\s+/u', ' ', $entry['name']));

            if ($name === '' || mb_strlen($name) > 100) {
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $type = $entry['type'] ?? 'mandatory';
            $proficiency = $entry['proficiency'] ?? null;

            $clean[] = [
                'name' => $name,
                'type' => in_array($type, self::REQUIREMENT_TYPES, true) ? $type : 'mandatory',
                'proficiency' => in_array($proficiency, self::REQUIREMENT_PROFICIENCIES, true) ? $proficiency : null,
            ];
        }

        return $clean === [] ? null : $clean;
    }
}
