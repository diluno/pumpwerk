<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { band, BAND_LIMITS } from '../bands.js';
import { useTween, tap } from '../motion.js';
import { num, shortDate, plural } from '../format.js';

const props = defineProps({ machines: Array });

const metrics = [
    { key: 'top_weight', label: 'Top weight' },
    { key: 'volume', label: 'Volume' },
];
const MAX_BARS = 24;

const selectedId = ref(props.machines[0]?.id ?? null);
const metric = ref(metrics[0]);
const picked = ref(null); // index of a tapped bar; null shows the latest session

const machine = computed(() => props.machines.find((m) => m.id === selectedId.value));
const points = computed(() => machine.value?.points.slice(-MAX_BARS) ?? []);
const values = computed(() => points.value.map((p) => p[metric.value.key]));
const byWeight = computed(() => metric.value.key === 'top_weight');
watch([selectedId, metric], () => (picked.value = null));

const scaleMax = computed(() => Math.max(...values.value, 1) * 1.12);
const pct = (v) => `${(v / scaleMax.value) * 100}%`;

const bars = computed(() =>
    points.value.map((p, i) => ({
        date: p.date,
        value: values.value[i],
        height: pct(values.value[i]),
        color: byWeight.value ? band(p.top_weight).bg : '#15151b',
    })),
);
// Where one weight band ends and the next begins
const lines = computed(() => (byWeight.value ? BAND_LIMITS.filter((v) => v < scaleMax.value) : []));

const current = computed(() => bars.value[picked.value ?? bars.value.length - 1]);
const shown = useTween(() => current.value?.value ?? 0, 420);

const first = computed(() => bars.value[0]);
const change = computed(() => {
    if (bars.value.length < 2 || !first.value.value) return null;
    return Math.round(((current.value.value - first.value.value) / first.value.value) * 100);
});
const best = computed(() => bars.value.reduce((top, b) => (!top || b.value >= top.value ? b : top), null));
const chip = computed(() => (byWeight.value && current.value ? band(current.value.value) : { bg: '#15151b', on: '#fafaf7' }));

function pick(i) {
    tap();
    picked.value = picked.value === i || i === bars.value.length - 1 ? null : i;
}
</script>

<template>
    <Head title="Progress" />
    <AppLayout>
        <h1 class="display mt-2.5 text-[3.125rem]">Progress</h1>

        <p v-if="!machines.length" class="mt-3 text-muted">Log a few sets and each machine gets its own chart here.</p>

        <template v-else>
            <!-- Bleeds to the screen edge so the row reads as scrollable -->
            <div class="-mx-5 mt-4 flex gap-1.5 overflow-x-auto px-5" role="group" aria-label="Machine">
                <button
                    v-for="m in machines"
                    :key="m.id"
                    class="press h-[2.875rem] shrink-0 rounded-full px-[1.125rem] font-semibold whitespace-nowrap"
                    :class="selectedId === m.id ? 'bg-ink text-bg' : 'bg-soft text-ink'"
                    :aria-pressed="selectedId === m.id"
                    @click="selectedId = m.id"
                >
                    {{ m.name }}
                </button>
            </div>

            <template v-if="current">
                <div class="mt-[1.375rem] flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[0.9375rem] text-muted">{{ metric.label }}, {{ picked === null ? 'latest session' : shortDate(current.date) }}</p>
                        <p class="mt-0.5 text-[4.25rem] leading-[0.95] font-extrabold tracking-[-0.04em] tabular-nums">
                            {{ num(byWeight ? shown : Math.round(shown)) }}<span class="ml-1.5 text-xl font-semibold tracking-normal">lb</span>
                        </p>
                    </div>
                    <p
                        v-if="change !== null"
                        class="mb-1.5 rounded-full px-3 py-1.5 font-extrabold whitespace-nowrap transition-colors duration-300"
                        :style="{ backgroundColor: chip.bg, color: chip.on }"
                    >
                        {{ change >= 0 ? '+' : '' }}{{ change }}%
                    </p>
                </div>
                <p class="mt-2 text-muted">
                    <template v-if="bars.length > 1">{{ change >= 0 ? 'Up' : 'Down' }} from {{ num(first.value) }} lb on {{ shortDate(first.date) }}.</template>
                    <template v-else>First session on this machine.</template>
                </p>

                <div class="mb-1 flex justify-end">
                    <div class="mt-3 flex rounded-full bg-soft p-1 text-[0.9375rem] font-semibold" role="radiogroup" aria-label="Metric">
                        <button
                            v-for="m in metrics"
                            :key="m.key"
                            role="radio"
                            :aria-checked="metric.key === m.key"
                            class="h-9 rounded-full px-3.5 transition-colors duration-200"
                            :class="metric.key === m.key ? 'bg-card text-ink shadow-sm' : 'text-muted'"
                            @click="metric = m"
                        >
                            {{ m.label }}
                        </button>
                    </div>
                </div>

                <!-- One bar per session, coloured by weight band. Tap a bar to read it. -->
                <div class="relative mt-2 h-52">
                    <div v-for="v in lines" :key="v" class="absolute inset-x-0 border-t border-dashed border-[#c9c9c1]" :style="{ bottom: pct(v) }" aria-hidden="true">
                        <span class="absolute right-0 bottom-0.5 text-[0.8125rem] text-muted">{{ v }}</span>
                    </div>
                    <div :key="`${selectedId}-${metric.key}`" class="absolute inset-y-0 left-0 flex items-end gap-1" :class="byWeight ? 'right-[2.125rem]' : 'right-0'">
                        <button
                            v-for="(b, i) in bars"
                            :key="i"
                            class="grow-y relative h-full flex-1"
                            :style="{ '--i': i }"
                            :aria-label="`${shortDate(b.date)}: ${num(b.value)} lb`"
                            :aria-pressed="picked === i"
                            @click="pick(i)"
                        >
                            <span
                                class="absolute inset-x-0 bottom-0 rounded-[7px] transition-[opacity,height] duration-300"
                                :class="picked !== null && picked !== i ? 'opacity-35' : ''"
                                :style="{ height: b.height, backgroundColor: b.color }"
                            />
                        </button>
                    </div>
                </div>
                <div class="mt-1.5 flex justify-between text-[0.8125rem] text-muted" :class="{ 'mr-[2.125rem]': byWeight }">
                    <span>{{ shortDate(first.date) }}</span>
                    <span v-if="bars.length > 1">{{ shortDate(bars.at(-1).date) }}</span>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2">
                    <div class="card px-4 py-3.5">
                        <p class="text-sm text-muted">Best so far</p>
                        <p class="mt-0.5 text-2xl font-extrabold tracking-tight">{{ num(best.value) }} lb</p>
                        <p class="mt-0.5 text-sm text-muted">{{ shortDate(best.date) }}</p>
                    </div>
                    <Link :href="`/machines/${machine.id}`" class="card press block px-4 py-3.5">
                        <span class="block text-sm text-muted">Every set</span>
                        <span class="mt-0.5 block text-2xl font-extrabold tracking-tight">{{ plural(machine.sessions, 'session') }}</span>
                        <span class="mt-0.5 block text-sm font-semibold underline underline-offset-4">Open history</span>
                    </Link>
                </div>
            </template>
        </template>
    </AppLayout>
</template>
