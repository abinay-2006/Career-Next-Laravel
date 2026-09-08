<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news_master';

    protected $primaryKey = 'NewsId';

    public $timestamps = false;

    protected $fillable = [
        'News',
        'NewsDate',
    ];
}
