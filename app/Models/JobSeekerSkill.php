<?php

namespace App\Models;

use App\Support\SchemaReady;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The skills and software a job seeker says they know (one row per seeker).
 * Each entry is {name, proficiency}; proficiency is what the seeker claims and
 * is never presented to employers as verified evidence.
 */
class JobSeekerSkill extends Model
{
    protected $table = 'jobseeker_skills';

    protected $fillable = [
        'JobSeekId',
        'skills',
        'software',
    ];

    protected $casts = [
        'skills' => 'array',
        'software' => 'array',
    ];

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * The saved row for one seeker, or null when nothing has been recorded yet.
     * A missing table reads as "no entries" so pages built on older databases,
     * and hand-built test schemas, still render.
     */
    public static function findFor(int $jobSeekerId): ?self
    {
        if ($jobSeekerId <= 0 || !SchemaReady::hasTable('jobseeker_skills')) {
            return null;
        }

        return static::query()->where('JobSeekId', $jobSeekerId)->first();
    }

    /**
     * Display names recorded in one column, in the order they were saved.
     *
     * @return list<string>
     */
    public function names(string $column): array
    {
        $entries = is_array($this->{$column}) ? $this->{$column} : [];

        $names = [];
        foreach ($entries as $entry) {
            $name = is_array($entry) ? trim((string) ($entry['name'] ?? '')) : '';
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
