<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerDocument extends Model
{
    protected $table = 'jobseeker_documents';

    protected $primaryKey = 'DocId';

    public $timestamps = false;

    const CREATED_AT = 'UploadDate';

    protected $fillable = [
        'JobSeekId',
        'DocumentType',
        'FileName',
        'OriginalFileName',
        'UploadDate',
        'VerificationStatus',
    ];

    protected $casts = [
        'UploadDate' => 'datetime',
    ];

    /**
     * Relationship with JobSeeker
     */
    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class, 'JobSeekId', 'JobSeekId');
    }
}
