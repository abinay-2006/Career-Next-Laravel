<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerSkill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Lets a job seeker record the skills and software they know. Stored as one row
 * per seeker so the Resume Builder's free-text skills field stays untouched.
 */
class JobSeekerSkillController extends Controller
{
    private const ENTRY_LIMIT = 40;

    private const PROFICIENCIES = ['basic', 'intermediate', 'advanced', 'expert'];

    public function update(Request $request): RedirectResponse
    {
        $jobSeekerId = (int) Auth::guard('jobseeker')->id();

        $validator = Validator::make($request->all(), [
            'skills' => ['nullable', 'string', 'max:30000', $this->entryRule()],
            'software' => ['nullable', 'string', 'max:30000', $this->entryRule()],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = [];

        // Only the columns this form posts are written, so a partial request
        // never wipes lists the seeker already saved.
        foreach (['skills', 'software'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $this->normalize($request->input($field));
            }
        }

        $record = JobSeekerSkill::query()->firstOrNew(['JobSeekId' => $jobSeekerId]);
        $record->fill($data);
        $record->save();

        return redirect()
            ->back()
            ->with('success', 'Skills and software saved.');
    }

    /**
     * Shape check for the JSON the pickers submit, reported under the field
     * itself so a rejected save keeps every typed entry.
     */
    private function entryRule(): \Closure
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
                $fail('Entries must be a JSON list.');

                return;
            }

            if (count($decoded) > self::ENTRY_LIMIT) {
                $fail('A maximum of '.self::ENTRY_LIMIT.' entries is allowed.');

                return;
            }

            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    $fail('Every entry must be an object with a name and proficiency.');

                    return;
                }

                $name = is_string($entry['name'] ?? null)
                    ? trim(preg_replace('/\s+/u', ' ', $entry['name']))
                    : '';

                if ($name === '' || mb_strlen($name) > 100) {
                    $fail('Every skill or software needs a name of 1-100 characters.');

                    return;
                }

                $proficiency = $entry['proficiency'] ?? null;

                if ($proficiency !== null && !in_array($proficiency, self::PROFICIENCIES, true)) {
                    $fail('Proficiency must be basic, intermediate, advanced or expert.');
                }
            }
        };
    }

    /**
     * Rebuild the stored list from a payload that passed entryRule(): trimmed
     * names, case-insensitive de-duplication and safe defaults.
     *
     * @return list<array{name: string, proficiency: string|null}>|null
     */
    private function normalize(mixed $raw): ?array
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
            $proficiency = $entry['proficiency'] ?? null;

            $clean[] = [
                'name' => $name,
                'proficiency' => in_array($proficiency, self::PROFICIENCIES, true) ? $proficiency : null,
            ];
        }

        return $clean === [] ? null : $clean;
    }
}
