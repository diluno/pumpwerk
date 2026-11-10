<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MachineController extends Controller
{
    public function history(Machine $machine)
    {
        $exercises = $machine->exercises()
            ->with(['sets', 'session'])
            ->get()
            ->sortByDesc(fn ($e) => [$e->session->date, $e->session->id])
            ->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'session_id' => $e->workout_session_id,
                'date' => $e->session->date->format('Y-m-d'),
                'notes' => $e->notes,
                'sets' => $e->sets->map(fn ($s) => [
                    'weight' => $s->weight,
                    'reps' => $s->reps,
                ])->values(),
            ]);

        return Inertia::render('Machines/History', [
            'machine' => ['id' => $machine->id, 'name' => $machine->name, 'muscle_group' => $machine->muscle_group],
            'muscleGroups' => config('coach.muscle_groups'),
            'exercises' => $exercises,
        ]);
    }

    public function update(Request $request, Machine $machine)
    {
        $machine->update($request->validate([
            'muscle_group' => ['required', Rule::in(config('coach.muscle_groups'))],
        ]));

        return back();
    }
}
