<?php

namespace App\Http\Controllers;

use App\Jobs\RunCoach;
use App\Models\CoachProfile;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function plan(Request $request, WorkoutSession $session)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->ask($session, 'plan', trim($data['note'] ?? ''));
    }

    public function feedback(WorkoutSession $session)
    {
        return $this->ask($session, 'feedback');
    }

    /**
     * Add the planned machines that are not in the session yet, in plan order.
     */
    public function applyPlan(WorkoutSession $session)
    {
        $present = $session->exercises()->pluck('machine_id')->all();
        $order = (int) $session->exercises()->max('sort_order');

        foreach ($session->plan_data['items'] ?? [] as $item) {
            if (! in_array($item['machine_id'], $present, true)) {
                $session->exercises()->create(['machine_id' => $item['machine_id'], 'sort_order' => ++$order]);
            }
        }

        return back();
    }

    public function updateProfile(Request $request)
    {
        CoachProfile::current()->update($request->validate([
            'goals' => 'nullable|string|max:1000',
            'limitations' => 'nullable|string|max:1000',
            'days_per_week' => 'nullable|integer|min:1|max:7',
            'session_minutes' => 'nullable|integer|min:10|max:300',
            'reps_min' => 'nullable|integer|min:1|max:50|lte:reps_max',
            'reps_max' => 'nullable|integer|min:1|max:50',
            'memory' => 'nullable|string|max:1500',
        ]));

        return back();
    }

    /**
     * Start a coach request in the background. The answer (or an error) lands on
     * the session; the page polls for it.
     */
    private function ask(WorkoutSession $session, string $kind, string $note = '')
    {
        $pending = "{$kind}_requested_at";

        // A second tap while one is running changes nothing
        if (! $session->coachState()["{$kind}_pending"]) {
            $session->update([$pending => now(), 'coach_error' => null]);
            RunCoach::dispatch($session, $kind, $note)->afterResponse();
        }

        return back();
    }
}
