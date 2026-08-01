<?php

namespace App\Console\Commands;

use App\Models\CardioEntry;
use App\Models\Exercise;
use App\Models\ExerciseSet;
use App\Models\Machine;
use App\Models\WorkoutSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

class ImportV1 extends Command
{
    protected $signature = 'import:v1 {path : Path to the v1 workout.db SQLite file}';

    protected $description = 'Import sessions, exercises, sets and cardio from the v1 workout-tracker SQLite database';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $v1 = new PDO('sqlite:'.$path);
        $v1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        DB::transaction(function () use ($v1) {
            $sessionMap = [];
            foreach ($v1->query('SELECT * FROM sessions ORDER BY date')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $session = WorkoutSession::create([
                    'date' => $row['date'],
                    'notes' => $row['notes'] ?: null,
                    'ai_feedback' => $row['ai_feedback'] ?: null,
                    'finished_at' => $row['created_at'], // v1 sessions are all historical → finished
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ]);
                $sessionMap[$row['id']] = $session->id;
            }

            $exerciseMap = [];
            foreach ($v1->query('SELECT * FROM exercises ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $machine = Machine::firstOrCreate(
                    ['slug' => Str::slug($row['name'])],
                    ['name' => $row['name']],
                );
                $exercise = Exercise::create([
                    'workout_session_id' => $sessionMap[$row['session_id']],
                    'machine_id' => $machine->id,
                    'notes' => $row['notes'] ?: null,
                    'sort_order' => $row['sort_order'],
                ]);
                $exerciseMap[$row['id']] = $exercise->id;
            }

            foreach ($v1->query('SELECT * FROM sets ORDER BY set_number')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                ExerciseSet::create([
                    'exercise_id' => $exerciseMap[$row['exercise_id']],
                    'set_number' => $row['set_number'],
                    'weight' => $row['weight'],
                    'reps' => $row['reps'],
                ]);
            }

            foreach ($v1->query('SELECT * FROM cardio ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                CardioEntry::create([
                    'workout_session_id' => $sessionMap[$row['session_id']],
                    'type' => $row['type'],
                    'duration_minutes' => $row['duration_minutes'],
                    'pace_seconds' => $row['pace_seconds'],
                    'distance_km' => $row['distance_km'],
                    'notes' => $row['notes'] ?: null,
                    'sort_order' => $row['sort_order'],
                ]);
            }
        });

        $this->info(sprintf(
            'Imported %d sessions, %d machines, %d exercises, %d sets, %d cardio entries.',
            WorkoutSession::count(), Machine::count(), Exercise::count(), ExerciseSet::count(), CardioEntry::count(),
        ));

        return self::SUCCESS;
    }
}
