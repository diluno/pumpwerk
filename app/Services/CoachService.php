<?php

namespace App\Services;

use App\Exceptions\CoachException;
use App\Models\CoachProfile;
use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CoachService
{
    private const SYSTEM = <<<'TXT'
        You are a knowledgeable, encouraging strength coach for one athlete who trains on gym machines.
        You get computed facts about their training, their recent sessions, and what you know about them.

        Rules:
        - All weights are in lb. Machines adjust in 20 lb plates (30, 50, 70, …) plus one optional add-on plate of 2.3, 4.5 or 6.8 lb. Only suggest weights reachable that way.
        - Refer to machines by their exact name from the list of available machines. Never invent a machine.
        - Progress sensibly: small steps, and only after the athlete has earned them. Respect pain and limitations.
        - Be specific and brief. No preamble, no filler, no tables.
        TXT;

    public function __construct(private Progression $progression) {}

    /**
     * A plan for the given (usually just started) session, optionally steered by a note.
     *
     * @return array{summary: string, cardio: string, items: array<int, array{machine_id: int, name: string, weight: float, reps: int, sets: int, note: ?string}>}
     *
     * @throws CoachException
     */
    public function plan(WorkoutSession $session, string $note = ''): array
    {
        $machines = Machine::orderBy('name')->get()
            ->map(fn ($m) => "{$m->name} [{$m->muscle_group}]")->implode(', ');

        $answer = $this->complete(
            "Plan today's gym session ({$session->date->format('l j F Y')}).\n\n"
            .$this->context($session)
            ."\n\nAVAILABLE MACHINES: {$machines}.\nCardio options: laufband (treadmill), crosstrainer, bike, rowing, stairs."
            .($note !== '' ? "\n\nTODAY'S REQUEST FROM THE ATHLETE (must be respected): {$note}" : '')
            ."\n\nReturn the machines in the order to do them, each with target weight, reps and number of sets."
            .' `note` is at most one short sentence and only when there is something to say (a swap, a caution, why it changed); otherwise an empty string.'
            .' `summary` is one or two sentences on the idea of the session. `cardio` is one short suggestion.'
            .' Fit the session length the athlete has.',
            'plan',
            [
                'summary' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => $this->itemSchema(withNote: true)],
                'cardio' => ['type' => 'string'],
            ],
        );

        $items = $this->normalizeItems($answer['items'] ?? []);
        if ($items === []) {
            throw new CoachException('The coach did not come up with a usable plan. Try again.');
        }

        return [
            'summary' => trim((string) ($answer['summary'] ?? '')),
            'cardio' => trim((string) ($answer['cardio'] ?? '')),
            'items' => $items,
        ];
    }

    /**
     * Feedback on a session, targets for next time, and the coach's updated notes.
     *
     * @return array{feedback: string, targets: array<int, array{machine_id: int, name: string, weight: float, reps: int, sets: int}>, memory: string}
     *
     * @throws CoachException
     */
    public function feedback(WorkoutSession $session): array
    {
        $session->load(['exercises.machine', 'exercises.sets', 'cardioEntries']);

        $answer = $this->complete(
            "Review today's gym session.\n\n"
            ."TODAY:\n{$this->formatSession($session)}"
            .($session->plan_data ? "\n\nTHE PLAN FOR TODAY WAS:\n".$this->formatItems($session->plan_data['items']) : '')
            ."\n\n".$this->context($session)
            ."\n\n`feedback` is Markdown, at most ~200 words, with short headings and bullet lists: what went well or progressed,"
            .' and anything to watch (stalls, drops, imbalance between muscle groups, jumps that were too big). Use numbers. Do not list next targets there.'
            .' `next_targets` holds one entry per machine trained today: the weight, reps and sets to aim for next time.'
            .' `memory` is your running note about this athlete for future sessions, rewritten in full (at most ~80 words):'
            .' keep what is still true from your previous note, add what you learned today, drop what is outdated. Facts and tendencies only, no pep talk.',
            'feedback',
            [
                'feedback' => ['type' => 'string'],
                'next_targets' => ['type' => 'array', 'items' => $this->itemSchema(withNote: false)],
                'memory' => ['type' => 'string'],
            ],
        );

        $feedback = trim((string) ($answer['feedback'] ?? ''));
        if ($feedback === '') {
            throw new CoachException('The coach returned an empty answer. Try again.');
        }

        return [
            'feedback' => $feedback,
            'targets' => array_map(fn ($item) => collect($item)->except('note')->all(), $this->normalizeItems($answer['next_targets'] ?? [])),
            'memory' => Str::limit(trim((string) ($answer['memory'] ?? '')), 1500, ''),
        ];
    }

    /**
     * Everything the coach knows going into a session: the athlete, computed
     * per-machine facts, the muscle-group balance and the latest sessions.
     */
    public function context(WorkoutSession $session): string
    {
        $window = WorkoutSession::with(['exercises.machine', 'exercises.sets', 'cardioEntries'])
            ->before($session)
            ->where('workout_sessions.date', '>=', $session->date->copy()->subWeeks(config('coach.facts_weeks')))
            ->get();

        $parts = [
            "ABOUT THE ATHLETE:\n".$this->formatProfile(CoachProfile::current()),
            'MACHINE FACTS (last '.config('coach.facts_weeks')." weeks, before today):\n".($this->machineFacts($window, $session) ?: 'No history yet.'),
            'MUSCLE GROUPS (sets in the last '.config('coach.balance_days')." days, and days since last trained):\n".$this->balance($window, $session),
            "RECENT SESSIONS (newest first):\n".($window->take(config('coach.history_sessions'))
                ->map(fn ($s) => $this->formatSession($s, includeFeedback: false))->implode("\n\n") ?: 'None.'),
        ];

        return implode("\n\n", $parts);
    }

    public function formatSession(WorkoutSession $s, bool $includeFeedback = false): string
    {
        $lines = ["Session {$s->date->format('Y-m-d')}:"];
        foreach ($s->exercises as $e) {
            $sets = $e->sets->map(fn ($set) => "{$set->weight}lb x {$set->reps}")->implode(', ');
            $lines[] = "- {$e->machine->name}: ".($sets ?: 'no sets').($e->notes ? " (note: {$e->notes})" : '');
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

    private function formatProfile(CoachProfile $profile): string
    {
        [$bottom, $top] = $profile->repRange();
        $lines = array_filter([
            "Works in a rep range of {$bottom} to {$top} reps per set and moves up in weight once every set reaches {$top}. Keep targets inside that range.",
            $profile->goals ? "Goals: {$profile->goals}" : null,
            $profile->limitations ? "Injuries and limitations: {$profile->limitations}" : null,
            $profile->days_per_week ? "Trains about {$profile->days_per_week} days a week." : null,
            $profile->session_minutes ? "A session lasts about {$profile->session_minutes} minutes." : null,
            $profile->memory ? "Your own notes from earlier sessions: {$profile->memory}" : null,
        ]);

        return implode("\n", $lines);
    }

    private function formatItems(array $items): string
    {
        return collect($items)->map(fn ($i) => "- {$i['name']}: {$i['weight']}lb x {$i['reps']} x {$i['sets']} sets")->implode("\n");
    }

    /**
     * One line of computed facts per machine: how often, how recently, where it
     * started and stands, whether it has stalled, and what the plain rule says.
     */
    private function machineFacts(Collection $sessions, WorkoutSession $today): string
    {
        // Newest first, so [0] is the latest time on each machine
        $byMachine = $sessions->flatMap(fn ($s) => $s->exercises->map(fn ($e) => ['session' => $s, 'exercise' => $e]))
            ->filter(fn ($row) => $row['exercise']->sets->isNotEmpty())
            ->groupBy(fn ($row) => $row['exercise']->machine_id);

        return $byMachine->map(function (Collection $rows) use ($today) {
            $machine = $rows[0]['exercise']->machine;
            $top = fn ($row) => $row['exercise']->sets->sortByDesc(fn ($s) => [$s->weight, $s->reps])->first();
            $label = fn ($set) => "{$set->weight}x{$set->reps}";

            $latest = $rows[0];
            $latestSets = $latest['exercise']->sets->map(fn ($s) => ['weight' => $s->weight, 'reps' => $s->reps])->all();
            $days = (int) $latest['session']->date->diffInDays($today->date);
            $best = $rows->map($top)->sortByDesc(fn ($s) => [$s->weight, $s->reps])->first();
            $recentTops = $rows->take(3)->map(fn ($row) => $label($top($row)));
            $stalled = $rows->count() >= 3 && $recentTops->unique()->count() === 1;
            $rule = $this->progression->rule($latestSets);

            return "- {$machine->name} [{$machine->muscle_group}]: {$rows->count()} sessions, last {$days} days ago ("
                .collect($latestSets)->map(fn ($s) => "{$s['weight']}x{$s['reps']}")->implode(', ').'). '
                ."Top weight {$top($rows->last())->weight} → {$top($latest)->weight} lb. Best set {$label($best)}. "
                .($stalled ? 'STALLED: same top set three sessions in a row. ' : '')
                .($rule ? "Plain progression rule says {$rule['weight']}x{$rule['reps']}." : '');
        })->implode("\n");
    }

    private function balance(Collection $sessions, WorkoutSession $today): string
    {
        $since = $today->date->copy()->subDays(config('coach.balance_days'));
        $sets = [];
        $lastTrained = [];
        foreach ($sessions as $s) {
            foreach ($s->exercises as $e) {
                $group = $e->machine->muscle_group ?: 'other';
                if ($e->sets->isEmpty()) {
                    continue;
                }
                $lastTrained[$group] ??= (int) $s->date->diffInDays($today->date);
                if ($s->date->gte($since)) {
                    $sets[$group] = ($sets[$group] ?? 0) + $e->sets->count();
                }
            }
        }

        return collect(config('coach.muscle_groups'))
            ->reject(fn ($g) => $g === 'other' && ! isset($lastTrained[$g]))
            ->map(fn ($g) => "- {$g}: ".($sets[$g] ?? 0).' sets, '.(isset($lastTrained[$g]) ? "last trained {$lastTrained[$g]} days ago" : 'not trained in this period'))
            ->implode("\n");
    }

    private function itemSchema(bool $withNote): array
    {
        $properties = [
            'machine' => ['type' => 'string'],
            'weight' => ['type' => 'number'],
            'reps' => ['type' => 'integer'],
            'sets' => ['type' => 'integer'],
        ] + ($withNote ? ['note' => ['type' => 'string']] : []);

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * Turn the model's items into something the app can rely on: only machines
     * that exist, each once, weights the stack can produce, sane reps and sets.
     */
    private function normalizeItems(array $items): array
    {
        $machines = Machine::all()->keyBy(fn ($m) => Str::slug($m->name));
        $clean = [];

        foreach ($items as $item) {
            $machine = $machines[Str::slug((string) ($item['machine'] ?? ''))] ?? null;
            if (! $machine || isset($clean[$machine->id]) || ! is_numeric($item['weight'] ?? null)) {
                continue;
            }
            $note = trim((string) ($item['note'] ?? ''));

            $clean[$machine->id] = [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'weight' => MachineWeights::snap((float) $item['weight']),
                'reps' => max(1, min(30, (int) ($item['reps'] ?? 10))),
                'sets' => max(1, min(6, (int) ($item['sets'] ?? 3))),
                'note' => $note !== '' ? Str::limit($note, 200) : null,
            ];
        }

        return array_values($clean);
    }

    /**
     * Ask the model for a JSON object matching the given properties.
     *
     * @throws CoachException with a user-presentable message
     */
    private function complete(string $prompt, string $name, array $properties): array
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new CoachException('The coach is not set up: no OpenAI API key is configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(config('coach.timeout'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model'),
                    'max_completion_tokens' => 4000,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => $name,
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => $properties,
                                'required' => array_keys($properties),
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            report($e);

            throw new CoachException('The coach took too long to answer. Try again in a moment.', previous: $e);
        }

        if (! $response->successful()) {
            logger()->warning('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new CoachException("The coach is unavailable right now (OpenAI error {$response->status()}). Try again in a moment.");
        }

        $answer = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($answer)) {
            logger()->warning('OpenAI answer was not JSON', ['body' => $response->body()]);

            throw new CoachException('The coach returned an answer the app could not read. Try again.');
        }

        return $answer;
    }
}
