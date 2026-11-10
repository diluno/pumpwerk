<?php

/*
|--------------------------------------------------------------------------
| Gym visitor counts
|--------------------------------------------------------------------------
|
| Fitnesspark publishes the current number of visitors per park through a
| public WordPress AJAX endpoint (plain GET, answers with a bare number).
| `gyms:poll` samples it on a schedule; the Gyms page turns the samples into
| "when is it quiet" statistics.
|
*/

return [
    // Opening hours and the statistics are in the gyms' local time.
    'timezone' => 'Europe/Zurich',

    'endpoint' => 'https://www.fitnesspark.ch/wp/wp-admin/admin-ajax.php',

    // The endpoint may reject clients that do not look like a browser.
    'user_agent' => env('GYMS_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'),

    // Hours of the day (local, start inclusive, end exclusive) shown in the charts
    // and covered by the scheduler: the earliest opening to the latest closing.
    'hours' => [6, 22],

    // A stored count older than this is refreshed when someone looks at it.
    'fresh_minutes' => 12,

    // Statistics look back this far; older samples are pruned.
    'stats_weeks' => 8,
    'keep_days' => 180,

    // Opening hours of the training area as [open, close], local time, from the
    // "Über den Park" pages on fitnesspark.ch (read 2026-11-10). Special hours on
    // public holidays are not modelled.
    'locations' => [
        'sihlcity' => [
            'name' => 'Sihlcity', 'park_id' => 704, 'location_id' => 34, 'location_name' => 'FP_Sihlcity',
            'open' => ['weekday' => ['06:30', '22:00'], 'weekend' => ['09:00', '20:00']],
        ],
        'puls5' => [
            'name' => 'Puls 5', 'park_id' => 508, 'location_id' => 29, 'location_name' => 'FP_Puls5',
            'open' => ['weekday' => ['06:00', '22:00'], 'weekend' => ['09:00', '20:00']],
        ],
        'stockerhof' => [
            'name' => 'Stockerhof', 'park_id' => 706, 'location_id' => 32, 'location_name' => 'FP_Stockerhof',
            'open' => ['weekday' => ['06:00', '22:00'], 'weekend' => ['08:00', '20:00']],
        ],
        'stadelhofen' => [
            'name' => 'Stadelhofen', 'park_id' => 702, 'location_id' => 30, 'location_name' => 'FP_Stadelhofen',
            'open' => ['weekday' => ['06:00', '22:00'], 'weekend' => ['09:00', '20:00']],
        ],
    ],
];
