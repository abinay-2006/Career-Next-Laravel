<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Walkin extends Model
{
    protected $table = 'walkin_master';

    protected $primaryKey = 'WalkInId';

    public $timestamps = false;

    protected $fillable = [
        'CompanyName',
        'JobTitle',
        'Vacancy',
        'MinQualification',
        'Description',
        'InterviewDate',
        'InterviewTime',
    ];
}
