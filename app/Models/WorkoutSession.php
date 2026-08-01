<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'finished_at' => 'datetime',
    ];

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('sort_order');
    }

    public function cardioEntries(): HasMany
    {
        return $this->hasMany(CardioEntry::class)->orderBy('sort_order');
    }
}
