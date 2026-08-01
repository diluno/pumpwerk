<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use Inertia\Inertia;

class ProgressionController extends Controller
{
    public function index()
    {
        $machines = Machine::with(['exercises.sets', 'exercises.session'])
            ->get()
            ->filter(fn ($m) => $m->exercises->isNotEmpty())
            ->map(function ($m) {
                $points = $m->exercises
                    ->filter(fn ($e) => $e->sets->isNotEmpty() && $e->session)
                    ->sortBy(fn ($e) => $e->session->date)
                    ->map(fn ($e) => [
                        'date' => $e->session->date->format('Y-m-d'),
                        'top_weight' => $e->sets->max('weight'),
                        'volume' => $e->sets->sum(fn ($s) => $s->weight * $s->reps),
                        'best_set' => $e->sets->sortByDesc(fn ($s) => $s->weight * (1 + $s->reps / 30))->first()?->only(['weight', 'reps']),
                    ])
                    ->values();

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'sessions' => $points->count(),
                    'points' => $points,
                ];
            })
            ->sortByDesc('sessions')
            ->values();

        return Inertia::render('Progression', ['machines' => $machines]);
    }
}
