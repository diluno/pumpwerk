<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import MachineBlock from '../../Components/MachineBlock.vue';
import { num, shortDate, formatDate, plural, setsSentence, topWeight } from '../../format.js';

const props = defineProps({ machine: Object, exercises: Array });

// Heaviest set ever; more reps break a tie
const best = computed(() => {
    let top = null;
    for (const e of props.exercises) {
        for (const s of e.sets) {
            if (!top || s.weight > top.weight || (s.weight === top.weight && s.reps > top.reps)) {
                top = { ...s, date: e.date };
            }
        }
    }
    return top;
});

function back() {
    if (window.history.length > 1) window.history.back();
    else router.visit('/progression');
}
</script>

<template>
    <Head :title="machine.name" />
    <AppLayout>
        <button class="press -ml-1.5 flex h-11 items-center gap-1 pr-2 text-muted" @click="back">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 5l-7 7 7 7" />
            </svg>
            Back
        </button>
        <h1 class="display mt-0.5 text-[2.625rem]">{{ machine.name }}</h1>
        <p v-if="best" class="mt-2 text-muted">
            {{ plural(exercises.length, 'session') }}. Heaviest set
            <b class="font-semibold text-ink">{{ num(best.weight) }} lb × {{ best.reps }}</b> on {{ shortDate(best.date) }}.
        </p>
        <p v-else class="mt-2 text-muted">Not used in any session yet.</p>

        <div class="mt-4 space-y-2">
            <Link
                v-for="(e, i) in exercises"
                :key="e.id"
                :href="`/sessions/${e.session_id}`"
                class="card press rise flex items-center gap-3.5 py-2 pr-3.5 pl-2"
                :style="{ '--i': Math.min(i, 10) }"
            >
                <MachineBlock :weight="topWeight(e.sets)" />
                <span class="min-w-0 flex-1">
                    <span class="block text-lg font-extrabold tracking-tight">{{ formatDate(e.date, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                    <span class="block text-[0.9375rem] text-muted">{{ setsSentence(e.sets) || 'No sets' }}</span>
                    <span v-if="e.notes" class="block truncate text-[0.9375rem] text-muted italic">{{ e.notes }}</span>
                </span>
            </Link>
        </div>
    </AppLayout>
</template>
