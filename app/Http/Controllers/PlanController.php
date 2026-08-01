<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlanController extends Controller
{
    public function store(Request $request, WorkoutSession $session)
    {
        $apiKey = config('services.anthropic.key');
        abort_unless($apiKey, 503, 'No Anthropic API key configured');

        $note = trim((string) $request->input('note', ''));

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
                $lines[] = "- Cardio {$c->type}: {$c->duration_minutes}min".($c->distance_km ? ", {$c->distance_km}km" : '');
            }
            if ($s->ai_feedback) {
                $lines[] = "Coach feedback after this session:\n{$s->ai_feedback}";
            }

            return implode("\n", $lines);
        };

        $machines = Machine::pluck('name')->implode(', ');

        $prompt = "You are a knowledgeable strength coach planning today's gym session.\n\n"
            ."AVAILABLE MACHINES: {$machines}. Cardio options: laufband (treadmill), crosstrainer, bike, rowing, stairs.\n\n"
            .'RECENT SESSIONS (newest first):'."\n"
            .$history->map($format)->implode("\n\n")
            .($note !== '' ? "\n\nTODAY'S REQUEST FROM THE ATHLETE (must be respected): {$note}" : '')
            ."\n\nWrite today's plan in Markdown (max ~200 words): an ordered list of machines with target weight × reps × sets"
            .' (progress sensibly from recent history), plus a cardio suggestion. If the athlete mentioned pain or a limitation,'
            .' avoid aggravating exercises and say what you swapped and why in one short line. No preamble.';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-5',
            'max_tokens' => 4000, // the model spends part of this on a thinking block
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        abort_unless($response->successful(), 502, 'Anthropic API error: '.$response->status());

        $text = collect($response->json('content'))->firstWhere('type', 'text')['text'] ?? null;
        abort_unless($text, 502, 'Anthropic returned no text');

        $session->update(['plan' => $text, 'plan_prompt' => $note ?: null]);

        return back();
    }
}
