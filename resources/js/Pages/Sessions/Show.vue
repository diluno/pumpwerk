<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import MachineBlock from '../../Components/MachineBlock.vue';
import MachinePicker from '../../Components/MachinePicker.vue';
import CardioSheet from '../../Components/CardioSheet.vue';
import BottomSheet from '../../Components/BottomSheet.vue';
import CoachText from '../../Components/CoachText.vue';
import { band } from '../../bands.js';
import { useTween } from '../../motion.js';
import { formatDate, shortDate, num, volume, paceLabel, plural, setLabel, setsSentence, topWeight } from '../../format.js';

const props = defineProps({
    session: Object,
    lastByMachine: Object, // machine id → what was done the previous time
    previousFeedback: Object, // { date, text } from the latest earlier session | null
    machines: Array, // picker list (open sessions only)
});

const url = `/sessions/${props.session.id}`;
const keep = { preserveScroll: true };

const readonly = computed(() => props.session.finished);
const exercises = computed(() => props.session.exercises);
const usedMachineIds = computed(() => exercises.value.map((e) => e.machine_id));
const totalSets = computed(() => exercises.value.reduce((n, e) => n + e.sets.length, 0));
const totalVolume = computed(() => Math.round(exercises.value.reduce((n, e) => n + volume(e.sets), 0)));
const shownVolume = useTween(() => totalVolume.value, 600);

// Each machine as a row: its block, a line about today, and where tapping leads
const rows = computed(() =>
    exercises.value.map((e) => {
        const last = props.lastByMachine[e.machine_id] ?? null;
        const top = topWeight(e.sets);
        const lastTop = last ? topWeight(last.sets) : null;
        let detail = setsSentence(e.sets);
        if (!e.sets.length) detail = last ? `No sets yet. Last time ${setLabel(last.sets[0])}` : 'No sets yet';
        else if (readonly.value && lastTop !== null) {
            detail += top > lastTop ? `. Up from ${num(lastTop)}` : top < lastTop ? `. Down from ${num(lastTop)}` : '';
        }
        return {
            id: e.id,
            name: e.machine_name,
            href: readonly.value ? `/machines/${e.machine_id}` : `/exercises/${e.id}`,
            weight: top ?? lastTop,
            logged: top !== null,
            detail,
            notes: e.notes,
            volume: volume(e.sets),
            color: top !== null ? band(top).bg : null,
        };
    }),
);
// The session as one bar: each machine's share of the weight moved
const shares = computed(() => rows.value.filter((r) => r.volume > 0));

const pickerOpen = ref(false);
function addExercise(payload) {
    pickerOpen.value = false;
    router.post(`${url}/exercises`, payload);
}

const cardioOpen = ref(false);
const cardioEntry = ref(null);
function openCardio(entry = null) {
    cardioEntry.value = entry;
    cardioOpen.value = true;
}

// --- Coach: plan before, feedback after
const planOpen = ref(totalSets.value === 0);
const previousOpen = ref(false);
const planSheetOpen = ref(false);
const planNote = ref(props.session.plan_prompt ?? '');
const planLoading = ref(false);
const feedbackLoading = ref(false);

function generatePlan() {
    planLoading.value = true;
    router.post(
        `${url}/plan`,
        { note: planNote.value },
        {
            ...keep,
            onSuccess: () => {
                planSheetOpen.value = false;
                planOpen.value = true;
            },
            onFinish: () => (planLoading.value = false),
        },
    );
}

function generateFeedback() {
    feedbackLoading.value = true;
    router.post(`${url}/ai-feedback`, {}, { ...keep, onFinish: () => (feedbackLoading.value = false) });
}

// --- Session itself
const finishOpen = ref(false);
const menuOpen = ref(false);
const confirmDelete = ref(false);
watch(menuOpen, () => (confirmDelete.value = false));

function saveNotes(e) {
    if (e.target.value !== (props.session.notes ?? '')) {
        router.patch(url, { notes: e.target.value }, keep);
    }
}
function saveDate(e) {
    if (e.target.value && e.target.value !== props.session.date) {
        router.patch(url, { date: e.target.value }, keep);
    }
}
function finish() {
    router.post(`${url}/finish`, {}, { viewTransition: true, onSuccess: () => (finishOpen.value = false) });
}
function reopen() {
    menuOpen.value = false;
    router.post(`${url}/reopen`, {}, { viewTransition: true });
}
function destroySession() {
    if (!confirmDelete.value) return (confirmDelete.value = true);
    router.delete(url);
}

const chevron = 'h-5 w-5 shrink-0 transition-transform duration-300';
</script>

