<script setup>
import { band } from '../bands.js';

// A session at a glance: one bar per machine, longer and redder for heavier.
defineProps({ weights: { type: Array, required: true } });
</script>

<template>
    <span class="flex gap-[3px]" aria-hidden="true">
        <span
            v-for="(w, i) in weights"
            :key="i"
            class="grow-x h-2 rounded-full"
            :style="{ width: `${Math.max(6, Math.round(w / 6))}px`, backgroundColor: band(w).bg, '--i': i }"
        />
    </span>
</template>
