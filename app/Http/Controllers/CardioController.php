<?php

namespace App\Http\Controllers;

use App\Models\CardioEntry;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;

class CardioController extends Controller
{
    private function rules(): array
    {
        return [
            'type' => 'sometimes|string|max:50',
            'duration_minutes' => 'required|numeric|min:0|max:1000',
            'pace_seconds' => 'nullable|integer|min:0|max:3600',
            'distance_km' => 'nullable|numeric|min:0|max:1000',
            'notes' => 'nullable|string',
        ];
    }

    public function store(Request $request, WorkoutSession $session)
    {
        $data = $request->validate($this->rules());

        $session->cardioEntries()->create($data + [
            'sort_order' => ($session->cardioEntries()->max('sort_order') ?? 0) + 1,
        ]);

        return back();
    }

    public function update(Request $request, CardioEntry $cardio)
    {
        $cardio->update($request->validate($this->rules()));

        return back();
    }

    public function destroy(CardioEntry $cardio)
    {
        $cardio->delete();

        return back();
    }
}
