<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before opening hours were known, counts were also stored while the gyms
     * were closed. Those say nothing about how busy a gym is.
     */
    public function up(): void
    {
        DB::table('gym_samples')->where(fn ($q) => $q->where('hour', '<', 6)->orWhere('hour', '>=', 22))->delete();
        DB::table('gym_samples')->whereIn('weekday', [6, 7])->where(fn ($q) => $q->where('hour', '<', 8)->orWhere('hour', '>=', 20))->delete();
    }

    public function down(): void
    {
        // Deleted samples cannot be restored.
    }
};
