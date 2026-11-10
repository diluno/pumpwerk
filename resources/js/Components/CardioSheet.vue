<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';
import Stepper from './Stepper.vue';

const props = defineProps({
    open: Boolean,
    sessionId: Number,
    // Existing entry to edit; null logs a new one
    entry: { type: Object, default: null },
});
const emit = defineEmits(['close']);

const types = ['laufband', 'crosstrainer', 'bike', 'rowing', 'stairs'];
const type = ref(types[0]);
const duration = ref(20);
const distance = ref(null);
const paceMin = ref(null);
const paceSec = ref(null);
const saving = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        const e = props.entry;
        type.value = e?.type ?? type.value;
        duration.value = e?.duration_minutes ?? 20;
        distance.value = e?.distance_km ?? null;
        paceMin.value = e?.pace_seconds ? Math.floor(e.pace_seconds / 60) : null;
        paceSec.value = e?.pace_seconds ? e.pace_seconds % 60 : null;
    },
);

const done = { preserveScroll: true, onSuccess: () => emit('close'), onFinish: () => (saving.value = false) };

function save() {
    const hasPace = paceMin.value !== null && paceMin.value !== '';
    const data = {
        type: type.value,
        duration_minutes: parseFloat(duration.value) || 0,
        distance_km: parseFloat(distance.value) || null,
        pace_seconds: hasPace || paceSec.value ? (parseInt(paceMin.value) || 0) * 60 + (parseInt(paceSec.value) || 0) : null,
    };
    saving.value = true;
    if (props.entry) {
        router.patch(`/cardio/${props.entry.id}`, data, done);
    } else {
        router.post(`/sessions/${props.sessionId}/cardio`, data, done);
    }
}

function remove() {
    saving.value = true;
    router.delete(`/cardio/${props.entry.id}`, done);
}
</script>

<template>
    <BottomSheet :open="open" :title="entry ? 'Edit cardio' : 'Add cardio'" @close="emit('close')">
        <div class="mb-5 flex flex-wrap gap-2" role="radiogroup" aria-label="Cardio type">
            <button
                v-for="t in types"
                :key="t"
                role="radio"
                :aria-checked="type === t"
                class="press h-11 rounded-full px-4 font-semibold capitalize"
                :class="type === t ? 'bg-ink text-bg' : 'bg-soft text-ink'"
                @click="type = t"
            >
                {{ t }}
            </button>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3">
            <Stepper v-model="duration" label="Minutes" :step="5" />
            <Stepper v-model="distance" label="Distance (km)" :step="0.5" placeholder="–" />
        </div>

        <fieldset class="mb-6">
            <legend class="label">Pace per km (optional)</legend>
            <div class="flex items-center gap-2">
                <input v-model="paceMin" type="number" inputmode="numeric" placeholder="min" aria-label="Pace minutes" class="field !rounded-full text-center text-2xl font-extrabold placeholder:font-normal" />
                <span class="text-2xl font-extrabold text-muted">:</span>
                <input v-model="paceSec" type="number" inputmode="numeric" placeholder="sec" aria-label="Pace seconds" class="field !rounded-full text-center text-2xl font-extrabold placeholder:font-normal" />
            </div>
        </fieldset>

        <button class="btn-primary w-full" :disabled="saving" @click="save">{{ entry ? 'Save changes' : 'Add cardio' }}</button>
        <button v-if="entry" class="press mt-2 h-12 w-full font-semibold text-red disabled:opacity-50" :disabled="saving" @click="remove">Delete entry</button>
    </BottomSheet>
</template>
