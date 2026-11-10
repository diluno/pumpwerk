<?php

use Illuminate\Support\Facades\Schedule;

// Sample gym visitor counts between the earliest opening and the latest closing;
// the command itself skips gyms that are closed. Needs the scheduler cron
// on the server: * * * * * php artisan schedule:run
Schedule::command('gyms:poll')
    ->everyTenMinutes()
    ->timezone(config('gyms.timezone'))
    ->between(config('gyms.hours.0').':00', config('gyms.hours.1').':00')
    ->withoutOverlapping();
