<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = WorkoutSession::withCount(['exercises', 'cardioEntries'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date->format('Y-m-d'),
                'finished' => $s->finished_at !== null,
                'exercise_count' => $s->exercises_count,
                'cardio_count' => $s->cardio_entries_count,
                'has_feedback' => $s->ai_feedback !== null,
            ]);

        return Inertia::render('Sessions/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
        ]);

        $session = WorkoutSession::create([
            'date' => $data['date'] ?? now()->format('Y-m-d'),
        ]);

        return redirect()->route('sessions.show', $session);
    }

    public function show(WorkoutSession $session)
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        // Last performance per machine used in this session, for "last time" prefill
        $lastByMachine = [];
        foreach ($session->exercises as $exercise) {
            $lastByMachine[$exercise->machine_id] = $this->lastPerformance($exercise->machine_id, $session->id);
        }

        // On an open session, surface the coach feedback from the most recent
        // previous session so its "next session targets" are visible while training.
        $previousFeedback = null;
        if ($session->finished_at === null) {
            $prev = WorkoutSession::whereNotNull('ai_feedback')
                ->where('id', '!=', $session->id)
                ->where('date', '<=', $session->date)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();
            if ($prev) {
                $previousFeedback = [
                    'date' => $prev->date->format('Y-m-d'),
                    'text' => $prev->ai_feedback,
                ];
            }
        }

        return Inertia::render('Sessions/Show', [
            'previousFeedback' => $previousFeedback,
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'notes' => $session->notes,
                'ai_feedback' => $session->ai_feedback,
                'plan' => $session->plan,
                'plan_prompt' => $session->plan_prompt,
                'finished' => $session->finished_at !== null,
                'exercises' => $session->exercises->map(fn ($e) => [
                    'id' => $e->id,
                    'machine_id' => $e->machine_id,
                    'machine_name' => $e->machine->name,
                    'notes' => $e->notes,
                    'sets' => $e->sets->map(fn ($s) => [
                        'id' => $s->id,
                        'set_number' => $s->set_number,
                        'weight' => $s->weight,
                        'reps' => $s->reps,
                    ]),
                ]),
                'cardio' => $session->cardioEntries->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'duration_minutes' => $c->duration_minutes,
                    'pace_seconds' => $c->pace_seconds,
                    'distance_km' => $c->distance_km,
                    'notes' => $c->notes,
                ]),
            ],
            'lastByMachine' => $lastByMachine,
        ]);
    }

    public function update(Request $request, WorkoutSession $session)
    {
        $session->update($request->validate([
            'date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]));

        return back();
    }

    public function finish(WorkoutSession $session)
    {
        $session->update(['finished_at' => now()]);

        return redirect()->route('sessions.index');
    }

    public function reopen(WorkoutSession $session)
    {
        $session->update(['finished_at' => null]);

        return back();
    }

    public function destroy(WorkoutSession $session)
    {
        $session->delete();

        return redirect()->route('sessions.index');
    }

    private function lastPerformance(int $machineId, int $excludeSessionId): ?array
    {
        $exercise = \App\Models\Exercise::where('machine_id', $machineId)
            ->where('workout_session_id', '!=', $excludeSessionId)
            ->whereHas('session')
            ->with(['sets', 'session'])
            ->get()
            ->sortByDesc(fn ($e) => $e->session->date)
            ->first();

        if (! $exercise) {
            return null;
        }

        return [
            'date' => $exercise->session->date->format('Y-m-d'),
            'sets' => $exercise->sets->map(fn ($s) => [
                'weight' => $s->weight,
                'reps' => $s->reps,
            ])->values()->all(),
        ];
    }
}
