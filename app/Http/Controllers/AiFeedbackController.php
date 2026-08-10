<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\WorkoutSession;
use App\Services\CoachService;
use App\Support\MachineWeight;

class AiFeedbackController extends Controller
{
    public function store(WorkoutSession $session, CoachService $coach)
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        $machines = Machine::pluck('name')->implode(', ');

        $prompt = "You are a knowledgeable, encouraging strength coach. Analyze today's gym session in the context of recent history.\n\n"
            ."AVAILABLE MACHINES (only suggest these — never invent a machine that isn't listed): {$machines}.\n\n"
            ."TODAY:\n{$coach->formatSession($session)}\n\n"
            .'RECENT HISTORY (newest first):'."\n"
            .$coach->formatHistory($coach->recentHistory($session))
            ."\n\nGive concise feedback in Markdown (max ~250 words): 1) What went well / notable progress, 2) Anything to watch (imbalances, stalls, jumps), 3) Concrete suggested weights/reps for the next session, only for machines from the list above. Be specific with numbers. "
            .MachineWeight::promptRule()
            .' Use headings and bullet lists, not tables.';

        $session->update(['ai_feedback' => $coach->complete($prompt)]);

        return back();
    }
}
