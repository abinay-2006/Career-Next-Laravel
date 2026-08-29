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
    ];

    public function job()
    {
        return $this->belongsTo(Job::class, 'JobId', 'JobId');
    }

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }
}
