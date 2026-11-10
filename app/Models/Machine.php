<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Machine extends Model
{
    protected $guarded = [];

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /**
     * Find a machine by (case- and punctuation-insensitive) name, or create it.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = Str::squish($name);
        // Names without any sluggable characters (e.g. "#1") still need a unique slug.
        $slug = Str::slug($name) ?: 'machine-'.substr(md5(Str::lower($name)), 0, 8);

        return self::firstOrCreate(['slug' => $slug], ['name' => $name, 'muscle_group' => self::guessMuscleGroup($name)]);
    }

    /**
     * A first guess at the muscle group from the machine's name; editable afterwards.
     */
    public static function guessMuscleGroup(string $name): string
    {
        $name = Str::lower($name);
        foreach (config('coach.muscle_keywords') as $group => $keywords) {
            if (Str::contains($name, $keywords)) {
                return $group;
            }
        }

        return 'other';
    }

    /**
     * All machines for the picker, most recently used first.
     *
     * @return Collection<int, array{id: int, name: string, use_count: int, last_used: ?string}>
     */
    public static function forPicker(): Collection
    {
        return self::query()
            ->leftJoin('exercises', 'exercises.machine_id', '=', 'machines.id')
            ->leftJoin('workout_sessions', 'workout_sessions.id', '=', 'exercises.workout_session_id')
            ->groupBy('machines.id', 'machines.name')
            ->select('machines.id', 'machines.name')
            ->selectRaw('COUNT(exercises.id) as use_count')
            ->selectRaw('MAX(workout_sessions.date) as last_used')
            ->orderByDesc('last_used')
            ->orderByDesc('use_count')
            ->orderBy('machines.name')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'use_count' => (int) $m->use_count,
                'last_used' => $m->last_used ? Carbon::parse($m->last_used)->format('Y-m-d') : null,
            ]);
    }
}
