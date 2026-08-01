<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ExerciseCard from '../../Components/ExerciseCard.vue';
import MachinePicker from '../../Components/MachinePicker.vue';
import CardioSheet from '../../Components/CardioSheet.vue';
import BottomSheet from '../../Components/BottomSheet.vue';
import { renderMarkdown } from '../../markdown';

const props = defineProps({ session: Object, lastByMachine: Object, previousFeedback: Object });

const coachOpen = ref(false);
const planOpen = ref(true);
const planSheetOpen = ref(false);
const planNote = ref('');
const planLoading = ref(false);
const pickerOpen = ref(false);
const cardioOpen = ref(false);
const finishOpen = ref(false);
const aiLoading = ref(false);

const readonly = computed(() => props.session.finished);
const usedMachineIds = computed(() => props.session.exercises.map((e) => e.machine_id));
const totalSets = computed(() => props.session.exercises.reduce((n, e) => n + e.sets.length, 0));
const totalVolume = computed(() =>
    Math.round(props.session.exercises.reduce((n, e) => n + e.sets.reduce((m, s) => m + s.weight * s.reps, 0), 0)),
);

const dateLabel = new Date(props.session.date).toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function addExercise(payload) {
    pickerOpen.value = false;
    router.post(`/sessions/${props.session.id}/exercises`, payload, { preserveScroll: true });
}

function finish() {
    router.post(`/sessions/${props.session.id}/finish`);
}

function reopen() {
    router.post(`/sessions/${props.session.id}/reopen`, {}, { preserveScroll: true });
}

function destroySession() {
    if (confirm('Delete this whole session?')) {
        router.delete(`/sessions/${props.session.id}`);
    }
}

function removeCardio(c) {
    router.delete(`/cardio/${c.id}`, { preserveScroll: true });
}

function generateFeedback() {
    aiLoading.value = true;
    router.post(
        `/sessions/${props.session.id}/ai-feedback`,
        {},
        { preserveScroll: true, onFinish: () => (aiLoading.value = false) },
    );
}

function saveNotes(e) {
    router.patch(`/sessions/${props.session.id}`, { notes: e.target.value }, { preserveScroll: true });
}

function generatePlan() {
    planLoading.value = true;
    router.post(
        `/sessions/${props.session.id}/plan`,
        { note: planNote.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                planSheetOpen.value = false;
                planOpen.value = true;
            },
            onFinish: () => (planLoading.value = false),
        },
    );
}

const paceLabel = (s) => (s ? `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}/km` : null);
</script>

