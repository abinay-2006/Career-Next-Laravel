<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerCareerPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic "Preference Match". When the seeker has named target job
 * titles the percentage stays the job-title score, so the number already shown
 * on the dashboard keeps its meaning; the listing's experience level, work
 * arrangement, employment type and pay then filter and rank those candidates.
 * When the seeker has named no title, the same checks stand on their own and
 * the percentage reports how much of what they asked for a listing delivers.
 * A preference that cannot be checked against a job (because either side left
 * it blank, because it is in a currency or period the listing does not use, or
 * because the installation has no such column) never counts as a mismatch, and
 * locations and industries are still not job fields at all.
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
        if ($preferences === null || $limit <= 0) {
            return [];
        }

        $titles = $this->cleanTitles($preferences->preferred_job_titles);
        $askedFor = $this->specifiedPreferenceCount($preferences);

        // With no title and nothing else named there is no way to rank one job
        // over another; the dashboard shows its "tell us more" state instead.
        if ($titles === [] && $askedFor === 0) {
            return [];
        }

        $scored = [];

        foreach ($this->candidateJobs($titles) as $job) {
            $fit = $this->classificationFit($preferences, $job);

            // A listing that states something the seeker ruled out is not worth
            // recommending however well its title matches.
            if ($fit['conflicts'] !== []) {
                continue;
            }

            if ($titles === []) {
                // A listing that says nothing the seeker asked for is not a
                // recommendation just because nothing contradicted it either.
                if ($fit['matched'] === []) {
                    continue;
                }

                $percentage = (int) round(count($fit['matched']) / max($askedFor, 1) * 100);
                $matchedTitles = [];
            } else {
                $titleScore = $this->scoreAgainstTitles($titles, (string) $job->JobTitle);

                if ($titleScore['percentage'] < 50) {
                    continue;
                }

                $percentage = $titleScore['percentage'];
                $matchedTitles = $titleScore['matched_titles'];
            }

            $scored[] = [
                'job' => $job,
                'percentage' => $percentage,
                'matched_titles' => $matchedTitles,
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
     * Compare what the listing states about experience, arrangement, employment
     * type and pay with the seeker's saved preferences.
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

        // 'any' means the seeker did not narrow the level down, so it is
        // deliberately not turned into a match.
        $jobYears = Job::experienceYears($level);
        $seekerYears = $preferredLevel === 'any' ? null : Job::experienceYears($preferredLevel);

        if ($jobYears !== null && $seekerYears !== null) {
            $label = (string) Job::experienceLevelLabel($level);

            if ($this->experienceFits($seekerYears, $jobYears)) {
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

        $expected = $this->expectedMonthlySalary($preferences);
        $offered = $this->listingMonthlySalary($job);

        if ($expected !== null && $offered !== null) {
            $agrees = $this->salaryAgrees($expected, $offered);

            if ($agrees !== null) {
                $label = 'Salary ' . $this->salaryLabel($offered);

                if ($agrees) {
                    $matched[] = $label;
                } else {
                    $conflicts[] = $label;
                }
            }
        }

        return ['matched' => $matched, 'conflicts' => $conflicts];
    }

    /**
     * Whether a listing's pay answers what the seeker asked for: true, false, or
     * null when the listing promises only a floor — the forms render a lone
     * SalaryFrom as '₹45,000+' — that stops short of the seeker's minimum and says
     * nothing about a ceiling. Such a listing is neither an answer nor a
     * contradiction, so it is left unchecked instead of credited or ruled out.
     *
     * @param  array{0: float, 1: float}  $expected
     * @param  array{0: float, 1: float}  $offered
     */
    private function salaryAgrees(array $expected, array $offered): ?bool
    {
        if ($offered[1] === INF && $expected[0] > $offered[0]) {
            return null;
        }

        return $expected[0] <= $offered[1] && $offered[0] <= $expected[1];
    }

    /**
     * A listing states the experience it wants as a band, but what it rules out
     * is only the minimum of that band: someone with 3–5 years is not disqualified
     * from a 1–2 year role, while a fresher cannot take a senior one.
     *
     * @param  array{0: int, 1: int|null}  $seekerYears
     * @param  array{0: int, 1: int|null}  $jobYears
     */
    private function experienceFits(array $seekerYears, array $jobYears): bool
    {
        $seekerCeiling = $seekerYears[1];

        return $seekerCeiling === null || $seekerCeiling >= $jobYears[0];
    }

    /**
     * How many preference categories can be checked against a listing at all.
     * Titles are counted separately because they score on their own.
     */
    private function specifiedPreferenceCount(JobSeekerCareerPreference $preferences): int
    {
        $level = $this->text($preferences->experience_level ?? null);
        $askedFor = $level !== 'any' && Job::experienceYears($level) !== null ? 1 : 0;

        foreach (['work_arrangements', 'employment_types'] as $key) {
            if ($this->tags($preferences->{$key} ?? null) !== []) {
                $askedFor++;
            }
        }

        if ($this->expectedMonthlySalary($preferences) !== null) {
            $askedFor++;
        }

        return $askedFor;
    }

    /**
     * The seeker's expected pay as a monthly rupee range, or null when it cannot
     * be compared. job_master has no currency or period column and the employer
     * forms ask for a monthly amount in rupees, so an annual expectation is
     * divided into months and any other currency is left unchecked rather than
     * converted by a rate nothing in the project maintains.
     *
     * @return array{0: float, 1: float}|null
     */
    private function expectedMonthlySalary(?JobSeekerCareerPreference $preferences): ?array
    {
        if ($preferences === null) {
            return null;
        }

        $currency = mb_strtoupper($this->text($preferences->currency ?? null));

        if ($currency !== '' && $currency !== 'INR') {
            return null;
        }

        $minimum = $this->money($preferences->minimum_salary ?? null);
        $maximum = $this->money($preferences->maximum_salary ?? null);

        if (mb_strtolower($this->text($preferences->salary_period ?? null)) === 'annual') {
            $minimum = $minimum === null ? null : $minimum / 12;
            $maximum = $maximum === null ? null : $maximum / 12;
        }

        return $this->range($minimum, $maximum);
    }

    /**
     * What the listing offers per month, or null when it states no usable pay.
     *
     * @return array{0: float, 1: float}|null
     */
    private function listingMonthlySalary(Job $job): ?array
    {
        return $this->range($this->money($job->SalaryFrom ?? null), $this->money($job->SalaryTo ?? null));
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function range(?float $minimum, ?float $maximum): ?array
    {
        // Zero is how a form stores "I did not set a limit", and a range that
        // reaches everything says nothing, so both bounds are dropped.
        $minimum = $minimum !== null && $minimum > 0 ? $minimum : null;
        $maximum = $maximum !== null && $maximum > 0 ? $maximum : null;

        if ($minimum === null && $maximum === null) {
            return null;
        }

        $low = $minimum ?? 0.0;
        $high = $maximum ?? INF;

        return $low <= $high ? [$low, $high] : [$high, $low];
    }

    /**
     * @param  array{0: float, 1: float}  $range
     */
    private function salaryLabel(array $range): string
    {
        [$low, $high] = $range;

        if ($high === INF) {
            return '₹' . number_format($low);
        }

        if ($low <= 0) {
            return 'up to ₹' . number_format($high);
        }

        return $low === $high
            ? '₹' . number_format($low)
            : '₹' . number_format($low) . ' – ₹' . number_format($high);
    }

    /**
     * A stored pay figure as a number, or null when it is blank or is free text
     * from before the forms asked for an amount. Spaces, thousands separators and
     * a currency sign still describe an amount, but anything with a word in it
     * ('45 LPA', 'Negotiable') is left unread rather than guessed at, because a
     * misread figure would wrongly rule a listing out instead of just skipping it.
     */
    private function money(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $amount = preg_replace('/[\s,₹$€£]/u', '', trim($value));

        return $amount !== null && $amount !== '' && is_numeric($amount) ? (float) $amount : null;
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
     * With no title to aim at the newest approved listings are the candidates
     * and the preference checks decide what is worth showing.
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
            ->when(
                $likeTerms !== [],
                fn ($query) => $query->where(function ($query) use ($likeTerms): void {
                    foreach ($likeTerms as $term) {
                        $query->orWhereRaw(
                            'LOWER(JobTitle) LIKE ?',
                            ['%' . addcslashes($term, '%_\\') . '%']
                        );
                    }
                })
            )
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
