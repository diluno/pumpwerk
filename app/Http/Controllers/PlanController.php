<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\WorkoutSession;
use App\Services\CoachService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function store(Request $request, WorkoutSession $session, CoachService $coach)
    {
        $note = trim((string) $request->input('note', ''));

        $machines = Machine::pluck('name')->implode(', ');

        $prompt = "You are a knowledgeable strength coach planning today's gym session.\n\n"
            ."AVAILABLE MACHINES: {$machines}. Cardio options: laufband (treadmill), crosstrainer, bike, rowing, stairs.\n\n"
            .'RECENT SESSIONS (newest first):'."\n"
            .$coach->formatHistory($coach->recentHistory($session), includeFeedback: true)
            .($note !== '' ? "\n\nTODAY'S REQUEST FROM THE ATHLETE (must be respected): {$note}" : '')
            ."\n\nWrite today's plan in Markdown (max ~200 words): an ordered list of machines with target weight × reps × sets"
            .' (progress sensibly from recent history; weights are in lb and machines adjust in 20lb steps from 30 plus'
            .' optional 2.3/4.5/6.8 lb add-on plates — only suggest reachable weights), plus a cardio suggestion. If the athlete mentioned pain or a limitation,'
            .' avoid aggravating exercises and say what you swapped and why in one short line. No preamble.'
            .' Use bullet or numbered lists, not tables.';

        $session->update([
            'plan' => $coach->complete($prompt),
            'plan_prompt' => $note ?: null,
        ]);

        return back();
    }
}
