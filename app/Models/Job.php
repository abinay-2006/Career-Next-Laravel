<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'job_master';

    protected $primaryKey = 'JobId';

    public $timestamps = false;

    protected $fillable = [
        'CompanyName',
        'JobTitle',
        'Vacancy',
        'SalaryFrom',
        'SalaryTo',
        'MinQualification',
        'Description',
        'Status',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'JobId', 'JobId');
    }
}
