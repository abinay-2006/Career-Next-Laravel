<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobSeekerCareerPreference;
use App\Models\JobSeekerEducation;
use App\Models\JobSeekerResume;
use App\Models\JobSeekerSchoolEducation;
use Illuminate\Support\Facades\Schema;

/**
 * Job Suitability Assessment.
 *
 * Reads one job seeker's recorded profile and answers a single question about a
 * listing: can this person be said to meet what the employer asked for? The
 * answer is one of four statuses plus the per-criterion evidence behind it, and
 * job cards and the job details page render the same structure.
 *
 * This service owns no matching maths of its own. Skill, software and the
 * weighted Smart Job Match coverage come from JobMatchService; the experience,
 * pay, employment type and work arrangement comparisons come from
 * CareerPreferenceMatchService. Neither is modified or re-derived here, and the
 * 2 (mandatory) / 1 (preferred) weights, percentages and labels are untouched.
 *
 * Status rules
 * ------------
 * suitable              every essential requirement that could be checked is met
 *                       and nothing contradicts the listing.
 * partially_suitable    no essential requirement is confirmed unmet, but a
 *                       non-essential one is: a preferred skill, a tool the
 *                       employer marked preferred, or a salary, employment type
 *                       or work arrangement the seeker's Career Preferences rule
 *                       out.
 * not_suitable          an essential requirement is CONFIRMED unmet, meaning the
 *                       value the seeker recorded fails a value the employer
 *                       actually stated: a qualification below the stated minimum,
 *                       a tagged mandatory skill absent from the profile, an
 *                       experience band whose ceiling is below the listing's
 *                       minimum, or a tool tagged mandatory (or left at the
 *                       form's mandatory default) that is recorded nowhere.
 * insufficient_information
 *                       no essential requirement could be checked, either because
 *                       the listing states none or because the seeker has nothing
 *                       recorded to compare.
 *
 * Guardrails that follow from those rules:
 *  - Missing information is never a mismatch. A blank side reads as "unknown",
 *    which can neither reject a candidate nor clear one; it only blocks a
 *    confident answer.
 *  - A missing preferred skill, or an unsaved preference, caps the result at
 *    partially_suitable.
 *  - No percentage threshold decides the status. This reads which requirements
 *    matched or did not; it never reduces suitability to a cut-off.
 *  - A listing that names no minimum qualification, experience level or tagged
 *    skills is not a rejection - those criteria report "not required".
 *  - Pay is compared only through the monthly rupee normalisation already in
 *    CareerPreferenceMatchService. Other currencies, unreadable figures and
 *    listings that state only a floor stay unknown, and a preference conflict is
 *    never reported as an eligibility failure.
 *
 * Read-only and private: the caller supplies one seeker's context, built once and
 * reused for every listing in a search result, and nothing is persisted.
 *
 * Overall percentage
 * ------------------
 * Alongside the status, assess() reports one percentage of its own. It counts
 * only the essential requirements the listing states - the minimum
 * qualification, the tagged mandatory skills, a non-zero experience band, and
 * software the employer marked mandatory rather than preferred - and weights
 * each of them fully met, half (partly covered, or not recorded), or nothing
 * (confirmed short). 100% therefore means every applicable essential condition
 * is confirmed satisfied: one failed requirement holds it below, and so does
 * one gap that could not be checked. Preferred skills, salary, employment type
 * and work arrangement are deliberately outside it.
 *
 * It is not the Smart Job Match score. That 2 (mandatory) / 1 (preferred)
 * weighted percentage stays exactly as JobMatchService computes it and the views
 * show both, the skill one captioned "Skill Match", so a listing can read
 * "100% Skill Match" next to "75% Suitable" when the experience bar is short.
 */
class JobSuitabilityService
{
    public const STATUS_SUITABLE = 'suitable';

    public const STATUS_PARTIALLY_SUITABLE = 'partially_suitable';

    public const STATUS_NOT_SUITABLE = 'not_suitable';

    public const STATUS_INSUFFICIENT = 'insufficient_information';

    /** The seeker's recorded value satisfies what the listing states. */
    public const CRITERION_MET = 'met';

    /** Partly satisfied: some of what the listing asks for is covered. */
    public const CRITERION_PARTIAL = 'partial';

    /** The seeker's recorded value is known to fall short of the listing. */
    public const CRITERION_UNMET = 'unmet';

    /** The seeker has not recorded enough for this requirement to be checked. */
    public const CRITERION_UNKNOWN = 'unknown';

    /** The listing states nothing here, so there is no bar to clear. */
    public const CRITERION_NOT_STATED = 'not_stated';

    private const TONES = [
        self::STATUS_SUITABLE => 'success',
        self::STATUS_PARTIALLY_SUITABLE => 'warning',
        self::STATUS_NOT_SUITABLE => 'danger',
        self::STATUS_INSUFFICIENT => 'muted',
    ];

    private const ICONS = [
        self::STATUS_SUITABLE => 'fa-circle-check',
        self::STATUS_PARTIALLY_SUITABLE => 'fa-circle-exclamation',
        self::STATUS_NOT_SUITABLE => 'fa-circle-xmark',
        self::STATUS_INSUFFICIENT => 'fa-circle-question',
    ];

