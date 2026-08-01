<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Stepper from './Stepper.vue';

const props = defineProps({
    exercise: Object,
    last: Object, // { date, sets: [{weight, reps}] } | null
    readonly: Boolean,
});

const lastSets = props.last?.sets ?? [];
const seed = lastSets[0] ?? props.exercise.sets[props.exercise.sets.length - 1] ?? { weight: 20, reps: 10 };
const weight = ref(seed.weight);
const reps = ref(seed.reps);
const justAdded = ref(false);

function addSet() {
    router.post(
        `/exercises/${props.exercise.id}/sets`,
        { weight: parseFloat(weight.value) || 0, reps: parseInt(reps.value) || 0 },
        {
            preserveScroll: true,
            onSuccess: () => {
                justAdded.value = true;
                setTimeout(() => (justAdded.value = false), 900);
                // prefill next set from last time's corresponding set
                const next = lastSets[props.exercise.sets.length];
                if (next) {
                    weight.value = next.weight;
                    reps.value = next.reps;
                }
            },
        },
    );
}

function removeSet(set) {
    router.delete(`/sets/${set.id}`, { preserveScroll: true });
}

function removeExercise() {
    if (confirm(`Remove ${props.exercise.machine_name} and its sets?`)) {
        router.delete(`/exercises/${props.exercise.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <article class="rise-in overflow-hidden rounded-2xl border border-line bg-surface">
        <header class="flex items-center justify-between border-b border-line px-4 py-3">
            <Link :href="`/machines/${exercise.machine_id}`" class="font-display text-xl uppercase active:text-volt">
                {{ exercise.machine_name }}
            </Link>
            <button v-if="!readonly" class="p-1 text-muted active:text-danger" aria-label="Remove exercise" @click="removeExercise">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </header>

        <p v-if="last" class="border-b border-line bg-raised/60 px-4 py-1.5 text-xs text-muted">
            Last time ({{ last.date }}):
            <span class="text-ink">{{ last.sets.map((s) => `${s.weight}×${s.reps}`).join('  ·  ') }}</span>
        </p>

        <ul>
            <li
                v-for="(set, i) in exercise.sets"
                :key="set.id"
                class="flex items-center gap-3 border-b border-line/60 px-4 py-2"
                :class="{ 'flash-volt': justAdded && i === exercise.sets.length - 1 }"
            >
                <span class="w-6 text-center text-xs font-bold text-muted">{{ set.set_number }}</span>
                <span class="font-display flex-1 text-2xl">
                    {{ set.weight }}<span class="ml-0.5 text-xs text-muted">kg</span>
                    <span class="mx-2 text-line">/</span>
                    {{ set.reps }}<span class="ml-0.5 text-xs text-muted">reps</span>
                </span>
                <span
                    v-if="last && last.sets[i] && set.weight * set.reps !== last.sets[i].weight * last.sets[i].reps"
                    class="text-xs font-bold"
                    :class="set.weight * set.reps > last.sets[i].weight * last.sets[i].reps ? 'text-volt' : 'text-danger'"
                >
                    {{ set.weight * set.reps > last.sets[i].weight * last.sets[i].reps ? '▲' : '▼' }}
                </span>
                <button v-if="!readonly" class="p-1.5 text-muted active:text-danger" aria-label="Delete set" @click="removeSet(set)">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                    </svg>
                </button>
            </li>
        </ul>

        <div v-if="!readonly" class="px-4 py-3">
            <div class="mb-3 flex items-end justify-center gap-4">
                <Stepper v-model="weight" label="Weight" unit="kg" :step="2.5" />
                <Stepper v-model="reps" label="Reps" :step="1" />
            </div>
            <button
                class="font-display h-12 w-full rounded-xl bg-volt text-lg text-volt-ink uppercase active:bg-volt-bright"
                @click="addSet"
            >
                Log set
            </button>
        </div>
    </article>
</template>
