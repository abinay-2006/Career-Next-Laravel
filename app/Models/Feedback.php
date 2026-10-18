<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $primaryKey = 'FeedbackId';

    public $timestamps = false;

    protected $fillable = [
        'JobSeekId',
        'EmployerId',
        'Feedback',
        'FeedbakDate',
    ];
}