    private const LABELS = [
        self::STATUS_SUITABLE => 'Suitable',
        self::STATUS_PARTIALLY_SUITABLE => 'Partially Suitable',
        self::STATUS_NOT_SUITABLE => 'Not Suitable',
        self::STATUS_INSUFFICIENT => 'Insufficient Information',
    ];

    private const CRITERION_LABELS = [
        self::CRITERION_MET => 'Met',
        self::CRITERION_PARTIAL => 'Partly met',
        self::CRITERION_UNMET => 'Not met',
        self::CRITERION_UNKNOWN => 'Not recorded',
        self::CRITERION_NOT_STATED => 'Not required',
    ];

    /**
     * What one essential requirement contributes to the overall suitability
     * score. A confirmed shortfall earns nothing, a confirmed match earns fully,
     * and the two middle cases earn half: a partial cover is not a complete one,
     * and an unrecorded value is not evidence of anything, so neither can help
     * the score reach 100%.
     */
    private const SCORE_WEIGHTS = [
        self::CRITERION_MET => 1.0,
        self::CRITERION_PARTIAL => 0.5,
        self::CRITERION_UNKNOWN => 0.5,
        self::CRITERION_UNMET => 0.0,
    ];

    /**
     * The qualification names this application stores, ranked by level so a
     * stated minimum can be compared with a stated highest qualification.
     *
     * Keys are the compact spellings produced by spellings(): lowercase with
     * dots, spaces and slashes removed, so 'B.C.A', 'BCA' and 'b c a' all reach
     * 'bca', and the employer form's 'B.E / B.Tech' reaches 'be' and 'btech'.
     * Every ranked name is a value one of this project's own forms can save; the
     * generic words (graduate, master, 12th) only let a free-text profile entry
     * land on the same ladder instead of failing to read.
     */
    private const QUALIFICATION_RANKS = [
        // 1 - school leaving
        'ssc' => 1,
        '10th' => 1,
        'class10' => 1,
        'highschool' => 1,
        'secondaryschool' => 1,
        'matric' => 1,
        'matriculation' => 1,
        // 2 - higher secondary
        'hsc' => 2,
        '12th' => 2,
        'class12' => 2,
        'highersecondary' => 2,
        'intermediate' => 2,
        // 3 - diploma
        'diploma' => 3,
        // 4 - bachelor's degree
        'bca' => 4,
        'bscit' => 4,
        'bsccs' => 4,
        'bsc' => 4,
        'be' => 4,
        'btech' => 4,
        'bcom' => 4,
        'ba' => 4,
        'bba' => 4,
        'bpharm' => 4,
        'mbbs' => 4,
        'llb' => 4,
        'bachelor' => 4,
        'bachelors' => 4,
        'graduate' => 4,
        'undergraduate' => 4,
        // 5 - master's degree
        'mca' => 5,
        'mscit' => 5,
        'msccs' => 5,
        'msc' => 5,
        'me' => 5,
        'mtech' => 5,
        'mcom' => 5,
        'ma' => 5,
        'mba' => 5,
        'mpharm' => 5,
        'llm' => 5,
        'master' => 5,
        'masters' => 5,
        'postgraduate' => 5,
        // 6 - doctorate
        'phd' => 6,
        'doctorate' => 6,
    ];

    private const RANK_LABELS = [
        1 => 'school leaving',
        2 => 'higher secondary',
        3 => 'a diploma',
        4 => "a bachelor's degree",
        5 => "a master's degree",
        6 => 'a doctorate',
    ];

    public function __construct(
        private readonly JobMatchService $jobMatch,
        private readonly CareerPreferenceMatchService $preferenceMatch,
    ) {
    }

    /**
     * Everything about one seeker that a suitability assessment can use, read
     * once so a search result of fifty listings does not re-query the profile,
     * skills, resume, education and preferences fifty times.
     *
     * Pass the $lists already built for Smart Job Match and the skill and tool
     * names are taken from them rather than read again.
     *
     * @param  array{skills: list<string>, software: list<string>}|null  $lists
     * @return array{job_seeker_id: int, skills: list<string>, software: list<string>, qualifications: list<string>, experience_level: string|null, preferences: JobSeekerCareerPreference|null}
     */
    public function candidateContext(JobSeeker|int $seeker, ?array $lists = null): array
    {
        $jobSeekerId = $seeker instanceof JobSeeker ? (int) $seeker->JobSeekId : $seeker;

        // Smart Job Match already unions the profile's Skills & Software with the
        // Resume Builder skills and folds every skill name into the tool list.
        // Suitability reads those lists instead of rebuilding them.
        $lists ??= $this->jobMatch->candidateLists($jobSeekerId);

        $preferences = $this->preferences($seeker, $jobSeekerId);
        $experienceLevel = $this->text($preferences?->experience_level ?? null);

        return [
            'job_seeker_id' => $jobSeekerId,
            'skills' => $lists['skills'],
            'software' => $lists['software'],
            'qualifications' => $this->qualifications($seeker, $jobSeekerId),
            // 'any' means the seeker did not narrow their level down, exactly as
            // CareerPreferenceMatchService reads it.
            'experience_level' => $experienceLevel === '' || $experienceLevel === 'any' ? null : $experienceLevel,
            'preferences' => $preferences,
        ];
    }

