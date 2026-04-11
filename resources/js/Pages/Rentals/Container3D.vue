<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import axios from 'axios';
import { useContainer3DScene } from '@/composables/useContainer3DScene';

const props = defineProps({
    rental: {
        type: Object,
        required: true,
    },
    container: {
        type: Object,
        required: true,
    },
    actuators: {
        type: Object,
        default: () => ({}),
    },
});

const containerState = reactive({
    mainLight: props.actuators.mainLight ?? false,
    irLamp: props.actuators.irLamp ?? false,
    acStatus: props.actuators.acStatus ?? false,
    acTemp: props.actuators.acTemp ?? 22,
    doorOpen: props.actuators.doorOpen ?? false,
    freshenerTrigger: 0,
    freshenerOn: props.actuators.freshenerOn ?? false,
    pump: props.actuators.pump ?? false,
    fireSprinklerTrigger: 0,
    humidifier: props.actuators.humidifier ?? false,
    heater: props.actuators.heater ?? false,
    ventilation: props.actuators.ventilation ?? false,
    smokeAlarm: false,
});

let syncTimeout = null;
function syncWithBackend() {
    if (syncTimeout) clearTimeout(syncTimeout);
    syncTimeout = setTimeout(async () => {
        try {
            await axios.patch(route('api.rentals.simulation.actuators', props.rental.id), {
                acStatus: containerState.acStatus,
                acTemp: containerState.acTemp,
                humidifier: containerState.humidifier,
                heater: containerState.heater,
                ventilation: containerState.ventilation,
                mainLight: containerState.mainLight,
                irLamp: containerState.irLamp,
                pump: containerState.pump,
                doorOpen: containerState.doorOpen,
                freshenerOn: containerState.freshenerOn,
            });
        } catch (e) {
            console.warn('Actuator sync failed:', e);
        }
    }, 400);
}

watch(
    () => ({
        mainLight: containerState.mainLight,
        irLamp: containerState.irLamp,
        acStatus: containerState.acStatus,
        acTemp: containerState.acTemp,
        pump: containerState.pump,
        doorOpen: containerState.doorOpen,
        humidifier: containerState.humidifier,
        heater: containerState.heater,
        ventilation: containerState.ventilation,
        freshenerOn: containerState.freshenerOn,
    }),
    () => syncWithBackend(),
    { deep: true }
);

const containerRef = ref(null);
useContainer3DScene(containerRef, props.container, containerState);
</script>

<template>
    <Head title="3D Container View" />

    <div class="fixed inset-0 h-screen w-screen overflow-hidden bg-slate-900">
        <div ref="containerRef" class="absolute inset-0 z-0" style="width: 100%; height: 100%; min-width: 1px; min-height: 1px;" />

        <div class="pointer-events-none absolute inset-0 z-10 flex flex-col justify-between p-4 sm:p-6">
            <div class="pointer-events-auto flex items-center justify-between">
                <Link
                    :href="route('rentals.monitor', rental.id)"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-slate-900/80 px-4 py-2.5 text-sm font-semibold text-white shadow-lg backdrop-blur-sm transition hover:bg-slate-800/90"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to IoT Monitor
                </Link>
                <div class="rounded-xl border border-white/20 bg-slate-900/80 px-4 py-2.5 text-sm font-medium text-white backdrop-blur-sm">
                    {{ container.serial_number }} · {{ container.width }} × {{ container.length }} × {{ container.height }} m
                </div>
            </div>

            <div class="pointer-events-auto w-full max-w-sm rounded-2xl border border-white/10 bg-slate-900/90 shadow-xl backdrop-blur-md max-h-[85vh] overflow-y-auto">
                <div class="border-b border-white/10 px-4 py-3 sticky top-0 bg-slate-900/95 backdrop-blur z-10">
                    <h3 class="text-sm font-semibold text-white">Smart Container IoT</h3>
                </div>
                <div class="space-y-3 p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Lighting</p>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Main light</span>
                        <input v-model="containerState.mainLight" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">IR lamp</span>
                        <input v-model="containerState.irLamp" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>

                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 pt-1">Climate</p>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">AC</span>
                        <input v-model="containerState.acStatus" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">AC temp</span>
                        <input
                            v-model.number="containerState.acTemp"
                            type="range"
                            min="16"
                            max="30"
                            class="w-24 accent-blue-500"
                        />
                        <span class="w-8 text-right text-xs text-slate-400">{{ containerState.acTemp }} °C</span>
                    </div>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Humidifier</span>
                        <input v-model="containerState.humidifier" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Heater</span>
                        <input v-model="containerState.heater" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Ventilation</span>
                        <input v-model="containerState.ventilation" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>

                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 pt-1">Water & safety</p>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Drain pump</span>
                        <input v-model="containerState.pump" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <button
                        type="button"
                        class="w-full rounded-lg border border-white/20 bg-slate-700/80 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-600/80"
                        @click="containerState.fireSprinklerTrigger += 1"
                    >
                        Fire sprinkler (test)
                    </button>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Smoke alarm (test)</span>
                        <input v-model="containerState.smokeAlarm" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-red-500 focus:ring-red-500" />
                    </label>

                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 pt-1">Access</p>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Cargo door (open)</span>
                        <input v-model="containerState.doorOpen" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>

                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 pt-1">Air</p>
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm text-slate-200">Air freshener (always on)</span>
                        <input v-model="containerState.freshenerOn" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-blue-500 focus:ring-blue-500" />
                    </label>
                    <button
                        type="button"
                        class="w-full rounded-lg border border-white/20 bg-slate-700/80 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-600/80"
                        @click="containerState.freshenerTrigger += 1"
                    >
                        One-shot spray (animation)
                    </button>
                </div>
            </div>

            <div class="pointer-events-none rounded-xl border border-white/10 bg-slate-900/60 px-4 py-2 text-xs text-slate-300 backdrop-blur-sm">
                Drag to rotate · Scroll to zoom · Click devices or the cargo door in the scene, or use the panel
            </div>
        </div>
    </div>
</template>
