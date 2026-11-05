<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic "Preference Match". The percentage stays the job-title score
 * against the seeker's preferred titles, so the number already shown on the
 * dashboard keeps its meaning; the listing's experience level, work
 * arrangement and employment type then filter and rank those candidates. A
 * preference that cannot be checked against a job (because either side left it
 * blank, or because the installation has no such column) never counts as a
 * mismatch, and locations and industries are still not job fields at all.
 *
 * Kept separate from JobMatchService so the existing Smart Job Match (skill)
 * score is untouched.
 */
class CareerPreferenceMatchService
{
    private const STOPWORDS = ['a', 'an', 'and', 'of', 'or', 'the', 'in', 'for', 'job', 'jobs', 'opening', 'openings'];

    private const CANDIDATE_LIMIT = 100;

    /**
     * @return array<int, array{job: Job, percentage: int, matched_titles: array<int, string>, matched_classifications: array<int, string>}>
     */
    public function recommendations(?JobSeekerCareerPreference $preferences, int $limit = 6): array
    {
        $titles = $this->cleanTitles($preferences?->preferred_job_titles);

        if ($titles === [] || $limit <= 0) {
            return [];
        }

        $jobs = $this->candidateJobs($titles);

        $scored = [];

        foreach ($jobs as $job) {
            $result = $this->scoreAgainstTitles($titles, (string) $job->JobTitle);

            if ($result['percentage'] < 50) {
                continue;
            }

            $fit = $this->classificationFit($preferences, $job);

            // A listing that states something the seeker ruled out is not worth
            // recommending however well its title matches.
            if ($fit['conflicts'] !== []) {
                continue;
            }

            $scored[] = [
                'job' => $job,
                'percentage' => $result['percentage'],
                'matched_titles' => $result['matched_titles'],
                'matched_classifications' => $fit['matched'],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => [
            $b['percentage'],
            count($b['matched_classifications']),
            (int) $b['job']->JobId,
        ] <=> [
            $a['percentage'],
            count($a['matched_classifications']),
            (int) $a['job']->JobId,
        ]);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Compare what the listing states about experience, arrangement and
     * employment type with the seeker's saved preferences.
     *
     * @return array{matched: array<int, string>, conflicts: array<int, string>}
     */
    public function classificationFit(?JobSeekerCareerPreference $preferences, Job $job): array
    {
        if ($preferences === null) {
            return ['matched' => [], 'conflicts' => []];
        }

        $matched = [];
        $conflicts = [];

        $level = $this->text($job->ExperienceLevel ?? null);
        $preferredLevel = $this->text($preferences->experience_level ?? null);

        // 'any' means the seeker did not narrow the level down.
        if ($level !== '' && $preferredLevel !== '' && $preferredLevel !== 'any') {
            $label = (string) Job::experienceLevelLabel($level);

            if ($level === $preferredLevel) {
                $matched[] = $label;
            } else {
                $conflicts[] = $label;
            }
        }

        $checks = [
            'WorkArrangement' => $this->tags($preferences->work_arrangements ?? null),
            'EmploymentType' => $this->tags($preferences->employment_types ?? null),
        ];

        foreach ($checks as $column => $preferred) {
            $value = $this->text($job->{$column} ?? null);

            if ($value === '' || $preferred === []) {
                continue;
            }

            $accepts = in_array(
                mb_strtolower($value),
                array_map(static fn (string $tag): string => mb_strtolower($tag), $preferred),
                true
            );

            if ($accepts) {
                $matched[] = $value;
            } else {
                $conflicts[] = $value;
            }
        }

        return ['matched' => $matched, 'conflicts' => $conflicts];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $value): string => $this->text($value), $values),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @param  array<int, string>  $preferredTitles
     * @return array{percentage: int, matched_titles: array<int, string>}
     */
    public function scoreAgainstTitles(array $preferredTitles, string $jobTitle): array
    {
        $jobTokens = $this->tokens($jobTitle);
        $jobNormalized = $this->normalize($jobTitle);

        $best = 0.0;
        $matched = [];

        foreach ($preferredTitles as $preferred) {
            $preferredTokens = $this->tokens($preferred);

            if ($preferredTokens === []) {
                continue;
            }

            $preferredNormalized = $this->normalize($preferred);

            if ($preferredNormalized === $jobNormalized
                || ($jobNormalized !== '' && str_contains($jobNormalized, $preferredNormalized))
            ) {
                $ratio = 1.0;
            } else {
                $overlap = array_intersect($preferredTokens, $jobTokens);
                $ratio = count($overlap) / count($preferredTokens);
            }

            if ($ratio > $best) {
                $best = $ratio;
            }

            if ($ratio >= 0.5) {
                $matched[] = $preferred;
            }
        }

        return [
            'percentage' => (int) round($best * 100),
            'matched_titles' => array_values(array_unique($matched)),
        ];
    }

    /**
     * Approved jobs pulled with one query; LIKE terms are escaped so custom
     * tags containing % or _ are matched literally. The classification columns
     * came after the original table, so they are only selected when present.
     *
     * @param  array<int, string>  $titles
     * @return Collection<int, Job>
     */
    private function candidateJobs(array $titles): Collection
    {
        $likeTerms = [];

        foreach ($titles as $title) {
            $normalized = $this->normalize($title);

            if ($normalized !== '') {
                $likeTerms[$normalized] = $normalized;
            }

            foreach ($this->tokens($title) as $token) {
                $likeTerms[$token] = $token;
            }
        }

        $columns = ['JobId', 'CompanyName', 'JobTitle', 'Vacancy', 'MinQualification', 'Description', 'SalaryFrom', 'SalaryTo', 'Status'];

        foreach (['ExperienceLevel', 'WorkArrangement', 'EmploymentType'] as $column) {
            if (Schema::hasColumn('job_master', $column)) {
                $columns[] = $column;
            }
        }

        return Job::query()
            ->select($columns)
            ->whereRaw('LOWER(Status) = ?', ['approved'])
            ->where(function ($query) use ($likeTerms): void {
                foreach ($likeTerms as $term) {
                    $query->orWhereRaw(
                        'LOWER(JobTitle) LIKE ?',
                        ['%' . addcslashes($term, '%_\\') . '%']
                    );
                }
            })
            ->orderByDesc('JobId')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();
    }

    /**
     * @param  array<int, mixed>|null  $titles
     * @return array<int, string>
     */
    private function cleanTitles(?array $titles): array
    {
        $clean = [];

        foreach ($titles ?? [] as $title) {
            if (is_string($title)) {
                $title = trim(preg_replace('/\s+/u', ' ', $title));

                if ($title !== '') {
                    $clean[] = mb_substr($title, 0, 100);
                }
            }
        }

        return $clean;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        preg_match_all('/[a-z0-9+#]+/i', $value, $matches);

        return array_values(array_unique(array_filter(
            array_map(static fn (string $token): string => mb_strtolower($token), $matches[0] ?? []),
            static fn (string $token): bool => $token !== '' && !in_array($token, self::STOPWORDS, true)
        )));
    }
}
