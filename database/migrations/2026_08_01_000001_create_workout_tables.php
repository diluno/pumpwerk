<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->decimal('weight', 6, 2);
            $table->unsignedInteger('reps');
            $table->timestamps();
        });

        Schema::create('cardio_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('laufband');
            $table->decimal('duration_minutes', 6, 1);
            $table->unsignedInteger('pace_seconds')->nullable();
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardio_entries');
        Schema::dropIfExists('exercise_sets');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('workout_sessions');
        Schema::dropIfExists('machines');
    }
};
