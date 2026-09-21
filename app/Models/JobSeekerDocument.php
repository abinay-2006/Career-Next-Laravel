<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSeekerDocument extends Model
{
    /**
     * Database table.
     */
    protected $table = 'jobseeker_documents';

    /**
     * Primary key.
     */
    protected $primaryKey = 'DocId';

    /**
     * Table does not use Laravel's created_at / updated_at columns.
     */
    public $timestamps = false;

    /**
     * Mass assignable fields.
     */
    protected $fillable = [
        'JobSeekId',
        'DocumentType',
        'FileName',
        'OriginalFileName',
        'UploadDate',
        'VerificationStatus',
        'AdminNotes',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'UploadDate' => 'datetime',
    ];

    /**
     * The job seeker who owns this document.
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
