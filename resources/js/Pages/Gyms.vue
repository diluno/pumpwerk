<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import CountUp from '../Components/CountUp.vue';
import { tap } from '../motion.js';
import { plural } from '../format.js';

const props = defineProps({
    gyms: Array, // deferred: [{ key, name, open, opens, visitors, minutes_ago }]
    statistics: Object, // key → { days, typical: { weekday: { hour: visitors } } }
    hours: Array, // charted hours, local gym time
    weeks: Number,
    now: Object, // { weekday: 1–7 (Mon–Sun), hour } in gym time
});

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const time = (hour) => `${String(hour).padStart(2, '0')}:00`;

// Remember the gym looked at last (a convenience; the page works without it)
const keys = Object.keys(props.statistics);
let remembered = null;
try {
    remembered = localStorage.getItem('pumpwerk.gym');
} catch {
    /* storage unavailable */
}
const selected = ref(keys.includes(remembered) ? remembered : keys[0]);
watch(selected, (key) => {
    try {
        localStorage.setItem('pumpwerk.gym', key);
    } catch {
        /* storage unavailable */
    }
});

const stats = computed(() => props.statistics[selected.value]);
// An average below one person means the gym is closed at that hour
const typical = (weekday, hour, key = selected.value) => {
    const value = props.statistics[key].typical[weekday]?.[hour];
    return value >= 1 ? value : null;
};
const hasData = computed(() => stats.value.days > 0);
const liveGym = computed(() => props.gyms?.find((g) => g.key === selected.value));
const name = computed(() => liveGym.value?.name ?? '');

// "Quieter than usual" for a gym's tile, once there is something to compare with
function mood(gym) {
    const usual = typical(props.now.weekday, props.now.hour, gym.key);
    if (!gym.open) return `Opens ${gym.opens}`;
    if (gym.visitors === null) return 'No count right now';
    if (!usual) return 'people right now';
    const ratio = gym.visitors / usual;
    return ratio < 0.85 ? 'Quieter than usual' : ratio > 1.15 ? 'Busier than usual' : 'About as usual';
}

// --- Today: the typical curve for this weekday
const peak = computed(() => Math.max(1, ...Object.values(stats.value.typical).flatMap((day) => Object.values(day))));
const today = computed(() =>
    props.hours.map((hour) => {
        const value = typical(props.now.weekday, hour);
        return { hour, value, height: value ? `${Math.max(6, (value / peak.value) * 100)}%` : '0%' };
    }),
);
const advice = computed(() => {
    const left = today.value.filter((h) => h.hour > props.now.hour && h.value);
    const usualNow = typical(props.now.weekday, props.now.hour);
    if (liveGym.value && !liveGym.value.open && !left.length) return `Closed for today. Opens ${liveGym.value.opens}.`;
    if (!left.length) return 'No more open hours with data today.';
    const best = left.reduce((a, b) => (b.value < a.value ? b : a));
    if (usualNow && usualNow <= best.value) return `It usually doesn’t get quieter than now today (around ${usualNow}).`;
    return `Quietest later today: ${time(best.hour)} to ${time(best.hour + 1)}, usually around ${best.value}.`;
});

// --- The week: weekday × hour
const week = computed(() =>
    DAYS.map((label, d) => ({
        label,
        weekday: d + 1,
        cells: props.hours.map((hour) => {
            const value = typical(d + 1, hour);
            return { hour, value, alpha: value ? 0.12 + 0.88 * Math.pow(value / peak.value, 1.15) : 0 };
        }),
    })),
);
const quietest = computed(() => {
    let best = null;
    for (const day of week.value) {
        for (const cell of day.cells) {
            if (cell.value && (!best || cell.value < best.value)) best = { ...cell, label: day.label };
        }
    }
    return best;
});
const picked = ref(null); // { label, hour, value }
watch(selected, () => (picked.value = null));
function pick(day, cell) {
    tap();
    picked.value = { label: day.label, hour: cell.hour, value: cell.value };
}
const readout = computed(() => {
    if (picked.value) {
        return picked.value.value
            ? `${picked.value.label} ${time(picked.value.hour)}: usually around ${picked.value.value}.`
            : `${picked.value.label} ${time(picked.value.hour)}: closed or no counts yet.`;
    }
    return quietest.value ? `Quietest in a typical week: ${quietest.value.label} ${time(quietest.value.hour)}, around ${quietest.value.value}. Tap a square to read it.` : '';
});

const columns = computed(() => ({ gridTemplateColumns: `2.25rem repeat(${props.hours.length}, minmax(0, 1fr))` }));
</script>

