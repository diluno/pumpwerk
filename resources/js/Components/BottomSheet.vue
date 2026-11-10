<script setup>
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({ open: Boolean, title: String });
const emit = defineEmits(['close']);

// Count open sheets so the page behind stays locked while any of them is up.
function lock(on) {
    const body = document.body;
    const count = Math.max(0, Number(body.dataset.sheets ?? 0) + (on ? 1 : -1));
    body.dataset.sheets = count;
    body.style.overflow = count ? 'hidden' : '';
}

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}

watch(
    () => props.open,
    (open, was) => {
        if (open === !!was) return;
        lock(open);
        document[open ? 'addEventListener' : 'removeEventListener']('keydown', onKey);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.open) {
        lock(false);
        document.removeEventListener('keydown', onKey);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="sheet">
            <div v-if="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-label="title">
                <div class="absolute inset-0 bg-ink/45" @click="emit('close')" />
                <div
                    class="sheet-panel absolute inset-x-0 bottom-0 mx-auto flex max-h-[90dvh] max-w-lg flex-col rounded-t-[2rem] bg-bg shadow-[0_-12px_40px_rgb(21_21_27/0.18)]"
                    style="padding-bottom: env(safe-area-inset-bottom)"
                >
                    <div class="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-line" aria-hidden="true" />
                    <div class="flex shrink-0 items-center justify-between pt-2 pr-2 pb-1 pl-5">
                        <h2 class="display text-[1.75rem]">{{ title }}</h2>
                        <button class="press grid h-11 w-11 place-items-center rounded-full text-muted active:bg-soft" aria-label="Close" @click="emit('close')">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto overscroll-contain px-5 pt-2 pb-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
