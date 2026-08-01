<script setup>
import { ref, computed, watch } from 'vue';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({ open: Boolean, usedMachineIds: { type: Array, default: () => [] } });
const emit = defineEmits(['close', 'pick']);

const machines = ref([]);
const query = ref('');
const loaded = ref(false);

watch(
    () => props.open,
    async (open) => {
        if (open) {
            query.value = '';
            const res = await fetch('/machines/picker', { headers: { Accept: 'application/json' } });
            machines.value = await res.json();
            loaded.value = true;
        }
    },
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return machines.value.filter((m) => !q || m.name.toLowerCase().includes(q));
});

const exactMatch = computed(() =>
    machines.value.some((m) => m.name.toLowerCase() === query.value.trim().toLowerCase()),
);

const recent = computed(() => filtered.value.slice(0, 6));
const rest = computed(() => filtered.value.slice(6).sort((a, b) => a.name.localeCompare(b.name)));
</script>

<template>
    <BottomSheet :open="open" title="Pick machine" @close="emit('close')">
        <input
            v-model="query"
            type="search"
            placeholder="Search or type a new machine…"
            class="mb-4 w-full rounded-xl border border-line bg-raised px-4 py-3 text-lg outline-none focus:border-volt"
        />

        <button
            v-if="query.trim() && !exactMatch"
            class="mb-4 flex w-full items-center gap-2 rounded-xl border border-dashed border-volt/60 px-4 py-3 text-left text-volt active:bg-volt/10"
            @click="emit('pick', { machine_name: query.trim() })"
        >
            <span class="font-display text-xl">+</span>
            Create “{{ query.trim() }}”
        </button>

        <template v-if="recent.length && !query">
            <p class="mb-2 text-[11px] font-bold tracking-widest text-muted uppercase">Recent</p>
            <div class="mb-4 grid grid-cols-2 gap-2">
                <button
                    v-for="m in recent"
                    :key="m.id"
                    class="rounded-xl border border-line bg-raised px-3 py-3.5 text-left font-semibold active:border-volt"
                    :class="{ 'opacity-40': usedMachineIds.includes(m.id) }"
                    @click="emit('pick', { machine_id: m.id })"
                >
                    {{ m.name }}
                    <span class="block text-[10px] font-normal text-muted">last {{ m.last_used ?? '—' }}</span>
                </button>
            </div>
        </template>

        <p v-if="rest.length && !query" class="mb-2 text-[11px] font-bold tracking-widest text-muted uppercase">All machines</p>
        <ul class="divide-y divide-line">
            <li v-for="m in query ? filtered : rest" :key="m.id">
                <button
                    class="flex w-full items-center justify-between py-3 text-left active:text-volt"
                    :class="{ 'opacity-40': usedMachineIds.includes(m.id) }"
                    @click="emit('pick', { machine_id: m.id })"
                >
                    <span class="font-semibold">{{ m.name }}</span>
                    <span class="text-xs text-muted">{{ m.use_count }}×</span>
                </button>
            </li>
        </ul>
        <p v-if="loaded && !filtered.length && !query.trim()" class="py-6 text-center text-muted">
            No machines yet — type a name above to create one.
        </p>
    </BottomSheet>
</template>