<template>
    <Head :title="`${session.date} · Pumpwerk`" />
    <AppLayout>
        <header class="rise-in mb-5">
            <Link href="/" class="mb-3 inline-flex items-center gap-1 text-sm text-muted active:text-ink">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
                All sessions
            </Link>
            <div class="flex items-start justify-between">
                <div>
                    <div class="mb-2 h-1.5 w-12" :class="readonly ? 'bg-line' : 'bg-volt'" />
                    <h1 class="font-display text-4xl uppercase leading-none">{{ dateLabel }}</h1>
                </div>
                <button class="p-2 text-muted active:text-danger" aria-label="Delete session" @click="destroySession">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                    </svg>
                </button>
            </div>
            <div class="mt-3 flex gap-4 text-sm text-muted">
                <span><b class="text-ink">{{ session.exercises.length }}</b> machines</span>
                <span><b class="text-ink">{{ totalSets }}</b> sets</span>
                <span><b class="text-ink">{{ totalVolume.toLocaleString() }}</b> kg volume</span>
            </div>
            <p v-if="readonly" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-raised px-3 py-1.5 text-sm text-muted">
                Session finished
                <button class="font-semibold text-volt" @click="reopen">Reopen</button>
            </p>
        </header>

        <div class="space-y-4">
            <!-- Today's plan: generate on demand, optionally steered by a note -->
            <section v-if="!readonly && session.plan" class="rise-in overflow-hidden rounded-2xl border border-volt bg-volt/10">
                <button class="flex w-full items-center justify-between px-4 py-3" @click="planOpen = !planOpen">
                    <span class="font-display text-lg uppercase text-volt">Today's plan</span>
                    <span class="flex items-center gap-3">
                        <span
                            class="rounded-lg px-2 py-1 text-xs font-semibold text-volt/80 underline-offset-2 hover:underline"
                            @click.stop="planSheetOpen = true"
                        >
                            Redo
                        </span>
                        <svg
                            class="h-5 w-5 text-volt transition-transform"
                            :class="{ 'rotate-180': planOpen }"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        >
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </span>
                </button>
                <div v-if="planOpen" class="border-t border-volt/20 px-4 py-3">
                    <p v-if="session.plan_prompt" class="mb-2 text-xs italic text-muted">"{{ session.plan_prompt }}"</p>
                    <div
                        class="space-y-2 text-sm leading-relaxed text-ink/90 [&_strong]:text-volt [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_h3]:font-bold [&_h4]:font-bold [&_h5]:font-bold"
                        v-html="renderMarkdown(session.plan)"
                    />
                </div>
            </section>
            <button
                v-else-if="!readonly"
                class="rise-in flex w-full items-center justify-center gap-2 rounded-2xl border border-volt/50 py-3.5 font-semibold text-volt active:bg-volt/10"
                @click="planSheetOpen = true"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2L4.09 12.11a.6.6 0 0 0 .45 1h5.96l-1.5 8.89L18.91 11.9a.6.6 0 0 0-.45-1h-5.96z" />
                </svg>
                Plan today's session
            </button>

            <section
                v-if="previousFeedback"
                class="rise-in overflow-hidden rounded-2xl border border-volt/40 bg-volt/5"
            >
                <button class="flex w-full items-center justify-between px-4 py-3" @click="coachOpen = !coachOpen">
                    <span class="font-display text-lg uppercase text-volt">Coach's plan · {{ previousFeedback.date }}</span>
                    <svg
                        class="h-5 w-5 text-volt transition-transform"
                        :class="{ 'rotate-180': coachOpen }"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    >
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>
                <div
                    v-if="coachOpen"
                    class="space-y-2 border-t border-volt/20 px-4 py-3 text-sm leading-relaxed text-ink/90 [&_strong]:text-volt [&_ul]:list-disc [&_ul]:pl-5 [&_h3]:font-bold [&_h4]:font-bold [&_h5]:font-bold"
                    v-html="renderMarkdown(previousFeedback.text)"
                />
            </section>

            <ExerciseCard
                v-for="e in session.exercises"
                :key="e.id"
                :exercise="e"
                :last="lastByMachine[e.machine_id] ?? null"
                :readonly="readonly"
            />

            <section v-if="session.cardio.length" class="rise-in overflow-hidden rounded-2xl border border-line bg-surface">
                <header class="border-b border-line px-4 py-3">
                    <h2 class="font-display text-xl uppercase">Cardio</h2>
                </header>
                <ul>
                    <li v-for="c in session.cardio" :key="c.id" class="flex items-center gap-3 border-b border-line/60 px-4 py-2.5">
                        <span class="flex-1 font-semibold capitalize">{{ c.type }}</span>
                        <span class="font-display text-xl">{{ c.duration_minutes }}<span class="text-xs text-muted">min</span></span>
                        <span v-if="c.distance_km" class="font-display text-xl">{{ c.distance_km }}<span class="text-xs text-muted">km</span></span>
                        <span v-if="c.pace_seconds" class="text-sm text-muted">{{ paceLabel(c.pace_seconds) }}</span>
                        <button v-if="!readonly" class="p-1.5 text-muted active:text-danger" aria-label="Delete cardio" @click="removeCardio(c)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </li>
                </ul>
            </section>

            <div v-if="!readonly" class="grid grid-cols-2 gap-3">
                <button
                    class="rounded-2xl border-2 border-dashed border-line py-4 font-semibold text-muted active:border-volt active:text-volt"
                    @click="pickerOpen = true"
                >
                    + Machine
                </button>
                <button
                    class="rounded-2xl border-2 border-dashed border-line py-4 font-semibold text-muted active:border-volt active:text-volt"
                    @click="cardioOpen = true"
                >
                    + Cardio
                </button>
            </div>

            <section class="rise-in rounded-2xl border border-line bg-surface px-4 py-3">
                <h2 class="mb-2 text-[11px] font-bold tracking-widest text-muted uppercase">Session notes</h2>
                <textarea
                    :value="session.notes"
                    :disabled="readonly"
                    rows="2"
                    placeholder="How did it go?"
                    class="w-full resize-none bg-transparent text-sm outline-none placeholder:text-muted/60"
                    @change="saveNotes"
                />
            </section>

            <section class="rise-in rounded-2xl border border-line bg-surface px-4 py-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-display text-xl uppercase">Coach</h2>
                    <button
                        class="rounded-lg bg-raised px-3 py-1.5 text-sm font-semibold text-volt active:bg-line disabled:opacity-50"
                        :disabled="aiLoading"
                        @click="generateFeedback"
                    >
                        {{ aiLoading ? 'Thinking…' : session.ai_feedback ? 'Regenerate' : 'Get feedback' }}
                    </button>
                </div>
                <div
                    v-if="session.ai_feedback"
                    class="prose-sm space-y-2 text-sm leading-relaxed text-ink/90 [&_strong]:text-volt [&_ul]:list-disc [&_ul]:pl-5 [&_h1]:font-bold [&_h2]:font-bold [&_h3]:font-bold"
                    v-html="renderMarkdown(session.ai_feedback)"
                />
                <p v-else class="text-sm text-muted">Get AI analysis of this session and suggested weights for next time.</p>
            </section>
        </div>

        <!-- Spacer so content can scroll clear of the floating finish bar -->
        <div v-if="!readonly" class="h-20" />

        <!-- Deliberate finish flow: separate action with summary confirmation -->
        <div
            v-if="!readonly"
            class="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+4.2rem)] z-30 mx-auto max-w-lg px-4"
        >
            <button
                class="font-display w-full rounded-xl border border-line bg-raised/95 py-3 text-lg uppercase tracking-wide backdrop-blur active:border-volt"
                @click="finishOpen = true"
            >
                Finish session
            </button>
        </div>

        <BottomSheet :open="planSheetOpen" title="Plan today" @close="planSheetOpen = false">
            <p class="mb-3 text-sm text-muted">
                The coach looks at your recent sessions and drafts today's machines, weights and reps.
                Anything it should know?
            </p>
            <textarea
                v-model="planNote"
                rows="3"
                placeholder="Optional — e.g. my knee hurts, plan around it / short on time, 30 min only / focus on upper body"
                class="mb-4 w-full resize-none rounded-xl border border-line bg-raised px-4 py-3 text-sm outline-none placeholder:text-muted/60 focus:border-volt"
            />
            <button
                class="font-display w-full rounded-xl bg-volt py-4 text-xl text-volt-ink uppercase active:bg-volt-bright disabled:opacity-60"
                :disabled="planLoading"
                @click="generatePlan"
            >
                {{ planLoading ? 'Thinking…' : session.plan ? 'Regenerate plan' : 'Generate plan' }}
            </button>
        </BottomSheet>

        <MachinePicker :open="pickerOpen" :used-machine-ids="usedMachineIds" @close="pickerOpen = false" @pick="addExercise" />
        <CardioSheet :open="cardioOpen" :session-id="session.id" @close="cardioOpen = false" />

        <BottomSheet :open="finishOpen" title="Finish session?" @close="finishOpen = false">
            <ul class="mb-4 space-y-1 text-sm text-muted">
                <li><b class="text-ink">{{ session.exercises.length }}</b> machines · <b class="text-ink">{{ totalSets }}</b> sets · <b class="text-ink">{{ totalVolume.toLocaleString() }}</b> kg</li>
                <li v-if="session.cardio.length"><b class="text-ink">{{ session.cardio.length }}</b> cardio {{ session.cardio.length === 1 ? 'entry' : 'entries' }}</li>
            </ul>

            <div v-if="!session.cardio.length" class="mb-4 rounded-xl border border-volt/50 bg-volt/10 px-4 py-3 text-sm">
                No cardio logged yet.
                <button class="font-bold text-volt" @click="finishOpen = false; cardioOpen = true">Add cardio first →</button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button class="rounded-xl border border-line py-3.5 font-semibold text-muted" @click="finishOpen = false">
                    Keep going
                </button>
                <button class="font-display rounded-xl bg-volt py-3.5 text-lg text-volt-ink uppercase" @click="finish">
                    Finish
                </button>
            </div>
        </BottomSheet>
    </AppLayout>
</template>
