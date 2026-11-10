<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { startSession } from '../session.js';

// `nav: false` gives a screen the whole height (the machine screen)
defineProps({ nav: { type: Boolean, default: true } });

const page = usePage();
const activeSessionId = computed(() => page.props.activeSessionId);

const tab = computed(() => {
    const url = page.url;
    if (url.startsWith('/progression') || url.startsWith('/machines')) return 'progress';
    if (activeSessionId.value && (url.startsWith(`/sessions/${activeSessionId.value}`) || url.startsWith('/exercises'))) return 'today';
    return 'sessions';
});

// Anything the server rejected (validation, coach failures) surfaces here.
const error = ref(null);
let timer;
watch(
    () => page.props.errors,
    (errors) => {
        clearTimeout(timer);
        error.value = Object.values(errors ?? {})[0] ?? null;
        if (error.value) timer = setTimeout(() => (error.value = null), 8000);
    },
    { immediate: true },
);

const item = 'press flex h-[2.875rem] items-center justify-center gap-2 rounded-full';
const on = 'bg-ink font-semibold text-bg';
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <Transition name="toast">
            <div v-if="error" role="alert" class="fixed inset-x-0 top-0 z-[60] mx-auto max-w-lg px-4 pt-[calc(env(safe-area-inset-top)+0.75rem)]">
                <button class="w-full rounded-3xl bg-red px-5 py-3.5 text-left text-white shadow-[0_10px_30px_rgb(217_45_32/0.35)]" @click="error = null">
                    <span class="font-extrabold">Didn’t work.</span> {{ error }}
                </button>
            </div>
        </Transition>

        <main class="flex-1 px-5 pt-[calc(env(safe-area-inset-top)+0.75rem)]" :class="nav ? 'pb-28' : 'pb-[calc(env(safe-area-inset-bottom)+1.25rem)]'">
            <slot />
        </main>

        <nav
            v-if="nav"
            aria-label="Main"
            class="vt-nav fixed inset-x-0 bottom-0 z-40 border-t border-line bg-bg/92 backdrop-blur"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <div class="mx-auto grid max-w-lg grid-cols-3 gap-1 px-3 pt-2 pb-2.5">
                <Link href="/" :class="[item, tab === 'sessions' ? on : 'text-muted']" :aria-current="tab === 'sessions' ? 'page' : null">Sessions</Link>

                <!-- Jumps back into the session being logged, or starts today's -->
                <Link
                    v-if="activeSessionId"
                    :href="`/sessions/${activeSessionId}`"
                    :class="[item, tab === 'today' ? on : 'font-semibold text-ink']"
                    :aria-current="tab === 'today' ? 'page' : null"
                >
                    <span v-if="tab !== 'today'" class="breathe h-2 w-2 rounded-full bg-red" aria-hidden="true" />
                    In progress
                </Link>
                <button v-else :class="[item, 'text-muted']" @click="startSession">Start session</button>

                <Link href="/progression" :class="[item, tab === 'progress' ? on : 'text-muted']" :aria-current="tab === 'progress' ? 'page' : null">Progress</Link>
            </div>
        </nav>
    </div>
</template>
