<?php

namespace App\Services;

use App\Models\Job;
use App\Support\JobComparisonState;
use App\Support\SchemaReady;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Job Offer Comparison Lab.
 *
 * Reads only the listing fields this database really has, takes the skill
 * percentage from JobMatchService rather than matching again, and turns the
 * seeker's five priority sliders into a weighted score.
 *
 * A factor the employer never disclosed is left out of that listing's score and
 * its weight redistributed across the factors it does answer, so silence is
 * never scored as a zero. Nothing here invents salary, benefits, growth or
 * quality figures.
 */
class JobOfferComparisonService
{
    public const NOT_DISCLOSED = 'Not disclosed';

    public const INSUFFICIENT = 'Not enough information to calculate a reliable comparison.';

    public const NO_PRIORITIES = 'Raise at least one priority slider to score these jobs.';

    /**
     * Sections of the comparison table, in display order.
     */
    public const GROUPS = [
        'Job information' => [
            'job_title' => 'Job title',
            'company' => 'Company',
            'company_info' => 'Company information',
            'location' => 'Job location',
            'employment_type' => 'Employment type',
            'work_mode' => 'Work mode',
            'experience' => 'Experience requirement',
            'qualification' => 'Qualification requirement',
            'vacancy' => 'Vacancies',
            'posted_on' => 'Job posting date',
        ],
        'Compensation and benefits' => [
            'salary_from' => 'Minimum salary',
            'salary_to' => 'Maximum salary',
            'salary_basis' => 'Salary frequency',
            'benefits' => 'Benefits',
            'extra_pay' => 'Additional compensation',
        ],
        'Skills and compatibility' => [
            'required_skills' => 'Required skills',
            'preferred_skills' => 'Preferred skills',
            'required_software' => 'Required software',
            'candidate_overlap' => 'Candidate skill compatibility',
            'smart_match' => 'Smart Match percentage',
        ],
    ];

    /**
     * Phrases an employer wrote themselves. Only these make the work-life
     * balance factor scoreable; a remote listing is not one of them, because
     * working from home does not by itself say anything about hours or leave.
     */
    private const BALANCE_SIGNALS = [
        'work-life balance', 'work life balance', 'work/life balance',
        'flexible hours', 'flexible schedule', 'flexible timing', 'flexible work pattern',
        'four-day week', '4-day week', '4 day week',
        'paid leave', 'privileged leave', 'personal leave', 'parental leave',
        'maternity leave', 'paternity leave', 'generous leave', 'ample leave',
        'leave encashment', 'no overtime', 'wellness', 'mental health', 'work from anywhere',
    ];

    private const GROWTH_SIGNALS = [
        'training', 'mentorship', 'mentoring', 'mentor', 'career growth', 'career development',
        'promotion', 'promotion track', 'upskilling', 'reskilling', 'certification',
        'learning opportunity', 'learning path', 'skill development', 'growth path',
        'internal mobility', 'leadership programme', 'leadership program', 'grooming',
    ];

    public function __construct(private readonly JobMatchService $jobMatch)
    {
    }

