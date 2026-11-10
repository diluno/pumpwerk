<script setup>
import { ref, computed, watch } from 'vue';
import { ADDONS, BASE_MIN, BASE_MAX, BASE_STEP, splitWeight, joinWeight } from '../machineWeight.js';
import { band } from '../bands.js';
import { useTween, tap } from '../motion.js';
import { num } from '../format.js';

const props = defineProps({
    modelValue: { type: Number, required: true },
    // Bump this number to play the "one rep" lift (after a set is logged)
    lift: { type: Number, default: 0 },
});
const emit = defineEmits(['update:modelValue']);

const VISIBLE = 11; // plates on screen
const PLATE = 27; // px
const PINNED = 64;
const GAP = 3;
const EDGE = 3; // plates revealed when the pin reaches the edge of the window

const split = computed(() => splitWeight(props.modelValue));
const maxStart = BASE_MAX - (VISIBLE - 1) * BASE_STEP;
const clampStart = (base) => Math.min(maxStart, Math.max(BASE_MIN, base));

// The window of plates shown. It starts around the pin and follows it:
// pinning the top or bottom plate reveals more in that direction.
const start = ref(clampStart(split.value.base - 5 * BASE_STEP));
const plates = computed(() => Array.from({ length: VISIBLE }, (_, i) => start.value + i * BASE_STEP));

watch(
    () => split.value.base,
    (base) => {
        const first = start.value;
        const last = first + (VISIBLE - 1) * BASE_STEP;
        if (base < first || base > last) start.value = clampStart(base - 5 * BASE_STEP);
        else if (base === first) start.value = clampStart(first - EDGE * BASE_STEP);
        else if (base === last) start.value = clampStart(first + EDGE * BASE_STEP);
    },
);

const pinIndex = computed(() => plates.value.indexOf(split.value.base));
const pinTop = computed(() => `${pinIndex.value * (PLATE + GAP) + PINNED / 2 - 22}px`);

function pick(base) {
    tap();
    emit('update:modelValue', joinWeight(base, split.value.addon));
}

function toggle(addon) {
    tap();
    emit('update:modelValue', joinWeight(split.value.base, split.value.addon === addon ? 0 : addon));
}

function plateStyle(base, i) {
    const b = band(base);
    const on = base <= split.value.base;
    const pinned = base === split.value.base;
    return {
        height: `${pinned ? PINNED : PLATE}px`,
        borderRadius: pinned ? '22px' : '14px',
        backgroundColor: on ? b.bg : b.pale,
        color: on ? b.on : '#15151b',
        animationDelay: `${i * 16}ms`,
    };
}

const shown = useTween(() => props.modelValue);

const lifting = ref(false);
let liftTimer;
watch(
    () => props.lift,
    () => {
        clearTimeout(liftTimer);
        lifting.value = false;
        requestAnimationFrame(() => {
            lifting.value = true;
            liftTimer = setTimeout(() => (lifting.value = false), 520 + VISIBLE * 16);
        });
    },
);
</script>

<template>
    <div :class="{ lifting }">
        <div class="mx-12 mb-1 grid grid-cols-3 gap-1" role="group" aria-label="Add-on plate">
            <button
                v-for="a in ADDONS"
                :key="a"
                class="press h-11 rounded-full font-extrabold"
                :class="split.addon === a ? 'bg-ink text-bg' : 'bg-soft text-ink'"
                :aria-pressed="split.addon === a"
                :aria-label="`Add-on plate ${a} lb`"
                @click="toggle(a)"
            >
                +{{ a }}
            </button>
        </div>

        <div class="relative mr-3.5">
            <TransitionGroup name="plate" tag="div" class="relative" role="group" aria-label="Weight stack">
                <button
                    v-for="(base, i) in plates"
                    :key="base"
                    class="plate mb-[3px] flex w-full items-center justify-between pl-[18px] font-extrabold"
                    :class="[base <= split.base ? 'plate-on' : '', base === split.base ? 'pr-9' : 'pr-[18px]']"
                    :style="plateStyle(base, i)"
                    :aria-pressed="base === split.base"
                    :aria-label="`Set pin at ${base} lb`"
                    @click="pick(base)"
                >
                    <template v-if="base === split.base">
                        <span class="text-sm font-semibold opacity-80">{{ split.exact ? '' : 'typed' }}</span>
                        <span class="flex items-baseline gap-1.5 text-[2.875rem] leading-none tracking-[-0.03em] tabular-nums">
                            {{ num(shown) }}<span class="text-lg font-semibold tracking-normal">lb</span>
                        </span>
                    </template>
                    <template v-else>
                        <span>{{ base }}</span>
                        <svg
                            v-if="(i === 0 && base > BASE_MIN) || (i === plates.length - 1 && base < BASE_MAX)"
                            class="h-4 w-4 opacity-60"
                            :class="{ 'rotate-180': i === 0 }"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                        >
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </template>
                </button>
            </TransitionGroup>

            <span
                v-if="pinIndex >= 0"
                class="pin pointer-events-none absolute -right-3.5 h-11 w-11 rounded-full border-[5px] border-bg bg-ink"
                :style="{ top: pinTop }"
                aria-hidden="true"
            />
        </div>
    </div>
</template>
