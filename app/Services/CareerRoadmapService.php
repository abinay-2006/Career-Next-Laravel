<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeeker;

/**
 * Career Gap-to-Job Roadmap.
 *
 * The Job Suitability Assessment answers whether a profile meets a listing. This
 * answers the next question: what would a seeker actually have to work on to get
 * closer? It turns one assessment into an ordered list of steps under four
 * headings - Learn, Improve, Gain Experience and Already Matched - plus the
 * profile screen each step belongs to.
 *
 * It owns no matching maths. It reads the criteria JobSuitabilityService has
 * already decided, which in turn reads JobMatchService and
 * CareerPreferenceMatchService, so the 2 (mandatory) / 1 (preferred) weights,
 * the qualification ladder, the experience bands and the four suitability
 * statuses are all used exactly once and are untouched here. A roadmap is
 * derived, never stored: like the assessment it sits beside, the same profile
 * produces the same roadmap and nothing is written to the database.
 *
 * Where a rule comes from
 * -----------------------
 * Missing mandatory skills, missing preferred skills and missing tools are the
 * names the Smart Job Match result already reports, so a step says exactly what
 * the listing tagged and the profile does not contain. A step whose requirement
 * is already covered is reported under Already Matched rather than dropped, so
 * the progress figure shows what has been earned as well as what is left.
 *
 * A qualification the seeker genuinely falls short of is an Improve step against
 * Education, not a promise of a degree: the roadmap says which level the listing
 * asks for and which one the profile holds. Experience is the only Gain
 * Experience step, because it is the one requirement no profile edit can create.
 *
 * Missing information is never turned into a gap. A criterion the assessment
 * could not check - nothing recorded, nothing tagged, an unrankable qualification
 * name - yields at most one "not recorded yet" step that points at the screen
 * where the value belongs, and it is never counted as failed. That keeps the two
 * guardrails the assessment was built on: an unrecorded profile cannot be
 * rejected, and a roadmap cannot invent work the employer did not ask for.
 *
 * Progress
 * --------
 * The percentage is the share of listed steps the CURRENT profile already
 * satisfies. It is recomputed from the assessment on every view, which reads the
 * live profile, so adding a skill or saving a qualification moves the number and
 * clicking a roadmap item cannot. Steps that could not be checked are excluded
 * from that figure rather than being counted as done or as outstanding.
 *
 * No step, group or percentage here says anything about being hired. A roadmap
 * measures how closely a profile matches what one employer wrote down; the
 * decision stays with the employer, and completing every step is not a
 * qualification for selection.
 */
class CareerRoadmapService
{
    /** A skill or tool the listing asks for and the profile does not name. */
    public const GROUP_LEARN = 'learn';

    /** Profile or resume information to add, correct or strengthen. */
    public const GROUP_IMPROVE = 'improve';

    /** An experience band the listing asks for above what is recorded. */
    public const GROUP_EXPERIENCE = 'experience';

    /** A requirement the current profile already satisfies. */
    public const GROUP_MATCHED = 'matched';

    /** Requirement the seeker's profile already covers. */
    public const STATE_MATCHED = 'matched';

    /** Requirement the seeker's profile is short of. */
    public const STATE_GAP = 'gap';

    /** Requirement that could not be checked because a value is not recorded. */
    public const STATE_UNRECORDED = 'unrecorded';

    /** The order the groups render in, and the order steps are ranked within them. */
    private const GROUP_ORDER = [
        self::GROUP_LEARN,
        self::GROUP_IMPROVE,
        self::GROUP_EXPERIENCE,
        self::GROUP_MATCHED,
    ];

    private const GROUP_META = [
        self::GROUP_LEARN => [
            'label' => 'Learn',
            'icon' => 'fa-graduation-cap',
            'note' => 'Skills and tools this listing names that your profile does not mention yet.',
        ],
        self::GROUP_IMPROVE => [
            'label' => 'Improve',
            'icon' => 'fa-pen-to-square',
            'note' => 'Profile and resume information to add, correct or present more clearly.',
        ],
        self::GROUP_EXPERIENCE => [
            'label' => 'Gain Experience',
            'icon' => 'fa-layer-group',
            'note' => 'Experience this listing asks for above what your profile records.',
        ],
        self::GROUP_MATCHED => [
            'label' => 'Already Matched',
            'icon' => 'fa-circle-check',
            'note' => 'Requirements your current profile already covers.',
        ],
    ];

