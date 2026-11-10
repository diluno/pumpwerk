// Gym machine weight stacks: 20 lb plates starting at 30 (30, 50, 70, …),
// each combinable with one add-on plate of 2.3, 4.5, or 6.8 lb.
export const ADDONS = [2.3, 4.5, 6.8];
export const BASE_MIN = 30;
export const BASE_STEP = 20;
export const BASE_MAX = 590;

const EPS = 0.05;
const round = (n) => Math.round(n * 10) / 10;

const WEIGHTS = [];
for (let base = BASE_MIN; base <= BASE_MAX; base += BASE_STEP) {
    for (const offset of [0, ...ADDONS]) WEIGHTS.push(round(base + offset));
}

// Next reachable machine weight in the given direction (+1 / -1),
// snapping onto the sequence from any starting value.
export function stepMachineWeight(current, dir) {
    const value = parseFloat(current) || 0;
    if (dir > 0) {
        return WEIGHTS.find((w) => w > value + EPS) ?? WEIGHTS[WEIGHTS.length - 1];
    }
    for (let i = WEIGHTS.length - 1; i >= 0; i--) {
        if (WEIGHTS[i] < value - EPS) return WEIGHTS[i];
    }
    return WEIGHTS[0];
}

// Split a weight into the pinned plate and the add-on plate.
// `exact` is false for weights the stack cannot produce (typed by hand, imported).
export function splitWeight(weight) {
    const value = Math.min(Math.max(parseFloat(weight) || BASE_MIN, BASE_MIN), BASE_MAX + 6.8);
    const base = Math.min(BASE_MAX, BASE_MIN + Math.floor((value - BASE_MIN + EPS) / BASE_STEP) * BASE_STEP);
    const rest = round(value - base);
    const addon = ADDONS.find((a) => Math.abs(a - rest) < EPS) ?? 0;
    return { base, addon, exact: Math.abs(round(base + addon) - round(parseFloat(weight) || 0)) < EPS };
}

export const joinWeight = (base, addon) => round(base + addon);
