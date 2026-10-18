<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'logs';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'JobId',
        'action',
        'cdate',
        'actor_type',
        'actor_id',
        'target_type',
        'target_id',
        'metadata',
    ];
}
