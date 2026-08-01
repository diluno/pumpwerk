<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Filler,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

const props = defineProps({ machines: Array });

const selected = ref(props.machines[0]?.id ?? null);
const metric = ref('top_weight');
const canvas = ref(null);
let chart = null;

const machine = computed(() => props.machines.find((m) => m.id === selected.value));

function draw() {
    if (!canvas.value || !machine.value) return;
    chart?.destroy();
    const points = machine.value.points;
    chart = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: points.map((p) => p.date.slice(5)),
            datasets: [
                {
                    data: points.map((p) => p[metric.value]),
                    borderColor: '#c8f542',
                    backgroundColor: 'rgba(200, 245, 66, 0.12)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#c8f542',
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { tooltip: { displayColors: false } },
            scales: {
                x: { ticks: { color: '#8a9086', maxTicksLimit: 6 }, grid: { color: '#2e332b' } },
                y: { ticks: { color: '#8a9086' }, grid: { color: '#2e332b' } },
            },
        },
    });
}

onMounted(draw);
watch([selected, metric], () => setTimeout(draw));
</script>

<template>
    <Head title="Progress · Pumpwerk" />
    <AppLayout>
        <header class="rise-in mb-6">
            <div class="mb-2 h-1.5 w-12 bg-volt" />
            <h1 class="font-display text-5xl uppercase leading-none">Progress</h1>
        </header>

        <p v-if="!machines.length" class="text-muted">No exercise data yet.</p>

        <template v-else>
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="m in machines"
                    :key="m.id"
                    class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold"
                    :class="selected === m.id ? 'border-volt bg-volt text-volt-ink' : 'border-line bg-surface text-muted'"
                    @click="selected = m.id"
                >
                    {{ m.name }} <span class="opacity-60">{{ m.sessions }}</span>
                </button>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-2">
                <button
                    class="rounded-xl border py-2.5 text-sm font-semibold"
                    :class="metric === 'top_weight' ? 'border-volt text-volt' : 'border-line text-muted'"
                    @click="metric = 'top_weight'"
                >
                    Top weight
                </button>
                <button
                    class="rounded-xl border py-2.5 text-sm font-semibold"
                    :class="metric === 'volume' ? 'border-volt text-volt' : 'border-line text-muted'"
                    @click="metric = 'volume'"
                >
                    Volume
                </button>
            </div>

            <div class="rise-in rounded-2xl border border-line bg-surface p-4">
                <div class="h-64">
                    <canvas ref="canvas" />
                </div>
            </div>

            <div v-if="machine" class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-line bg-surface px-4 py-3">
                    <p class="text-[10px] font-bold tracking-widest text-muted uppercase">Best set</p>
                    <p class="font-display text-2xl text-volt">
                        {{ machine.points.at(-1)?.best_set?.weight }}kg × {{ machine.points.at(-1)?.best_set?.reps }}
                    </p>
                    <p class="text-xs text-muted">most recent session</p>
                </div>
                <Link :href="`/machines/${machine.id}`" class="flex flex-col justify-center rounded-xl border border-line bg-surface px-4 py-3 active:border-volt">
                    <p class="text-[10px] font-bold tracking-widest text-muted uppercase">Full history</p>
                    <p class="font-display text-2xl">{{ machine.sessions }} sessions →</p>
                </Link>
            </div>
        </template>
    </AppLayout>
</template>
