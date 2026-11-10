<script setup>
import { solid, pale } from '../bands.js';
import { num } from '../format.js';

// The coloured block that stands for a machine's weight: solid once sets are
// logged at that weight, pale when it is only what was done last time.
defineProps({
    weight: { type: Number, default: null },
    logged: { type: Boolean, default: true },
});
</script>

<template>
    <span
        class="grid h-[3.75rem] w-[4.25rem] shrink-0 place-items-center rounded-[1.375rem] text-xl font-extrabold tracking-tight transition-colors duration-300"
        :class="{ 'bg-soft text-muted': weight === null }"
        :style="weight === null ? null : logged ? solid(weight) : pale(weight)"
    >
        {{ weight === null ? '–' : num(weight) }}
    </span>
</template>
