<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MachineController extends Controller
{
    // JSON list for the machine picker: ordered by usage recency + frequency
    public function picker()
    {
        $machines = Machine::query()
            ->leftJoin('exercises', 'exercises.machine_id', '=', 'machines.id')
            ->leftJoin('workout_sessions', 'workout_sessions.id', '=', 'exercises.workout_session_id')
            ->groupBy('machines.id', 'machines.name')
            ->select('machines.id', 'machines.name')
            ->selectRaw('COUNT(exercises.id) as use_count')
            ->selectRaw('MAX(workout_sessions.date) as last_used')
            ->orderByDesc('last_used')
            ->orderByDesc('use_count')
            ->get();

        return response()->json($machines);
    }

    public function history(Machine $machine)
    {
        $exercises = $machine->exercises()
            ->with(['sets', 'session'])
            ->get()
            ->sortByDesc(fn ($e) => $e->session->date)
            ->values()
            ->map(fn ($e) => [
                'session_id' => $e->workout_session_id,
                'date' => $e->session->date->format('Y-m-d'),
                'notes' => $e->notes,
                'sets' => $e->sets->map(fn ($s) => [
                    'weight' => $s->weight,
                    'reps' => $s->reps,
                ])->values(),
            ]);

        return Inertia::render('Machines/History', [
            'machine' => ['id' => $machine->id, 'name' => $machine->name],
            'exercises' => $exercises,
        ]);
    }
}
