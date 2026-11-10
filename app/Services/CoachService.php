<?php

namespace App\Services;

use App\Exceptions\CoachException;
use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CoachService
{
    /**
     * Seconds to wait for the model. Must stay below the 60 s PHP-FPM / nginx
     * limits on the production server, or the request dies without an error message.
     */
    private const TIMEOUT = 50;

    private const WEIGHT_RULES = 'All weights are in lb; machines adjust in 20lb steps (30, 50, 70, …) plus optional'
        .' add-on plates of 2.3, 4.5, or 6.8 lb — only suggest weights reachable that way.';

    /**
     * Feedback on a session in the context of recent history.
     */
    public function feedback(WorkoutSession $session): string
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        return $this->complete(
            "You are a knowledgeable, encouraging strength coach. Analyze today's gym session in the context of recent history.\n\n"
            ."TODAY:\n{$this->formatSession($session)}\n\n"
            ."RECENT HISTORY (newest first):\n"
            .$this->formatHistory($this->recentHistory($session))
            ."\n\nGive concise feedback in Markdown (max ~250 words): 1) What went well / notable progress,"
            .' 2) Anything to watch (imbalances, stalls, jumps), 3) Concrete suggested weights/reps per machine for the next session.'
            .' Be specific with numbers. '.self::WEIGHT_RULES.' Use headings and bullet lists, not tables.'
        );
    }

    /**
     * A plan for the given (usually just started) session, optionally steered by a note.
     */
    public function plan(WorkoutSession $session, string $note = ''): string
    {
        $machines = Machine::orderBy('name')->pluck('name')->implode(', ');

        return $this->complete(
            "You are a knowledgeable strength coach planning today's gym session.\n\n"
            ."AVAILABLE MACHINES: {$machines}. Cardio options: laufband (treadmill), crosstrainer, bike, rowing, stairs.\n\n"
            ."RECENT SESSIONS (newest first):\n"
            .$this->formatHistory($this->recentHistory($session), includeFeedback: true)
            .($note !== '' ? "\n\nTODAY'S REQUEST FROM THE ATHLETE (must be respected): {$note}" : '')
            ."\n\nWrite today's plan in Markdown (max ~200 words): an ordered list of machines with target weight × reps × sets"
            .' (progress sensibly from recent history), plus a cardio suggestion. '.self::WEIGHT_RULES
            .' If the athlete mentioned pain or a limitation, avoid aggravating exercises and say what you swapped and why in one short line.'
            .' No preamble. Use bullet or numbered lists, not tables.'
        );
    }

    /**
     * The last N sessions (finished or not) before, and excluding, the given one.
     */
    public function recentHistory(WorkoutSession $session, int $limit = 8): Collection
    {
        return WorkoutSession::with(['exercises.machine', 'exercises.sets', 'cardioEntries'])
            ->where('id', '!=', $session->id)
            ->where('date', '<=', $session->date)
            ->orderByDesc('date')
            ->orderByDesc('id')
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
     * Run a single-prompt completion.
     *
     * @throws CoachException with a user-presentable message
     */
    public function complete(string $prompt): string
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new CoachException('The coach is not set up: no OpenAI API key is configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(self::TIMEOUT)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model'),
                    'max_completion_tokens' => 4000,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (ConnectionException $e) {
            report($e);

            throw new CoachException('The coach took too long to answer. Try again in a moment.', previous: $e);
        }

        if (! $response->successful()) {
            logger()->warning('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new CoachException("The coach is unavailable right now (OpenAI error {$response->status()}). Try again in a moment.");
        }

        $text = trim((string) $response->json('choices.0.message.content'));
        if ($text === '') {
            throw new CoachException('The coach returned an empty answer. Try again.');
        }

        return $text;
    }
}