    /**
     * Assess one listing against a context from candidateContext().
     *
     * Pass the $match already calculated for this job to reuse it; a fresh
     * calculation happens only when none is given.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $match  a JobMatchService result for this job
     * @return array{status: string, label: string, tone: string, icon: string, percentage: int|null, essential_met: int, essential_total: int, explanation: string, criteria: array<string, array<string, mixed>>, matched_requirements: list<string>, missing_requirements: list<string>, unknown_requirements: list<string>, suggestions: list<string>}
     */
    public function assess(array $context, Job $job, ?array $match = null): array
    {
        $match ??= $this->jobMatch->calculate(
            $context['skills'] ?? [],
            $job,
            $context['software'] ?? []
        );

        $fit = $this->preferenceMatch->classificationFit($context['preferences'] ?? null, $job);
        $preferences = $context['preferences'] ?? null;

        $criteria = [
            'qualification' => $this->qualificationCriterion($context, $job),
            'mandatory_skills' => $this->skillCriterion($match, 'mandatory'),
            'preferred_skills' => $this->skillCriterion($match, 'preferred'),
            'experience' => $this->experienceCriterion($context, $job),
            'software' => $this->softwareCriterion($match, $job),
            'salary' => $this->salaryCriterion($preferences, $job, $fit),
            'employment_type' => $this->classificationCriterion(
                'Employment type',
                $this->tags($preferences?->employment_types ?? null),
                $this->text($job->EmploymentType ?? null),
                $fit,
                'employment type'
            ),
            'work_arrangement' => $this->classificationCriterion(
                'Work arrangement',
                $this->tags($preferences?->work_arrangements ?? null),
                $this->text($job->WorkArrangement ?? null),
                $fit,
                'work arrangement'
            ),
        ];

        $status = $this->decide($criteria);
        $score = $this->overallScore($criteria);

        return [
            'status' => $status,
            'label' => self::LABELS[$status],
            'tone' => self::TONES[$status],
            'icon' => self::ICONS[$status],
            'percentage' => $status === self::STATUS_INSUFFICIENT ? null : $score['percentage'],
            'essential_met' => $score['met'],
            'essential_total' => $score['total'],
            'explanation' => $this->explanation($status, $criteria),
            'criteria' => $criteria,
            'matched_requirements' => $this->summarise($criteria, 'matched'),
            'missing_requirements' => $this->summarise($criteria, 'missing'),
            'unknown_requirements' => $this->summarise($criteria, 'unknown'),
            'suggestions' => $this->suggestions($criteria),
        ];
    }

    /**
     * Convenience for a single listing. A search result should build the context
     * once with candidateContext() and then call assess() per job.
     *
     * @return array<string, mixed>
     */
    public function assessFor(JobSeeker|int $seeker, Job $job): array
    {
        return $this->assess($this->candidateContext($seeker), $job);
    }

    /*
    |--------------------------------------------------------------------------
    | Criteria
    |--------------------------------------------------------------------------
    */

