<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'job_master';

    protected $primaryKey = 'JobId';

    public $timestamps = false;

    /**
     * Experience slugs and labels match the job seeker's Career Preferences
     * choices so the two sides can be compared; 'any' is left out because a
     * listing that does not care simply stores null.
     */
    public const EXPERIENCE_LEVELS = [
        'fresher' => 'Fresher',
        'internship' => 'Internship',
        'entry_level' => 'Entry-level',
        '1-2_years' => '1–2 years',
        '3-5_years' => '3–5 years',
        '6-10_years' => '6–10 years',
        '10_plus_years' => '10+ years',
    ];

    /**
     * How many years of experience each slug stands for, as [minimum, maximum]
     * with a null maximum meaning "no upper bound". The same map is used for
     * the employer's listing and the seeker's preference so the two sides are
     * compared as ranges instead of having to pick the identical wording.
     */
    public const EXPERIENCE_YEARS = [
        'fresher' => [0, 0],
        'internship' => [0, 1],
        'entry_level' => [0, 2],
        '1-2_years' => [1, 2],
        '3-5_years' => [3, 5],
        '6-10_years' => [6, 10],
        '10_plus_years' => [10, null],
        'any' => [0, null],
    ];

    public const WORK_ARRANGEMENTS = ['On-site', 'Remote', 'Hybrid', 'Flexible'];

    public const EMPLOYMENT_TYPES = [
        'Full-time', 'Part-time', 'Internship', 'Apprenticeship', 'Contract',
        'Temporary', 'Freelance', 'Permanent', 'Graduate trainee',
    ];

    protected $fillable = [
        'EmployerId',
        'CompanyName',
        'JobTitle',
        'Vacancy',
        'SalaryFrom',
        'SalaryTo',
        'MinQualification',
        'ExperienceLevel',
        'WorkArrangement',
        'EmploymentType',
        'Description',
        'RequiredSkills',
        'RequiredSoftware',
        'Status',
    ];

    protected $casts = [
        'RequiredSkills' => 'array',
        'RequiredSoftware' => 'array',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'JobId', 'JobId');
    }

    /**
     * Label for a stored experience slug, so views and matching never repeat
     * the mapping.
     */
    public static function experienceLevelLabel(mixed $level): ?string
    {
        return is_string($level) ? (self::EXPERIENCE_LEVELS[$level] ?? $level) : null;
    }

    /**
     * Years represented by a stored slug, or null when it is blank or is a
     * legacy value that no longer maps to a band. Callers treat null as
     * "cannot be checked" rather than as a mismatch.
     *
     * @return array{0: int, 1: int|null}|null
     */
    public static function experienceYears(mixed $level): ?array
    {
        if (!is_string($level)) {
            return null;
        }

        $level = trim($level);

        return self::EXPERIENCE_YEARS[$level] ?? null;
    }
}
