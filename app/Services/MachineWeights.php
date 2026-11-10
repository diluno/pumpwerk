<?php

namespace App\Services;

/**
 * The weights a machine stack can produce: 20 lb plates starting at 30, each
 * combinable with one add-on plate of 2.3, 4.5 or 6.8 lb.
 * Mirrors resources/js/machineWeight.js.
 */
class MachineWeights
{
    public const ADDONS = [0, 2.3, 4.5, 6.8];

    public const MIN = 30;

    public const STEP = 20;

    public const MAX = 590;

    /** @return float[] ascending */
    public static function all(): array
    {
        static $weights = null;
        if ($weights === null) {
            $weights = [];
            for ($base = self::MIN; $base <= self::MAX; $base += self::STEP) {
                foreach (self::ADDONS as $addon) {
                    $weights[] = round($base + $addon, 1);
                }
            }
        }

        return $weights;
    }

    /**
     * The reachable weight closest to the given one.
     */
    public static function snap(float $weight): float
    {
        $best = self::all()[0];
        foreach (self::all() as $candidate) {
            if (abs($candidate - $weight) < abs($best - $weight)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * One step heavier than the given weight.
     */
    public static function next(float $weight): float
    {
        foreach (self::all() as $candidate) {
            if ($candidate > $weight + 0.05) {
                return $candidate;
            }
        }

        return self::all()[count(self::all()) - 1];
    }
}
