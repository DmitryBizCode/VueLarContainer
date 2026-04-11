<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { primeSanctumCsrfOnce, rentalJsonApiPath } from '@/utils/spaApi';

const props = defineProps({
    rentalId: { type: [Number, String], required: true },
    dateFrom: { type: String, default: '' },
    dateTo: { type: String, default: '' },
    iotChartsConfig: { type: Object, default: () => ({}) },
});

const layouts = ref([]);
const loading = ref(false);
const saveModalOpen = ref(false);
const newLayoutName = ref('');

async function loadLayouts() {
    loading.value = true;
    try {
        await primeSanctumCsrfOnce();
        const { data } = await axios.get(rentalJsonApiPath(props.rentalId, '/chart-layouts'));
        layouts.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    const params = new URLSearchParams();
    if (props.dateFrom) params.set('from', props.dateFrom);
    if (props.dateTo) params.set('to', props.dateTo);
    const url = `${rentalJsonApiPath(props.rentalId, '/telemetry/export-csv')}?${params}`;
    window.open(url, '_blank');
}

async function saveCurrent() {
    if (!newLayoutName.value.trim()) return;
    try {
        await primeSanctumCsrfOnce();
        await axios.post(rentalJsonApiPath(props.rentalId, '/chart-layouts'), {
            name: newLayoutName.value.trim(),
            is_default: false,
            config: props.iotChartsConfig || {},
        });
        saveModalOpen.value = false;
        newLayoutName.value = '';
        await loadLayouts();
    } catch (e) {
        console.error(e);
    }
}

onMounted(loadLayouts);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            @click="exportCsv"
        >
            Export CSV
        </button>
        <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            @click="saveModalOpen = true"
        >
            Save layout
        </button>
        <div v-if="saveModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="saveModalOpen = false">
            <div class="rounded-xl bg-white p-4 shadow-lg">
                <p class="mb-2 text-sm font-semibold">Layout name</p>
                <input
                    v-model="newLayoutName"
                    type="text"
                    class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    placeholder="e.g. My dashboard"
                    @keyup.enter="saveCurrent"
                />
                <div class="mt-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600"
                        @click="saveCurrent"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        @click="saveModalOpen = false; newLayoutName = ''"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