    /**
     * Which profile screen a step belongs to. The views turn these keys into the
     * Update Skills, Update Education, Update Experience and Update Resume
     * actions, so the service stays free of route and URL knowledge.
     */
    private const KIND_ACTIONS = [
        'skill' => 'skills',
        'software' => 'skills',
        'qualification' => 'education',
        'experience' => 'experience',
        'profile' => 'resume',
    ];

    /** The four actions the roadmap can point a seeker at. */
    public const ACTIONS = ['skills', 'education', 'experience', 'resume'];

    /**
     * The small label a compact step carries: which kind of requirement it is,
     * worded for someone deciding what to work on.
     */
    private const CATEGORY_LABELS = [
        'skill' => 'Skill Gap',
        'software' => 'Software',
        'qualification' => 'Education',
        'experience' => 'Experience',
        'profile' => 'Profile',
    ];

    public function __construct(
        private readonly JobSuitabilityService $suitability,
    ) {
    }

    /**
     * Build the roadmap for one listing from the assessment already made for it.
     *
     * Pass the $assessment JobSuitabilityService produced for this job; a fresh
     * assessment happens only when none is given, exactly as assess() does.
     *
     * @param  array<string, mixed>  $context  a JobSuitabilityService::candidateContext() result
     * @param  array<string, mixed>|null  $assessment  a JobSuitabilityService::assess() result for this job
     * @return array{job_id: int, status: string, tone: string, percentage: int|null, steps_total: int, steps_completed: int, steps_outstanding: int, groups: array<string, array<string, mixed>>, steps: list<array<string, mixed>>, next_steps: list<array<string, mixed>>, actions: list<string>, note: string|null}
     */
    public function build(array $context, Job $job, ?array $assessment = null): array
    {
        $assessment ??= $this->suitability->assess($context, $job);

        $steps = $this->steps($assessment, $job);

        return $this->present($job, $steps, $assessment['status'] ?? JobSuitabilityService::STATUS_INSUFFICIENT);
    }

    /**
     * Convenience for a single listing, mirroring assessFor(). A search result
     * should reuse one context and one assessment per job rather than call this.
     *
     * @return array<string, mixed>
     */
    public function forSeeker(JobSeeker|int $seeker, Job $job): array
    {
        return $this->build($this->suitability->candidateContext($seeker), $job);
    }

    /*
    |--------------------------------------------------------------------------
    | Steps
    |--------------------------------------------------------------------------
    */

    /**
     * One step per requirement the listing states. Outstanding steps are built
     * first - mandatory skills, preferred skills, tools, qualification,
     * experience - then the covered ones, then the "not recorded" lines; the sort
     * below is stable, so that build order is what decides between steps that
     * carry the same weight.
     *
     * @param  array<string, mixed>  $assessment
     * @return list<array<string, mixed>>
     */
    private function steps(array $assessment, Job $job): array
    {
        $criteria = $assessment['criteria'] ?? [];

        $gaps = [];
        $matched = [];

        // Skill groups come straight out of the match result the assessment
        // reused: matched names are already covered, missing names are the gap.
        // The weights are the tiers the listing itself writes: essential skills,
        // essential tools, preferred skills, preferred tools.
        foreach ([
            'mandatory_skills' => ['kind' => 'skill', 'level' => 'Essential', 'article' => 'an', 'weight' => 0],
            'preferred_skills' => ['kind' => 'skill', 'level' => 'Preferred', 'article' => 'a', 'weight' => 1],
        ] as $key => $meta) {
            $criterion = $criteria[$key] ?? null;

            if ($criterion === null) {
                continue;
            }

            foreach ($criterion['missing'] as $name) {
                $gaps[] = $this->gapStep(
                    self::GROUP_LEARN,
                    $meta['kind'],
                    $name,
                    'This listing tags '.$name.' as '.$meta['article'].' '.$this->lower($meta['level']).' skill that your profile does not mention.',
                    $meta['weight']
                );
            }

            foreach ($criterion['matched'] as $name) {
                $matched[] = $this->matchedStep(self::GROUP_LEARN, $meta['kind'], $name, $meta['weight'] + 1);
            }
        }

        $steps = array_merge(
            $gaps,
            $this->softwareSteps($criteria['software'] ?? [], $job),
            array_filter([$this->qualificationStep($criteria['qualification'] ?? [])]),
            array_filter([$this->experienceStep($criteria['experience'] ?? [])]),
            $matched,
            $this->unrecordedSteps($criteria)
        );

        $steps = array_values(array_filter($steps));

        return $this->rankAndDedupe($steps);
    }

