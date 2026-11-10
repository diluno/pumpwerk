<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import WeightStack from '../../Components/WeightStack.vue';
import BottomSheet from '../../Components/BottomSheet.vue';
import Stepper from '../../Components/Stepper.vue';
import { stepMachineWeight } from '../../machineWeight.js';
import { formatDate, shortDate, setLabel, num, plural } from '../../format.js';
import { tap } from '../../motion.js';

const props = defineProps({
    session: Object, // { id, date }
    exercise: Object, // { id, machine_id, machine_name, notes, sets }
    last: Object, // previous time on this machine: { date, sets } | null
    position: Number,
    count: Number,
    previousId: Number,
    nextId: Number,
});

const keep = { preserveScroll: true };
const sets = computed(() => props.exercise.sets);

// The next set starts from the set just logged; a fresh machine starts from last time.
const seed = sets.value.at(-1) ?? props.last?.sets[0] ?? { weight: 30, reps: 10 };
const weight = ref(seed.weight);
const reps = ref(seed.reps);
const logging = ref(false);
const lift = ref(0);

function logSet() {
    if (logging.value) return;
    logging.value = true;
    router.post(
        `/exercises/${props.exercise.id}/sets`,
        { weight: weight.value, reps: reps.value },
        {
            ...keep,
            onSuccess: () => {
                lift.value++;
                tap(25);
            },
            onFinish: () => (logging.value = false),
        },
    );
}

function use(set) {
    tap();
    weight.value = set.weight;
    reps.value = set.reps;
}

function bumpReps(dir) {
    tap();
    reps.value = Math.max(1, reps.value + dir);
}

// --- Editing a logged set
const editing = ref(null);
const editWeight = ref(0);
const editReps = ref(0);
function edit(set) {
    editing.value = set;
    editWeight.value = set.weight;
    editReps.value = set.reps;
}
function saveSet() {
    router.patch(
        `/sets/${editing.value.id}`,
        { weight: parseFloat(editWeight.value) || 0, reps: parseInt(editReps.value) || 0 },
        { ...keep, onSuccess: () => (editing.value = null) },
    );
}
function deleteSet() {
    router.delete(`/sets/${editing.value.id}`, { ...keep, onSuccess: () => (editing.value = null) });
}

// --- A weight the stack cannot show (odd plates, another machine type)
const typing = ref(false);
const typed = ref(0);
function openTyping() {
    typed.value = weight.value;
    typing.value = true;
}
function applyTyped() {
    const value = parseFloat(typed.value);
    if (value > 0) weight.value = Math.round(value * 10) / 10;
    typing.value = false;
}

function saveNotes(e) {
    const notes = e.target.value.trim() || null;
    if (notes !== (props.exercise.notes ?? null)) {
        router.patch(`/exercises/${props.exercise.id}`, { notes }, keep);
    }
}

const removing = ref(false);
function removeExercise() {
    router.delete(`/exercises/${props.exercise.id}`);
}

const round = 'press grid h-11 w-11 place-items-center rounded-full bg-soft';
</script>