<template>
    <Head :title="formatDate(session.date, { day: 'numeric', month: 'short' })" />
    <AppLayout>
        <div class="flex items-center justify-between">
            <Link href="/" class="press -ml-1.5 flex h-11 items-center gap-1 pr-2 text-muted">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 5l-7 7 7 7" />
                </svg>
                Sessions
            </Link>
            <button class="press -mr-2 grid h-11 w-11 place-items-center rounded-full active:bg-soft" aria-label="Session options" @click="menuOpen = true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="5" cy="12" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="19" cy="12" r="2" />
                </svg>
            </button>
        </div>

        <h1 class="display mt-0.5 text-[2.625rem]" :style="{ viewTransitionName: `session-${session.id}` }">
            {{ formatDate(session.date, { weekday: 'long' }) }}<br />{{ formatDate(session.date, { day: 'numeric', month: 'long' }) }}
        </h1>
        <p class="mt-2 text-muted">
            <template v-if="readonly">Finished. </template>
            <template v-if="exercises.length || session.cardio.length">
                {{ plural(exercises.length, 'machine') }}, {{ plural(totalSets, 'set') }},
                <span class="tabular-nums">{{ num(Math.round(shownVolume)) }}</span> lb moved<template v-if="session.cardio.length">, cardio</template>
            </template>
            <template v-else>{{ readonly ? 'Nothing was logged.' : 'Nothing logged yet.' }}</template>
        </p>

        <div
            v-if="shares.length"
            class="mt-3.5 flex h-3.5 gap-[3px]"
            role="img"
            :aria-label="`Share of weight moved: ${shares.map((s) => s.name).join(', ')}`"
        >
            <span
                v-for="(s, i) in shares"
                :key="s.id"
                class="grow-x rounded-full transition-[flex-grow] duration-700 ease-out-soft"
                :style="{ flexGrow: s.volume, flexBasis: 0, backgroundColor: s.color, '--i': i }"
            />
        </div>

        <div class="mt-4 space-y-2">
            <!-- After the session: coach feedback leads -->
            <section v-if="readonly || session.ai_feedback" class="rise rounded-[1.75rem] bg-ink px-5 py-[1.125rem] text-bg">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-[1.375rem] font-extrabold tracking-tight">Coach feedback</h2>
                    <button v-if="session.ai_feedback && !feedbackLoading" class="press -my-3 -mr-2 min-h-11 px-2 text-[0.9375rem] text-faint" @click="generateFeedback">
                        Ask again
                    </button>
                </div>
                <p v-if="feedbackLoading" class="breathe mt-2 text-faint" role="status">Reading your session and recent history. This can take up to a minute.</p>
                <CoachText v-else-if="session.ai_feedback" :text="session.ai_feedback" class="mt-2.5" />
                <template v-else>
                    <p class="mt-1 mb-4 text-faint">What went well, what to watch, and target weights for next time.</p>
                    <button class="btn w-full bg-bg text-xl font-extrabold text-ink" :disabled="!totalSets && !session.cardio.length" @click="generateFeedback">
                        Get feedback
                    </button>
                </template>
            </section>

            <!-- Before and during: today's plan, steered by an optional note -->
            <template v-if="!readonly">
                <section v-if="session.plan" class="rounded-[1.75rem] bg-soft">
                    <button class="flex h-[3.375rem] w-full items-center justify-between px-[1.125rem] text-left font-semibold" :aria-expanded="planOpen" @click="planOpen = !planOpen">
                        Today’s plan from the coach
                        <svg :class="[chevron, { 'rotate-180': planOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>
                    <Transition name="unfold">
                        <div v-if="planOpen" class="unfold">
                            <div>
                                <div class="px-[1.125rem] pb-2">
                                    <p v-if="session.plan_prompt" class="mb-2 text-[0.9375rem] text-muted">You asked: “{{ session.plan_prompt }}”</p>
                                    <CoachText :text="session.plan" />
                                    <button class="press mt-1 -ml-2 min-h-11 px-2 text-[0.9375rem] font-semibold underline underline-offset-4" @click="planSheetOpen = true">Change plan</button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </section>
                <button v-else class="btn-soft w-full justify-between !px-[1.125rem]" @click="planSheetOpen = true">
                    Plan today with the coach
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <section v-if="previousFeedback" class="rounded-[1.75rem] bg-soft">
                    <button class="flex h-[3.375rem] w-full items-center justify-between gap-3 px-[1.125rem] text-left font-semibold" :aria-expanded="previousOpen" @click="previousOpen = !previousOpen">
                        Coach feedback from {{ shortDate(previousFeedback.date) }}
                        <svg :class="[chevron, { 'rotate-180': previousOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>
                    <Transition name="unfold">
                        <div v-if="previousOpen" class="unfold">
                            <div><CoachText :text="previousFeedback.text" class="px-[1.125rem] pb-4" /></div>
                        </div>
                    </Transition>
                </section>
            </template>

            <!-- One row per machine. Solid block: today's weight. Pale: nothing logged yet, showing last time's. -->
            <Link
                v-for="(r, i) in rows"
                :key="r.id"
                :href="r.href"
                class="card press rise flex items-center gap-3.5 py-2 pr-3.5 pl-2"
                :style="{ '--i': i }"
            >
                <MachineBlock :weight="r.weight" :logged="r.logged" />
                <span class="min-w-0 flex-1">
                    <span class="block text-xl font-extrabold tracking-tight" :style="{ viewTransitionName: readonly ? null : `machine-${r.id}` }">{{ r.name }}</span>
                    <span class="block text-[0.9375rem] text-muted">{{ r.detail }}</span>
                    <span v-if="r.notes" class="block truncate text-[0.9375rem] text-muted italic">{{ r.notes }}</span>
                </span>
                <svg class="h-5 w-5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <component
                :is="readonly ? 'div' : 'button'"
                v-for="c in session.cardio"
                :key="`c${c.id}`"
                class="card flex w-full items-center gap-3.5 py-2 pr-3.5 pl-2 text-left"
                :class="{ press: !readonly }"
                :aria-label="readonly ? null : `Edit ${c.type}`"
                @click="!readonly && openCardio(c)"
            >
                <span class="grid h-[3.75rem] w-[4.25rem] shrink-0 place-items-center rounded-[1.375rem] bg-soft text-xl leading-none font-extrabold">
                    <span>{{ num(c.duration_minutes) }}<span class="block text-[0.8125rem] font-normal text-muted">min</span></span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xl font-extrabold tracking-tight capitalize">{{ c.type }}</span>
                    <span class="block text-[0.9375rem] text-muted">
                        {{ [c.distance_km && `${num(c.distance_km)} km`, paceLabel(c.pace_seconds)].filter(Boolean).join(' at ') || 'Cardio' }}
                    </span>
                </span>
            </component>

            <div v-if="!readonly" class="grid grid-cols-2 gap-2">
                <button :class="exercises.length ? 'btn-soft' : 'btn-primary'" @click="pickerOpen = true">Add machine</button>
                <button class="btn-soft" @click="openCardio()">Add cardio</button>
            </div>
        </div>

        <section v-if="!readonly || session.notes" class="mt-5">
            <label for="notes" class="label">Session notes</label>
            <p v-if="readonly" class="whitespace-pre-line">{{ session.notes }}</p>
            <textarea v-else id="notes" :value="session.notes" rows="2" maxlength="5000" placeholder="Energy, sleep, anything the coach should know" class="field resize-none !text-base" @change="saveNotes" />
        </section>

        <button v-if="!readonly" class="btn-primary mt-5 w-full h-[3.75rem]" @click="finishOpen = true">Finish session</button>
        <button v-else class="press mx-auto mt-3 block min-h-11 px-4 text-[0.9375rem] text-muted" @click="reopen">Reopen to add or change sets</button>

        <!-- Sheets -->
        <MachinePicker :open="pickerOpen" :machines="machines" :used-machine-ids="usedMachineIds" @close="pickerOpen = false" @pick="addExercise" />
        <CardioSheet :open="cardioOpen" :session-id="session.id" :entry="cardioEntry" @close="cardioOpen = false" />

        <BottomSheet :open="planSheetOpen" :title="session.plan ? 'Change plan' : 'Plan today'" @close="planSheetOpen = false">
            <label for="plan-note" class="mb-3 block text-muted">
                The coach reads your recent sessions and drafts today’s machines, weights and reps. Anything it should work around?
            </label>
            <textarea id="plan-note" v-model="planNote" rows="3" maxlength="1000" placeholder="Optional: knee hurts, only 30 minutes, upper body today…" class="field mb-4 resize-none !text-base" />
            <button class="btn-primary w-full" :disabled="planLoading" @click="generatePlan">
                <span :class="{ breathe: planLoading }">{{ planLoading ? 'Planning…' : session.plan ? 'Make a new plan' : 'Make a plan' }}</span>
            </button>
            <p v-if="planLoading" class="mt-3 text-center text-sm text-muted" role="status">This can take up to a minute.</p>
        </BottomSheet>

        <BottomSheet :open="finishOpen" title="Finish session?" @close="finishOpen = false">
            <p class="mb-4 text-lg">
                {{ plural(exercises.length, 'machine') }}, {{ plural(totalSets, 'set') }}, {{ num(totalVolume) }} lb moved<template v-if="session.cardio.length">, cardio</template>
            </p>
            <button v-if="!session.cardio.length" class="press mb-4 w-full rounded-3xl bg-soft px-5 py-3.5 text-left" @click="finishOpen = false; openCardio()">
                No cardio logged. <span class="font-semibold underline underline-offset-4">Add cardio first</span>
            </button>
            <div class="grid grid-cols-2 gap-3">
                <button class="btn-soft" @click="finishOpen = false">Keep going</button>
                <button class="btn-primary" @click="finish">Finish</button>
            </div>
        </BottomSheet>

        <BottomSheet :open="menuOpen" title="Session" @close="menuOpen = false">
            <label for="session-date" class="label">Date</label>
            <input id="session-date" type="date" :value="session.date" class="field mb-4" @change="saveDate" />
            <button v-if="readonly" class="btn-soft mb-3 w-full" @click="reopen">Reopen session</button>
            <button class="btn w-full" :class="confirmDelete ? 'bg-red text-white' : 'text-red shadow-[inset_0_0_0_1.5px_var(--color-red)]'" @click="destroySession">
                {{ confirmDelete ? 'Tap again to delete everything in it' : 'Delete session' }}
            </button>
        </BottomSheet>
    </AppLayout>
</template>
