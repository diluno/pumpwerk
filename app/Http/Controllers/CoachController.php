<?php

namespace App\Http\Controllers;

use App\Exceptions\CoachException;
use App\Models\WorkoutSession;
use App\Services\CoachService;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function feedback(WorkoutSession $session, CoachService $coach)
    {
        try {
            $session->update(['ai_feedback' => $coach->feedback($session)]);
        } catch (CoachException $e) {
            return back()->withErrors(['coach' => $e->getMessage()]);
        }

        return back();
    }

    public function plan(Request $request, WorkoutSession $session, CoachService $coach)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $note = trim($data['note'] ?? '');

        try {
            $session->update([
                'plan' => $coach->plan($session, $note),
                'plan_prompt' => $note ?: null,
            ]);
        } catch (CoachException $e) {
            return back()->withErrors(['coach' => $e->getMessage()]);
        }

        return back();
    }
}