<template>
    <Head :title="exercise.machine_name" />
    <AppLayout :nav="false">
        <div class="flex items-center justify-between">
            <Link :href="`/sessions/${session.id}`" class="press -ml-1.5 flex h-11 items-center gap-1 pr-2 text-muted">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 5l-7 7 7 7" />
                </svg>
                {{ formatDate(session.date, { weekday: 'long' }) }}’s session
            </Link>
            <div v-if="count > 1" class="flex items-center gap-2">
                <Link :href="`/exercises/${previousId}`" :class="round" aria-label="Previous machine" replace>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" />
                    </svg>
                </Link>
                <span class="font-semibold tabular-nums">{{ position }} of {{ count }}</span>
                <Link :href="`/exercises/${nextId}`" :class="round" aria-label="Next machine" replace>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>

        <h1 class="display mt-1.5 text-[3.125rem]" :style="{ viewTransitionName: `machine-${exercise.id}` }">{{ exercise.machine_name }}</h1>

        <div v-if="last" class="-mx-5 mt-2 flex items-center gap-1.5 overflow-x-auto px-5 whitespace-nowrap">
            <span class="mr-1 text-muted">Last time, {{ shortDate(last.date) }}</span>
            <button
                v-for="(s, i) in last.sets"
                :key="i"
                class="press h-9 rounded-full bg-soft px-3.5 font-semibold"
                :aria-label="`Use ${setLabel(s)} from last time`"
                @click="use(s)"
            >
                {{ setLabel(s) }}
            </button>
        </div>
        <p v-else class="mt-2 text-muted">First time on this machine.</p>

        <WeightStack v-model="weight" :lift="lift" class="mt-4" />

        <div class="mt-3 flex items-stretch gap-2.5">
            <div class="flex h-[3.875rem] w-40 shrink-0 items-stretch rounded-full bg-soft" role="group" aria-label="Reps">
                <button class="press w-[3.125rem] text-[1.75rem] leading-none" aria-label="Fewer reps" @click="bumpReps(-1)">−</button>
                <p class="flex flex-1 flex-col items-center justify-center text-[1.75rem] leading-[0.95] font-extrabold tabular-nums">
                    {{ reps }}<span class="text-[0.8125rem] leading-tight font-normal text-muted">reps</span>
                </p>
                <button class="press w-[3.125rem] text-[1.75rem] leading-none" aria-label="More reps" @click="bumpReps(1)">+</button>
            </div>
            <button class="btn-primary h-[3.875rem] flex-1 text-[1.375rem]" :disabled="logging" @click="logSet">Log set {{ sets.length + 1 }}</button>
        </div>

        <TransitionGroup name="pop" tag="div" class="relative mt-4 flex min-h-11 flex-wrap items-center gap-2">
            <p v-if="!sets.length" key="none" class="text-muted">No sets on this machine yet today.</p>
            <button
                v-for="set in sets"
                :key="set.id"
                class="press flex h-11 items-baseline gap-2 rounded-full bg-soft px-4"
                :aria-label="`Edit set ${set.set_number}: ${num(set.weight)} lb, ${set.reps} reps`"
                @click="edit(set)"
            >
                <span class="text-sm text-muted">{{ set.set_number }}</span>
                <span class="text-lg font-extrabold whitespace-nowrap">{{ setLabel(set) }}</span>
            </button>
        </TransitionGroup>

        <input
            :value="exercise.notes"
            type="text"
            maxlength="1000"
            enterkeyhint="done"
            aria-label="Note for this machine"
            placeholder="Note: seat height, how it felt…"
            class="mt-4 w-full border-b border-line bg-transparent py-2.5 outline-none placeholder:text-muted/70 focus:border-ink"
            @change="saveNotes"
            @keydown.enter="$event.target.blur()"
        />
        <div class="mt-1 flex flex-wrap items-center justify-between gap-x-4 text-[0.9375rem] font-semibold text-muted">
            <button class="press -ml-2 min-h-11 px-2" @click="openTyping">Type a weight</button>
            <Link :href="`/machines/${exercise.machine_id}`" class="press flex min-h-11 items-center px-2">History</Link>
            <button class="press -mr-2 min-h-11 px-2" @click="removing = true">Remove</button>
        </div>

        <BottomSheet :open="!!editing" :title="editing ? `Set ${editing.set_number}` : ''" @close="editing = null">
            <div class="mb-4 grid grid-cols-2 gap-3">
                <Stepper v-model="editWeight" label="Weight (lb)" :step-fn="stepMachineWeight" />
                <Stepper v-model="editReps" label="Reps" :step="1" />
            </div>
            <button class="btn-primary w-full" @click="saveSet">Save set</button>
            <button class="press mt-2 h-12 w-full font-semibold text-red" @click="deleteSet">Delete set</button>
        </BottomSheet>

        <BottomSheet :open="typing" title="Type a weight" @close="typing = false">
            <p class="mb-4 text-muted">For weights the stack can’t show, like a different machine or odd plates.</p>
            <Stepper v-model="typed" label="Weight (lb)" :step="2.5" class="mb-4" />
            <button class="btn-primary w-full" @click="applyTyped">Use this weight</button>
        </BottomSheet>

        <BottomSheet :open="removing" :title="`Remove ${exercise.machine_name}?`" @close="removing = false">
            <p class="mb-5 text-muted">
                {{ sets.length ? `This also deletes its ${plural(sets.length, 'set')} from this session.` : 'It has no sets in this session yet.' }}
            </p>
            <div class="grid grid-cols-2 gap-3">
                <button class="btn-soft" @click="removing = false">Keep</button>
                <button class="btn-danger" @click="removeExercise">Remove</button>
            </div>
        </BottomSheet>
    </AppLayout>
</template>
