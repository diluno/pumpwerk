<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->text('plan')->nullable();
            $table->text('plan_prompt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_prompt']);
        });
    }
};
