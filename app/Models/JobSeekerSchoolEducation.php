<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerSchoolEducation extends Model
{
    protected $table = 'JobSeeker_School_Education';

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
    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }
}
