<?php

namespace App\Services;

use App\Models\Job;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Job Quality Checker.
 *
 * Reads the listing fields job_master really has - title, description, vacancy,
 * qualification, salary bounds, the three classification columns and the two
 * structured requirement columns - plus the employer's own company profile for
 * the location, and turns them into a 100 point rule score.
 *
 * Nothing is stored. A draft that is only half typed still gets an honest
 * number because the report is rebuilt from the fields handed to it, which is
 * also what lets the same rules score a saved job in the listing table.
 *
 * Undisclosed salary, benefits or software cost visibility points and raise an
 * informational warning. They never brand a listing suspicious: a quality score
 * says how complete the advertisement is, not who wrote it or whether the job
 * is real.
 */
class JobQualityService
{
    public const MAX_SCORE = 100;

    public const DISCLAIMER = 'This score measures how complete and clear the advertisement is. It does not verify an employer and it is not proof that a job is genuine.';

    /**
     * Category weights. The five maximums add up to self::MAX_SCORE.
     */
    public const CATEGORIES = [
        'job_information' => ['label' => 'Job information', 'max' => 30],
        'description' => ['label' => 'Job description quality', 'max' => 25],
        'requirements' => ['label' => 'Skills and requirements', 'max' => 25],
        'compensation' => ['label' => 'Compensation and benefits', 'max' => 10],
        'employment' => ['label' => 'Employment details', 'max' => 10],
    ];

    /**
     * Descriptive bands, checked from the top down. These are wording for the
     * score, not a moderation verdict.
     */
    public const BANDS = [
        ['min' => 90, 'key' => 'excellent', 'label' => 'Excellent'],
        ['min' => 75, 'key' => 'good', 'label' => 'Good'],
        ['min' => 60, 'key' => 'needs_improvement', 'label' => 'Needs improvement'],
        ['min' => 0, 'key' => 'incomplete', 'label' => 'Incomplete'],
    ];

    /**
     * Column widths mirrored from EmployerJobController::jobRules(), so a
     * draft the checker liked can never be the one the save then rejected.
     */
    public const TITLE_LIMIT = 50;

    public const DESCRIPTION_LIMIT = 200;

    /**
     * Titles that advertise nothing. Compared as the whole trimmed value,
     * because "Developer" is a real title once it says where and what level.
     */
    private const PLACEHOLDER_TITLES = [
        'job', 'jobs', 'job post', 'job posting', 'job title', 'job vacancy', 'vacancy',
        'work', 'working', 'post', 'position', 'role', 'hiring', 'urgent hiring',
        'staff', 'employee', 'worker', 'helper', 'office', 'new job', 'test', 'testing',
        'demo', 'demo job', 'test job', 'sample', 'sample job', 'abc', 'asdf', 'xyz', 'qwerty',
    ];

    /**
     * Wording that means the description is filler rather than an
     * advertisement. Multi-word phrases may appear anywhere; the short ones are
     * only counted when they are the whole text, so "Naive Bayes work" is safe.
     */
    private const PLACEHOLDER_PHRASES = [
        'lorem ipsum', 'description here', 'text here', 'job description here',
        'sample text', 'sample description', 'placeholder text', 'this is a test',
        'test description', 'demo description', 'to be filled', 'to be added',
        'content here', 'write here', 'add details here', 'fill this up',
    ];

    private const PLACEHOLDER_WORDS = ['n/a', 'na', 'none', 'nil', 'null', 'todo', 'tbd', 'test', 'testing', 'demo', 'abc', 'asdf', 'xyz', 'xxx', '----'];

    /**
     * Presence checks, not scoring of style. One signal is enough, so a
     * description written differently from these lists still earns the points.
     */
    private const RESPONSIBILITY_SIGNALS = [
        'responsibilit', 'accountabilit', 'duties', 'tasks', 'you will', 'you\'ll',
        'day to day', 'day-to-day', 'role involves', 'work includes', 'job involves',
        'develop', 'design', 'build', 'maintain', 'manage', 'lead', 'coordinate',
        'support', 'prepare', 'operate', 'review', 'deliver', 'create', 'assist',
        'train', 'monitor', 'implement', 'install', 'handle', 'oversee', 'collaborate',
        'write', 'test the', 'sales of', 'customer', 'production',
    ];

    private const EXPECTATION_SIGNALS = [
        'require', 'qualification', 'experience', 'degree', 'graduate', 'diploma',
        'certif', 'skill', 'proficien', 'knowledge', 'ability', 'background',
        'must have', 'should have', 'preferred', 'minimum', 'fresher', 'intern',
        'years in', 'hands on', 'strong', 'excellent',
    ];

