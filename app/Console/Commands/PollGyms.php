<?php

namespace App\Console\Commands;

use App\Services\GymService;
use Illuminate\Console\Command;

class PollGyms extends Command
{
    protected $signature = 'gyms:poll';

    protected $description = 'Store the current visitor count of every gym (scheduled every 10 minutes)';

    public function handle(GymService $gyms): int
    {
        $stored = $gyms->poll();
        $pruned = $gyms->prune();

        $this->info("Stored {$stored} of ".count(config('gyms.locations'))." counts, pruned {$pruned} old samples.");

        return $stored > 0 ? self::SUCCESS : self::FAILURE;
    }
}
