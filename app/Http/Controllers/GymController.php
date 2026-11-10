<?php

namespace App\Http\Controllers;

use App\Services\GymService;
use Inertia\Inertia;

class GymController extends Controller
{
    public function index(GymService $gyms)
    {
        $now = $gyms->localNow();

        return Inertia::render('Gyms', [
            // Live counts may need a request to the gyms, so they arrive after the page.
            'gyms' => Inertia::defer(fn () => $gyms->current()),
            'statistics' => $gyms->statistics(),
            'hours' => range(config('gyms.hours.0'), config('gyms.hours.1') - 1),
            'weeks' => config('gyms.stats_weeks'),
            'now' => ['weekday' => $now->dayOfWeekIso, 'hour' => $now->hour],
        ]);
    }
}
