<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'application_master';

    protected $primaryKey = 'ApplicationId';

    public $timestamps = false;

    protected $fillable = [
        'JobSeekId',
        'JobId',
        'Status',
        'Description',
        'ApplicationDate',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class, 'JobId', 'JobId');
    }

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    /**
     * Follow-up activity the seeker recorded themselves. Employer-managed status
     * changes are not stored here.
     */
    public function followUps()
    {
        return $this->hasMany(JobSeekerApplicationFollowUp::class, 'ApplicationId', 'ApplicationId');
    }
}
