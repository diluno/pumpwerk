<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetController extends Controller
{
    public function store(Request $request, Exercise $exercise)
    {
        $data = $request->validate([
            'weight' => 'required|numeric|min:0|max:2000',
            'reps' => 'required|integer|min:0|max:1000',
        ]);

        $exercise->sets()->create([
            'set_number' => ($exercise->sets()->max('set_number') ?? 0) + 1,
            'weight' => $data['weight'],
            'reps' => $data['reps'],
        ]);

        return back();
    }

    public function update(Request $request, ExerciseSet $set)
    {
        $set->update($request->validate([
            'weight' => 'sometimes|numeric|min:0|max:2000',
            'reps' => 'sometimes|integer|min:0|max:1000',
        ]));

        return back();
    }

    public function destroy(ExerciseSet $set)
    {
        DB::transaction(function () use ($set) {
            $exercise = $set->exercise;
            $set->delete();

            // Renumber the remaining sets
            $exercise->sets()->get()->each(
                fn ($s, $i) => $s->update(['set_number' => $i + 1])
            );
        });

        return back();
    }
}