    private const BENEFIT_SIGNALS = [
        'benefit', 'health insurance', 'medical insurance', 'insurance cover', 'provident fund',
        'esi', 'gratuity', 'paid leave', 'leave', 'paid holiday', 'pto', 'perks',
        'allowance', 'reimbursement', 'transport', 'food', 'meal', 'canteen', 'gym',
        'wellness', 'pension', 'retirement', 'maternity', 'paternity', 'insurance',
    ];

    private const EXTRA_PAY_SIGNALS = [
        'bonus', 'incentive', 'commission', 'variable pay', 'performance pay',
        'profit sharing', 'profit-sharing', 'signing bonus', 'stocks', 'equity',
        'esop', 'tip', 'overtime pay', 'shift allowance',
    ];

    private const SENIORITY_SIGNALS = [
        'senior', 'junior', 'lead', 'principal', 'head', 'chief', 'manager', 'executive',
        'intern', 'internship', 'fresher', 'trainee', 'assistant', 'specialist', 'analyst',
        'engineer', 'developer', 'designer', 'supervisor', 'officer', 'consultant', 'staff',
    ];

    /**
     * Words too common to say anything about repetition when they appear twice.
     */
    private const STOP_WORDS = [
        'the', 'and', 'for', 'with', 'you', 'your', 'our', 'we', 'will', 'are', 'is',
        'in', 'on', 'to', 'of', 'a', 'an', 'this', 'that', 'have', 'has', 'be', 'as',
        'at', 'or', 'by', 'it', 'from', 'who', 'all', 'can',
    ];

    private const REQUIREMENT_TYPES = ['mandatory', 'preferred'];

    /**
     * Score a draft or a saved job.
     *
     * Keys are the job form's own field names so the browser can post what the
     * employer typed without a translation layer. Values already resolved by the
     * database ('qualification', 'company') are accepted too.
     *
     * @param  array<string, mixed>  $fields
     * @param  mixed  $employer  the signed-in employer, used for the company profile only
     * @return array<string, mixed>
     */
    public function evaluate(array $fields, mixed $employer = null): array
    {
        $data = $this->normalise($fields, $employer);

        $signals = $this->descriptionSignals($data);

        $rules = array_merge(
            $this->jobInformationRules($data),
            $this->descriptionRules($data, $signals),
            $this->requirementRules($data),
            $this->compensationRules($data, $signals),
            $this->employmentRules($data),
        );

        return $this->report($rules);
    }

    /**
     * Score a saved job. The listing table uses this for its quality chip.
     */
    public function evaluateJob(Job $job, mixed $employer = null): array
    {
        return $this->evaluate($this->fieldsFromJob($job), $employer);
    }