    /**
     * Compare the given listings for one seeker.
     *
     * $context comes from JobSuitabilityService::candidateContext() so the
     * seeker's skills, resume and preferences are read once for the whole set
     * rather than once per listing.
     *
     * @param  list<int>  $jobIds
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function compare(array $jobIds, array $context): array
    {
        $jobs = $this->visibleJobs($jobIds);

        $locations = $this->employerLocations($jobs);
        $posted = $this->postedOn($jobs);
        $preferences = $context['preferences'] ?? null;

        $skills = $context['skills'] ?? [];
        $software = $context['software'] ?? [];

        // Salary is only normalisable when at least two listings state a figure
        // that can be read; one offer on its own says nothing about pay.
        $pay = $jobs->mapWithKeys(fn (Job $job) => [$job->JobId => $this->monthlyPay($job)]);
        $comparable = $pay->filter()->count() >= 2;
        $bounds = $this->payBounds($pay);

        $matches = $jobs->mapWithKeys(fn (Job $job) => [
            $job->JobId => $this->jobMatch->calculate($skills, $job, $software),
        ]);

        $weights = JobComparisonState::weights();
        $preferredLocations = $this->tags($preferences?->preferred_locations ?? null);

        $columns = [];

        foreach ($jobs as $job) {
            $match = $matches[$job->JobId];
            $rows = $this->rows($job, $locations->get((int) ($job->EmployerId ?? 0)), $posted, $match);

            $factors = [
                'salary' => $this->salaryFactor($job, $pay, $bounds, $comparable),
                'location' => $this->locationFactor($job, $rows['location'], $preferredLocations, $preferences),
                'work_life_balance' => $this->signalFactor($job, 'work_life_balance', self::BALANCE_SIGNALS, 'work-life balance'),
                'career_growth' => $this->signalFactor($job, 'career_growth', self::GROWTH_SIGNALS, 'career growth'),
                'skill_match' => $this->skillFactor($match),
            ];

            $columns[$job->JobId] = [
                'job' => $job,
                'rows' => $rows,
                'factors' => $factors,
                'match' => $match,
                'disclosed' => $this->disclosure($rows),
                'score' => $this->score($factors, $weights),
            ];
        }

        $availability = $this->availability($columns);

        return [
            'columns' => $columns,
            'groups' => self::GROUPS,
            'weights' => $weights,
            'shares' => $this->shares($weights, $availability),
            'availability' => $availability,
            'factors' => JobComparisonState::FACTORS,
            'salary_comparable' => $comparable,
            'locations_saved' => $preferredLocations !== [],
            'requested' => $jobIds,
            ...$this->verdict($columns),
        ];
    }

    /**
     * Split the whole hundred across the weights, largest remainder first, so the
     * shares add up to exactly 100 instead of drifting a point on rounding.
     *
     * @param  array<string, int>  $weights
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function allocate(array $weights, array $keys): array
    {
        $total = 0;

        foreach ($keys as $key) {
            $total += max(0, $weights[$key] ?? 0);
        }

        $shares = array_fill_keys($keys, 0);

        if ($total <= 0) {
            return $shares;
        }

        $remainders = [];

        foreach ($keys as $key) {
            $exact = 100 * max(0, $weights[$key] ?? 0) / $total;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - floor($exact);
        }

        arsort($remainders);

        $left = 100 - array_sum($shares);

        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }

            $shares[$key]++;
            $left--;
        }

        return $shares;
    }

    /**
     * The sliders as percentages of one another, so the panel can show how much
     * say each priority currently carries before any listing is scored.
     *
     * A factor that none of the compared listings can answer is held at zero and
     * its share moves onto the factors that do get scored, so the weights on
     * screen are the weights the score uses: five equal sliders with everything
     * answerable are worth 20% each.
     *
     * @param  array<string, int>  $weights
     * @param  array<string, array<string, mixed>>  $availability
     * @return array<string, int>
     */
    private function shares(array $weights, array $availability): array
    {
        $effective = [];

        foreach ($weights as $key => $weight) {
            $effective[$key] = ($availability[$key]['available'] ?? false) ? $weight : 0;
        }

        return $this->allocate($effective, array_keys($effective));
    }

    /**
     * Whether this comparison can use each factor at all, how many listings
     * answer it, and - when none do - why in words that speak about the whole
     * set rather than about one listing.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, array<string, mixed>>
     */
    private function availability(array $columns): array
    {
        $compared = count($columns);
        $summary = [];

        foreach (array_keys(JobComparisonState::FACTORS) as $key) {
            $disclosed = 0;
            $reason = null;

            foreach ($columns as $column) {
                $factor = $column['factors'][$key];

                if ($factor['available']) {
                    $disclosed++;
                } elseif ($reason === null) {
                    $reason = $factor['unavailable_panel'];
                }
            }

            $summary[$key] = [
                'available' => $disclosed > 0,
                'disclosed_by' => $disclosed,
                'compared' => $compared,
                'partial' => $disclosed > 0 && $disclosed < $compared,
                'reason' => $disclosed > 0 ? null : $reason,
            ];
        }

        return $summary;
    }

    /**
     * Approved listings only, in the order the seeker arranged them. A listing
     * that was pulled down or never approved is simply absent, which the caller
     * uses to drop it from the saved selection.
     *
     * @param  list<int>  $jobIds
     * @return Collection<int, Job>
     */
    private function visibleJobs(array $jobIds): Collection
    {
        if ($jobIds === []) {
            return collect();
        }

        $found = Job::query()
            ->whereIn('JobId', $jobIds)
            ->whereRaw('LOWER(Status) = ?', ['approved'])
            ->get()
            ->keyBy('JobId');

        return collect($jobIds)
            ->map(fn (int $id) => $found->get($id))
            ->filter()
            ->values();
    }

