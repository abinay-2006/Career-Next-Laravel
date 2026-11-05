<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerResume;
use App\Models\JobSeekerSkill;
use Illuminate\Support\Facades\Schema;

/**
 * Indicates how well a Job Seeker's recorded skills overlap with an approved job.
 * The score is a deterministic keyword overlap, not a hiring or ATS prediction.
 *
 * The candidate side is the union of the entries saved under Skills & Software
 * on the profile and the skills typed into the Resume Builder, so a seeker with
 * only one of the two is still scored.
 *
 * Jobs with structured requirements (job_master.RequiredSkills / RequiredSoftware)
 * are scored on requirement coverage instead:
 *   percentage = (sum of matched skill weights / sum of all skill weights) x 100
 *   with mandatory skills weighted 2 and preferred skills weighted 1.
 * Software requirements are reported but NOT folded into the percentage; they are
 * matched against the seeker's software list plus every skill name. Proficiency
 * levels are never treated as matched — a level is only what the seeker claimed.
 * Jobs without structured requirements keep the original formula:
 *   matched candidate skills / all candidate skills.
 */
class JobMatchService
{
    public const STATUS_NEEDS_RESUME = 'needs_resume';
    public const STATUS_INSUFFICIENT_JOB = 'insufficient_job';
    public const STATUS_SCORED = 'scored';

    public const NO_RESUME_MESSAGE = 'List your skills under Skills & Software on your profile, or in Resume Builder, to calculate your job match.';

    /** A listing with fewer searchable words than this cannot be compared fairly. */
    public const MINIMUM_JOB_TOKENS = 3;

    /** Maximum number of consecutive words a single resume skill may span. */
    private const MAX_SKILL_TOKENS = 4;

    /** Structured requirement weights: mandatory vs preferred. */
    private const WEIGHT_MANDATORY = 2;

    private const WEIGHT_PREFERRED = 1;

    /** Alternate spellings of the same tool; every entry maps to one canonical form. */
    private const SYNONYMS = [
        'js' => 'javascript',
        'javascript' => 'javascript',
        'ts' => 'typescript',
        'typescript' => 'typescript',
        'node' => 'nodejs',
        'nodejs' => 'nodejs',
        'react' => 'reactjs',
        'reactjs' => 'reactjs',
        'next' => 'nextjs',
        'nextjs' => 'nextjs',
        'vue' => 'vuejs',
        'vuejs' => 'vuejs',
        'angular' => 'angularjs',
        'angularjs' => 'angularjs',
        'c++' => 'cpp',
        'cpp' => 'cpp',
        'c#' => 'csharp',
        'csharp' => 'csharp',
        'ml' => 'machinelearning',
        'ai' => 'artificialintelligence',
        'gcp' => 'googlecloud',
        'aws' => 'amazonwebservices',
    ];

