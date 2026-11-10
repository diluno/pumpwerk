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

        // Editing a set
        $remaining = $exercise->sets()->first();
        $this->patch("/sets/{$remaining->id}", ['weight' => 52.3, 'reps' => 8])->assertRedirect();
        $this->assertSame([52.3, 8], [$remaining->fresh()->weight, $remaining->fresh()->reps]);

        // Finish (stays on the session) and reopen
        $this->post("/sessions/{$session->id}/finish")->assertRedirect(route('sessions.show', $session));
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

    public function test_starting_twice_on_one_day_continues_the_open_session(): void
    {
        $this->actingAs($this->user);

        $this->post('/sessions', ['date' => '2026-08-02']);
        $this->post('/sessions', ['date' => '2026-08-02'])
            ->assertRedirect(route('sessions.show', WorkoutSession::first()));
        $this->assertSame(1, WorkoutSession::count());

        // Once finished, the same day can hold another session
        WorkoutSession::first()->update(['finished_at' => now()]);
        $this->post('/sessions', ['date' => '2026-08-02']);
        $this->assertSame(2, WorkoutSession::count());
    }

    public function test_machines_are_created_by_name_without_duplicates(): void
    {
        $this->actingAs($this->user);
        $session = WorkoutSession::create(['date' => '2026-08-02']);

        $this->post("/sessions/{$session->id}/exercises", ['machine_name' => '  Leg  Press '])->assertRedirect();
        $this->post("/sessions/{$session->id}/exercises", ['machine_name' => 'leg press'])->assertRedirect();
        $this->post("/sessions/{$session->id}/exercises", ['machine_name' => '#1'])->assertRedirect();
        $this->post("/sessions/{$session->id}/exercises", [])->assertSessionHasErrors('machine_name');

        $this->assertSame(['Leg Press', '#1'], Machine::orderBy('id')->pluck('name')->all());
        $this->assertSame(3, $session->exercises()->count());
    }

    public function test_last_time_only_looks_at_earlier_sessions(): void
    {
        $this->actingAs($this->user);
        $machine = Machine::create(['slug' => 'row', 'name' => 'Row']);

        $log = function (string $date, float $weight) use ($machine) {
            $session = WorkoutSession::create(['date' => $date, 'finished_at' => now()]);
            $session->exercises()->create(['machine_id' => $machine->id])
                ->sets()->create(['set_number' => 1, 'weight' => $weight, 'reps' => 10]);

            return $session;
        };

        $log('2026-08-01', 50);
        $middle = $log('2026-08-05', 70);
        $log('2026-08-09', 90);

        $this->get("/sessions/{$middle->id}")
            ->assertInertia(fn ($page) => $page
                ->where("lastByMachine.{$machine->id}.date", '2026-08-01')
                ->where("lastByMachine.{$machine->id}.sets.0.weight", 50));
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->user);
        $machine = Machine::create(['slug' => 'row', 'name' => 'Row']);
        $session = WorkoutSession::create(['date' => '2026-08-02']);
        $session->exercises()->create(['machine_id' => $machine->id])
            ->sets()->create(['set_number' => 1, 'weight' => 50, 'reps' => 10]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('sessions.0.machines', ['Row'])
            ->where('sessions.0.set_count', 1)
            ->where('sessions.0.weights', [50])
            ->where('activeSessionId', $session->id));
        $this->get("/sessions/{$session->id}")->assertInertia(fn ($page) => $page->where('machines.0.name', 'Row'));
        $exercise = $session->exercises()->first();
        $this->get("/exercises/{$exercise->id}")->assertInertia(fn ($page) => $page
            ->component('Sessions/Machine')
            ->where('exercise.sets.0.weight', 50)
            ->where('position', 1)
            ->where('count', 1));
        $this->get("/machines/{$machine->id}")->assertOk();
        $this->get('/progression')->assertOk();
    }
}
