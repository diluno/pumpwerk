<?php

namespace App\Http\Controllers;

use App\Models\WorkoutSession;
use App\Services\CoachService;

class AiFeedbackController extends Controller
{
    public function store(WorkoutSession $session, CoachService $coach)
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        $prompt = "You are a knowledgeable, encouraging strength coach. Analyze today's gym session in the context of recent history.\n\n"
            ."TODAY:\n{$coach->formatSession($session)}\n\n"
            .'RECENT HISTORY (newest first):'."\n"
            .$coach->formatHistory($coach->recentHistory($session))
            ."\n\nGive concise feedback in Markdown (max ~250 words): 1) What went well / notable progress, 2) Anything to watch (imbalances, stalls, jumps), 3) Concrete suggested weights/reps per machine for the next session. Be specific with numbers. All weights are in lb; machines adjust in 20lb steps (30, 50, 70, …) plus optional add-on plates of 2.3, 4.5, or 6.8 lb — only suggest weights reachable that way. Use headings and bullet lists, not tables.";

        $session->update(['ai_feedback' => $coach->complete($prompt)]);

        return back();
    }
}
