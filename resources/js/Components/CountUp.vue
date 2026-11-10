<script setup>
import { useTween } from '../motion.js';

// A whole number that counts to its value when it changes
const props = defineProps({ value: { type: Number, required: true } });
const shown = useTween(() => props.value, 500);
</script>

<template>
    <span class="tabular-nums">{{ Math.round(shown) }}</span>
</template>