    /**
     * Tools are listed from the employer's own tags so a preferred tool reads as
     * preferred here too; the matched and missing names come from the assessment.
     *
     * @param  array<string, mixed>  $criterion
     * @return list<array<string, mixed>>
     */
    private function softwareSteps(array $criterion, Job $job): array
    {
        if ($criterion === [] || $criterion['status'] === JobSuitabilityService::CRITERION_NOT_STATED) {
            return [];
        }

        $preferred = [];
        $mandatory = [];

        foreach ($this->entries($job->RequiredSoftware ?? null) as $entry) {
            if ((string) ($entry['type'] ?? '') === 'preferred') {
                $preferred[] = (string) $entry['name'];
            } else {
                $mandatory[] = (string) $entry['name'];
            }
        }

        $missing = array_fill_keys($criterion['missing'], true);
        $matched = array_fill_keys($criterion['matched'], true);
        $steps = [];

        foreach ([['Essential', 'an', $mandatory], ['Preferred', 'a', $preferred]] as [$level, $article, $names]) {
            foreach ($names as $name) {
                if (isset($missing[$name])) {
                    $steps[] = $this->gapStep(
                        self::GROUP_LEARN,
                        'software',
                        $name,
                        'This listing asks for '.$name.' as '.$article.' '.$this->lower($level).' tool and it is not in your saved software.',
                        $level === 'Essential' ? 0 : 2
                    );
                } elseif (isset($matched[$name])) {
                    $steps[] = $this->matchedStep(self::GROUP_LEARN, 'software', $name, 0);
                }
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $criterion
     * @return array<string, mixed>|null
     */
    private function qualificationStep(array $criterion): ?array
    {
        if ($criterion === []) {
            return null;
        }

        if ($criterion['status'] === JobSuitabilityService::CRITERION_MET) {
            return $this->matchedStep(
                self::GROUP_IMPROVE,
                'qualification',
                $criterion['name'],
                0,
                $criterion['expected'],
                $criterion['candidate'],
                'Your '.$criterion['candidate'].' covers this listing\'s minimum of '.$criterion['expected'].'.'
            );
        }

        if ($criterion['status'] !== JobSuitabilityService::CRITERION_UNMET) {
            return null;
        }

        return $this->gapStep(
            self::GROUP_IMPROVE,
            'qualification',
            $criterion['name'],
            'This listing asks for '.($criterion['expected'] ?? 'a higher qualification').', which is above the '
                .($criterion['candidate'] ?? 'qualification on your profile').' you have recorded.',
            0,
            $criterion['expected'],
            $criterion['candidate']
        );
    }

    /**
     * @param  array<string, mixed>  $criterion
     * @return array<string, mixed>|null
     */
    private function experienceStep(array $criterion): ?array
    {
        if ($criterion === []) {
            return null;
        }

        if ($criterion['status'] === JobSuitabilityService::CRITERION_MET) {
            return $this->matchedStep(
                self::GROUP_EXPERIENCE,
                'experience',
                $criterion['name'],
                0,
                $criterion['expected'],
                $criterion['candidate'],
                'Your recorded experience reaches the '.($criterion['expected'] ?? 'level').' this listing asks for.'
            );
        }

        if ($criterion['status'] !== JobSuitabilityService::CRITERION_UNMET) {
            return null;
        }

        return $this->gapStep(
            self::GROUP_EXPERIENCE,
            'experience',
            $criterion['name'],
            'This listing asks for '.($criterion['expected'] ?? 'more experience').', above the '
                .($criterion['candidate'] ?? 'level recorded in Career Preferences').'. Gaining that experience is the way to close it.',
            0,
            $criterion['expected'],
            $criterion['candidate']
        );
    }

    /**
     * One honest step per requirement that could not be checked because the
     * seeker has recorded nothing. Wording matters here: an unrecorded value is
     * not a shortfall, so these steps say where the value belongs and do not
     * claim the listing is out of reach.
     *
     * @param  array<string, array<string, mixed>>  $criteria
     * @return list<array<string, mixed>>
     */
    private function unrecordedSteps(array $criteria): array
    {
        // Rank 3 keeps these behind the real gaps and the covered steps inside
        // their group: a value that was never recorded is work, but not a shortfall.
        $steps = [];

        $skills = $criteria['mandatory_skills']['status'] ?? null;
        $preferred = $criteria['preferred_skills']['status'] ?? null;

        if ($skills === JobSuitabilityService::CRITERION_UNKNOWN
            || $preferred === JobSuitabilityService::CRITERION_UNKNOWN) {
            $steps[] = $this->unrecordedStep(
                'profile',
                'Your skills',
                'Nothing is saved under Skills & Software or in Resume Builder, so this listing\'s required skills could not be compared with anything.',
                3,
                'skills'
            );
        }

        if (($criteria['qualification']['status'] ?? null) === JobSuitabilityService::CRITERION_UNKNOWN) {
            $expected = $criteria['qualification']['expected'] ?? null;

            $steps[] = $this->unrecordedStep(
                'qualification',
                'Your qualification',
                'This listing asks for '.($expected !== null ? $expected : 'a minimum qualification').
                    ' and no qualification is recorded, so the comparison could not be made.',
                3
            );
        }

        if (($criteria['experience']['status'] ?? null) === JobSuitabilityService::CRITERION_UNKNOWN) {
            $steps[] = $this->unrecordedStep(
                'experience',
                'Your experience level',
                'This listing states an experience band ('.$criteria['experience']['expected'].') that no saved Career Preferences level can be compared with.',
                3
            );
        }

        if (($criteria['software']['status'] ?? null) === JobSuitabilityService::CRITERION_UNKNOWN) {
            $steps[] = $this->unrecordedStep(
                'software',
                'Your software tools',
                'This listing names tools that nothing in your profile can be compared with.',
                3
            );
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<array<string, mixed>>
     */
    private function rankAndDedupe(array $steps): array
    {
        $seen = [];
        $unique = [];

        foreach ($steps as $step) {
            $key = $step['kind'].'|'.mb_strtolower($step['label']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $step;
        }

        usort($unique, static function (array $left, array $right): int {
            return ((int) $left['complete'] <=> (int) $right['complete'])
                ?: (array_search($left['group'], self::GROUP_ORDER, true) <=> array_search($right['group'], self::GROUP_ORDER, true))
                ?: ($left['rank'] <=> $right['rank']);
        });

        return $unique;
    }

    /*
    |--------------------------------------------------------------------------
    | Step builders
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function gapStep(
        string $group,
        string $kind,
        string $label,
        string $detail,
        int $rank,
        ?string $expected = null,
        ?string $current = null
    ): array {
        return $this->step($group, $kind, self::STATE_GAP, $label, $detail, $rank, $expected, $current);
    }

    /**
     * @return array<string, mixed>
     */
    private function matchedStep(
        string $group,
        string $kind,
        string $label,
        int $rank,
        ?string $expected = null,
        ?string $current = null,
        ?string $detail = null
    ): array {
        return $this->step($group, $kind, self::STATE_MATCHED, $label, $detail ?? 'Covered by your profile.', $rank, $expected, $current);
    }

    /**
     * @return array<string, mixed>
     */
    private function unrecordedStep(string $kind, string $label, string $detail, int $rank, ?string $action = null): array
    {
        return $this->step(self::GROUP_IMPROVE, $kind, self::STATE_UNRECORDED, $label, $detail, $rank, null, null, $action);
    }

    /**
     * @return array<string, mixed>
     */
    private function step(
        string $group,
        string $kind,
        string $state,
        string $label,
        string $detail,
        int $rank,
        ?string $expected = null,
        ?string $current = null,
        ?string $action = null
    ): array {
        return [
            'group' => $group,
            'group_label' => self::GROUP_META[$group]['label'],
            'kind' => $kind,
            'action' => $action ?? (self::KIND_ACTIONS[$kind] ?? 'resume'),
            'state' => $state,
            'complete' => $state === self::STATE_MATCHED,
            'label' => $label,
            'detail' => $detail,
            'title' => $this->title($kind, $state, $label),
            'hint' => $this->hint($kind, $state, $label, $expected, $current),
            'category' => $kind === 'skill' && $state === self::STATE_MATCHED
                ? 'Skill'
                : (self::CATEGORY_LABELS[$kind] ?? 'Profile'),
            'expected' => $expected,
            'current' => $current,
            'rank' => $rank,
        ];
    }

    /**
     * What to do, in a few words. Presentation only: the same wording comes out
     * of the same kind and state the step was built with, so no calculation here
     * decides anything about the roadmap itself.
     */
    private function title(string $kind, string $state, string $label): string
    {
        if ($state === self::STATE_MATCHED) {
            return $label;
        }

        if ($state === self::STATE_UNRECORDED) {
            return match ($kind) {
                'qualification' => 'Record your qualification',
                'experience' => 'Record your experience level',
                'software' => 'Add your software tools',
                default => 'Record your skills',
            };
        }

        return match ($kind) {
            'skill' => 'Learn '.$label,
            'software' => 'Add '.$label,
            'qualification' => 'Improve your qualification',
            'experience' => 'Gain relevant experience',
            default => 'Update '.$label,
        };
    }

    /**
     * One line that says why the step exists, for the compact list. The longer
     * $detail stays with the full breakdown.
     */
    private function hint(string $kind, string $state, string $label, ?string $expected, ?string $current): string
    {
        if ($state === self::STATE_MATCHED) {
            return 'Covered by your profile.';
        }

        if ($state === self::STATE_UNRECORDED) {
            return 'Not recorded yet, so it could not be checked.';
        }

        return match ($kind) {
            'skill' => 'The listing asks for '.$label.'; your profile does not mention it.',
            'software' => 'The listing asks for '.$label.'; it is not in your saved software.',
            'qualification' => 'Needs '.($expected ?? 'a higher qualification').', your profile records '.($current ?? 'a lower one').'.',
            'experience' => 'Needs '.($expected ?? 'more experience').', your profile records '.($current ?? 'less').'.',
            default => 'Not covered yet.',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    */

    /**
     * Assemble the render-ready result: group blocks, the recomputed progress
     * figure and the short lines a job card can show without opening the page.
     *
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function present(Job $job, array $steps, string $status): array
    {
        $groups = [];

        foreach (self::GROUP_ORDER as $key) {
            $inGroup = array_values(array_filter($steps, static fn (array $step): bool => $step['group'] === $key));

            if ($inGroup === []) {
                continue;
            }

            $groups[$key] = array_merge(
                self::GROUP_META[$key],
                [
                    'key' => $key,
                    'total' => count($inGroup),
                    'completed' => count(array_filter($inGroup, static fn (array $step): bool => $step['complete'])),
                    'steps' => $inGroup,
                ]
            );
        }

        $checked = array_values(array_filter(
            $steps,
            static fn (array $step): bool => $step['state'] !== self::STATE_UNRECORDED
        ));
        $completed = count(array_filter($checked, static fn (array $step): bool => $step['complete']));
        $outstanding = count($checked) - $completed;

        $actions = array_values(array_unique(array_map(
            static fn (array $step): string => $step['action'],
            array_filter($steps, static fn (array $step): bool => $step['state'] !== self::STATE_MATCHED)
        )));

        return [
            'job_id' => (int) $job->JobId,
            'status' => $status,
            'tone' => $checked === [] ? 'muted' : ($outstanding === 0 ? 'success' : ($completed === 0 ? 'danger' : 'warning')),
            'percentage' => $checked === [] ? null : (int) round($completed / count($checked) * 100),
            'steps_total' => count($checked),
            'steps_completed' => $completed,
            'steps_outstanding' => $outstanding,
            'groups' => $groups,
            'steps' => $steps,
            'next_steps' => $this->nextSteps($steps),
            'actions' => $actions,
            'note' => match (true) {
                $checked !== [] => null,
                // The listing does state requirements; the profile holds nothing
                // they can be weighed against, so the steps below are still work.
                $steps !== [] => 'Nothing in your profile can be compared with this listing yet, so there is no progress to measure. The steps below say what to record.',
                default => 'This listing tags no requirement your roadmap can track, so there is nothing to work towards yet.',
            },
        ];
    }

    /**
     * The outstanding steps a card can lead with, best first.
     *
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function nextSteps(array $steps): array
    {
        return array_slice(array_values(array_filter(
            $steps,
            static fn (array $step): bool => $step['state'] !== self::STATE_MATCHED
        )), 0, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Reading the listing
    |--------------------------------------------------------------------------
    */

    /**
     * Requirement columns are read defensively, the same way JobMatchService reads
     * them: a legacy row whose JSON did not reach the array cast simply lists no
     * entries, and the assessment's own statuses still stand.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $entry): bool => is_array($entry) && isset($entry['name']) && is_scalar($entry['name'])
                && trim((string) $entry['name']) !== ''
        ));
    }

    private function lower(string $value): string
    {
        return mb_strtolower($value);
    }
}
