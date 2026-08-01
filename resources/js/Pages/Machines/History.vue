<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ machine: Object, exercises: Array });

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
</script>

<template>
    <Head :title="`${machine.name} · Workout`" />
    <AppLayout>
        <header class="rise-in mb-6">
            <Link href="/" class="mb-3 inline-flex items-center gap-1 text-sm text-muted active:text-ink">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
                Back
            </Link>
            <div class="mb-2 h-1.5 w-12 bg-volt" />
            <h1 class="font-display text-4xl uppercase leading-none">{{ machine.name }}</h1>
            <p v-if="best" class="mt-3 text-sm text-muted">
                Best set: <b class="font-display text-lg text-volt">{{ best.weight }}kg × {{ best.reps }}</b>
                <span class="ml-1">({{ best.date }})</span>
            </p>
        </header>

        <ul class="space-y-3">
            <li v-for="e in exercises" :key="e.session_id + e.date" class="rounded-xl border border-line bg-surface px-4 py-3">
                <Link :href="`/sessions/${e.session_id}`" class="mb-1 block text-xs font-bold tracking-widest text-muted uppercase active:text-volt">
                    {{ e.date }}
                </Link>
                <p class="font-display text-xl">
                    {{ e.sets.map((s) => `${s.weight}×${s.reps}`).join('  ·  ') }}
                </p>
                <p v-if="e.notes" class="mt-1 text-sm text-muted">{{ e.notes }}</p>
            </li>
        </ul>
    </AppLayout>
</template>
