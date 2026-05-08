<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    dateFrom: { type: String, default: '' },
    dateTo: { type: String, default: '' },
    rentalId: { type: [Number, String], required: true },
});

const emit = defineEmits(['mode-reset']);

/** Avoid RangeError from Date#toISOString on invalid input (breaks whole Monitor page). */
function toDatetimeLocalSlice(value) {
    if (!value) return '';
    const d = new Date(value);
    if (!Number.isFinite(d.getTime())) return '';
    return d.toISOString().slice(0, 16);
}

const fromDisplay = computed(() => toDatetimeLocalSlice(props.dateFrom));
const toDisplay = computed(() => toDatetimeLocalSlice(props.dateTo));

const fromInput = ref('');
const toInput = ref('');

watch([() => props.dateFrom, () => props.dateTo], ([from, to]) => {
    fromInput.value = toDatetimeLocalSlice(from);
    toInput.value = toDatetimeLocalSlice(to);
}, { immediate: true });

function applyRange() {
    const from = (fromInput.value || fromDisplay.value || '').toString().slice(0, 19);
    const to = (toInput.value || toDisplay.value || '').toString().slice(0, 19);
    if (from && to) {
        emit('mode-reset');
        router.get(route('rentals.monitor', props.rentalId), { from, to }, { preserveState: true });
    }
}

function setPreset(hours) {
    const end = new Date();
    const start = new Date(end);
    start.setHours(start.getHours() - hours);
    emit('mode-reset');
    router.get(route('rentals.monitor', props.rentalId), {
        from: start.toISOString().slice(0, 19),
        to: end.toISOString().slice(0, 19),
    }, { preserveState: true });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
        <label class="text-xs font-semibold text-slate-600">Range:</label>
        <input
            v-model="fromInput"
            type="datetime-local"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
        />
        <span class="text-slate-400">–</span>
        <input
            v-model="toInput"
            type="datetime-local"
            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
        />
        <button
            type="button"
            class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600"
            @click="applyRange"
        >
            Apply
        </button>
        <div class="flex gap-1">
            <button
                v-for="h in [6, 24, 72]"
                :key="h"
                type="button"
                class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-100"
                @click="setPreset(h)"
            >
                {{ h }} h
            </button>
        </div>
    </div>
</template>
