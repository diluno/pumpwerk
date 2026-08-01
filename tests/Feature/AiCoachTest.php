<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCoachTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WorkoutSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->session = WorkoutSession::create(['date' => '2026-08-02']);
        $machine = Machine::create(['slug' => 'chest-press', 'name' => 'Chest Press']);
        $exercise = $this->session->exercises()->create(['machine_id' => $machine->id]);
        $exercise->sets()->create(['set_number' => 1, 'weight' => 40, 'reps' => 12]);
    }

    public function test_feedback_returns_503_without_api_key(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs($this->user)
            ->post("/sessions/{$this->session->id}/ai-feedback")
            ->assertStatus(503);
    }

    public function test_feedback_is_stored_on_success(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '## Great session']]],
            ]),
        ]);

        $this->actingAs($this->user)
            ->post("/sessions/{$this->session->id}/ai-feedback")
            ->assertRedirect();

        $this->assertSame('## Great session', $this->session->fresh()->ai_feedback);
    }

    public function test_feedback_returns_502_on_api_error(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->actingAs($this->user)
            ->post("/sessions/{$this->session->id}/ai-feedback")
            ->assertStatus(502);

        $this->assertNull($this->session->fresh()->ai_feedback);
    }

    public function test_plan_is_stored_with_note(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '1. Chest Press 42.5kg']]],
            ]),
        ]);

        $this->actingAs($this->user)
            ->post("/sessions/{$this->session->id}/plan", ['note' => 'shoulder hurts'])
            ->assertRedirect();

        $fresh = $this->session->fresh();
        $this->assertSame('1. Chest Press 42.5kg', $fresh->plan);
        $this->assertSame('shoulder hurts', $fresh->plan_prompt);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'shoulder hurts')
            && $request['model'] === config('services.openai.model'));
    }
}
