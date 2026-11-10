<?php

return [
    // Seconds to wait for the model. Coach requests run after the HTTP response
    // has been sent but still inside the PHP-FPM request, which the production
    // server kills at 60 s — stay below that.
    'timeout' => 50,

    // A request still pending after this many seconds is reported as failed.
    'stale_after' => 90,

    // Default rep range for the rule-based progression: reach the top on every
    // set, then go up a step. The athlete can set their own in the coach profile.
    'rep_range' => [12, 15],

    // What the coach sees
    'history_sessions' => 5,   // recent sessions in full
    'facts_weeks' => 12,       // window for per-machine facts
    'balance_days' => 14,      // window for the muscle-group balance

    'muscle_groups' => ['chest', 'back', 'shoulders', 'arms', 'legs', 'core', 'other'],

    // Guess a muscle group from a machine name (first match wins)
    'muscle_keywords' => [
        'legs' => ['leg', 'hip', 'abduct', 'adduct', 'calf', 'glute', 'squat'],
        'chest' => ['chest', 'pec', 'fly', 'bench'],
        'shoulders' => ['shoulder', 'delt', 'lateral raise'],
        'back' => ['pulldown', 'pull down', 'row', 'lat', 'back', 'pull up', 'pullup'],
        'arms' => ['bicep', 'tricep', 'curl', 'pushdown', 'dip'],
        'core' => ['abdominal', 'abs', 'crunch', 'core', 'rotary', 'torso'],
    ],
];
