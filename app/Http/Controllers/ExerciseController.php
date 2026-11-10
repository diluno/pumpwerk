<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExerciseController extends Controller
{
    public function store(Request $request, WorkoutSession $session)
    {
        $data = $request->validate([
            'machine_id' => 'nullable|integer|exists:machines,id',
            'machine_name' => 'required_without:machine_id|nullable|string|max:100',
        ]);

        $machine = empty($data['machine_id'])
            ? Machine::findOrCreateByName($data['machine_name'])
            : Machine::findOrFail($data['machine_id']);

        $exercise = $session->exercises()->create([
            'machine_id' => $machine->id,
            'sort_order' => ($session->exercises()->max('sort_order') ?? 0) + 1,
        ]);

        // Straight to the stack: adding a machine means you are about to use it.
        return redirect()->route('exercises.show', $exercise);
    }

    /**
     * The logging screen for one machine of an open session.
     */
    public function show(Exercise $exercise)
    {
        $session = $exercise->session;
        if ($session->isFinished()) {
            return redirect()->route('sessions.show', $session);
        }

        $exercise->load(['machine', 'sets']);
        $ids = $session->exercises->pluck('id')->values();
        $index = $ids->search($exercise->id);
        $count = $ids->count();

        return Inertia::render('Sessions/Machine', [
            'session' => [
                'id' => $session->id,
                'date' => $session->date->format('Y-m-d'),
            ],
            'exercise' => [
                'id' => $exercise->id,
                'machine_id' => $exercise->machine_id,
                'machine_name' => $exercise->machine->name,
                'notes' => $exercise->notes,
                'sets' => $exercise->sets->map->only(['id', 'set_number', 'weight', 'reps']),
            ],
            'last' => $session->lastPerformances()[$exercise->machine_id] ?? null,
            'position' => $index + 1,
            'count' => $count,
            'previousId' => $count > 1 ? $ids[($index - 1 + $count) % $count] : null,
            'nextId' => $count > 1 ? $ids[($index + 1) % $count] : null,
        ]);
    }

    public function update(Request $request, Exercise $exercise)
    {
        $exercise->update($request->validate([
            'notes' => 'nullable|string|max:1000',
        ]));

        return back();
    }

    public function destroy(Exercise $exercise)
    {
        $session = $exercise->session;
        $exercise->delete();

        return redirect()->route('sessions.show', $session);
    }
}
