<?php

namespace App\Http\Controllers;

use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Http;

class AiFeedbackController extends Controller
{
    public function store(WorkoutSession $session)
    {
        $apiKey = config('services.anthropic.key');
        abort_unless($apiKey, 503, 'No Anthropic API key configured');

        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        // Recent history for context: last 8 sessions before this one
        $history = WorkoutSession::with(['exercises.machine', 'exercises.sets', 'cardioEntries'])
            ->where('id', '!=', $session->id)
            ->where('date', '<=', $session->date)
            ->orderByDesc('date')
            ->limit(8)
            ->get();

        $format = function (WorkoutSession $s) {
            $lines = ["Session {$s->date->format('Y-m-d')}:"];
            foreach ($s->exercises as $e) {
                $sets = $e->sets->map(fn ($set) => "{$set->weight}kg x {$set->reps}")->implode(', ');
                $lines[] = "- {$e->machine->name}: {$sets}".($e->notes ? " (note: {$e->notes})" : '');
            }
            foreach ($s->cardioEntries as $c) {
                $pace = $c->pace_seconds ? gmdate('i:s', $c->pace_seconds).'/km' : null;
                $lines[] = "- Cardio {$c->type}: {$c->duration_minutes}min".($c->distance_km ? ", {$c->distance_km}km" : '').($pace ? ", pace {$pace}" : '');
            }
            if ($s->notes) {
                $lines[] = "Notes: {$s->notes}";
            }

            return implode("\n", $lines);
        };

        $prompt = "You are a knowledgeable, encouraging strength coach. Analyze today's gym session in the context of recent history.\n\n"
            ."TODAY:\n{$format($session)}\n\n"
            .'RECENT HISTORY (newest first):'."\n"
            .$history->map($format)->implode("\n\n")
            ."\n\nGive concise feedback in Markdown (max ~250 words): 1) What went well / notable progress, 2) Anything to watch (imbalances, stalls, jumps), 3) Concrete suggested weights/reps per machine for the next session. Be specific with numbers.";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-5',
            'max_tokens' => 4000, // the model spends part of this on a thinking block
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        abort_unless($response->successful(), 502, 'Anthropic API error: '.$response->status());

        // Content may lead with a thinking block; take the first text block.
        $text = collect($response->json('content'))->firstWhere('type', 'text')['text'] ?? null;
        abort_unless($text, 502, 'Anthropic returned no text');

        $session->update(['ai_feedback' => $text]);

        return back();
    }
}
