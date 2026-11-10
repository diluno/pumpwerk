<script setup>
import { useId } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    step: { type: Number, default: 1 },
    // Optional (current, dir) => next; overrides linear stepping
    stepFn: { type: Function, default: null },
    min: { type: Number, default: 0 },
    label: String,
    placeholder: { type: String, default: '0' },
});
const emit = defineEmits(['update:modelValue']);
const id = useId();

function bump(dir) {
    const current = parseFloat(props.modelValue) || 0;
    const next = props.stepFn
        ? props.stepFn(current, dir)
        : Math.max(props.min, Math.round((current + dir * props.step) * 100) / 100);
    emit('update:modelValue', next);
}
</script>

<template>
    <div>
        <label :for="id" class="label">{{ label }}</label>
        <div class="flex h-16 items-stretch rounded-full bg-soft transition-shadow focus-within:shadow-[inset_0_0_0_2px_var(--color-ink)]">
            <button type="button" class="press w-12 shrink-0 text-3xl leading-none select-none" :aria-label="`Decrease ${label}`" @click="bump(-1)">−</button>
            <input
                :id="id"
                type="number"
                inputmode="decimal"
                :value="modelValue"
                :placeholder="placeholder"
                class="w-full min-w-0 bg-transparent text-center text-[1.625rem] font-extrabold tracking-tight outline-none placeholder:font-normal placeholder:text-muted/60"
                @input="emit('update:modelValue', $event.target.value)"
                @focus="$event.target.select()"
            />
            <button type="button" class="press w-12 shrink-0 text-3xl leading-none select-none" :aria-label="`Increase ${label}`" @click="bump(1)">+</button>
        </div>
    </div>
</template>
