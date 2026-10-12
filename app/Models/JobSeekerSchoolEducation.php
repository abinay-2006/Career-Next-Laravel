<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerSchoolEducation extends Model
{
    protected $table = 'jobseeker_school_education';

    // Change this if your primary key has a different name
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'JobSeekId',
        'SchoolName',
        'Qualification',
        'Board',
        'YearOfPassing',
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
