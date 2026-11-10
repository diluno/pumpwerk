<script setup>
import { solid } from '../bands.js';
import { num } from '../format.js';

// Coach targets as rows: weight chip in its band colour, machine, reps × sets.
defineProps({
    items: { type: Array, required: true }, // [{ name, weight, reps, sets, note? }]
    onInk: Boolean, // rendered on the black coach card
});
</script>

<template>
    <ul class="space-y-1.5">
        <li v-for="(item, i) in items" :key="item.machine_id ?? i" class="rise flex items-start gap-2.5" :style="{ '--i': i }">
            <span class="mt-px w-[3.875rem] shrink-0 rounded-xl py-1 text-center font-extrabold tabular-nums" :style="solid(item.weight)">{{ num(item.weight) }}</span>
            <span class="min-w-0 flex-1 py-1 leading-snug">
                <span class="font-semibold">{{ item.name }}</span>, {{ item.sets }} × {{ item.reps }}
                <span v-if="item.note" class="block text-[0.9375rem]" :class="onInk ? 'text-faint' : 'text-muted'">{{ item.note }}</span>
            </span>
        </li>
    </ul>
</template>
