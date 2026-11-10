<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';
import Stepper from './Stepper.vue';

const props = defineProps({
    open: Boolean,
    profile: { type: Object, required: true }, // { goals, limitations, days_per_week, session_minutes, reps_min, reps_max, memory }
});
const emit = defineEmits(['close']);

const form = useForm({ goals: '', limitations: '', days_per_week: null, session_minutes: null, reps_min: null, reps_max: null, memory: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({ ...props.profile });
        form.reset();
        form.clearErrors();
    },
);

function save() {
    form
        .transform((data) => ({
            ...data,
            days_per_week: parseInt(data.days_per_week) || null,
            session_minutes: parseInt(data.session_minutes) || null,
            reps_min: parseInt(data.reps_min) || null,
            reps_max: parseInt(data.reps_max) || null,
        }))
        .patch('/coach/profile', { preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <BottomSheet :open="open" title="About you" @close="emit('close')">
        <p class="mb-4 text-muted">The coach reads this before every plan and every feedback.</p>

        <label for="cp-goals" class="label">What you are training for</label>
        <textarea id="cp-goals" v-model="form.goals" rows="2" maxlength="1000" placeholder="Stronger legs, general fitness, a bit of everything…" class="field mb-4 resize-none !text-base" />

        <label for="cp-limits" class="label">Injuries and things to work around</label>
        <textarea id="cp-limits" v-model="form.limitations" rows="2" maxlength="1000" placeholder="Left knee, lower back after long days…" class="field mb-4 resize-none !text-base" />

        <div class="mb-4 grid grid-cols-2 gap-3">
            <Stepper v-model="form.days_per_week" label="Days a week" :step="1" :min="1" placeholder="–" />
            <Stepper v-model="form.session_minutes" label="Minutes a session" :step="5" :min="10" placeholder="–" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <Stepper v-model="form.reps_min" label="Reps per set, from" :step="1" :min="1" />
            <Stepper v-model="form.reps_max" label="to" :step="1" :min="1" />
        </div>
        <p class="mt-1.5 mb-4 text-sm text-muted">Once every set reaches {{ form.reps_max }} reps, the next target goes up one step in weight.</p>

        <label for="cp-memory" class="label">The coach’s own notes about you</label>
        <textarea id="cp-memory" v-model="form.memory" rows="4" maxlength="1500" placeholder="Empty so far. The coach writes here after each feedback." class="field resize-none !text-base" />
        <p class="mt-1.5 mb-5 text-sm text-muted">Rewritten by the coach after each feedback. Correct or delete anything that is wrong.</p>

        <p v-if="form.hasErrors" class="mb-3 text-sm font-semibold text-red">{{ Object.values(form.errors)[0] }}</p>
        <button class="btn-primary w-full" :disabled="form.processing" @click="save">Save</button>
    </BottomSheet>
</template>
