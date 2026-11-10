<script setup>
import { ref, computed, watch } from 'vue';
import BottomSheet from './BottomSheet.vue';
import { shortDate } from '../format.js';

const props = defineProps({
    open: Boolean,
    // [{ id, name, use_count, last_used }], most recently used first
    machines: { type: Array, default: () => [] },
    usedMachineIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'pick']);

const query = ref('');
watch(
    () => props.open,
    (open) => open && (query.value = ''),
);

const term = computed(() => query.value.trim().toLowerCase());
const matches = computed(() => props.machines.filter((m) => m.name.toLowerCase().includes(term.value)));
const exactMatch = computed(() => props.machines.some((m) => m.name.toLowerCase() === term.value));

const recent = computed(() => props.machines.filter((m) => m.use_count > 0).slice(0, 6));
const everything = computed(() => [...props.machines].sort((a, b) => a.name.localeCompare(b.name)));
const used = (m) => props.usedMachineIds.includes(m.id);
</script>

<template>
    <BottomSheet :open="open" title="Add machine" @close="emit('close')">
        <input
            v-model="query"
            type="search"
            enterkeyhint="search"
            autocomplete="off"
            aria-label="Search machines"
            placeholder="Search, or name a new machine"
            class="field mb-4"
        />

        <button
            v-if="term && !exactMatch"
            class="press mb-3 flex w-full items-center gap-3 rounded-3xl bg-ink px-5 py-4 text-left font-semibold text-bg"
            @click="emit('pick', { machine_name: query.trim() })"
        >
            <span class="text-2xl leading-none font-extrabold">+</span>
            Add “{{ query.trim() }}” as a new machine
        </button>

        <template v-if="!term">
            <template v-if="recent.length">
                <h3 class="label">Recently used</h3>
                <div class="mb-5 grid grid-cols-2 gap-2">
                    <button
                        v-for="(m, i) in recent"
                        :key="m.id"
                        class="press rise rounded-3xl bg-soft px-4 py-3 text-left"
                        :class="{ 'opacity-45': used(m) }"
                        :style="{ '--i': i }"
                        @click="emit('pick', { machine_id: m.id })"
                    >
                        <span class="block leading-tight font-semibold">{{ m.name }}</span>
                        <span class="mt-0.5 block text-sm text-muted">{{ used(m) ? 'Already in this session' : shortDate(m.last_used) }}</span>
                    </button>
                </div>
            </template>
            <h3 v-if="everything.length" class="label !mb-0">All machines</h3>
        </template>

        <ul class="divide-y divide-line">
            <li v-for="m in term ? matches : everything" :key="m.id">
                <button
                    class="flex min-h-12 w-full items-center justify-between gap-3 py-3 text-left active:opacity-60"
                    :class="{ 'opacity-45': used(m) }"
                    @click="emit('pick', { machine_id: m.id })"
                >
                    <span class="font-semibold">{{ m.name }}</span>
                    <span class="shrink-0 text-sm text-muted">{{ used(m) ? 'in session' : `${m.use_count}×` }}</span>
                </button>
            </li>
        </ul>

        <p v-if="!machines.length && !term" class="py-6 text-center text-muted">
            No machines yet. Type a name above to add your first one.
        </p>
    </BottomSheet>
</template>
