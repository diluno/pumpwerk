<script setup>
import { computed } from 'vue';
import { Head, Link, Deferred, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import BandBars from '../../Components/BandBars.vue';
import CountUp from '../../Components/CountUp.vue';
import { startSession } from '../../session.js';
import { parseDate, toYmd, formatDate, daysBetween, plural } from '../../format.js';

const props = defineProps({
    sessions: Array,
    gyms: Array, // deferred live visitor counts: [{ key, name, open, visitors }]
});

const active = computed(() => props.sessions.find((s) => !s.finished));
const past = computed(() => props.sessions.filter((s) => s !== active.value));

const months = computed(() => {
    const groups = new Map();
    for (const s of past.value) {
        const key = s.date.slice(0, 7);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(s);
    }
    const thisYear = new Date().getFullYear();
    return [...groups.entries()].map(([key, items]) => ({
        key,
        label: formatDate(`${key}-01`, { month: 'long', ...(Number(key.slice(0, 4)) === thisYear ? {} : { year: 'numeric' }) }),
        items,
    }));
});

// One line of orientation instead of a dashboard: how often lately, how long ago.
const rhythm = computed(() => {
    if (!props.sessions.length) return null;
    const today = new Date();
    const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - ((today.getDay() + 6) % 7));
    const thisWeek = props.sessions.filter((s) => s.date >= toYmd(monday)).length;
    const days = daysBetween(props.sessions[0].date);
    const last = days <= 0 ? 'today' : days === 1 ? 'yesterday' : `${days} days ago`;
    return `${plural(thisWeek, 'session')} this week. Last one ${last}.`;
});

function summary(s) {
    if (s.set_count) return plural(s.set_count, 'set');
    return s.cardio_count ? 'cardio' : 'empty';
}

// Stagger rows across the whole list, not per month
const order = computed(() => new Map(past.value.map((s, i) => [s.id, Math.min(i, 10)])));

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Sessions" />
    <AppLayout>
        <h1 class="display mt-2.5 text-[3.125rem]">Sessions</h1>
        <p v-if="rhythm" class="mt-2 text-muted">{{ rhythm }}</p>

        <Link
            v-if="active"
            :href="`/sessions/${active.id}`"
            class="press mt-[1.125rem] flex items-center justify-between gap-3 rounded-[1.75rem] bg-ink px-5 py-[1.125rem] text-bg"
        >
            <span>
                <span class="block text-2xl font-extrabold tracking-tight">Continue session</span>
                <span class="mt-0.5 block text-faint">
                    {{ formatDate(active.date, { weekday: 'long' }) }}, {{ active.set_count ? `${plural(active.set_count, 'set')} so far` : 'nothing logged yet' }}
                </span>
                <BandBars v-if="active.weights.length" :weights="active.weights" class="mt-3" />
            </span>
            <svg class="h-[1.625rem] w-[1.625rem] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 5l7 7-7 7" />
            </svg>
        </Link>
        <button v-else class="btn-primary mt-[1.125rem] h-[4.25rem] w-full text-2xl" @click="startSession">Start today’s session</button>

        <!-- How full the gyms are right now; the page behind it says when to go -->
        <Link href="/gyms" class="card press mt-2 block px-5 py-4" aria-label="Gyms: visitors right now and quiet hours">
            <span class="flex items-center justify-between">
                <span class="font-extrabold tracking-tight">In the gyms right now</span>
                <svg class="h-5 w-5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </span>
            <Deferred data="gyms">
                <template #fallback>
                    <span class="breathe mt-3 block h-[3.25rem] rounded-2xl bg-soft" />
                </template>
                <span class="mt-2 grid grid-cols-4 gap-2">
                    <span v-for="g in gyms" :key="g.key" class="min-w-0">
                        <span class="block text-[1.75rem] leading-none font-extrabold tracking-tight">
                            <CountUp v-if="g.visitors !== null" :value="g.visitors" />
                            <span v-else-if="!g.open" class="text-base font-semibold text-muted">closed</span>
                            <template v-else>–</template>
                        </span>
                        <span class="mt-1 block truncate text-[0.8125rem] text-muted">{{ g.name }}</span>
                    </span>
                </span>
            </Deferred>
        </Link>

        <p v-if="!sessions.length" class="mt-6 text-muted">
            Nothing logged yet. Start a session, add the machines you use, and your history builds up here.
        </p>

        <section v-for="month in months" :key="month.key">
            <div class="mt-6 mb-1.5 flex items-baseline justify-between">
                <h2 class="text-xl font-extrabold tracking-tight">{{ month.label }}</h2>
                <p class="text-[0.9375rem] text-muted">{{ plural(month.items.length, 'session') }}</p>
            </div>
            <Link
                v-for="s in month.items"
                :key="s.id"
                :href="`/sessions/${s.id}`"
                class="rise flex items-center gap-3.5 border-t border-line py-3 active:opacity-60"
                :style="{ '--i': order.get(s.id) }"
            >
                <span class="w-10 shrink-0 text-center">
                    <span class="block text-[1.75rem] leading-none font-extrabold tracking-tight">{{ parseDate(s.date).getDate() }}</span>
                    <span class="mt-0.5 block text-[0.8125rem] text-muted">{{ formatDate(s.date, { weekday: 'short' }) }}</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold">{{ s.machines.length ? s.machines.join(', ') : s.cardio_count ? 'Cardio' : 'Empty session' }}</span>
                    <BandBars v-if="s.weights.length" :weights="s.weights" class="mt-[7px]" />
                </span>
                <span class="shrink-0 text-[0.9375rem] text-muted">{{ summary(s) }}</span>
            </Link>
        </section>

        <p v-if="past.length" class="mt-4 text-sm text-muted">Each bar is a machine. Longer and redder means heavier.</p>

        <button class="press mx-auto mt-4 block min-h-11 px-4 text-[0.9375rem] font-semibold text-muted" @click="logout">Log out</button>
    </AppLayout>
</template>
