<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerCareerPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CareerPreferencesController extends Controller
{
    private const TAG_FIELDS = [
        'preferred_job_titles' => 15,
        'preferred_locations' => 20,
        'employment_types' => 12,
        'work_arrangements' => 6,
        'preferred_industries' => 15,
    ];

    private const OPTION_SUGGESTIONS = [
        'employment_types' => [
            'Full-time', 'Part-time', 'Internship', 'Apprenticeship', 'Contract',
            'Temporary', 'Freelance', 'Permanent', 'Work from home',
            'Graduate trainee', 'Entry-level',
        ],
        'work_arrangements' => ['On-site', 'Remote', 'Hybrid', 'Flexible'],
        'experience_levels' => [
            'fresher', 'internship', 'entry_level', '1-2_years', '3-5_years',
            '6-10_years', '10_plus_years', 'any',
        ],
        'relocate' => ['yes', 'no', 'maybe'],
        'notice_periods' => ['immediate', '15_days', '30_days', '60_days', '90_days', 'other'],
        'working_hours' => ['day', 'night', 'flexible'],
        'search_status' => ['actively_looking', 'open_to_opportunities', 'just_exploring'],
        'salary_periods' => ['monthly', 'annual'],
        'currencies' => ['INR', 'USD', 'EUR', 'GBP', 'AED'],
    ];

    public function edit(): View
    {
        $preferences = $this->currentPreferences();

        return view('jobseeker.career-preferences.edit', [
            'preferences' => $preferences,
            'suggestions' => self::OPTION_SUGGESTIONS,
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $jobSeekerId = (int) Auth::guard('jobseeker')->id();

        $validator = Validator::make($request->all(), $this->rules());

        $validator->after(function ($validator) use ($request) {
            $min = $request->input('minimum_salary');
            $max = $request->input('maximum_salary');

            if (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                $validator->errors()->add(
                    'minimum_salary',
                    'Minimum expected salary cannot exceed the maximum.'
                );
            }
        });

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $data = [];

        foreach (self::TAG_FIELDS as $field => $limit) {
            $data[$field] = $this->normalizeTags($request->input($field), $limit);
        }

        foreach ([
            'salary_period', 'currency', 'experience_level', 'willing_to_relocate',
            'notice_period', 'preferred_working_hours', 'job_search_status',
        ] as $field) {
            $value = $request->input($field);
            $data[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        foreach (['minimum_salary', 'maximum_salary'] as $field) {
            $value = $request->input($field);
            $data[$field] = is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
        }

        if ($data['currency'] === null) {
            $data['currency'] = 'INR';
        }

        $preferences = JobSeekerCareerPreference::query()->firstOrNew(['JobSeekId' => $jobSeekerId]);
        $preferences->fill($data);

        $hasPreference = $this->rowHasPreference($data);
        if ($hasPreference && !$preferences->completed_at) {
            $preferences->completed_at = now();
        } elseif (!$hasPreference) {
            $preferences->completed_at = null;
        }

        $preferences->save();

        $message = 'Career preferences saved.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('jobseeker.index'),
            ]);
        }

        return redirect()
            ->route('jobseeker.index')
            ->with('success', $message);
    }

    private function currentPreferences(): ?JobSeekerCareerPreference
    {
        return JobSeekerCareerPreference::query()
            ->where('JobSeekId', (int) Auth::guard('jobseeker')->id())
            ->first();
    }

    private function rules(): array
    {
        $rules = [];

        foreach (self::TAG_FIELDS as $field => $limit) {
            $rules[$field] = ['nullable', 'array', "max:$limit"];
            $rules["{$field}.*"] = ['string', 'max:100'];
        }

        $rules['minimum_salary'] = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $rules['maximum_salary'] = ['nullable', 'numeric', 'min:0', 'max:99999999'];
        $rules['salary_period'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['salary_periods'])];
        $rules['currency'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['currencies'])];
        $rules['experience_level'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['experience_levels'])];
        $rules['willing_to_relocate'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['relocate'])];
        $rules['notice_period'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['notice_periods'])];
        $rules['preferred_working_hours'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['working_hours'])];
        $rules['job_search_status'] = ['nullable', Rule::in(self::OPTION_SUGGESTIONS['search_status'])];

        return $rules;
    }

    /**
     * Trim, collapse whitespace, drop blanks and case-insensitive duplicates, cap length.
     */
    private function normalizeTags(mixed $values, int $limit): ?array
    {
        if (!is_array($values)) {
            return null;
        }

        $seen = [];
        $clean = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim(preg_replace('/\s+/u', ' ', $value));

            if ($value === '' || mb_strlen($value) > 100) {
                continue;
            }

            $key = mb_strtolower($value);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[] = $value;

            if (count($clean) >= $limit) {
                break;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function rowHasPreference(array $data): bool
    {
        foreach (array_keys(self::TAG_FIELDS) as $field) {
            if (!empty($data[$field])) {
                return true;
            }
        }

        return $data['minimum_salary'] !== null
            || $data['maximum_salary'] !== null
            || $data['experience_level'] !== null
            || $data['job_search_status'] !== null;
    }
}
