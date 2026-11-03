<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedJob extends Model
{
    protected $table = 'saved_jobs';

    protected $primaryKey = 'SavedJobId';

    protected $fillable = ['JobSeekId', 'JobId'];

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'JobId', 'JobId');
    }
}