    /**
     * The seeker's highest recorded qualification against the listing's stated
     * minimum. Only a level that is actually known on both sides can fail.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function qualificationCriterion(array $context, Job $job): array
    {
        $required = $this->text($job->MinQualification ?? null);
        $requiredRank = $this->rank($required, 'min');
        $held = $this->highest($context['qualifications'] ?? []);

        if ($requiredRank === null) {
            // 'Any', a blank, or wording this application does not rank. Not
            // stating a bar is not the same as an unreachable one.
            return $this->criterion(
                'Qualification',
                self::CRITERION_NOT_STATED,
                false,
                $required === '' ? null : $required,
                $held['display'] ?? null,
                $required === ''
                    ? 'This listing states no minimum qualification.'
                    : 'This listing asks for "'.$required.'", which is not a qualification level this assessment can rank, so no comparison was made.'
            );
        }

        if ($held === null) {
            return $this->criterion(
                'Qualification',
                self::CRITERION_UNKNOWN,
                true,
                $required,
                null,
                'This listing needs '.$this->rankLabel($requiredRank).' ('.$required.') and your highest qualification has not been recorded, so it could not be checked.'
            );
        }

        if ($held['rank'] >= $requiredRank) {
            return $this->criterion(
                'Qualification',
                self::CRITERION_MET,
                true,
                $required,
                $held['display'],
                $held['rank'] === $requiredRank
                    ? 'Your '.$held['display'].' meets this listing\'s minimum of '.$required.'.'
                    : 'Your '.$held['display'].' is above this listing\'s minimum of '.$required.'.',
                [$required]
            );
        }

        return $this->criterion(
            'Qualification',
            self::CRITERION_UNMET,
            true,
            $required,
            $held['display'],
            'This listing requires '.$this->rankLabel($requiredRank).' ('.$required.'), which is above the '.$held['display'].' on your profile.',
            [],
            [$required]
        );
    }

    /**
     * Mandatory and preferred skill coverage, read straight out of the Smart Job
     * Match result so the weights and the alias table are used exactly once.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function skillCriterion(array $match, string $group): array
    {
        $mandatory = $group === 'mandatory';
        $name = $mandatory ? 'Mandatory skills' : 'Preferred skills';
        $matched = $match['matched_'.$group] ?? [];
        $missing = $match['missing_'.$group] ?? [];

        if (($match['status'] ?? '') === JobMatchService::STATUS_NEEDS_RESUME) {
            return $this->criterion(
                $name,
                self::CRITERION_UNKNOWN,
                $mandatory,
                null,
                null,
                'No skills are saved on your profile or in Resume Builder, so this listing\'s '.$group.' skills could not be checked.'
            );
        }

        if (empty($match['structured'])) {
            // The listing carries no requirement tags, only prose. Calling a
            // required skill "missing" from a description would guess at what the
            // employer meant, so nothing is claimed either way.
            return $this->criterion(
                $name,
                self::CRITERION_NOT_STATED,
                $mandatory,
                null,
                null,
                'This listing does not tag any '.$group.' skills, so there is nothing to compare your skills against.'
            );
        }

        if ($matched === [] && $missing === []) {
            return $this->criterion(
                $name,
                self::CRITERION_NOT_STATED,
                $mandatory,
                null,
                null,
                'This listing does not tag any '.$group.' skills.'
            );
        }

        if ($missing === []) {
            return $this->criterion(
                $name,
                self::CRITERION_MET,
                $mandatory,
                null,
                null,
                'Your profile covers every '.$group.' skill this listing tags: '.implode(', ', $matched).'.',
                $matched
            );
        }

        // A tagged mandatory skill the profile does not contain is a confirmed
        // shortfall against a stated requirement; a preferred one never is.
        return $this->criterion(
            $name,
            $mandatory ? self::CRITERION_UNMET : self::CRITERION_PARTIAL,
            $mandatory,
            null,
            null,
            ($mandatory ? 'Required skills not in your profile: ' : 'Preferred skills not in your profile yet: ')
                .implode(', ', $missing).'.'
                .($matched === [] ? '' : ' Covered: '.implode(', ', $matched).'.'),
            $matched,
            $missing
        );
    }

    /**
     * The experience band saved in Career Preferences against the band the
     * listing asks for. Both sides are read through Job::EXPERIENCE_YEARS, the
     * same map the matcher uses, and by the same rule it documents: a listing
     * states a band but only its minimum can rule someone out, so someone at
     * 3-5 years is never disqualified from a 1-2 year role.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function experienceCriterion(array $context, Job $job): array
    {
        $level = $this->text($job->ExperienceLevel ?? null);
        $jobYears = Job::experienceYears($level === '' ? null : $level);

        if ($jobYears === null) {
            return $this->criterion(
                'Experience',
                self::CRITERION_NOT_STATED,
                false,
                null,
                $this->experienceLabel($context['experience_level'] ?? null),
                'This listing does not state an experience level.'
            );
        }

        $expected = (string) Job::experienceLevelLabel($level);
        $saved = $context['experience_level'] ?? null;
        $held = $this->experienceLabel($saved);

        // A role that starts at zero years cannot rule anyone out, so it is a
        // preference rather than an eligibility bar.
        $essential = $jobYears[0] > 0;

        if (!is_string($saved) || $saved === 'any' || Job::experienceYears($saved) === null) {
            return $this->criterion(
                'Experience',
                self::CRITERION_UNKNOWN,
                $essential,
                $expected,
                $held,
                'This listing asks for '.$expected.', but no experience level is saved in your Career Preferences, so it could not be checked.'
            );
        }

        $seekerYears = Job::experienceYears($saved);

        if ($seekerYears[1] === null || $seekerYears[1] >= $jobYears[0]) {
            return $this->criterion(
                'Experience',
                self::CRITERION_MET,
                $essential,
                $expected,
                $held,
                $essential
                    ? 'Your experience ('.$held.') reaches the '.$expected.' this listing asks for.'
                    : 'This listing asks for '.$expected.', which your experience ('.$held.') fits.',
                [$expected]
            );
        }

        return $this->criterion(
            'Experience',
            self::CRITERION_UNMET,
            $essential,
            $expected,
            $held,
            'This listing asks for '.$expected.', but your recorded experience is '.($held ?? 'incomplete').', which is below that minimum.',
            [],
            [$expected]
        );
    }

    /**
     * Tool coverage, reported apart from the skill percentage exactly as Smart
     * Job Match reports it: a hit means the seeker named the tool, nothing more.
     *
     * A tool the employer marked preferred stays a preference, so it can shorten
     * the list of gaps but can never reject; a tool left at the app's default or
     * tagged mandatory counts as an essential requirement, and the overall score
     * reflects that.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function softwareCriterion(array $match, Job $job): array
    {
        $essential = $this->requiresSoftware($job);
        $software = $match['software'] ?? null;

        if (!is_array($software)) {
            return $this->criterion(
                'Software',
                self::CRITERION_NOT_STATED,
                false,
                null,
                null,
                'This listing names no software to compare against your saved tools.'
            );
        }

        $matched = $software['matched'];
        $missing = $software['missing'];

        if ($missing === []) {
            return $this->criterion(
                'Software',
                self::CRITERION_MET,
                $essential,
                implode(', ', $matched),
                null,
                'You have named every tool this listing mentions.',
                $matched
            );
        }

        return $this->criterion(
            'Software',
            $matched === [] ? self::CRITERION_UNMET : self::CRITERION_PARTIAL,
            $essential,
            implode(', ', array_merge($matched, $missing)),
            null,
            ($matched === []
                ? 'None of the '.$software['total'].' tools this listing names appear in your saved software: '.implode(', ', $missing).'.'
                : 'Tools this listing names that you have not recorded: '.implode(', ', $missing).'.')
                .' Tool coverage is reported separately and never added to the skill percentage.',
            $matched,
            $missing
        );
    }

    /**
     * Does this listing mark at least one required tool as mandatory?
     *
     * JobMatchService keeps each entry's type to itself, so this reads the same
     * column with the same rule the rest of the application uses:
     * EmployerJobController::normalizeRequirements() defaults an entry to
     * mandatory and the employer form exposes the type column for software too,
     * so only an entry explicitly tagged "preferred" is a nice-to-have.
     */
    private function requiresSoftware(Job $job): bool
    {
        $entries = $job->RequiredSoftware ?? null;

        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && (string) ($entry['type'] ?? '') !== 'preferred') {
                return true;
            }
        }

        return false;
    }

    /**
     * Pay. Conversion, salary period, the "a lone floor says nothing" rule and
     * the currencies this project cannot convert all live in
     * CareerPreferenceMatchService; this only reports which side of that check
     * the listing landed on, and never reads an unusable figure as a failure.
     *
     * @param  array{matched: list<string>, conflicts: list<string>}  $fit
     * @return array<string, mixed>
     */
    private function salaryCriterion(?JobSeekerCareerPreference $preferences, Job $job, array $fit): array
    {
        $offered = $this->offeredSalary($job);
        $expected = $this->expectedSalary($preferences);

        if ($this->hasSalaryLabel($fit['conflicts'])) {
            return $this->criterion(
                'Salary',
                self::CRITERION_UNMET,
                false,
                $offered,
                $expected,
                'This listing pays '.$offered.', which does not overlap your saved expectation of '.$expected.'.',
                [],
                [$offered]
            );
        }

        if ($this->hasSalaryLabel($fit['matched'])) {
            return $this->criterion(
                'Salary',
                self::CRITERION_MET,
                false,
                $offered,
                $expected,
                'This listing pays '.$offered.', which overlaps your saved expectation of '.$expected.'.',
                [$offered]
            );
        }

        // Nothing landed on either side of the comparison: the listing states no
        // usable pay, the seeker saved none, the two use currencies this project
        // cannot convert, or a figure is free text.
        return $this->criterion(
            'Salary',
            $offered === null ? self::CRITERION_NOT_STATED : self::CRITERION_UNKNOWN,
            false,
            $offered,
            $expected,
            $offered === null
                ? 'This listing states no salary to compare.'
                : ($expected === null
                    ? 'This listing pays '.$offered.'. You have not saved a salary expectation, so it was left unchecked.'
                    : 'This listing pays '.$offered.' and you expect '.$expected.', but the two cannot be compared, so this was left unchecked.')
        );
    }

    /**
     * Employment type and work arrangement, from the same classificationFit()
     * result. Both are facts about the job rather than bars the seeker must
     * clear, so neither is essential: a conflict only says this job is not the
     * kind of work the seeker asked for.
     *
     * @param  list<string>  $saved  the seeker's saved tags for this category
     * @param  array{matched: list<string>, conflicts: list<string>}  $fit
     * @return array<string, mixed>
     */
    private function classificationCriterion(string $name, array $saved, string $jobValue, array $fit, string $phrase): array
    {
        if ($jobValue === '') {
            return $this->criterion($name, self::CRITERION_NOT_STATED, false, null, null, 'This listing does not state a '.$phrase.'.');
        }

        if (in_array($jobValue, $fit['conflicts'], true)) {
            return $this->criterion(
                $name,
                self::CRITERION_UNMET,
                false,
                $jobValue,
                $saved === [] ? null : implode(', ', $saved),
                'This listing is '.$jobValue.', which is not among the '.$phrase.' you saved: '.implode(', ', $saved).'.',
                [],
                [$jobValue]
            );
        }

        if (in_array($jobValue, $fit['matched'], true)) {
            return $this->criterion(
                $name,
                self::CRITERION_MET,
                false,
                $jobValue,
                $saved === [] ? null : implode(', ', $saved),
                'This listing is '.$jobValue.', one of the '.$phrase.' you asked for.',
                [$jobValue]
            );
        }

        return $this->criterion(
            $name,
            self::CRITERION_UNKNOWN,
            false,
            $jobValue,
            null,
            'This listing is '.$jobValue.'. You have not saved a '.$phrase.' preference, so it was left unchecked.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Overall status
    |--------------------------------------------------------------------------
    */

    /**
     * The overall suitability score, out of the essential requirements only.
     *
     * Preferred skills, salary, employment type and work arrangement never enter
     * this: they can change the wording under the number but not the number, so
     * a candidate who fits the role is not pushed below 100% over a pay
     * preference. The Smart Job Match percentage is a separate, unchanged number
     * and is not blended in here.
     *
     * Only requirements the listing actually states are counted, so a listing
     * that names no experience band is not scored as if the seeker failed one.
     * A criterion the seeker cannot be checked against counts as half earned:
     * it is not a failure, but it is not proof either, so it caps the score
     * below 100% rather than reporting a full mark nobody has confirmed.
     *
     * @param  array<string, array<string, mixed>>  $criteria
     * @return array{percentage: int|null, met: int, total: int}
     */
    private function overallScore(array $criteria): array
    {
        $total = 0;
        $met = 0;
        $earned = 0.0;

        foreach ($criteria as $criterion) {
            if (!$criterion['essential'] || $criterion['status'] === self::CRITERION_NOT_STATED) {
                continue;
            }

            $total++;
            $earned += self::SCORE_WEIGHTS[$criterion['status']];

            if ($criterion['status'] === self::CRITERION_MET) {
                $met++;
            }
        }

        if ($total === 0) {
            return ['percentage' => null, 'met' => 0, 'total' => 0];
        }

        return [
            'percentage' => (int) round($earned / $total * 100),
            'met' => $met,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $criteria
     */
    private function decide(array $criteria): string
    {
        $essentialChecked = false;
        $weak = false;

        foreach ($criteria as $criterion) {
            $status = $criterion['status'];

            if ($criterion['essential']) {
                if ($status === self::CRITERION_UNMET) {
                    return self::STATUS_NOT_SUITABLE;
                }

                if (in_array($status, [self::CRITERION_MET, self::CRITERION_PARTIAL], true)) {
                    $essentialChecked = true;
                }

                continue;
            }

            if (in_array($status, [self::CRITERION_UNMET, self::CRITERION_PARTIAL], true)) {
                $weak = true;
            }
        }

        // No essential requirement could be checked: the listing stated none, or
        // the seeker has recorded nothing to compare. Either way a confident
        // answer would have to be invented.
        if (!$essentialChecked) {
            return self::STATUS_INSUFFICIENT;
        }

        return $weak ? self::STATUS_PARTIALLY_SUITABLE : self::STATUS_SUITABLE;
    }

    /**
     * One line that says why. The card shows this; the details page shows the
     * per-criterion explanations under it.
     *
     * @param  array<string, array<string, mixed>>  $criteria
     */
    private function explanation(string $status, array $criteria): string
    {
        if ($status === self::STATUS_NOT_SUITABLE) {
            $failed = array_values(array_filter(
                $criteria,
                static fn (array $c): bool => $c['essential'] && $c['status'] === self::CRITERION_UNMET
            ));

            $first = $failed[0]['explanation'] ?? 'A requirement of this listing is not met.';

            return count($failed) > 1
                ? $first.' Also short: '.implode(', ', array_map(static fn (array $c): string => $c['name'], array_slice($failed, 1))).'.'
                : $first;
        }

        if ($status === self::STATUS_PARTIALLY_SUITABLE) {
            $gaps = array_values(array_filter(
                $criteria,
                static fn (array $c): bool => in_array($c['status'], [self::CRITERION_UNMET, self::CRITERION_PARTIAL], true)
            ));

            return 'Your profile meets the essential requirements, but '.count($gaps).' item'
                .(count($gaps) === 1 ? '' : 's').' need attention: '
                .implode(', ', array_map(static fn (array $c): string => $c['name'], $gaps)).'.';
        }

        if ($status === self::STATUS_INSUFFICIENT) {
            $unchecked = array_values(array_filter(
                $criteria,
                static fn (array $c): bool => $c['essential'] && in_array($c['status'], [self::CRITERION_UNKNOWN, self::CRITERION_NOT_STATED], true)
            ));

            return $unchecked === []
                ? 'This listing states no requirements that can be checked against a profile.'
                : 'Not enough information to assess suitability: '
                    .implode(', ', array_map(static fn (array $c): string => $c['name'], $unchecked))
                    .' could not be compared with your profile.';
        }

        return 'Your profile meets every requirement this listing states.';
    }

    /**
     * Short, readable lines for the card and the details page.
     *
     * @param  array<string, array<string, mixed>>  $criteria
     * @return list<string>
     */
    private function summarise(array $criteria, string $side): array
    {
        $lines = [];

        foreach ($criteria as $criterion) {
            $status = $criterion['status'];

            if ($side === 'matched' && in_array($status, [self::CRITERION_MET, self::CRITERION_PARTIAL], true)) {
                $items = $criterion['matched'];
                $lines[] = $criterion['name'].($items === [] ? '' : ': '.implode(', ', $items));
            }

            if ($side === 'missing') {
                if (in_array($status, [self::CRITERION_UNMET, self::CRITERION_PARTIAL], true)) {
                    $lines[] = $criterion['name'].($criterion['missing'] === [] ? '' : ': '.implode(', ', $criterion['missing']));
                }

                continue;
            }

            if ($side === 'unknown' && in_array($status, [self::CRITERION_UNKNOWN, self::CRITERION_NOT_STATED], true)) {
                $lines[] = $criterion['name'];
            }
        }

        return $lines;
    }

    /**
     * Concrete next steps, in criterion order. Nothing here claims a profile
     * edit can fix a requirement the seeker genuinely does not meet.
     *
     * @param  array<string, array<string, mixed>>  $criteria
     * @return list<string>
     */
    private function suggestions(array $criteria): array
    {
        $suggestions = [];

        foreach ($criteria as $key => $criterion) {
            $suggestion = $this->suggestion($key, $criterion);

            if ($suggestion !== null && !in_array($suggestion, $suggestions, true)) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    private function suggestion(string $key, array $criterion): ?string
    {
        $status = $criterion['status'];
        $missing = $criterion['missing'];

        if (in_array($status, [self::CRITERION_MET, self::CRITERION_NOT_STATED], true)) {
            return null;
        }

        // Only the three item-listing criteria can name what to add.
        if ($missing !== [] && in_array($key, ['mandatory_skills', 'preferred_skills', 'software'], true)) {
            $items = implode(', ', array_slice($missing, 0, 3));

            return match ($key) {
                'mandatory_skills' => 'Add the required skills your profile is missing: '.$items.'.',
                'preferred_skills' => 'Adding '.$items.' would strengthen this application.',
                'software' => 'Record '.$items.' under Skills & Software if you use them.',
                default => null,
            };
        }

        if ($status === self::CRITERION_UNKNOWN) {
            return match ($key) {
                'qualification' => 'Add your highest qualification on your profile or in Resume Builder so listings can be checked against it.',
                'mandatory_skills', 'preferred_skills' => 'List your skills under Skills & Software on your profile, or in Resume Builder.',
                'experience' => 'Save your experience level in Career Preferences so listings can be checked against it.',
                'salary' => 'Save an expected salary in Career Preferences to see whether this listing pays what you need.',
                'employment_type' => 'Save the employment types you want in Career Preferences.',
                'work_arrangement' => 'Save the work arrangements you want in Career Preferences.',
                default => null,
            };
        }

        return match ($key) {
            'qualification' => 'This listing needs a higher qualification than the one on your profile.',
            'experience' => 'This listing needs more experience than your profile records.',
            'salary' => 'This listing pays outside the salary range you saved in Career Preferences.',
            'employment_type', 'work_arrangement' => 'This job\'s '.$criterion['name'].' is not what you asked for in Career Preferences.',
            default => null,
        };
    }

    /**
     * One row of evidence. Every criterion is built here so the details page can
     * render all of them from the same shape.
     *
     * @param  list<string>  $matched  what the seeker's profile covers here
     * @param  list<string>  $missing  what the listing asked for and was not met
     * @return array<string, mixed>
     */
    private function criterion(
        string $name,
        string $status,
        bool $essential,
        ?string $expected,
        ?string $candidate,
        string $explanation,
        array $matched = [],
        array $missing = []
    ): array {
        return [
            'name' => $name,
            'status' => $status,
            'label' => self::CRITERION_LABELS[$status],
            'essential' => $essential,
            'expected' => $expected,
            'candidate' => $candidate,
            'explanation' => $explanation,
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate facts
    |--------------------------------------------------------------------------
    */

    private function preferences(JobSeeker|int $seeker, int $jobSeekerId): ?JobSeekerCareerPreference
    {
        // Older databases have no preferences table at all; that is "no saved
        // preferences", not an error, and every preference criterion reads it.
        if (!Schema::hasTable('jobseeker_career_preferences')) {
            return null;
        }

        if ($seeker instanceof JobSeeker && $seeker->relationLoaded('careerPreferences')) {
            return $seeker->careerPreferences;
        }

        return JobSeekerCareerPreference::query()->where('JobSeekId', $jobSeekerId)->first();
    }

    /**
     * Every qualification the seeker has recorded, in the order it was saved: the
     * profile field, higher and school education records, and the Resume Builder
     * header and education list. Suitability reads the highest level the ranking
     * knows; a name it cannot rank is simply not evidence.
     *
     * @return list<string>
     */
    private function qualifications(JobSeeker|int $seeker, int $jobSeekerId): array
    {
        $names = [];

        if ($seeker instanceof JobSeeker) {
            $names[] = $this->text($seeker->Qualification ?? null);
        } elseif (Schema::hasTable('jobseeker_reg') && Schema::hasColumn('jobseeker_reg', 'Qualification')) {
            $names[] = $this->text((string) JobSeeker::query()->where('JobSeekId', $jobSeekerId)->value('Qualification'));
        }

        if (Schema::hasTable('jobseeker_education')) {
            foreach (JobSeekerEducation::query()->where('JobSeekId', $jobSeekerId)->pluck('Degree') as $degree) {
                $names[] = $this->text((string) $degree);
            }
        }

        if (Schema::hasTable('jobseeker_school_education')) {
            foreach (JobSeekerSchoolEducation::query()->where('JobSeekId', $jobSeekerId)->pluck('Qualification') as $qualification) {
                $names[] = $this->text((string) $qualification);
            }
        }

        $content = $this->resumeContent($jobSeekerId);

        $names[] = $this->text($content['qualification'] ?? '');

        foreach ($content['education'] ?? [] as $entry) {
            if (is_array($entry)) {
                $names[] = $this->text((string) ($entry['qualification'] ?? ''));
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * The Resume Builder content of the seeker's newest saved resume.
     *
     * Only the content column is fetched: the same row is already read in full by
     * Smart Job Match when it collects the resume's skills, and a search must not
     * pay for that row twice.
     *
     * @return array<string, mixed>
     */
    private function resumeContent(int $jobSeekerId): array
    {
        if (!Schema::hasTable('jobseeker_resumes')) {
            return [];
        }

        $resume = JobSeekerResume::query()
            ->select('content')
            ->where('JobSeekId', $jobSeekerId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return is_array($resume?->content) ? $resume->content : [];
    }

    /**
     * The strongest qualification in the list.
     *
     * @param  list<string>  $qualifications
     * @return array{display: string, rank: int}|null
     */
    private function highest(array $qualifications): ?array
    {
        $best = null;

        foreach ($qualifications as $name) {
            $rank = $this->rank($name, 'max');

            if ($rank !== null && ($best === null || $rank > $best['rank'])) {
                $best = ['display' => $name, 'rank' => $rank];
            }
        }

        return $best;
    }

    /**
     * Rank a qualification string. A listing may name alternatives ("B.E /
     * B.Tech", "Graduate or Diploma"), so the bar it sets is the LOWEST rank it
     * names, while the level a seeker holds is the HIGHEST rank their entries
     * reach.
     *
     * @param  string  $mode  'min' for a requirement, 'max' for a held qualification
     */
    private function rank(string $value, string $mode): ?int
    {
        $rank = null;

        foreach ($this->spellings($value) as $spelling) {
            $found = self::QUALIFICATION_RANKS[$spelling] ?? null;

            if ($found === null) {
                continue;
            }

            if ($rank === null
                || ($mode === 'min' && $found < $rank)
                || ($mode === 'max' && $found > $rank)) {
                $rank = $found;
            }
        }

        return $rank;
    }

    /**
     * Compact spellings of one qualification string, split on the separators the
     * forms actually use, then widened to the individual words so a phrase like
     * "Bachelor of Technology" still lands on the rung its leading word names.
     *
     * @return list<string>
     */
    private function spellings(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $groups = preg_split('#\s*(?:/|,|;|&|\bor\b)\s*#i', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $spellings = [];

        foreach ($groups as $group) {
            foreach (array_merge([$group], preg_split('/\s+/', $group, -1, PREG_SPLIT_NO_EMPTY) ?: []) as $part) {
                $spelling = preg_replace('/[^a-z0-9+]/', '', mb_strtolower(trim($part))) ?? '';

                if ($spelling !== '') {
                    $spellings[$spelling] = $spelling;
                }
            }
        }

        return array_values($spellings);
    }

    private function rankLabel(int $rank): string
    {
        return self::RANK_LABELS[$rank] ?? 'that level';
    }

    private function experienceLabel(?string $level): ?string
    {
        if (!is_string($level) || $level === '' || $level === 'any') {
            return null;
        }

        $label = Job::experienceLevelLabel($level);

        return is_string($label) ? $label : null;
    }

    private function offeredSalary(Job $job): ?string
    {
        $from = $this->rupees($job->SalaryFrom ?? null);
        $to = $this->rupees($job->SalaryTo ?? null);

        if ($from === '' && $to === '') {
            return null;
        }

        if ($from !== '' && $to !== '') {
            return $from.' – '.$to;
        }

        return $from !== '' ? $from : 'up to '.$to;
    }

    /**
     * The listing's pay, grouped and signed the same way the preference matcher
     * labels it. SalaryFrom is free text, so a figure that is not a number
     * ("3.5 LPA", "₹45,000") is shown exactly as the employer saved it.
     */
    private function rupees(mixed $value): string
    {
        $text = $this->text($value);
        $number = $this->number($text);

        return $number === null ? $text : '₹'.number_format($number);
    }

    private function number(string $value): ?float
    {
        $digits = str_replace([',', ' '], '', $value);

        return is_numeric($digits) ? (float) $digits : null;
    }

    private function expectedSalary(?JobSeekerCareerPreference $preferences): ?string
    {
        if ($preferences === null) {
            return null;
        }

        $minimum = $this->number($this->text($preferences->minimum_salary ?? null));
        $maximum = $this->number($this->text($preferences->maximum_salary ?? null));

        if ($minimum === null && $maximum === null) {
            return null;
        }

        $period = $this->text($preferences->salary_period ?? null);
        $currency = $this->text($preferences->currency ?? null);
        // Only rupees are ever converted here, so another code is written out in
        // full rather than folded into a ₹ that would misstate the figure.
        $sign = ($currency === '' || $currency === 'INR') ? '₹' : $currency.' ';
        $suffix = $period === '' ? '' : ' / '.mb_strtolower($period);

        if ($minimum !== null && $maximum !== null) {
            return $sign.number_format($minimum).' – '.$sign.number_format($maximum).$suffix;
        }

        return $minimum !== null
            ? 'from '.$sign.number_format($minimum).$suffix
            : 'up to '.$sign.number_format($maximum).$suffix;
    }

    /**
     * classificationFit() labels the pay check 'Salary …'; its other entries are
     * plain job values.
     *
     * @param  list<string>  $labels
     */
    private function hasSalaryLabel(array $labels): bool
    {
        foreach ($labels as $label) {
            if (str_starts_with($label, 'Salary ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (mixed $value): string => $this->text($value), $values), static fn (string $tag): bool => $tag !== ''));
    }

    private function text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $text === false ? '' : $text;
    }
}
