// Gym machine weight stacks: 20 lb steps starting at 30 (30, 50, 70, …),
// each combinable with one add-on plate of 2.3, 4.5, or 6.8 lb.
const OFFSETS = [0, 2.3, 4.5, 6.8];
const BASE_MIN = 30;
const BASE_STEP = 20;
const BASE_MAX = 590;

const WEIGHTS = [];
for (let base = BASE_MIN; base <= BASE_MAX; base += BASE_STEP) {
    for (const offset of OFFSETS) {
        WEIGHTS.push(Math.round((base + offset) * 10) / 10);
    }
}

const EPS = 0.05;

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