    /**
     * The seeker's most recently updated saved Resume Builder record.
     */
    public function latestResume(int|string $jobSeekerId): ?JobSeekerResume
    {
        return JobSeekerResume::query()
            ->where('JobSeekId', $jobSeekerId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Distinct skills typed into the resume, in their original display form.
     *
     * @return list<string>
     */
    public function extractSkills(?JobSeekerResume $resume): array
    {
        $raw = $resume?->content['skills'] ?? null;

        $entries = is_array($raw)
            ? $raw
            : (preg_split('/[,\r\n]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return $this->distinctNames($entries);
    }

    /**
     * Skills from the seeker's latest saved Resume Builder record. An empty
     * list is returned when there is no resume, or no resume table yet.
     *
     * @return list<string>
     */
    public function resumeSkillNames(int|string $jobSeekerId): array
    {
        if (!Schema::hasTable('jobseeker_resumes')) {
            return [];
        }

        return $this->extractSkills($this->latestResume($jobSeekerId));
    }

    /**
     * Everything Smart Job Match can compare against a listing for one seeker:
     * the profile's Skills & Software entries unioned with the resume skills.
     *
     * Software carries every skill too, because a resume has no separate tools
     * field and seekers commonly name an IDE or design tool as a skill.
     *
     * @return array{skills: list<string>, software: list<string>}
     */
    public function candidateLists(int|string $jobSeekerId): array
    {
        $saved = JobSeekerSkill::findFor((int) $jobSeekerId);

        $skills = $this->distinctNames(array_merge(
            $saved?->names('skills') ?? [],
            $this->resumeSkillNames($jobSeekerId)
        ));

        $software = $this->distinctNames(array_merge(
            $saved?->names('software') ?? [],
            $skills
        ));

        return [
            'skills' => $skills,
            'software' => $software,
        ];
    }

    /**
     * Drop repeated spellings such as "React", "react" and "Node.js" next to
     * "nodejs", keeping the first display form seen.
     *
     * @param  iterable<mixed>  $names
     * @return list<string>
     */
    public function distinctNames(iterable $names): array
    {
        return array_values($this->distinctBySignature($names));
    }

    /**
     * @param  iterable<mixed>  $names
     * @return array<string, string>  identity => display form
     */
    private function distinctBySignature(iterable $names): array
    {
        $distinct = [];

        foreach ($names as $name) {
            if (!is_scalar($name)) {
                continue;
            }

            $display = $this->cleanDisplay((string) $name);
            if ($display === '') {
                continue;
            }

            $signature = $this->identity($this->tokens($display));
            if (!isset($distinct[$signature])) {
                $distinct[$signature] = $display;
            }
        }

        return $distinct;
    }

    /**
     * Compare one set of candidate skills against one job.
     *
     * @param  list<string>  $candidateSkills
     * @param  list<string>  $candidateSoftware  tools the seeker named, matched against software requirements only
     * @return array{status: string, tone: string, percentage: int|null, label: string|null, matched: list<string>, matched_in: array<string, list<string>>, missing: list<string>, total_skills: int, structured: bool, matched_mandatory: list<string>, missing_mandatory: list<string>, matched_preferred: list<string>, missing_preferred: list<string>, software: array{matched: list<string>, missing: list<string>, total: int}|null, explanation: string, suggestion: string|null}
     */
    public function calculate(array $candidateSkills, Job $job, array $candidateSoftware = []): array
    {
        if ($candidateSkills === []) {
            return $this->result(self::STATUS_NEEDS_RESUME);
        }

        $distinct = $this->distinctBySignature($candidateSkills);

        if ($distinct === []) {
            return $this->result(self::STATUS_NEEDS_RESUME);
        }

        $skillEntries = $this->requirementEntries($job->RequiredSkills ?? null);

        if ($skillEntries !== []) {
            return $this->calculateStructured(
                $distinct,
                $skillEntries,
                $this->requirementEntries($job->RequiredSoftware ?? null),
                $this->distinctBySignature($candidateSoftware)
            );
        }

        $fields = array_filter([
            'Job title' => (string) $job->JobTitle,
            'Description' => (string) $job->Description,
            'Qualification' => (string) $job->MinQualification,
        ], fn (string $text): bool => trim($text) !== '');

        $maxWindow = 1;
        foreach ($distinct as $skill) {
            $maxWindow = max($maxWindow, count($this->tokens($skill)));
        }
        $maxWindow = min($maxWindow, self::MAX_SKILL_TOKENS);

        $index = [];
        $distinctTokens = [];
        foreach ($fields as $label => $text) {
            $tokens = $this->tokens($text);
            $distinctTokens += array_fill_keys($tokens, true);
            foreach ($this->windows($tokens, $maxWindow) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = [];
                }
                if (!in_array($label, $index[$key], true)) {
                    $index[$key][] = $label;
                }
            }
        }

        if (count($distinctTokens) < self::MINIMUM_JOB_TOKENS) {
            return $this->result(self::STATUS_INSUFFICIENT_JOB);
        }

        $matched = [];
        $missing = [];
        foreach ($distinct as $skill) {
            $foundIn = [];
            foreach ($this->needles($skill) as $needle) {
                foreach ($index[$needle] ?? [] as $label) {
                    if (!in_array($label, $foundIn, true)) {
                        $foundIn[] = $label;
                    }
                }
            }
            if ($foundIn === []) {
                $missing[] = $skill;
            } else {
                $matched[$skill] = $foundIn;
            }
        }

        $matchedCount = count($matched);
        $total = count($distinct);
        $percentage = (int) round($matchedCount / $total * 100);

        return $this->result(self::STATUS_SCORED, [
            'tone' => $this->tone($percentage),
            'percentage' => $percentage,
            'label' => $this->label($percentage),
            'matched' => array_keys($matched),
            'matched_in' => $matched,
            'missing' => $missing,
            'total_skills' => $total,
            'explanation' => $this->explanation($percentage, $matchedCount, $total),
            'suggestion' => $this->suggestion($matchedCount, $missing),
        ]);
    }

    /**
     * Read one job requirement column defensively. Anything that is not a list
     * of {name, type, proficiency} objects yields no entries, so a legacy or
     * hand-edited row simply falls back to the original scoring path.
     *
     * @return list<array{name: string, type: string, proficiency: string|null}>
     */
    private function requirementEntries(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        $entries = [];
        $seen = [];
        foreach ($value as $entry) {
            if (!is_array($entry) || !isset($entry['name']) || !is_scalar($entry['name'])) {
                continue;
            }

            $name = trim((string) preg_replace('/\s+/', ' ', (string) $entry['name']));
            if ($name === '') {
                continue;
            }

            $identity = $this->identity($this->tokens($name));
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;

            $proficiency = isset($entry['proficiency']) && is_scalar($entry['proficiency'])
                ? strtolower(trim((string) $entry['proficiency']))
                : '';

            $entries[] = [
                'name' => $name,
                'type' => (string) ($entry['type'] ?? '') === 'preferred' ? 'preferred' : 'mandatory',
                'proficiency' => $proficiency === '' ? null : $proficiency,
            ];
        }

        return $entries;
    }

    /**
     * Score a resume against a job's structured requirements. Only the skills
     * column drives the percentage; software is reported alongside it.
     *
     * @param  array<string, string>  $distinct  identity => candidate skill display
     * @param  list<array{name: string, type: string, proficiency: string|null}>  $skillEntries
     * @param  list<array{name: string, type: string, proficiency: string|null}>  $softwareEntries
     * @param  array<string, string>  $toolDistinct  identity => candidate tool display
     * @return array<string, mixed>
     */
    private function calculateStructured(array $distinct, array $skillEntries, array $softwareEntries, array $toolDistinct = []): array
    {
        $forms = $this->candidateForms($distinct);
        $toolForms = $this->candidateForms($distinct + $toolDistinct);

        $matched = [];
        $missing = [];
        $matchedIn = [];
        $groups = [
            'mandatory' => ['matched' => [], 'missing' => []],
            'preferred' => ['matched' => [], 'missing' => []],
        ];
        $weightTotal = 0;
        $weightMatched = 0;

        foreach ($skillEntries as $entry) {
            $weight = $entry['type'] === 'mandatory' ? self::WEIGHT_MANDATORY : self::WEIGHT_PREFERRED;
            $weightTotal += $weight;
            $name = $entry['name'];
            $group = $entry['type'];

            if ($this->matchRequirement($name, $forms) === null) {
                $missing[] = $name;
                $groups[$group]['missing'][] = $name;
                continue;
            }

            $weightMatched += $weight;
            $matched[] = $name;
            $matchedIn[$name] = ['Requirements'];
            $groups[$group]['matched'][] = $name;
        }

        $percentage = $weightTotal === 0 ? 0 : (int) round($weightMatched / $weightTotal * 100);
        $software = $softwareEntries === [] ? null : $this->matchSoftware($softwareEntries, $toolForms);

        return $this->result(self::STATUS_SCORED, [
            'structured' => true,
            'tone' => $this->tone($percentage),
            'percentage' => $percentage,
            'label' => $this->label($percentage),
            'matched' => $matched,
            'matched_in' => $matchedIn,
            'missing' => $missing,
            'matched_mandatory' => $groups['mandatory']['matched'],
            'missing_mandatory' => $groups['mandatory']['missing'],
            'matched_preferred' => $groups['preferred']['matched'],
            'missing_preferred' => $groups['preferred']['missing'],
            'software' => $software,
            'total_skills' => count($skillEntries),
            'explanation' => $this->structuredExplanation($percentage, $weightMatched, $weightTotal, $software),
            'suggestion' => $this->structuredSuggestion($percentage, $groups['mandatory']['missing'], $groups['preferred']['missing']),
        ]);
    }

    /**
     * Every spelling a candidate skill can be looked up under, including the
     * sub-phrases of a multi-word skill so "React" is found inside "React JS".
     *
     * @param  array<string, string>  $distinct
     * @return array<string, string>  form => candidate skill display
     */
    private function candidateForms(array $distinct): array
    {
        $forms = [];
        foreach ($distinct as $skill) {
            $tokens = $this->tokens($skill);
            foreach ($this->windows($tokens, count($tokens)) as $form) {
                $forms[$form] = $skill;
            }
        }

        return $forms;
    }

    /**
     * @param  array<string, string>  $forms
     */
    private function matchRequirement(string $name, array $forms): ?string
    {
        foreach ($this->needles($name) as $needle) {
            if (isset($forms[$needle])) {
                return $forms[$needle];
            }
        }

        return null;
    }

    /**
     * Software requirements are reported as coverage only. A hit here means
     * "the seeker named this tool", not "the seeker is proficient in it".
     *
     * @param  list<array{name: string, type: string, proficiency: string|null}>  $softwareEntries
     * @param  array<string, string>  $forms
     * @return array{matched: list<string>, missing: list<string>, total: int}
     */
    private function matchSoftware(array $softwareEntries, array $forms): array
    {
        $matched = [];
        $missing = [];

        foreach ($softwareEntries as $entry) {
            if ($this->matchRequirement($entry['name'], $forms) === null) {
                $missing[] = $entry['name'];
            } else {
                $matched[] = $entry['name'];
            }
        }

        return [
            'matched' => $matched,
            'missing' => $missing,
            'total' => count($softwareEntries),
        ];
    }

    private function structuredExplanation(int $percentage, int $weightMatched, int $weightTotal, ?array $software): string
    {
        $text = $weightMatched === 0
            ? "None of this listing's required skills appear in your profile (0% requirement coverage)."
            : "{$percentage}% of this listing's weighted skill requirements appear in your profile.";

        if ($software !== null && $software['total'] > 0) {
            $text .= ' Tools: '.$software['total'].' named, '.count($software['matched']).' in your profile.';
        }

        return $text;
    }

    /**
     * @param  list<string>  $missingMandatory
     * @param  list<string>  $missingPreferred
     */
    private function structuredSuggestion(int $percentage, array $missingMandatory, array $missingPreferred): ?string
    {
        if ($missingMandatory === [] && $missingPreferred === []) {
            return 'You meet every skill requirement in this listing.';
        }

        $lead = $missingMandatory === []
            ? 'Preferred skills not in your profile: '.implode(', ', array_slice($missingPreferred, 0, 3)).'.'
            : 'Required skills missing from your profile: '.implode(', ', array_slice($missingMandatory, 0, 3)).'.';

        return $percentage >= 60
            ? $lead.' Add them under Skills & Software, with any experience you have using them, to raise this score.'
            : $lead.' This listing needs skills your profile does not mention yet - consider roles closer to your current stack.';
    }

    /**
     * Match bands are indicative profile overlap only.
     */
    public function label(int $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'Excellent Match',
            $percentage >= 60 => 'Good Match',
            $percentage >= 40 => 'Moderate Match',
            default => 'Low Match',
        };
    }

    /**
     * Colour band for the score, exposed as a class name instead of hard-coded styling.
     */
    private function tone(int $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'excellent',
            $percentage >= 60 => 'good',
            $percentage >= 40 => 'moderate',
            default => 'low',
        };
    }

    /**
     * Lowercase words that keep '#' and '+' so "C++" stays a single token while
     * "Java" and "JavaScript" remain different tokens.
     *
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $matches = [];
        preg_match_all('/[a-z0-9+#]+/i', $text, $matches);

        return array_map(fn (string $token): string => strtolower($token), $matches[0] ?? []);
    }

    /**
     * Every form a phrase can be written in, so "Node.js", "Node JS" and
     * "NodeJS" all reach the same lookup keys.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function forms(array $tokens): array
    {
        $spaced = implode(' ', $tokens);
        $compact = implode('', $tokens);

        return array_values(array_filter([
            $this->canonicalise($spaced),
            $this->canonicalise($compact),
        ], fn (string $form): bool => $form !== ''));
    }

    /**
     * @return list<string>
     */
    private function windows(array $tokens, int $maxWindow): array
    {
        $keys = [];
        $count = count($tokens);
        $limit = min($maxWindow, max(1, $count));
        for ($size = 1; $size <= $limit; $size++) {
            for ($start = 0; $start + $size <= $count; $start++) {
                foreach ($this->forms(array_slice($tokens, $start, $size)) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @return list<string>
     */
    private function needles(string $skill): array
    {
        return $this->forms($this->tokens($skill));
    }

    /**
     * Identity used to drop repeated spellings such as "React", "react" and
     * "Node.js" next to "nodejs".
     *
     * @param  list<string>  $tokens
     */
    private function identity(array $tokens): string
    {
        return $this->canonicalise(implode('', $tokens));
    }

    private function canonicalise(string $token): string
    {
        return self::SYNONYMS[$token] ?? $token;
    }

    private function cleanDisplay(string $skill): string
    {
        return trim(preg_replace('/\s+/', ' ', $skill), " \t\n\r\0\x0B.,;:/|-");
    }

    private function explanation(int $percentage, int $matchedCount, int $totalSkills): string
    {
        $skillWord = $totalSkills === 1 ? 'skill' : 'skills';

        return $matchedCount === 0
            ? "None of your {$totalSkills} {$skillWord} appear in this listing."
            : "{$matchedCount} of your {$totalSkills} resume {$skillWord} appear in this listing ({$percentage}% indicative profile overlap).";
    }

    private function suggestion(int $matchedCount, array $missing): ?string
    {
        if ($matchedCount === 0) {
            return $missing === []
                ? null
                : 'This listing mentions none of your resume skills. Look for roles that need '.implode(', ', array_slice($missing, 0, 3)).', or add the skills this one asks for in Resume Builder.';
        }

        if ($missing === []) {
            return 'Every skill on your resume is mentioned in this listing.';
        }

        return 'Lead with the skills this listing mentions. '.implode(', ', array_slice($missing, 0, 3)).' are not part of these requirements, so keep them lower on your resume.';
    }

    private function result(string $status, array $extra = []): array
    {
        return array_merge([
            'status' => $status,
            'tone' => 'muted',
            'percentage' => null,
            'label' => null,
            'matched' => [],
            'matched_in' => [],
            'missing' => [],
            'total_skills' => 0,
            'structured' => false,
            'matched_mandatory' => [],
            'missing_mandatory' => [],
            'matched_preferred' => [],
            'missing_preferred' => [],
            'software' => null,
            'explanation' => $status === self::STATUS_NEEDS_RESUME
                ? self::NO_RESUME_MESSAGE
                : 'A match cannot be calculated: this listing does not describe enough requirements for a meaningful comparison.',
            'suggestion' => null,
        ], $extra);
    }
}
