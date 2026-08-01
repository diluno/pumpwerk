<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_full_session_flow(): void
    {
        $this->actingAs($this->user);

        $machine = Machine::create(['slug' => 'chest-press', 'name' => 'Chest Press']);

        // Create session
        $response = $this->post('/sessions', ['date' => '2026-08-02']);
        $session = WorkoutSession::first();
        $response->assertRedirect(route('sessions.show', $session));
        $this->assertSame('2026-08-02', $session->date->format('Y-m-d'));

        // Add exercise + sets
        $this->post("/sessions/{$session->id}/exercises", ['machine_id' => $machine->id]);
        $exercise = $session->exercises()->first();
        $this->assertNotNull($exercise);

        $this->post("/exercises/{$exercise->id}/sets", ['weight' => 40, 'reps' => 12])->assertRedirect();
        $this->post("/exercises/{$exercise->id}/sets", ['weight' => 45, 'reps' => 10])->assertRedirect();
        $this->assertSame([1, 2], $exercise->sets()->pluck('set_number')->all());

        // Deleting the first set renumbers the rest
        $first = $exercise->sets()->first();
        $this->delete("/sets/{$first->id}");
        $this->assertSame([1], $exercise->sets()->pluck('set_number')->all());

        // Finish and reopen
        $this->post("/sessions/{$session->id}/finish")->assertRedirect(route('sessions.index'));
        $this->assertNotNull($session->fresh()->finished_at);
        $this->post("/sessions/{$session->id}/reopen");
        $this->assertNull($session->fresh()->finished_at);

        // Session list renders
        $this->get('/')->assertOk();
        $this->get("/sessions/{$session->id}")->assertOk();
    }

    public function test_invalid_session_date_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->from('/')
            ->post('/sessions', ['date' => 'not-a-date'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('date');

        $this->assertSame(0, WorkoutSession::count());
    }

    public function test_invalid_set_values_are_rejected(): void
    {
        $this->actingAs($this->user);
        $machine = Machine::create(['slug' => 'row', 'name' => 'Row']);
        $session = WorkoutSession::create(['date' => '2026-08-02']);
        $exercise = $session->exercises()->create(['machine_id' => $machine->id]);

        $this->post("/exercises/{$exercise->id}/sets", ['weight' => -5, 'reps' => 10])
            ->assertSessionHasErrors('weight');
        $this->post("/exercises/{$exercise->id}/sets", ['weight' => 50, 'reps' => 'many'])
            ->assertSessionHasErrors('reps');
        $this->assertSame(0, $exercise->sets()->count());
    }
}
