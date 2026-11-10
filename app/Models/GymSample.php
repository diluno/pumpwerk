<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymSample extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'visitors' => 'integer',
        'weekday' => 'integer',
        'hour' => 'integer',
        'recorded_at' => 'datetime',
    ];
}
