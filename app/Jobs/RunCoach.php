<?php

namespace App\Jobs;

use App\Exceptions\CoachException;
use App\Models\CoachProfile;
use App\Models\WorkoutSession;
use App\Services\CoachService;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * One coach request (a plan or feedback) for a session. Dispatched after the
 * HTTP response, so the athlete keeps logging while the coach thinks; the page
 * polls the session's coach state until the answer is stored.
 */
class RunCoach
{
    use Dispatchable;

    public function __construct(
        public WorkoutSession $session,
        public string $kind, // 'plan' | 'feedback'
        public string $note = '',
    ) {}

    public function handle(CoachService $coach): void
    {
        $pending = "{$this->kind}_requested_at";

        try {
            $this->kind === 'plan' ? $this->plan($coach) : $this->feedback($coach);
        } catch (CoachException $e) {
            $this->session->update(['coach_error' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $this->session->update(['coach_error' => 'Something went wrong while asking the coach. Try again.']);
        } finally {
            $this->session->update([$pending => null]);
        }
    }

    private function plan(CoachService $coach): void
    {
        $plan = $coach->plan($this->session, $this->note);

        $this->session->update([
            'plan_data' => $plan,
            'plan' => $plan['summary'] ?: null,
            'plan_prompt' => $this->note ?: null,
        ]);
    }

    private function feedback(CoachService $coach): void
    {
        $result = $coach->feedback($this->session);

        $this->session->update([
            'ai_feedback' => $result['feedback'],
            'feedback_targets' => $result['targets'],
        ]);
        // The coach's running notes only move forward: feedback regenerated for
        // an older session must not overwrite what it has learned since.
        $latestId = WorkoutSession::orderByDesc('date')->orderByDesc('id')->value('id');
        if ($result['memory'] !== '' && $this->session->id === $latestId) {
            CoachProfile::current()->update(['memory' => $result['memory']]);
        }
    }
}
