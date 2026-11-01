<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerResume extends Model
{
    protected $table = 'jobseeker_resumes';

    protected $fillable = ['JobSeekId', 'title', 'template', 'content'];

    protected $casts = ['content' => 'array'];

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }
}
