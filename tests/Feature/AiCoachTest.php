<?php

namespace Tests\Feature;

use App\Models\CoachProfile;
use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Progression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCoachTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutSession $session;

    private Machine $chest;

    private Machine $row;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        config(['services.openai.key' => 'test-key']);

        $this->chest = Machine::create(['slug' => 'chest-press', 'name' => 'Chest Press', 'muscle_group' => 'chest']);
        $this->row = Machine::create(['slug' => 'row', 'name' => 'Row', 'muscle_group' => 'back']);

        $this->log('2026-07-30', $this->chest, [[150, 15], [150, 15], [150, 15]]);
        $this->session = WorkoutSession::create(['date' => '2026-08-02']);
    }

    private function log(string $date, Machine $machine, array $sets): WorkoutSession
    {
        $session = WorkoutSession::create(['date' => $date, 'finished_at' => now()]);
        $exercise = $session->exercises()->create(['machine_id' => $machine->id]);
        foreach ($sets as $i => [$weight, $reps]) {
            $exercise->sets()->create(['set_number' => $i + 1, 'weight' => $weight, 'reps' => $reps]);
        }

        return $session;
    }

    /** The model answers with a JSON object as message content */
    private function fakeAnswer(array $answer): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($answer)]]],
        ])]);
    }

    public function test_plan_is_structured_and_made_safe_for_the_app(): void
    {
        $this->fakeAnswer([
            'summary' => 'Push day.',
            'cardio' => '15 min bike',
            'items' => [
                ['machine' => 'chest press', 'weight' => 153, 'reps' => 10, 'sets' => 3, 'note' => 'Up a step.'],
                ['machine' => 'Cable Crossover', 'weight' => 50, 'reps' => 10, 'sets' => 3, 'note' => ''],
                ['machine' => 'Row', 'weight' => 12, 'reps' => 99, 'sets' => 20, 'note' => ''],
                ['machine' => 'Row', 'weight' => 90, 'reps' => 10, 'sets' => 3, 'note' => ''],
            ],
        ]);

        $this->post("/sessions/{$this->session->id}/plan", ['note' => 'shoulder hurts'])->assertRedirect();

        $fresh = $this->session->fresh();
        $this->assertSame('shoulder hurts', $fresh->plan_prompt);
        $this->assertNull($fresh->plan_requested_at);
        $this->assertSame('Push day.', $fresh->plan_data['summary']);
        // Unknown machine dropped, duplicate dropped, weights snapped to the stack, reps and sets clamped
        $this->assertEquals([
            ['machine_id' => $this->chest->id, 'name' => 'Chest Press', 'weight' => 152.3, 'reps' => 10, 'sets' => 3, 'note' => 'Up a step.'],
            ['machine_id' => $this->row->id, 'name' => 'Row', 'weight' => 30.0, 'reps' => 30, 'sets' => 6, 'note' => null],
        ], $fresh->plan_data['items']);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'shoulder hurts')
            && $request['model'] === config('services.openai.model')
            && $request['response_format']['json_schema']['strict'] === true
            // Computed facts, not just raw history
            && str_contains($request->body(), 'Plain progression rule says 152.3x15')
            && str_contains($request->body(), 'rep range of 12 to 15'));
    }

    public function test_applying_the_plan_adds_the_missing_machines(): void
    {
        $this->session->exercises()->create(['machine_id' => $this->row->id, 'sort_order' => 1]);
        $this->session->update(['plan_data' => ['summary' => '', 'cardio' => '', 'items' => [
            ['machine_id' => $this->chest->id, 'name' => 'Chest Press', 'weight' => 152.3, 'reps' => 10, 'sets' => 3, 'note' => null],
            ['machine_id' => $this->row->id, 'name' => 'Row', 'weight' => 90, 'reps' => 10, 'sets' => 3, 'note' => null],
        ]]]);

        $this->post("/sessions/{$this->session->id}/plan/apply")->assertRedirect();

        $this->assertSame([$this->row->id, $this->chest->id], $this->session->exercises()->pluck('machine_id')->all());

        // The plan is now the target on the machine screen
        $exercise = $this->session->exercises()->where('machine_id', $this->chest->id)->first();
        $this->get("/exercises/{$exercise->id}")->assertInertia(fn ($page) => $page
            ->where('target.source', 'plan')
            ->where('target.weight', 152.3));
    }

    public function test_feedback_stores_text_targets_and_the_coach_memory(): void
    {
        $this->session->exercises()->create(['machine_id' => $this->chest->id])
            ->sets()->create(['set_number' => 1, 'weight' => 152.3, 'reps' => 10]);
        $this->fakeAnswer([
            'feedback' => '## Solid',
            'next_targets' => [['machine' => 'Chest Press', 'weight' => 155, 'reps' => 10, 'sets' => 3]],
            'memory' => 'Responds well to small steps on chest press.',
        ]);

        $this->post("/sessions/{$this->session->id}/ai-feedback")->assertRedirect();

        $fresh = $this->session->fresh();
        $this->assertSame('## Solid', $fresh->ai_feedback);
        $this->assertSame(154.5, $fresh->feedback_targets[0]['weight']);
        $this->assertSame('Responds well to small steps on chest press.', CoachProfile::current()->memory);

        // The next session aims for the coach's target instead of the plain rule
        $next = WorkoutSession::create(['date' => '2026-08-05']);
        $next->exercises()->create(['machine_id' => $this->chest->id]);
        $this->get("/sessions/{$next->id}")->assertInertia(fn ($page) => $page
            ->where("targets.{$this->chest->id}.source", 'feedback')
            ->where("targets.{$this->chest->id}.weight", 154.5));
    }

    public function test_errors_land_on_the_session_and_clear_the_pending_state(): void
    {
        $url = "/sessions/{$this->session->id}/ai-feedback";

        Http::fake(['api.openai.com/*' => Http::response(['error' => 'boom'], 500)]);
        $this->post($url)->assertRedirect();
        $this->assertStringContainsString('OpenAI error 500', $this->session->fresh()->coach_error);

        Http::fake(['api.openai.com/*' => Http::failedConnection()]);
        $this->post("/sessions/{$this->session->id}/plan")->assertRedirect();
        $this->assertStringContainsString('too long', $this->session->fresh()->coach_error);

        config(['services.openai.key' => null]);
        $this->post($url)->assertRedirect();
        $fresh = $this->session->fresh();
        $this->assertStringContainsString('no OpenAI API key', $fresh->coach_error);
        $this->assertNull($fresh->ai_feedback);
        $this->assertNull($fresh->feedback_requested_at);
        $this->assertNull($fresh->plan_requested_at);
    }

    public function test_a_request_that_never_came_back_is_reported(): void
    {
        $this->session->update(['plan_requested_at' => now()->subMinutes(5)]);

        $this->get("/sessions/{$this->session->id}")->assertInertia(fn ($page) => $page
            ->where('session.coach.plan_pending', false)
            ->where('session.coach.error', 'The coach took too long to answer. Try again in a moment.'));
    }

    public function test_progression_rule_follows_the_athletes_rep_range(): void
    {
        $rule = app(Progression::class);
        $target = fn (array $sets) => array_values(array_slice($rule->rule($sets), 0, 2));

        // Default range tops out at 15: every set there means one step up, aiming for 15 again
        $this->assertSame([152.3, 15], $target([['weight' => 150, 'reps' => 15], ['weight' => 150, 'reps' => 15]]));
        // Otherwise the same weight and one more rep than the weakest set
        $this->assertSame([150.0, 14], $target([['weight' => 150, 'reps' => 15], ['weight' => 150, 'reps' => 13]]));
        $this->assertNull($rule->rule([]));

        // The athlete's own range wins
        $this->patch('/coach/profile', ['reps_min' => 8, 'reps_max' => 10])->assertRedirect();
        $this->assertSame([152.3, 10], $target([['weight' => 150, 'reps' => 10], ['weight' => 150, 'reps' => 11]]));
        $this->patch('/coach/profile', ['reps_min' => 12, 'reps_max' => 10])->assertSessionHasErrors('reps_min');
    }

    public function test_profile_and_muscle_group_can_be_edited(): void
    {
        $this->patch('/coach/profile', ['goals' => 'Stronger legs', 'days_per_week' => 3])->assertRedirect();
        $this->assertSame(['Stronger legs', 3], [CoachProfile::current()->goals, CoachProfile::current()->days_per_week]);

        $this->patch("/machines/{$this->row->id}", ['muscle_group' => 'legs'])->assertRedirect();
        $this->patch("/machines/{$this->row->id}", ['muscle_group' => 'wings'])->assertSessionHasErrors('muscle_group');
        $this->assertSame('legs', $this->row->fresh()->muscle_group);

        $this->assertSame('legs', Machine::guessMuscleGroup('Seated Leg Curl'));
        $this->assertSame('back', Machine::guessMuscleGroup('pulldown'));
        $this->assertSame('shoulders', Machine::guessMuscleGroup('rear delt'));
    }
}
