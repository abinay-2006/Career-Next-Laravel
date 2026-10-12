<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerEducation extends Model
{
    protected $table = 'jobseeker_education';

    protected $primaryKey = 'EduId';

    public $timestamps = false;

    protected $fillable = [
        'JobSeekId',
        'Degree',
        'University',
        'PassingYear',
        'Percentage',
    ];

    /**
     * Relationship with JobSeeker
     */
    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(
            JobSeeker::class,
            'JobSeekId',
            'JobSeekId'
        );
    }
}