    /**
     * Compact verdict per job for the employer's listing table: the number, the
     * band and the first two things worth fixing, nothing else.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array<string, mixed>>
     */
    public function summariseMany(Collection $jobs, mixed $employer = null): array
    {
        $summary = [];

        foreach ($jobs as $job) {
            $summary[(int) $job->JobId] = $this->summariseReport($this->evaluateJob($job, $employer));
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function summariseReport(array $report): array
    {
        $improve = [];

        foreach ($report['warnings'] as $warning) {
            $improve[] = $warning['label'];
        }

        foreach ($report['suggestions'] as $suggestion) {
            $improve[] = $suggestion['label'];
        }

        return [
            'score' => $report['score'],
            'label' => $report['label'],
            'band' => $report['band'],
            'missing' => array_slice($improve, 0, 2),
            'counts' => $report['counts'],
        ];
    }

    /**
     * The stored row expressed with the job form's field names.
     *
     * @return array<string, mixed>
     */
    public function fieldsFromJob(Job $job): array
    {
        return [
            'txtTitle' => $job->JobTitle,
            'txtTotal' => $job->Vacancy,
            'txtSalaryFrom' => $job->SalaryFrom,
            'txtSalaryTo' => $job->SalaryTo,
            'qualification' => $job->MinQualification,
            'txtDesc' => $job->Description,
            'cmbExperience' => $job->ExperienceLevel,
            'cmbArrangement' => $job->WorkArrangement,
            'cmbEmployment' => $job->EmploymentType,
            'required_skills' => $job->RequiredSkills,
            'required_software' => $job->RequiredSoftware,
            'company' => $job->CompanyName,
        ];
    }

    /**
     * A Remote arrangement answers the location question by itself; On-site,
     * Hybrid and Flexible only say how the week is split.
     *
     * @param  array<string, mixed>  $d
     */
    private function isRemoteLocation(array $d): bool
    {
        return $d['arrangement'] === Job::REMOTE_ARRANGEMENT;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalise(array $fields, mixed $employer): array
    {
        $qualification = $this->text($fields['qualification'] ?? null);

        if ($qualification === '') {
            $chosen = $this->text($fields['cmbQual'] ?? null);

            $qualification = $chosen === 'Other'
                ? $this->text($fields['txtOther'] ?? null)
                : $chosen;
        }

        return [
            'title' => $this->text($fields['txtTitle'] ?? null),
            'description' => $this->text($fields['txtDesc'] ?? null),
            'vacancy' => $this->text($fields['txtTotal'] ?? null),
            'qualification' => $qualification,
            'qualificationIsOther' => $this->text($fields['cmbQual'] ?? null) === 'Other',
            'experience' => $this->text($fields['cmbExperience'] ?? null),
            'arrangement' => $this->text($fields['cmbArrangement'] ?? null),
            'employment' => $this->text($fields['cmbEmployment'] ?? null),
            'salaryFrom' => $this->text($fields['txtSalaryFrom'] ?? null),
            'salaryTo' => $this->text($fields['txtSalaryTo'] ?? null),
            'skills' => $this->requirements($fields['required_skills'] ?? null),
            'software' => $this->requirements($fields['required_software'] ?? null),
            'company' => $this->companyName($fields, $employer),
            'city' => $this->employerValue($employer, 'City'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function companyName(array $fields, mixed $employer): string
    {
        $fromJob = $this->text($fields['company'] ?? null);

        return $fromJob !== '' ? $fromJob : $this->employerValue($employer, 'CompanyName');
    }

    /**
     * job_master has no location column, so the place a job is based comes from
     * the company profile - the same reading JobOfferComparisonService uses.
     */
    private function employerValue(mixed $employer, string $column): string
    {
        if (!is_object($employer) || !isset($employer->{$column})) {
            return '';
        }

        $table = method_exists($employer, 'getTable') ? $employer->getTable() : null;

        if ($table !== null && !Schema::hasColumn($table, $column)) {
            return '';
        }

        return $this->text($employer->{$column});
    }

    /**
     * Structured requirements as the pickers submit them: a JSON string in a
     * hidden field, or the decoded list once the row is saved. Anything that is
     * not an entry the save would have accepted is ignored rather than trusted.
     *
     * @return list<array{name: string, type: string, proficiency: string|null}>
     */
    private function requirements(mixed $raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $value = $this->text($raw);

            if ($value === '' || $value === '[]') {
                return [];
            }

            $decoded = json_decode($value, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        $clean = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $this->text($entry['name'] ?? null);

            if ($name === '') {
                continue;
            }

            $type = $this->text($entry['type'] ?? 'mandatory');
            $proficiency = $this->text($entry['proficiency'] ?? null);

            $clean[] = [
                'name' => $name,
                'type' => in_array($type, self::REQUIREMENT_TYPES, true) ? $type : 'mandatory',
                'proficiency' => $proficiency !== '' ? $proficiency : null,
            ];
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    private function namesOf(array $entries, string $type): array
    {
        $names = [];

        foreach ($entries as $entry) {
            if ($entry['type'] === $type) {
                $names[] = $entry['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, bool>
     */
    private function descriptionSignals(array $data): array
    {
        $description = $data['description'];
        $lower = mb_strtolower($description);

        $words = preg_match_all('/\p{L}+/u', $description, $matches) ? $matches[0] : [];
        $contentWords = array_filter(
            array_map(mb_strtolower(...), $words),
            fn (string $word): bool => !in_array($word, self::STOP_WORDS, true) && mb_strlen($word) > 2
        );

        $counts = array_count_values($contentWords);
        $repeats = $counts === [] ? 0 : max($counts);
        $contentTotal = count($contentWords);

        $sentences = preg_split('/[.!?\n]+/u', $lower) ?: [];
        $sentences = array_values(array_filter(array_map(trim(...), $sentences), fn (string $s): bool => $s !== ''));
        $duplicates = count($sentences) - count(array_unique($sentences));

        $titleWords = array_filter(
            array_map(mb_strtolower(...), preg_split('/\s+/u', $data['title']) ?: []),
            fn (string $word): bool => $word !== '' && !in_array($word, self::STOP_WORDS, true)
        );

        $descriptionWords = array_map(mb_strtolower(...), $words);

        $isEcho = $titleWords !== []
            && count($descriptionWords) > 0
            && count(array_filter($descriptionWords, fn (string $word): bool => !in_array($word, $titleWords, true))) === 0;

        return [
            'placeholder' => $this->looksLikePlaceholder($description, $lower, $words),
            'echoes_title' => $isEcho,
            'repeated' => $duplicates > 0 || ($repeats >= 4 && $repeats >= ceil($contentTotal * 0.4)),
            'responsibilities' => $this->containsAny($lower, self::RESPONSIBILITY_SIGNALS),
            'expectations' => $this->containsAny($lower, self::EXPECTATION_SIGNALS),
            'benefits' => $this->containsAny($lower, self::BENEFIT_SIGNALS),
            'extra_pay' => $this->containsAny($lower, self::EXTRA_PAY_SIGNALS),
            'word_count' => count($words),
        ];
    }

    private function looksLikePlaceholder(string $description, string $lower, array $words): bool
    {
        if ($description === '') {
            return false;
        }

        foreach (self::PLACEHOLDER_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        if (in_array($lower, self::PLACEHOLDER_WORDS, true)) {
            return true;
        }

        // One character or one digit repeated, with no real word in sight.
        if (preg_match('/^(.)\1*\z/u', $description) === 1) {
            return true;
        }

        return $words !== [] && count(array_filter($words, 'is_numeric')) === count($words);
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<array<string, mixed>>
     */
    private function jobInformationRules(array $d): array
    {
        $rules = [];

        $title = $d['title'];

        $rules[] = $title === ''
            ? $this->rule('title_present', 'job_information', 0, 3, 'txtTitle', 'fail', 'warning',
                'No job title yet.', 'Candidates search by title, so this listing cannot be found at all.',
                'Add a job title that names the role.', 'Job title')
            : $this->rule('title_present', 'job_information', 3, 3, 'txtTitle', 'pass', 'none',
                'Job title is filled in.', null, null, null);

        $lower = mb_strtolower($title);

        if ($title === '') {
            $rules[] = $this->rule('title_clear', 'job_information', 0, 5, 'txtTitle', 'skip', 'none',
                null, null, null, null);
        } elseif (mb_strlen($title) > self::TITLE_LIMIT) {
            $rules[] = $this->rule('title_clear', 'job_information', 0, 5, 'txtTitle', 'fail', 'critical',
                'Job titles are limited to '.self::TITLE_LIMIT.' characters and this one is '.mb_strlen($title).'.',
                'The save would reject this title, so shortening it is worth doing first.',
                'Shorten the job title.', null);
        } elseif (in_array($lower, self::PLACEHOLDER_TITLES, true) || preg_match('/^\W*$/', $title) === 1) {
            $rules[] = $this->rule('title_clear', 'job_information', 0, 5, 'txtTitle', 'fail', 'warning',
                'The title "' . $title . '" does not describe a role.',
                'A one-word placeholder makes the listing indistinguishable from spam.',
                'Add a clearer job title, such as "PHP Developer" or "Sales Executive".', null);
        } elseif (count(preg_split('/\s+/u', trim($title)) ?: []) >= 2) {
            $rules[] = $this->rule('title_clear', 'job_information', 5, 5, 'txtTitle', 'pass', 'none',
                'Job title is clear.', null, null, null);
        } else {
            $rules[] = $this->rule('title_clear', 'job_information', 3, 5, 'txtTitle', 'fail', 'info',
                'The title names a role but stops there.',
                'Two or three words help candidates tell your opening apart from every other one.',
                $this->seniorityHint($title), null);
        }

        $vacancy = $d['vacancy'];

        if ($vacancy === '') {
            $rules[] = $this->rule('vacancy', 'job_information', 0, 4, 'txtTotal', 'fail', 'warning',
                'The number of openings is missing.', 'Candidates weigh how many people a team is hiring.',
                'Add how many vacancies this role fills.', 'Vacancies');
        } elseif (!preg_match('/^\d+$/', $vacancy) || (int) $vacancy < 1) {
            $rules[] = $this->rule('vacancy', 'job_information', 0, 4, 'txtTotal', 'fail', 'critical',
                'Vacancies has to be a whole number of 1 or more.',
                'The form will not save until it does.', 'Set the vacancy count to a number above zero.', null);
        } else {
            $rules[] = $this->rule('vacancy', 'job_information', 4, 4, 'txtTotal', 'pass', 'none',
                'Vacancies are stated.', null, null, null);
        }

        if ($d['qualification'] === '') {
            if ($d['qualificationIsOther']) {
                $rules[] = $this->rule('qualification', 'job_information', 0, 8, 'txtOther', 'fail', 'critical',
                    '"Other" is chosen but the qualification beside it is empty.',
                    'The save needs the typed qualification, so this listing cannot be posted yet.',
                    'Type the qualification next to "Other".', 'Minimum qualification');
            } else {
                $rules[] = $this->rule('qualification', 'job_information', 0, 8, 'cmbQual', 'fail', 'warning',
                    'No minimum qualification is given.',
                    'Students and freshers cannot judge whether they should apply.',
                    'Choose the minimum qualification candidates need.', 'Minimum qualification');
            }
        } else {
            $rules[] = $this->rule('qualification', 'job_information', 8, 8, 'cmbQual', 'pass', 'none',
                'Qualification requirement is stated.', null, null, null);
        }

        $rules[] = $d['company'] === ''
            ? $this->rule('company', 'job_information', 0, 4, null, 'fail', 'warning',
                'The company behind this listing is not named.',
                'Candidates expect to see who is hiring before they apply.',
                'Complete the company details in your employer profile.', 'Company name')
            : $this->rule('company', 'job_information', 4, 4, null, 'pass', 'none',
                'Company information is available.', null, null, null);

        if ($this->isRemoteLocation($d)) {
            $rules[] = $this->rule('location', 'job_information', 6, 6, 'cmbArrangement', 'pass', 'none',
                'Work location is covered by the Remote arrangement.', null, null, null);
        } elseif ($d['city'] !== '') {
            $rules[] = $this->rule('location', 'job_information', 6, 6, 'cmbArrangement', 'pass', 'none',
                'The listing is placed in ' . $d['city'] . ' from your company profile.', null, null, null);
        } else {
            $rules[] = $this->rule('location', 'job_information', 0, 6, 'cmbArrangement', 'fail', 'warning',
                'Nothing in this listing says where the job is based.',
                'job_master has no location field, so the city comes from your company profile or from a Remote arrangement.',
                'Set the work arrangement, or add your city in the employer profile.', 'Job location');
        }

        return $rules;
    }

    private function seniorityHint(string $title): string
    {
        return str_contains(mb_strtolower($title), 'developer')
            ? 'Name the stack or the level, for example "Java Developer" or "Senior Developer".'
            : 'Add the specialisation, the team or the level so the role reads as one specific opening.';
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, bool|int>  $signals
     * @return list<array<string, mixed>>
     */
    private function descriptionRules(array $d, array $signals): array
    {
        $length = mb_strlen($d['description']);
        $placeholder = $signals['placeholder'];

        if ($length === 0) {
            $rules = [$this->rule('description_length', 'description', 0, 8, 'txtDesc', 'fail', 'warning',
                'The description is empty.',
                'This is the only free-text field a candidate reads, and it is what Smart Job Match can match on.',
                'Describe the role, the responsibilities and what you expect.', 'Description')];
        } elseif ($placeholder) {
            $rules = [$this->rule('description_length', 'description', 0, 8, 'txtDesc', 'fail', 'critical',
                'The description is placeholder text rather than information about the job.',
                'Filler such as "lorem ipsum" or "test" tells candidates the posting was never finished.',
                'Replace the placeholder with what the job actually involves.', null)];
        } elseif ($length < 60) {
            $rules = [$this->rule('description_length', 'description', 2, 8, 'txtDesc', 'fail', 'warning',
                'The description is only ' . $length . ' characters.',
                'At this length there is rarely room for both the work and the requirements.',
                'Add one more sentence about the role.', null)];
        } elseif ($length < 120) {
            $rules = [$this->rule('description_length', 'description', 5, 8, 'txtDesc', 'pass', 'info',
                'The description has enough room to be useful.', null, null, null)];
        } else {
            $rules = [$this->rule('description_length', 'description', 8, 8, 'txtDesc', 'pass', 'none',
                'The description is detailed enough.', null, null, null)];
        }

        if ($length > self::DESCRIPTION_LIMIT) {
            $rules[] = $this->rule('description_limit', 'description', 0, 0, 'txtDesc', 'fail', 'critical',
                'The description is ' . $length . ' characters and the field holds '.self::DESCRIPTION_LIMIT.'.',
                'The save will reject it, so trim before posting.',
                'Shorten the description to '.self::DESCRIPTION_LIMIT.' characters.', null);
        }

        // Nothing was written, or only filler was, so the checks that read the
        // wording have nothing to judge. Saying so once, in the rules above, is
        // enough - repeating it as three more warnings would only be noise.
        $silence = $length === 0 || $placeholder;

        if ($silence) {
            $rules[] = $this->rule('description_content', 'description', 0, 3, 'txtDesc', 'skip', 'none',
                null, null, null, null);
        } elseif ($signals['echoes_title']) {
            $rules[] = $this->rule('description_content', 'description', 0, 3, 'txtDesc', 'fail', 'warning',
                'The description only repeats words already in the job title.',
                'It reads as real text but adds no information a candidate can use.',
                'Say what the job involves instead of restating the title.', null);
        } else {
            $rules[] = $this->rule('description_content', 'description', 3, 3, 'txtDesc', 'pass', 'none',
                'The description is real content.', null, null, null);
        }

        if ($silence) {
            $rules[] = $this->rule('description_responsibilities', 'description', 0, 6, 'txtDesc', 'skip', 'none',
                null, null, null, null);
        } else {
            $rules[] = $signals['responsibilities']
                ? $this->rule('description_responsibilities', 'description', 6, 6, 'txtDesc', 'pass', 'none',
                    'Responsibilities come through.', null, null, null)
                : $this->rule('description_responsibilities', 'description', 0, 6, 'txtDesc', 'fail', 'warning',
                    'The description never says what the work involves.',
                    'Duties are the first thing candidates weigh a role against.',
                    'Describe the main responsibilities in a sentence or two.', null);
        }

        if ($silence) {
            $rules[] = $this->rule('description_expectations', 'description', 0, 5, 'txtDesc', 'skip', 'none',
                null, null, null, null);
        } else {
            $rules[] = $signals['expectations']
                ? $this->rule('description_expectations', 'description', 5, 5, 'txtDesc', 'pass', 'none',
                    'Candidate expectations are explained.', null, null, null)
                : $this->rule('description_expectations', 'description', 0, 5, 'txtDesc', 'fail', 'info',
                    'The description does not say who should apply.',
                    'The qualification and experience fields cover the minimum, but a sentence about the right candidate filters out unqualified applications.',
                    'Mention the skills or background you expect.', null);
        }

        if ($silence) {
            $rules[] = $this->rule('description_repetition', 'description', 0, 3, 'txtDesc', 'skip', 'none',
                null, null, null, null);
        } else {
            $rules[] = $signals['repeated']
                ? $this->rule('description_repetition', 'description', 0, 3, 'txtDesc', 'fail', 'warning',
                    'Parts of the description repeat themselves.',
                    'Repeated sentences or one word used over and over read as a copy-paste error.',
                    'Remove the repeated lines and keep each point once.', null)
                : $this->rule('description_repetition', 'description', 3, 3, 'txtDesc', 'pass', 'none',
                    'The description reads cleanly.', null, null, null);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<array<string, mixed>>
     */
    private function requirementRules(array $d): array
    {
        $mandatory = $this->namesOf($d['skills'], 'mandatory');
        $preferred = $this->namesOf($d['skills'], 'preferred');
        $software = $d['software'];

        $rules = [];

        if ($mandatory !== []) {
            $count = count($mandatory);

            $rules[] = $this->rule('required_skills', 'requirements', $count >= 2 ? 10 : 7, 10, 'required_skills',
                $count >= 2 ? 'pass' : 'fail', $count >= 2 ? 'none' : 'info',
                $count >= 2
                    ? 'Required skills are specified.'
                    : 'Only one required skill is listed.',
                $count >= 2 ? null : 'Two or three mandatory skills let Smart Job Match put your listing in front of the right people.',
                $count >= 2 ? null : 'List every skill a candidate genuinely needs.', null);
        } elseif ($preferred !== []) {
            $rules[] = $this->rule('required_skills', 'requirements', 4, 10, 'required_skills', 'fail', 'warning',
                'Skills are marked as preferred, so nothing is mandatory.',
                'Candidates cannot tell what they must already know.',
                'Mark the skills a candidate must have as mandatory.', null);
        } else {
            $rules[] = $this->rule('required_skills', 'requirements', 0, 10, 'required_skills', 'fail', 'warning',
                'No skills are specified.',
                'The skills list is what the structured match reads; without it only the description is left.',
                'Add the required skills.', null);
        }

        $rules[] = $preferred !== []
            ? $this->rule('preferred_skills', 'requirements', 4, 4, 'required_skills', 'pass', 'none',
                'Preferred skills are separated from mandatory ones.', null, null, null)
            : $this->rule('preferred_skills', 'requirements', 0, 4, 'required_skills', 'fail', 'info',
                'No nice-to-have skills are listed.',
                'Optional to mention, but it widens the pool without weakening the must-haves.',
                'Add any skills that would be a plus but are not required.', null);

        $rules[] = $software !== []
            ? $this->rule('required_software', 'requirements', 6, 6, 'required_software', 'pass', 'none',
                'Tools and software are named.', null, null, null)
            : $this->rule('required_software', 'requirements', 0, 6, 'required_software', 'fail', 'info',
                'No software or tools are listed.',
                'Names like the IDE, design tool or ERP in use set a clear expectation.',
                'Mention the software candidates will use.', null);

        $entries = array_merge($d['skills'], $software);
        $levelled = array_filter($entries, fn (array $entry): bool => $entry['proficiency'] !== null);

        if ($entries === []) {
            $rules[] = $this->rule('proficiency_levels', 'requirements', 0, 5, 'required_skills', 'skip', 'none',
                null, null, null, null);
        } elseif (count($levelled) === count($entries)) {
            $rules[] = $this->rule('proficiency_levels', 'requirements', 5, 5, 'required_skills', 'pass', 'none',
                'Every requirement has a proficiency level.', null, null, null);
        } elseif ($levelled !== []) {
            $rules[] = $this->rule('proficiency_levels', 'requirements', 3, 5, 'required_skills', 'fail', 'info',
                'Proficiency is set for ' . count($levelled) . ' of ' . count($entries) . ' requirements.',
                'A level distinguishes "used Laravel in a college project" from "ships Laravel in production".',
                'Set the proficiency level for the remaining requirements.', null);
        } else {
            $rules[] = $this->rule('proficiency_levels', 'requirements', 0, 5, 'required_skills', 'fail', 'info',
                'None of the requirements carry a proficiency level.',
                'The picker can record basic, intermediate, advanced or expert for each entry.',
                'Add the expected proficiency for each requirement.', null);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, bool|int>  $signals
     * @return list<array<string, mixed>>
     */
    private function compensationRules(array $d, array $signals): array
    {
        $from = $d['salaryFrom'];
        $to = $d['salaryTo'];

        $rules = [];

        if ($from !== '' && $to !== '') {
            $rules[] = $this->rule('salary_disclosed', 'compensation', 5, 5, 'txtSalaryFrom', 'pass', 'none',
                'A salary range is published.', null, null, null);
        } elseif ($from !== '' || $to !== '') {
            $rules[] = $this->rule('salary_disclosed', 'compensation', 3, 5,
                $from !== '' ? 'txtSalaryTo' : 'txtSalaryFrom', 'fail', 'info',
                'Only one end of the salary range is given.',
                'A lone number reads as a floor or a ceiling rather than a budget.',
                'Fill in the other end of the salary range.', null);
        } else {
            $rules[] = $this->rule('salary_disclosed', 'compensation', 0, 5, 'txtSalaryFrom', 'fail', 'info',
                'Salary information is missing.',
                'Informational only: salary is optional here, and a listing without it is neither rejected nor treated as suspicious.',
                'Consider disclosing a salary range.', 'Salary');
        }

        // A range that was never written cannot be an invalid one, so the
        // validity points stay unspent instead of being taken away.
        if ($from === '' || $to === '') {
            $rules[] = $this->rule('salary_valid', 'compensation', 3, 3, null, 'na', 'none',
                null, null, null, null);
        } elseif (!$this->isAmount($from) || !$this->isAmount($to)) {
            $rules[] = $this->rule('salary_valid', 'compensation', 0, 3, 'txtSalaryFrom', 'fail', 'critical',
                'The salary values have to be numbers.',
                'Both boxes are numeric fields, so text in them blocks the save.',
                'Enter the salary as numbers, without symbols.', null);
        } elseif ((float) $from > (float) $to) {
            $rules[] = $this->rule('salary_valid', 'compensation', 0, 3, 'txtSalaryTo', 'fail', 'critical',
                'The salary range is the wrong way round (' . $from . ' above ' . $to . ').',
                'Candidates read the two boxes as a floor and a ceiling.',
                'Swap the minimum and maximum salary.', null);
        } elseif ((float) $from === 0.0 || (float) $to === 0.0) {
            $rules[] = $this->rule('salary_valid', 'compensation', 0, 3, 'txtSalaryFrom', 'fail', 'warning',
                'One end of the salary range is zero.',
                'Zero usually means the box was left unfilled.',
                'Enter the real minimum and maximum salary.', null);
        } else {
            $rules[] = $this->rule('salary_valid', 'compensation', 3, 3, 'txtSalaryFrom', 'pass', 'none',
                'The salary range is valid.', null, null, null);
        }

        $rules[] = $signals['benefits']
            ? $this->rule('benefits', 'compensation', 1, 1, 'txtDesc', 'pass', 'none',
                'Benefits are described.', null, null, null)
            : $this->rule('benefits', 'compensation', 0, 1, 'txtDesc', 'fail', 'info',
                'Benefits are not disclosed.',
                'There is no benefits field on a listing, so this only counts when the description mentions them. Nothing here suggests the job is poor.',
                'Include available employment benefits, such as insurance, paid leave or transport.', null);

        $rules[] = $signals['extra_pay']
            ? $this->rule('extra_compensation', 'compensation', 1, 1, 'txtDesc', 'pass', 'none',
                'Additional compensation is mentioned.', null, null, null)
            : $this->rule('extra_compensation', 'compensation', 0, 1, 'txtDesc', 'fail', 'info',
                'No bonus, incentive or commission is mentioned.',
                'Optional, and only worth a line if the role really has one.',
                'Say so in the description if pay above the salary range is possible.', null);

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return list<array<string, mixed>>
     */
    private function employmentRules(array $d): array
    {
        return [
            $d['employment'] === ''
                ? $this->rule('employment_type', 'employment', 0, 4, 'cmbEmployment', 'fail', 'warning',
                    'Employment type is not chosen.',
                    'Full-time, contract and internship attract very different applicants.',
                    'Choose the employment type.', 'Employment type')
                : $this->rule('employment_type', 'employment', 4, 4, 'cmbEmployment', 'pass', 'none',
                    'Employment type is stated as ' . $d['employment'] . '.', null, null, null),

            $d['arrangement'] === ''
                ? $this->rule('work_mode', 'employment', 0, 4, 'cmbArrangement', 'fail', 'warning',
                    'The work arrangement is missing.',
                    'On-site, Remote or Hybrid decides whether someone applies at all.',
                    'Choose the work arrangement.', 'Work mode')
                : $this->rule('work_mode', 'employment', 4, 4, 'cmbArrangement', 'pass', 'none',
                    'Work mode is stated as ' . $d['arrangement'] . '.', null, null, null),

            $d['experience'] === ''
                ? $this->rule('experience_level', 'employment', 0, 2, 'cmbExperience', 'fail', 'info',
                    'Experience level is not set.',
                    'Naming it stops freshers and senior candidates both applying to the same posting.',
                    'Add the expected experience level.', 'Experience level')
                : $this->rule('experience_level', 'employment', 2, 2, 'cmbExperience', 'pass', 'none',
                    'Experience requirement is available.', null, null, null),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function report(array $rules): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $key => $meta) {
            $categories[$key] = ['key' => $key, 'label' => $meta['label'], 'earned' => 0, 'max' => $meta['max'], 'state' => 'weak'];
        }

        $completed = [];
        $warnings = [];
        $suggestions = [];
        $missing = [];
        $total = 0;

        foreach ($rules as $rule) {
            $category = $rule['category'];
            $earned = max(0, min($rule['max'], $rule['earned']));

            $categories[$category]['earned'] += $earned;
            $total += $earned;

            if ($rule['missing'] !== null && !in_array($rule['missing'], array_column($missing, 'label'), true)) {
                $missing[] = ['label' => $rule['missing'], 'field' => $rule['field']];
            }

            if ($rule['status'] === 'pass') {
                $completed[] = [
                    'id' => $rule['id'],
                    'label' => $rule['label'],
                    'category' => $category,
                ];

                continue;
            }

            if ($rule['status'] === 'skip' || $rule['status'] === 'na') {
                continue;
            }

            if (in_array($rule['severity'], ['critical', 'warning', 'info'], true)) {
                $warnings[] = [
                    'id' => $rule['id'],
                    'label' => $rule['label'],
                    'detail' => $rule['detail'],
                    'severity' => $rule['severity'],
                    'field' => $rule['field'],
                    'category' => $category,
                ];
            }

            if ($rule['action'] !== null) {
                $suggestions[] = [
                    'id' => $rule['id'],
                    'label' => $rule['action'],
                    'field' => $rule['field'],
                    'category' => $category,
                ];
            }
        }

        $score = max(0, min(self::MAX_SCORE, $total));

        foreach ($categories as $key => $category) {
            $share = $category['max'] > 0 ? $category['earned'] / $category['max'] : 0;
            $categories[$key]['state'] = $share >= 0.8 ? 'strong' : ($share >= 0.5 ? 'partial' : 'weak');
        }

        $band = self::BANDS[count(self::BANDS) - 1];

        foreach (self::BANDS as $candidate) {
            if ($score >= $candidate['min']) {
                $band = $candidate;

                break;
            }
        }

        return [
            'score' => $score,
            'max_score' => self::MAX_SCORE,
            'label' => $band['label'],
            'band' => $band['key'],
            'categories' => array_values($categories),
            'completed' => $completed,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'missing' => $missing,
            'counts' => [
                'completed' => count($completed),
                'warnings' => count($warnings),
                'suggestions' => count($suggestions),
                'missing' => count($missing),
            ],
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    /**
     * One evaluation rule: its verdict, its points, and what the employer can do
     * about it.
     */
    private function rule(
        string $id,
        string $category,
        int $earned,
        int $max,
        ?string $field,
        string $status,
        string $severity,
        ?string $label,
        ?string $detail,
        ?string $action,
        ?string $missing,
    ): array {
        return [
            'id' => $id,
            'category' => $category,
            'earned' => $earned,
            'max' => $max,
            'field' => $field,
            'status' => $status,
            'severity' => $severity,
            'label' => $label,
            'detail' => $detail,
            'action' => $action,
            'missing' => $missing,
        ];
    }

    private function isAmount(string $value): bool
    {
        return preg_match('/^\d+(?:[.,]\d{1,2})?$/', str_replace(' ', '', $value)) === 1;
    }

    /**
     * Trim to a plain string, and treat anything that is not text or a number as
     * blank rather than guessing at it.
     */
    private function text(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return $value === null ? '' : trim((string) $value);
    }
}
