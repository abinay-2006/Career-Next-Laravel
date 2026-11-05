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
}
