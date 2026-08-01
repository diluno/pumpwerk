<script setup>
const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    step: { type: Number, default: 1 },
    min: { type: Number, default: 0 },
    label: String,
    unit: String,
});
const emit = defineEmits(['update:modelValue']);

function bump(dir) {
    const current = parseFloat(props.modelValue) || 0;
    const next = Math.max(props.min, Math.round((current + dir * props.step) * 100) / 100);
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <span class="text-[10px] font-semibold tracking-widest text-muted uppercase">{{ label }}</span>
        <div class="flex items-center overflow-hidden rounded-xl border border-line bg-raised">
            <button
                type="button"
                class="h-14 w-12 text-2xl font-bold text-muted active:bg-line active:text-ink"
                @click="bump(-1)"
            >
                −
            </button>
            <div class="relative w-[4.5rem]">
                <input
                    type="number"
                    inputmode="decimal"
                    :value="modelValue"
                    class="font-display w-full bg-transparent py-2 text-center text-3xl text-ink outline-none"
                    @input="emit('update:modelValue', $event.target.value)"
                    @focus="$event.target.select()"
                />
                <span v-if="unit" class="absolute inset-x-0 -bottom-0.5 text-center text-[9px] tracking-widest text-muted uppercase">{{ unit }}</span>
            </div>
            <button
                type="button"
                class="h-14 w-12 text-2xl font-bold text-muted active:bg-line active:text-ink"
                @click="bump(1)"
            >
                +
            </button>
        </div>
    </div>
</template>
