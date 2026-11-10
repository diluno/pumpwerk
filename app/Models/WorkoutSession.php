<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('finished_at');
    }

    /**
     * Sessions on or before this one's date, excluding this one, newest first.
     */
    public function scopeBefore(Builder $query, self $session): void
    {
        $query->where('workout_sessions.id', '!=', $session->id)
            ->where('workout_sessions.date', '<=', $session->date)
            ->orderByDesc('workout_sessions.date')
            ->orderByDesc('workout_sessions.id');
    }

    /**
     * For every machine in this session: what was done on it the previous time,
     * i.e. in the latest earlier session. Keyed by machine id.
     *
     * @return array<int, array{date: string, sets: array<int, array{weight: float, reps: int}>}>
     */
    public function lastPerformances(): array
    {
        $machineIds = $this->exercises->pluck('machine_id')->unique();
        if ($machineIds->isEmpty()) {
            return [];
        }

        return Exercise::query()
            ->select('exercises.*')
            ->join('workout_sessions', 'workout_sessions.id', '=', 'exercises.workout_session_id')
            ->whereIn('exercises.machine_id', $machineIds)
            ->whereHas('sets')
            ->before($this)
            ->with('session:id,date')
            ->get()
            ->unique('machine_id')
            ->load('sets')
            ->mapWithKeys(fn ($e) => [$e->machine_id => [
                'date' => $e->session->date->format('Y-m-d'),
                'sets' => $e->sets->map->only(['weight', 'reps'])->all(),
            ]])
            ->all();
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