<template>
    <Head title="Gyms" />
    <AppLayout>
        <Link href="/" class="press -ml-1.5 flex h-11 w-fit items-center gap-1 pr-2 text-muted">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 5l-7 7 7 7" />
            </svg>
            Sessions
        </Link>
        <h1 class="display mt-0.5 text-[3.125rem]">Gyms</h1>
        <p class="mt-2 text-muted">How many people are in right now, and when it is usually quiet.</p>

        <!-- Live counts; tap a gym to see its pattern -->
        <div class="mt-4 grid grid-cols-2 gap-2" role="group" aria-label="Gym">
            <template v-if="gyms">
                <button
                    v-for="(g, i) in gyms"
                    :key="g.key"
                    class="press rise rounded-[1.75rem] px-4 py-3.5 text-left"
                    :class="selected === g.key ? 'bg-ink text-bg' : 'card'"
                    :style="{ '--i': i }"
                    :aria-pressed="selected === g.key"
                    @click="selected = g.key"
                >
                    <span class="block font-semibold">{{ g.name }}</span>
                    <span class="mt-1 block text-[2.75rem] leading-none font-extrabold tracking-[-0.03em]">
                        <CountUp v-if="g.visitors !== null" :value="g.visitors" />
                        <span v-else-if="!g.open" class="text-[1.75rem] tracking-tight">Closed</span>
                        <template v-else>–</template>
                    </span>
                    <span class="mt-1 block text-sm" :class="selected === g.key ? 'text-faint' : 'text-muted'">{{ mood(g) }}</span>
                </button>
            </template>
            <template v-else>
                <div v-for="key in keys" :key="key" class="breathe h-[7.25rem] rounded-[1.75rem] bg-soft" />
            </template>
        </div>
        <p v-if="liveGym?.minutes_ago > 0" class="mt-2 text-sm text-muted">Counted {{ plural(liveGym.minutes_ago, 'minute') }} ago.</p>

        <template v-if="hasData">
            <!-- Today -->
            <h2 class="mt-7 text-xl font-extrabold tracking-tight">A typical {{ DAYS[now.weekday - 1] }} at {{ name || 'this gym' }}</h2>
            <p class="mt-1 text-muted">{{ advice }}</p>
            <div :key="`today-${selected}`" class="mt-3 flex h-28 items-end gap-[3px]" role="img" :aria-label="`Typical visitors per hour today. ${advice}`">
                <span
                    v-for="(h, i) in today"
                    :key="h.hour"
                    class="grow-y flex-1 rounded-[5px]"
                    :class="h.hour === now.hour ? 'bg-ink' : h.hour < now.hour ? 'bg-ink/15' : 'bg-ink/35'"
                    :style="{ height: h.height, '--i': i }"
                />
            </div>
            <div class="mt-1.5 flex gap-[3px] text-[0.8125rem] text-muted" aria-hidden="true">
                <span v-for="h in hours" :key="h" class="flex-1 text-center" :class="{ 'font-extrabold text-ink': h === now.hour }">
                    {{ h % 3 === 0 || h === now.hour ? h : '' }}
                </span>
            </div>

            <!-- The week -->
            <h2 class="mt-7 text-xl font-extrabold tracking-tight">A typical week</h2>
            <p class="mt-1 min-h-12 text-muted" aria-live="polite">{{ readout }}</p>
            <div :key="`week-${selected}`" class="mt-2 space-y-[3px]">
                <div v-for="(day, d) in week" :key="day.label" class="rise grid items-center gap-[3px]" :style="{ ...columns, '--i': d }">
                    <span class="text-[0.8125rem]" :class="day.weekday === now.weekday ? 'font-extrabold text-ink' : 'text-muted'">{{ day.label }}</span>
                    <button
                        v-for="cell in day.cells"
                        :key="cell.hour"
                        class="h-7 rounded-[5px] transition-transform duration-150 active:scale-125"
                        :class="[
                            cell.value ? '' : 'shadow-[inset_0_0_0_1px_var(--color-line)]',
                            picked?.label === day.label && picked?.hour === cell.hour ? 'outline-2 outline-offset-1 outline-red' : '',
                            !picked && day.weekday === now.weekday && cell.hour === now.hour ? 'outline-2 outline-offset-1 outline-ink' : '',
                        ]"
                        :style="cell.value ? { backgroundColor: `rgb(21 21 27 / ${cell.alpha})` } : null"
                        :aria-label="`${day.label} ${time(cell.hour)}: ${cell.value ? `around ${cell.value}` : 'no data'}`"
                        @click="pick(day, cell)"
                    />
                </div>
                <div class="grid gap-[3px] text-[0.8125rem] text-muted" :style="columns" aria-hidden="true">
                    <span />
                    <span v-for="h in hours" :key="h" class="text-center">{{ h % 3 === 0 ? h : '' }}</span>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-[0.8125rem] text-muted" aria-hidden="true">
                Quiet
                <span v-for="a in [0.12, 0.34, 0.56, 0.78, 1]" :key="a" class="h-3.5 w-6 rounded-[4px]" :style="{ backgroundColor: `rgb(21 21 27 / ${a})` }" />
                Busy
                <span class="ml-2 h-3.5 w-6 rounded-[4px] shadow-[inset_0_0_0_1px_var(--color-line)]" />
                Closed
            </div>

            <p class="mt-5 text-sm text-muted">
                Based on {{ plural(stats.days, 'day') }} of counts for this gym, saved every 10 minutes while it is open. Holiday hours are not taken into account. The picture covers the last {{ weeks }} weeks
                and sharpens as more days come in.
            </p>
        </template>

        <div v-else class="mt-7 rounded-[1.75rem] bg-soft px-5 py-4">
            <h2 class="text-xl font-extrabold tracking-tight">No pattern yet</h2>
            <p class="mt-1 text-muted">
                Counts are saved every 10 minutes while the gyms are open. After a few days this page shows the quiet hours for each weekday.
            </p>
        </div>
    </AppLayout>
</template>
