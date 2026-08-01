<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ sessions: Array });

const active = computed(() => props.sessions.find((s) => !s.finished));

const months = computed(() => {
    const groups = new Map();
    for (const s of props.sessions) {
        const key = s.date.slice(0, 7);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(s);
    }
    return [...groups.entries()].map(([key, items]) => ({
        label: new Date(key + '-01').toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
        items,
    }));
});

const weekday = (d) => new Date(d).toLocaleDateString('en-GB', { weekday: 'short' });
const dayNum = (d) => new Date(d).getDate();

function startSession() {
    router.post('/sessions');
}
</script>

<template>
    <Head title="Sessions · Workout" />
    <AppLayout>
        <header class="rise-in mb-6 flex items-end justify-between">
            <div>
                <div class="mb-2 h-1.5 w-12 bg-volt" />
                <h1 class="font-display text-5xl uppercase leading-none">Sessions</h1>
            </div>
            <span class="font-display text-5xl text-line">{{ sessions.length }}</span>
        </header>

        <Link
            v-if="active"
            :href="`/sessions/${active.id}`"
            class="rise-in mb-6 flex items-center justify-between rounded-2xl border-2 border-volt bg-volt/10 px-5 py-4"
        >
            <div>
                <p class="text-[11px] font-bold tracking-widest text-volt uppercase">Session in progress</p>
                <p class="font-display text-2xl uppercase">Continue →</p>
            </div>
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-volt opacity-60" />
                <span class="relative inline-flex h-3 w-3 rounded-full bg-volt" />
            </span>
        </Link>
        <button
            v-else
            class="font-display rise-in mb-6 w-full rounded-2xl bg-volt py-5 text-2xl tracking-wide text-volt-ink uppercase active:bg-volt-bright"
            @click="startSession"
        >
            + Start today's session
        </button>

        <section v-for="month in months" :key="month.label" class="mb-6">
            <h2 class="mb-2 text-[11px] font-bold tracking-widest text-muted uppercase">{{ month.label }}</h2>
            <ul class="space-y-2">
                <li v-for="s in month.items" :key="s.id">
                    <Link
                        :href="`/sessions/${s.id}`"
                        class="flex items-center gap-4 rounded-xl border border-line bg-surface px-4 py-3 active:bg-raised"
                    >
                        <div class="w-11 text-center">
                            <p class="text-[10px] font-bold tracking-widest text-muted uppercase">{{ weekday(s.date) }}</p>
                            <p class="font-display text-2xl leading-none">{{ dayNum(s.date) }}</p>
                        </div>
                        <div class="h-8 w-px bg-line" />
                        <div class="flex-1 text-sm text-muted">
                            <span v-if="s.exercise_count" class="mr-3">
                                <b class="text-ink">{{ s.exercise_count }}</b> machines
                            </span>
                            <span v-if="s.cardio_count">
                                <b class="text-ink">{{ s.cardio_count }}</b> cardio
                            </span>
                            <span v-if="!s.exercise_count && !s.cardio_count">empty</span>
                        </div>
                        <svg v-if="s.has_feedback" class="h-4 w-4 text-volt" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l2.4 7.2H22l-6 4.5 2.3 7.3-6.3-4.6-6.3 4.6L8 13.7 2 9.2h7.6z" />
                        </svg>
                        <span v-if="!s.finished" class="rounded bg-volt px-1.5 py-0.5 text-[10px] font-bold text-volt-ink uppercase">open</span>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
