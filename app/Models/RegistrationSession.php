<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationSession extends Model
{
    protected $table = 'registration_sessions';

    protected $fillable = [
        'account_type',
        'name',
        'username',
        'email',
        'mobile',
        'password',
        'registration_data',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'otp_attempts',
        'otp_last_sent_at',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    protected $casts = [
        'registration_data' => 'array',
        'otp_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'otp_last_sent_at' => 'datetime',
    ];
}
