<?php

namespace App\Services;

use App\Models\WorkoutSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CoachService
{
    /**
     * Last N finished-or-not sessions before (and excluding) the given one.
     */
    public function recentHistory(WorkoutSession $session, int $limit = 8): Collection
    {
        return WorkoutSession::with(['exercises.machine', 'exercises.sets', 'cardioEntries'])
            ->where('id', '!=', $session->id)
            ->where('date', '<=', $session->date)
            ->orderByDesc('date')
            ->limit($limit)
            ->get();
    }

    public function formatSession(WorkoutSession $s, bool $includeFeedback = false): string
    {
        $lines = ["Session {$s->date->format('Y-m-d')}:"];
        foreach ($s->exercises as $e) {
            $sets = $e->sets->map(fn ($set) => "{$set->weight}lb x {$set->reps}")->implode(', ');
            $lines[] = "- {$e->machine->name}: {$sets}".($e->notes ? " (note: {$e->notes})" : '');
        }
        foreach ($s->cardioEntries as $c) {
            $pace = $c->pace_seconds ? gmdate('i:s', $c->pace_seconds).'/km' : null;
            $lines[] = "- Cardio {$c->type}: {$c->duration_minutes}min"
                .($c->distance_km ? ", {$c->distance_km}km" : '')
                .($pace ? ", pace {$pace}" : '');
        }
        if ($s->notes) {
            $lines[] = "Notes: {$s->notes}";
        }
        if ($includeFeedback && $s->ai_feedback) {
            $lines[] = "Coach feedback after this session:\n{$s->ai_feedback}";
        }

        return implode("\n", $lines);
    }

    public function formatHistory(Collection $sessions, bool $includeFeedback = false): string
    {
        return $sessions->map(fn ($s) => $this->formatSession($s, $includeFeedback))->implode("\n\n");
    }

    /**
     * Run a single-prompt completion. Aborts with 503/502 on config or API errors.
     */
    public function complete(string $prompt): string
    {
        $apiKey = config('services.openai.key');
        abort_unless($apiKey, 503, 'No OpenAI API key configured');

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model'),
                'max_completion_tokens' => 4000,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        abort_unless($response->successful(), 502, 'OpenAI API error: '.$response->status());

        $text = $response->json('choices.0.message.content');
        abort_unless($text, 502, 'OpenAI returned no text');

        return $text;
    }
}
