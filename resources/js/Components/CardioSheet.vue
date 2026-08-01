<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BottomSheet from './BottomSheet.vue';
import Stepper from './Stepper.vue';

const props = defineProps({ open: Boolean, sessionId: Number });
const emit = defineEmits(['close']);

const type = ref('laufband');
const types = ['laufband', 'crosstrainer', 'bike', 'rowing', 'stairs'];
const duration = ref(20);
const distance = ref(null);
const paceMin = ref(null);
const paceSec = ref(null);

function save() {
    const pace =
        paceMin.value || paceSec.value
            ? (parseInt(paceMin.value) || 0) * 60 + (parseInt(paceSec.value) || 0)
            : null;
    router.post(
        `/sessions/${props.sessionId}/cardio`,
        {
            type: type.value,
            duration_minutes: parseFloat(duration.value) || 0,
            distance_km: distance.value ? parseFloat(distance.value) : null,
            pace_seconds: pace,
        },
        { preserveScroll: true, onSuccess: () => emit('close') },
    );
}
</script>

<template>
    <BottomSheet :open="open" title="Add cardio" @close="emit('close')">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="t in types"
                :key="t"
                class="rounded-full border px-4 py-2 text-sm font-semibold capitalize"
                :class="type === t ? 'border-volt bg-volt text-volt-ink' : 'border-line bg-raised text-muted'"
                @click="type = t"
            >
                {{ t }}
            </button>
        </div>

        <div class="mb-4 flex justify-center gap-4">
            <Stepper v-model="duration" label="Duration" unit="min" :step="5" />
            <Stepper v-model="distance" label="Distance" unit="km" :step="0.5" />
        </div>

        <div class="mb-6">
            <p class="mb-1 text-center text-[10px] font-semibold tracking-widest text-muted uppercase">Pace (per km, optional)</p>
            <div class="flex items-center justify-center gap-2">
                <input
                    v-model="paceMin"
                    type="number"
                    inputmode="numeric"
                    placeholder="mm"
                    class="font-display w-20 rounded-xl border border-line bg-raised py-3 text-center text-2xl outline-none focus:border-volt"
                />
                <span class="font-display text-2xl text-muted">:</span>
                <input
                    v-model="paceSec"
                    type="number"
                    inputmode="numeric"
                    placeholder="ss"
                    class="font-display w-20 rounded-xl border border-line bg-raised py-3 text-center text-2xl outline-none focus:border-volt"
                />
            </div>
        </div>

        <button class="font-display w-full rounded-xl bg-volt py-4 text-xl text-volt-ink uppercase active:bg-volt-bright" @click="save">
            Save cardio
        </button>
    </BottomSheet>
</template>
