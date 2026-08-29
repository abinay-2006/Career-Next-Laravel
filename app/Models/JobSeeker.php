<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class JobSeeker extends Authenticatable
{
    protected $table = 'jobseeker_reg';

    protected $primaryKey = 'JobSeekId';

    public $timestamps = false;

    protected $fillable = [
        'JobSeekerName',
        'Address',
        'City',
        'Email',
        'Mobile',
        'Qualification',
        'Gender',
        'BirthDate',
        'Resume',
        'Status',
        'UserName',
        'Password',
        'Question',
        'Answer',
        'Photo',
    ];

    protected $hidden = [
        'Password'
    ];

    public function getAuthPassword()
    {
        return $this->Password;
    }

    /**
     * Higher Education
     */
    public function educations()
    {
        return $this->hasMany(JobSeekerEducation::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * School Education
     */
    public function schoolEducations()
    {
        return $this->hasMany(JobSeekerSchoolEducation::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * Uploaded Documents
     */
    public function documents()
    {
        return $this->hasMany(JobSeekerDocument::class, 'JobSeekId', 'JobSeekId');
    }
}
