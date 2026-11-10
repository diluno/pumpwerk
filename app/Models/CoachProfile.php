<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What the coach knows about the athlete. Pumpwerk is single-user, so there is one row.
 */
class CoachProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'days_per_week' => 'integer',
        'session_minutes' => 'integer',
        'reps_min' => 'integer',
        'reps_max' => 'integer',
    ];

    public static function current(): self
    {
        return self::query()->first() ?? self::create();
    }

    /**
     * The rep range the athlete works in: [bottom, top].
     *
     * @return array{0: int, 1: int}
     */
    public function repRange(): array
    {
        [$bottom, $top] = config('coach.rep_range');
        $top = $this->reps_max ?: $top;
        $bottom = min($this->reps_min ?: $bottom, $top);

        return [$bottom, $top];
    }

    public function forClient(): array
    {
        [$bottom, $top] = $this->repRange();

        return $this->only(['goals', 'limitations', 'days_per_week', 'session_minutes', 'memory'])
            + ['reps_min' => $bottom, 'reps_max' => $top];
    }
}
