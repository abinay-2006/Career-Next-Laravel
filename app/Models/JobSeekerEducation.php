<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerEducation extends Model
{
    protected $table = 'JobSeeker_Education';

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
    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }
}
