<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Employer extends Authenticatable
{
    protected $table = 'employer_reg';

    protected $primaryKey = 'EmployerId';

    public $timestamps = false;

    protected $fillable = [
        'CompanyName',
        'ContactPerson',
        'Address',
        'City',
        'Email',
        'Mobile',
        'Area_Work',
        'Status',
        'UserName',
        'Password',
        'Question',
        'Answer'
    ];

    protected $hidden = [
        'Password'
    ];

    public function getAuthPassword()
    {
        return $this->Password;
    }
}
