<?php

namespace App\Support;

/**
 * Server-side mirror of resources/js/machineWeight.js — the machine stacks
 * step in 20 lb increments from 30, each combinable with one add-on plate of
 * 2.3, 4.5, or 6.8 lb. Keep the constants in sync with the JS stepper.
 */
class MachineWeight
{
    private const OFFSETS = [0, 2.3, 4.5, 6.8];

    private const BASE_MIN = 30;

    private const BASE_STEP = 20;

    private const BASE_MAX = 590;

    /**
     * Every reachable machine weight in lb, ascending.
     *
     * @return list<float>
     */
    public static function all(): array
    {
        $weights = [];
        for ($base = self::BASE_MIN; $base <= self::BASE_MAX; $base += self::BASE_STEP) {
            foreach (self::OFFSETS as $offset) {
                $weights[] = round($base + $offset, 1);
            }
        }

        return $weights;
    }

    /**
     * A strict, unambiguous description of the only weights a machine can be
     * set to — for feeding to the AI coach so it never suggests an
     * unreachable weight (no round numbers like 40/45, no kilograms).
     */
    public static function promptRule(): string
    {
        $bases = range(self::BASE_MIN, self::BASE_MAX, self::BASE_STEP);
        $plates = array_slice(self::OFFSETS, 1); // drop the 0 offset

        // Show the exact reachable values for the first few bases as a concrete
        // example, then the rule so the model can extend it.
        $examples = [];
        foreach (array_slice($bases, 0, 3) as $base) {
            foreach (self::OFFSETS as $offset) {
                $examples[] = rtrim(rtrim(number_format($base + $offset, 1, '.', ''), '0'), '.');
            }
        }

        return 'Weights are in lb and a machine can ONLY be set to a base of '
            .implode(', ', $bases).' lb (20 lb steps), optionally plus ONE add-on plate of '
            .implode(', ', array_map(fn ($p) => rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.'), $plates))
            .' lb. So the only valid weights are: '.implode(', ', $examples).', … '
            .'Never suggest any other weight — no round numbers like 40 or 45, no kilograms. '
            .'When progressing, pick the nearest reachable weight from that list.';
    }
}
