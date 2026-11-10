<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\WorkoutSession;
use App\Services\GymService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = WorkoutSession::query()
            ->select(['id', 'date', 'finished_at'])
            ->selectRaw('ai_feedback IS NOT NULL as has_feedback')
            ->with(['exercises' => fn ($q) => $q->withCount('sets')->withMax('sets', 'weight'), 'exercises.machine:id,name'])
            ->withCount('cardioEntries')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'date' => $s->date->format('Y-m-d'),
                'finished' => $s->isFinished(),
                'machines' => $s->exercises->map(fn ($e) => $e->machine->name)->values(),
                // Top weight per machine, for the coloured bars
                'weights' => $s->exercises->pluck('sets_max_weight')->filter()->map(fn ($w) => (float) $w)->values(),
                'set_count' => $s->exercises->sum('sets_count'),
                'cardio_count' => $s->cardio_entries_count,
                'has_feedback' => (bool) $s->has_feedback,
            ]);

        return Inertia::render('Sessions/Index', [
            'sessions' => $sessions,
            // Live gym counts may need a request to the gyms, so they arrive after the page.
            'gyms' => Inertia::defer(fn () => app(GymService::class)->current()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
        ]);
        $date = $data['date'] ?? now()->format('Y-m-d');

        // Starting twice on the same day (double tap, second device) continues the open session.
        $session = WorkoutSession::open()->whereDate('date', $date)->first()
            ?? WorkoutSession::create(['date' => $date]);

        return redirect()->route('sessions.show', $session);
    }

    public function show(WorkoutSession $session)
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        return Inertia::render('Sessions/Show', [
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
                'notes' => $session->notes,
                'ai_feedback' => $session->ai_feedback,
                'plan' => $session->plan,
                'plan_prompt' => $session->plan_prompt,
                'finished' => $session->isFinished(),
                'exercises' => $session->exercises->map(fn ($e) => [
                    'id' => $e->id,
                    'machine_id' => $e->machine_id,
                    'machine_name' => $e->machine->name,
                    'notes' => $e->notes,
                    'sets' => $e->sets->map->only(['id', 'set_number', 'weight', 'reps']),
                ]),
                'cardio' => $session->cardioEntries->map->only([
                    'id', 'type', 'duration_minutes', 'pace_seconds', 'distance_km', 'notes',
                ]),
            ],
            'lastByMachine' => (object) $session->lastPerformances(),
            'previousFeedback' => $session->isFinished() ? null : $this->previousFeedback($session),
            // Only an open session can add machines.
            'machines' => $session->isFinished() ? [] : Machine::forPicker(),
        ]);
    }

    public function update(Request $request, WorkoutSession $session)
    {
        $session->update($request->validate([
            'date' => 'sometimes|required|date',
            'notes' => 'sometimes|nullable|string|max:5000',
        ]));

        return back();
    }

    public function finish(WorkoutSession $session)
    {
        $session->update(['finished_at' => now()]);

        // Stay on the session: the finished view leads with the coach feedback.
        return redirect()->route('sessions.show', $session);
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

    /**
     * Coach feedback from the most recent earlier session, so its
     * "next session" targets are at hand while training.
     */
    private function previousFeedback(WorkoutSession $session): ?array
    {
        $previous = WorkoutSession::whereNotNull('ai_feedback')->before($session)->first();

        return $previous ? [
            'date' => $previous->date->format('Y-m-d'),
            'text' => $previous->ai_feedback,
        ] : null;
    }
}
