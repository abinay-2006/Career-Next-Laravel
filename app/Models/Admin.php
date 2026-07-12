<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    protected $table = 'user_master';

    protected $primaryKey = 'UserId';

    public $timestamps = false;

    protected $fillable = [
        'UserName',
        'Password'
    ];

    protected $hidden = [
        'Password'
    ];

    public function getAuthPassword()
    {
        return $this->Password;
    }
}
