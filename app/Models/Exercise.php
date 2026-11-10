<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    protected $guarded = [];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(ExerciseSet::class)->orderBy('set_number');
    }

    /**
     * Exercises from sessions before the given one, newest first.
     * Requires a join on workout_sessions.
     */
    public function scopeBefore(Builder $query, WorkoutSession $session): void
    {
        $query->where('workout_sessions.id', '!=', $session->id)
            ->where('workout_sessions.date', '<=', $session->date)
            ->orderByDesc('workout_sessions.date')
            ->orderByDesc('workout_sessions.id');
    }
}
