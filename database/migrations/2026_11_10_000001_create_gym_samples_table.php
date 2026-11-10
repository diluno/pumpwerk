<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_samples', function (Blueprint $table) {
            $table->id();
            $table->string('gym', 40);
            $table->unsignedInteger('visitors');
            // Local (gym timezone) weekday 1 = Monday … 7 = Sunday and hour 0–23,
            // stored so statistics can be grouped in SQL without timezone maths.
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('hour');
            $table->dateTime('recorded_at'); // UTC

            $table->index(['gym', 'recorded_at']);
            $table->index(['gym', 'weekday', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_samples');
    }
};
