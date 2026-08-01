<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExerciseController extends Controller
{
    public function store(Request $request, WorkoutSession $session)
    {
        $data = $request->validate([
            'machine_id' => 'nullable|integer|exists:machines,id',
            'machine_name' => 'nullable|string|max:100',
        ]);

        if (empty($data['machine_id'])) {
            $name = trim($data['machine_name'] ?? '');
            abort_if($name === '', 422, 'Machine name required');
            $machine = Machine::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        } else {
            $machine = Machine::findOrFail($data['machine_id']);
        }

        $session->exercises()->create([
            'machine_id' => $machine->id,
            'sort_order' => ($session->exercises()->max('sort_order') ?? 0) + 1,
        ]);

        return back();
    }

    public function update(Request $request, Exercise $exercise)
    {
        $exercise->update($request->validate([
            'notes' => 'nullable|string',
        ]));

        return back();
    }

    public function destroy(Exercise $exercise)
    {
        $exercise->delete();

        return back();
    }
}
