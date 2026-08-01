<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardioEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'duration_minutes' => 'float',
        'distance_km' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
