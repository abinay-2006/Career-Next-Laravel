<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerCareerPreference extends Model
{
    protected $table = 'jobseeker_career_preferences';

    protected $fillable = [
        'JobSeekId',
        'preferred_job_titles',
        'preferred_locations',
        'minimum_salary',
        'maximum_salary',
        'salary_period',
        'currency',
        'employment_types',
        'work_arrangements',
        'preferred_industries',
        'experience_level',
        'willing_to_relocate',
        'notice_period',
        'preferred_working_hours',
        'job_search_status',
        'completed_at',
    ];

    protected $casts = [
        'preferred_job_titles' => 'array',
        'preferred_locations' => 'array',
        'employment_types' => 'array',
        'work_arrangements' => 'array',
        'preferred_industries' => 'array',
        'minimum_salary' => 'decimal:2',
        'maximum_salary' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * True when the seeker saved at least one meaningful preference.
     */
    public function hasAnyPreference(): bool
    {
        foreach (['preferred_job_titles', 'preferred_locations', 'employment_types', 'work_arrangements', 'preferred_industries'] as $key) {
            if (!empty($this->{$key})) {
                return true;
            }
        }

        return $this->experience_level !== null
            || $this->minimum_salary !== null
            || $this->maximum_salary !== null
            || $this->job_search_status !== null;
    }
}
