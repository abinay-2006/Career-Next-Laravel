<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\JobSeekerDocument;

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
    /**
 * Higher / professional education records.
 */
public function education(): HasMany
{
    return $this->hasMany(
        JobSeekerEducation::class,
        'JobSeekId',
        'JobSeekId'
    );
}

/**
 * School education records.
 */
public function schoolEducation(): HasMany
{
    return $this->hasMany(
        JobSeekerSchoolEducation::class,
        'JobSeekId',
        'JobSeekId'
    );
}

    /**
     * Uploaded Documents
     */
    /**
     * Documents uploaded by this job seeker.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(
            JobSeekerDocument::class,
            'JobSeekId',
            'JobSeekId'
        );
    }
}
