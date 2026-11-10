<?php

namespace App\Services;

use App\Models\CoachProfile;
use App\Models\WorkoutSession;

/**
 * What to aim for on a machine: the coach's plan if there is one, else the
 * coach's targets from the last feedback, else a plain progression rule.
 */
class Progression
{
    /**
     * Progression in the athlete's own rep range: reach the top of the range on
     * every set, then go up one step (steps are small, so aim for the top
     * again); until then keep the weight and add a rep.
     *
     * @param  array<int, array{weight: float|int, reps: int}>  $sets  what was done last time
     * @return ?array{weight: float, reps: int, sets: int, reason: string}
     */
    public function rule(array $sets): ?array
    {
        if ($sets === []) {
            return null;
        }
        [, $top] = CoachProfile::current()->repRange();

        $weight = (float) max(array_column($sets, 'weight'));
        $atTop = array_values(array_filter($sets, fn ($s) => abs($s['weight'] - $weight) < 0.05));
        $fewest = min(array_column($atTop, 'reps'));
        $count = max(1, count($sets));

        if ($fewest >= $top) {
            return [
                'weight' => MachineWeights::next($weight),
                'reps' => $top,
                'sets' => $count,
                'reason' => "You reached {$top} reps on every set, so go up a step.",
            ];
        }

        return [
            'weight' => $weight,
            'reps' => min($top, $fewest + 1),
            'sets' => $count,
            'reason' => "Same weight until every set reaches {$top} reps.",
        ];
    }

    /**
     * Targets for the machines of a session, keyed by machine id.
     *
     * @param  array<int, array{date: string, sets: array}>  $last  WorkoutSession::lastPerformances()
     * @return array<int, array{weight: float, reps: int, sets: int, source: string, reason: ?string}>
     */
    public function targets(WorkoutSession $session, array $last): array
    {
        $targets = [];

        // 3. The rule, from what was done last time
        foreach ($last as $machineId => $performance) {
            if ($rule = $this->rule($performance['sets'])) {
                $targets[$machineId] = $rule + ['source' => 'rule'];
            }
        }

        // 2. The coach's "next time" from the latest feedback, unless the machine
        //    has been trained again since that feedback was written
        $feedback = WorkoutSession::whereNotNull('feedback_targets')->before($session)->first();
        foreach ($feedback?->feedback_targets ?? [] as $item) {
            $id = $item['machine_id'];
            if (! isset($last[$id]) || $last[$id]['date'] <= $feedback->date->format('Y-m-d')) {
                $targets[$id] = $this->shape($item, 'feedback', 'From the coach’s feedback on '.$feedback->date->format('j M').'.');
            }
        }

        // 1. Today's plan
        foreach ($session->plan_data['items'] ?? [] as $item) {
            $targets[$item['machine_id']] = $this->shape($item, 'plan', $item['note'] ?? null);
        }

        return $targets;
    }

    private function shape(array $item, string $source, ?string $reason): array
    {
        return [
            'weight' => (float) $item['weight'],
            'reps' => (int) $item['reps'],
            'sets' => (int) $item['sets'],
            'source' => $source,
            'reason' => $reason ?: null,
        ];
    }
}
