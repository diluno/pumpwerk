<script setup>
defineProps({ open: Boolean, title: String });
const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/60" @click="emit('close')" />
            <div
                class="sheet-up absolute inset-x-0 bottom-0 mx-auto max-w-lg rounded-t-2xl border-t border-line bg-surface shadow-2xl"
                style="padding-bottom: env(safe-area-inset-bottom)"
            >
                <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-line" />
                <div class="flex items-center justify-between px-5 pt-3 pb-1">
                    <h2 class="font-display text-xl uppercase">{{ title }}</h2>
                    <button class="p-2 text-muted" @click="emit('close')" aria-label="Close">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
                <div class="max-h-[70dvh] overflow-y-auto px-5 pt-2 pb-6">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