    /**
     * job_master has no location column, so the listing's place is whatever the
     * employer account states. Only the two public columns are read - never the
     * employer's contact details - and one query covers the whole comparison.
     *
     * @param  Collection<int, Job>  $jobs
     * @return Collection<int, object>
     */
    private function employerLocations(Collection $jobs): Collection
    {
        $ids = $jobs->pluck('EmployerId')->filter()->map(static fn ($id) => (int) $id)->values();

        if ($ids->isEmpty()
            || !SchemaReady::hasTable('employer_reg')
            || !SchemaReady::hasColumn('employer_reg', 'City')
        ) {
            return collect();
        }

        return DB::table('employer_reg')
            ->select('EmployerId', 'City', 'Area_Work')
            ->whereIn('EmployerId', $ids)
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->EmployerId => $row]);
    }

    /**
     * There is no posting-date column, but the insert trigger on job_master
     * writes an 'Inserted' log row, and that is the only honest date available.
     *
     * @param  Collection<int, Job>  $jobs
     * @return Collection<int, string>
     */
    private function postedOn(Collection $jobs): Collection
    {
        $ids = $jobs->pluck('JobId')->map(static fn ($id) => (int) $id)->values();

        if ($ids->isEmpty()
            || !SchemaReady::hasTable('logs')
            || !SchemaReady::hasColumn('logs', 'cdate')
            || !SchemaReady::hasColumn('logs', 'action')
        ) {
            return collect();
        }

        return DB::table('logs')
            ->select('JobId', DB::raw('MIN(cdate) as posted_at'))
            ->whereIn('JobId', $ids)
            ->where('action', 'Inserted')
            ->groupBy('JobId')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->JobId => $row->posted_at]);
    }

    /**
     * The side-by-side values, each with whether the employer actually disclosed
     * it. Columns the database does not have are reported as not disclosed
     * instead of being filled in.
     *
     * @return array<string, array{display: string, missing: bool, items: list<string>}>
     */
    private function rows(Job $job, mixed $employer, Collection $posted, array $match): array
    {
        $city = $this->text($employer?->City ?? null);
        $workAreas = $this->text($employer?->Area_Work ?? null);
        $date = $posted->get((int) $job->JobId);

        $requirements = $this->requirements($job->RequiredSkills, 'mandatory');
        $preferred = $this->requirements($job->RequiredSkills, 'preferred');
        $software = $this->requirements($job->RequiredSoftware, 'mandatory');

        $rows = [
            'job_title' => $this->cell($job->JobTitle),
            'company' => $this->cell($job->CompanyName),
            'company_info' => $this->cell($workAreas !== '' ? 'Hires for: ' . $workAreas : ''),
            'location' => $this->cell($city),
            'employment_type' => $this->cell($job->EmploymentType),
            'work_mode' => $this->cell($job->WorkArrangement),
            'experience' => $this->cell(Job::experienceLevelLabel($job->ExperienceLevel ?? null)),
            'qualification' => $this->cell($job->MinQualification),
            'vacancy' => $this->cell($job->Vacancy > 0 ? (string) $job->Vacancy : ''),
            'posted_on' => $this->cell(is_string($date) && $date !== '' ? $this->date($date) : ''),
            'salary_from' => $this->cell($job->SalaryFrom),
            'salary_to' => $this->cell($job->SalaryTo),
            // No frequency or currency column exists on job_master, and employer
            // forms ask for a monthly amount, so nothing is claimed here.
            'salary_basis' => $this->cell(''),
            // The schema has no benefits or bonus storage at all.
            'benefits' => $this->cell(''),
            'extra_pay' => $this->cell(''),
            'required_skills' => $this->cell(implode(', ', $requirements), $requirements),
            'preferred_skills' => $this->cell(implode(', ', $preferred), $preferred),
            'required_software' => $this->cell(implode(', ', $software), $software),
            'candidate_overlap' => $this->cell(
                implode(', ', $match['matched'] ?? []),
                array_values(array_slice($match['matched'] ?? [], 0, 12))
            ),
            'smart_match' => $this->cell(
                $match['status'] === JobMatchService::STATUS_SCORED && $match['percentage'] !== null
                    ? $match['percentage'] . '% ' . $match['label']
                    : ''
            ),
        ];

        return $rows;
    }

    /**
     * @param  array<int, mixed>|null  $entries
     * @return list<string>
     */
    private function requirements(?array $entries, string $type): array
    {
        $names = [];

        foreach ($entries ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $this->text($entry['name'] ?? null);
            $entryType = mb_strtolower($this->text($entry['type'] ?? 'mandatory'));

            if ($name !== '' && $entryType === $type) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array{display: string, missing: bool, items: list<string>}
     */
    private function cell(mixed $value, array $items = []): array
    {
        $text = $this->text($value);

        return [
            'display' => $text === '' ? self::NOT_DISCLOSED : $text,
            'missing' => $text === '',
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, array{0: float, 1: float}|null>  $pay
     * @return array{0: float, 1: float}|null
     */
    private function payBounds(Collection $pay): ?array
    {
        $points = $pay->map($this->midpoint(...))->filter();

        if ($points->isEmpty()) {
            return null;
        }

        return [$points->min(), $points->max()];
    }

    /**
     * The listing's pay scored against the other listings in this comparison.
     *
     * @param  Collection<int, array{0: float, 1: float}|null>  $pay
     * @param  array{0: float, 1: float}|null  $bounds
     * @return array<string, mixed>
     */
    private function salaryFactor(Job $job, Collection $pay, ?array $bounds, bool $comparable): array
    {
        if (!$comparable) {
            return $this->unavailable(
                'Fewer than two listings state a salary that can be read, so pay cannot be ranked.'
            );
        }

        $point = $this->midpoint($pay->get($job->JobId));

        if ($point === null) {
            return $this->unavailable(
                'This listing does not disclose a readable salary.',
                'None of these listings state a salary that can be ranked.'
            );
        }

        [$low, $high] = $bounds ?? [$point, $point];

        if ($high <= $low) {
            return $this->factor(100, 'Every listing here offers the same pay.');
        }

        $score = (int) round(($point - $low) / ($high - $low) * 100);

        return $this->factor(
            $score,
            'Ranked across the salaries disclosed in this comparison.'
        );
    }

    /**
     * @param  array{display: string, missing: bool, items: list<string>}  $locationRow
     * @return array<string, mixed>
     */
    private function locationFactor(Job $job, array $locationRow, array $preferredLocations, mixed $preferences): array
    {
        if ($preferredLocations === []) {
            return $this->unavailable(
                'No preferred locations saved in Career Preferences.',
                'Location is judged against the places saved under Career Preferences, and none are saved yet.'
            );
        }

        $arrangement = $this->text($job->WorkArrangement ?? null);

        if (mb_strtolower($arrangement) === 'remote') {
            return $this->factor(100, 'Remote - the listing is not tied to a location.');
        }

        if ($locationRow['missing']) {
            return $this->unavailable(
                'This listing does not state where the work is.',
                'None of these listings state where the work is.'
            );
        }

        $place = mb_strtolower($locationRow['display']);
        $wanted = array_map(static fn (string $tag): string => mb_strtolower($tag), $preferredLocations);

        $matches = array_values(array_filter(
            $wanted,
            static fn (string $tag): bool => str_contains($place, $tag) || str_contains($tag, $place)
        ));

        if ($matches !== []) {
            return $this->factor(100, 'In ' . $locationRow['display'] . ', one of your saved locations.');
        }

        if (mb_strtolower($this->text($preferences?->willing_to_relocate ?? null)) === 'yes') {
            return $this->factor(
                60,
                'Outside your saved locations, but you marked that relocating works for you.'
            );
        }

        return $this->factor(0, 'In ' . $locationRow['display'] . ', outside your saved locations.');
    }

    /**
     * Work-life balance and career growth are scored only on phrases the
     * employer wrote into the listing, and the phrase is shown as the evidence.
     *
     * @param  list<string>  $signals
     * @return array<string, mixed>
     */
    private function signalFactor(Job $job, string $factor, array $signals, string $label): array
    {
        $description = mb_strtolower($this->text($job->Description ?? null));

        if ($description === '') {
            return $this->unavailable(
                'The listing carries no description to read ' . $label . ' from.',
                'None of these listings carry a description to read ' . $label . ' from.'
            );
        }

        $evidence = array_values(array_unique(array_filter(
            $signals,
            static fn (string $signal): bool => str_contains($description, $signal)
        )));

        if ($evidence === []) {
            return $this->unavailable(
                'This listing does not mention ' . $label . '.',
                'None of these listings mention ' . $label . ', so it is not scored.'
            );
        }

        // One written signal is a partial answer, two or more is a commitment the
        // employer spells out, and nothing beyond that is claimed.
        return $this->factor(
            count($evidence) >= 2 ? 100 : 75,
            'From the listing: ' . implode(', ', array_slice($evidence, 0, 3)) . '.'
        );
    }

    /**
     * Reuse the Smart Job Match percentage as it stands.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function skillFactor(array $match): array
    {
        if ($match['status'] !== JobMatchService::STATUS_SCORED || $match['percentage'] === null) {
            return $this->unavailable(
                $match['status'] === JobMatchService::STATUS_NEEDS_RESUME
                    ? 'Smart Job Match has no resume skills to score yet.'
                    : 'This listing does not list requirements to match against.',
                $match['status'] === JobMatchService::STATUS_NEEDS_RESUME
                    ? 'Smart Job Match has no resume skills to score yet.'
                    : 'None of these listings list requirements to match against.'
            );
        }

        return $this->factor((int) $match['percentage'], 'The Smart Job Match score for this listing.');
    }

    /**
     * @return array<string, mixed>
     */
    private function factor(int $score, string $basis): array
    {
        return ['available' => true, 'score' => $score, 'basis' => $basis, 'excluded_from' => null, 'unavailable_panel' => null];
    }

    /**
     * $reason speaks about the one listing, $panel about the whole comparison.
     * The priority panel needs the second wording; without it a slider would
     * blame a single listing for something every listing left out.
     *
     * @return array<string, mixed>
     */
    private function unavailable(string $reason, ?string $panel = null): array
    {
        return [
            'available' => false,
            'score' => null,
            'basis' => $reason,
            'excluded_from' => $reason,
            'unavailable_panel' => $panel ?? $reason,
        ];
    }

    /**
     * Weighted score over the factors this listing can answer. A slider at zero
     * means the seeker does not care about that factor, and an unanswered factor
     * takes no part; either way the remaining weights are renormalised to the
     * whole hundred so the score stays comparable.
     *
     * @param  array<string, array<string, mixed>>  $factors
     * @param  array<string, int>  $weights
     * @return array<string, mixed>
     */
    private function score(array $factors, array $weights): array
    {
        $usable = [];

        foreach ($factors as $key => $factor) {
            if ($factor['available'] && $weights[$key] > 0) {
                $usable[$key] = $weights[$key];
            }
        }

        $total = array_sum($usable);

        $shares = $this->allocate($usable, array_keys($factors));

        $points = [];

        foreach ($factors as $key => $factor) {
            $points[$key] = [
                'score' => $factor['available'] ? $factor['score'] : null,
                'weight' => $weights[$key],
                'share' => $shares[$key],
                'basis' => $factor['basis'],
            ];
        }

        if ($weights === array_map(static fn (): int => 0, $weights)) {
            return ['personalized' => null, 'status' => 'no_priorities', 'message' => self::NO_PRIORITIES, 'points' => $points];
        }

        if ($total <= 0) {
            return [
                'personalized' => null,
                'status' => 'insufficient',
                'message' => self::INSUFFICIENT,
                'points' => $points,
            ];
        }

        $sum = 0.0;

        foreach ($usable as $key => $weight) {
            $sum += $factors[$key]['score'] * ($weight / $total);
        }

        return [
            'personalized' => (int) round($sum),
            'status' => 'scored',
            'message' => null,
            'points' => $points,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    private function verdict(array $columns): array
    {
        $scored = array_filter(
            $columns,
            static fn (array $column): bool => $column['score']['personalized'] !== null
        );

        $highest = null;

        if ($scored !== []) {
            uasort($scored, static fn (array $a, array $b): int => $b['score']['personalized'] <=> $a['score']['personalized']);
            $jobId = array_key_first($scored);
            $highest = ['job_id' => $jobId, 'label' => $columns[$jobId]['rows']['job_title']['display']];
        }

        $message = null;

        if ($columns !== [] && $scored === []) {
            $message = $columns[array_key_first($columns)]['score']['status'] === 'no_priorities'
                ? self::NO_PRIORITIES
                : self::INSUFFICIENT;
        }

        return [
            'highest' => $highest,
            'highlights' => $this->highlights($columns),
            'explanation' => $highest === null ? null : $this->explain($columns[$highest['job_id']]),
            'message' => $message,
        ];
    }

    /**
     * Best-per-criterion markers, each only when the data supports the claim.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, int|null>
     */
    private function highlights(array $columns): array
    {
        $best = static function (array $columns, callable $value): ?int {
            $top = null;
            $topValue = null;

            foreach ($columns as $jobId => $column) {
                $candidate = $value($column);

                if ($candidate === null) {
                    continue;
                }

                if ($topValue === null || $candidate > $topValue) {
                    $top = $jobId;
                    $topValue = $candidate;
                }
            }

            return $topValue === null || count($columns) < 2 ? null : $top;
        };

        return [
            'salary' => $best($columns, static fn (array $c): ?int => $c['factors']['salary']['available'] ? $c['factors']['salary']['score'] : null),
            'skill_match' => $best($columns, static fn (array $c): ?int => $c['factors']['skill_match']['available'] ? $c['factors']['skill_match']['score'] : null),
            'location' => $best($columns, static fn (array $c): ?int => $c['factors']['location']['available'] && $c['factors']['location']['score'] > 0 ? $c['factors']['location']['score'] : null),
            'completeness' => $best($columns, static fn (array $c): int => $c['disclosed']['count']),
        ];
    }

    /**
     * What the listing did and did not answer, so the table can flag gaps.
     *
     * @param  array<string, array{display: string, missing: bool, items: list<string>}>  $rows
     * @return array{count: int, missing: list<string>}
     */
    private function disclosure(array $rows): array
    {
        $missing = [];
        $count = 0;

        foreach (self::GROUPS as $label => $group) {
            foreach ($group as $key => $rowLabel) {
                if ($key === 'smart_match' || $key === 'candidate_overlap') {
                    // Derived from the seeker's own profile, not employer-disclosed.
                    continue;
                }

                if ($rows[$key]['missing']) {
                    $missing[] = $rowLabel;
                } else {
                    $count++;
                }
            }
        }

        return ['count' => $count, 'missing' => $missing];
    }

    /**
     * A sentence built from this listing's own results, never a general claim
     * that it is the best job going.
     *
     * @param  array<string, mixed>  $column
     */
    private function explain(array $column): string
    {
        $title = $column['rows']['job_title']['display'];

        $named = [
            'salary' => 'the pay it discloses',
            'location' => 'a location that suits your saved preferences',
            'work_life_balance' => 'work-life balance written into the listing',
            'career_growth' => 'career growth written into the listing',
            'skill_match' => 'your skill compatibility of ' . $column['factors']['skill_match']['score'] . '%',
        ];

        $reasons = [];

        foreach ($column['score']['points'] as $key => $point) {
            if ($point['score'] === null || $point['score'] <= 0 || $point['share'] <= 0) {
                continue;
            }

            $reasons[] = [$point['score'] * $point['share'] / 100, $key];
        }

        usort($reasons, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        $top = array_slice(array_map(static fn (array $entry): string => $named[$entry[1]], array_slice($reasons, 0, 2)), 0, 2);

        if ($top === []) {
            return $title . ' ranks highest for your current priorities, though little of it is disclosed.';
        }

        return $title . ' is the best match for your current priorities because of ' . implode(' and ', $top) . '.';
    }

    /**
     * @param  array{0: float, 1: float}|null  $range
     */
    private function midpoint(?array $range): ?float
    {
        if ($range === null) {
            return null;
        }

        // An open-ended "45,000+" is read at its floor; guessing a ceiling would
        // be inventing a salary.
        return $range[1] === INF ? $range[0] : ($range[0] + $range[1]) / 2;
    }

    /**
     * What the listing states per month, or null when it states nothing readable.
     * job_master stores pay as free text with no frequency or currency column, so
     * this reads a plain amount the same way CareerPreferenceMatchService does and
     * leaves 'Negotiable' or '45 LPA' undisclosed rather than guessing at them.
     *
     * @return array{0: float, 1: float}|null
     */
    private function monthlyPay(Job $job): ?array
    {
        $low = $this->money($job->SalaryFrom ?? null);
        $high = $this->money($job->SalaryTo ?? null);

        $low = $low !== null && $low > 0 ? $low : null;
        $high = $high !== null && $high > 0 ? $high : null;

        if ($low === null && $high === null) {
            return null;
        }

        return [$low ?? 0.0, $high ?? INF];
    }

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

    private function date(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('d M Y', $timestamp);
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '') : '';
    }

    /**
     * @return list<string>
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
}
