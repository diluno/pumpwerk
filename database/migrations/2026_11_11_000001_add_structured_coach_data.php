<?php

use App\Models\Machine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            // Structured plan: {summary, cardio, items: [{machine_id, name, weight, reps, sets, note}]}
            $table->json('plan_data')->nullable();
            // Targets for next time from the feedback: [{machine_id, name, weight, reps, sets}]
            $table->json('feedback_targets')->nullable();
            // Set while a coach request is running in the background
            $table->timestamp('plan_requested_at')->nullable();
            $table->timestamp('feedback_requested_at')->nullable();
            $table->text('coach_error')->nullable();
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->string('muscle_group', 20)->nullable();
        });

        // What the coach knows about the (single) athlete
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->text('goals')->nullable();
            $table->text('limitations')->nullable();
            $table->unsignedTinyInteger('days_per_week')->nullable();
            $table->unsignedSmallInteger('session_minutes')->nullable();
            // Rep range worked in; null falls back to config('coach.rep_range')
            $table->unsignedTinyInteger('reps_min')->nullable();
            $table->unsignedTinyInteger('reps_max')->nullable();
            // Running notes the coach keeps and rewrites after each feedback
            $table->text('memory')->nullable();
            $table->timestamps();
        });

        Machine::query()->each(fn (Machine $m) => $m->update(['muscle_group' => Machine::guessMuscleGroup($m->name)]));
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
        Schema::table('machines', fn (Blueprint $table) => $table->dropColumn('muscle_group'));
        Schema::table('workout_sessions', fn (Blueprint $table) => $table->dropColumn([
            'plan_data', 'feedback_targets', 'plan_requested_at', 'feedback_requested_at', 'coach_error',
        ]));
    }
};
