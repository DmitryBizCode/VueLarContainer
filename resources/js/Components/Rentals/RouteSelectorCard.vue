<script setup>
import { DatePicker } from 'v-calendar';
import 'v-calendar/style.css';
import { computed, watch } from 'vue';

const props = defineProps({
    routes: {
        type: Array,
        default: () => [],
    },
    ports: {
        type: Array,
        default: () => [],
    },
    /** Origin options for "Select ports" — ports with at least one available container (load-out). */
    originPorts: {
        type: Array,
        default: () => [],
    },
    routeMode: {
        type: String,
        required: true,
    },
    routeId: {
        type: [String, Number, null],
        default: '',
    },
    originPortId: {
        type: [String, Number, null],
        default: '',
    },
    destinationPortId: {
        type: [String, Number, null],
        default: '',
    },
    startDate: {
        type: String,
        default: '',
    },
    endDate: {
        type: String,
        default: '',
    },
    requestedWeight: {
        type: [String, Number, null],
        default: '',
    },
    minStartDate: {
        type: String,
        default: '',
    },
    minEndDate: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'update:routeMode',
    'update:routeId',
    'update:originPortId',
    'update:destinationPortId',
    'update:startDate',
    'update:endDate',
    'update:requestedWeight',
]);

const destinationPortsOptions = computed(() => {
    const originId = props.originPortId != null ? String(props.originPortId) : '';
    if (!originId) return props.ports;
    return props.ports.filter((port) => String(port.id) !== originId);
});

watch(
    () => [props.routeMode, props.originPorts, props.originPortId],
    () => {
        if (props.routeMode !== 'ports') return;
        const id = props.originPortId != null && props.originPortId !== '' ? String(props.originPortId) : '';
        if (!id) return;
        const list = props.originPorts?.length ? props.originPorts : [];
        const valid = list.some((p) => String(p.id) === id);
        if (!valid) {
            emit('update:originPortId', '');
        }
    },
    { immediate: true }
);

const startDateModel = computed({
    get: () => (props.startDate ? new Date(props.startDate + 'T12:00:00') : null),
    set: (v) => emit('update:startDate', v ? new Date(v).toISOString().slice(0, 10) : ''),
});

const minStartDateModel = computed(() => (props.minStartDate ? new Date(props.minStartDate + 'T12:00:00') : null));

const endDateModel = computed({
    get: () => (props.endDate ? new Date(props.endDate + 'T12:00:00') : null),
    set: (v) => emit('update:endDate', v ? new Date(v).toISOString().slice(0, 10) : ''),
});

const minEndDateModel = computed(() => (props.minEndDate ? new Date(props.minEndDate + 'T12:00:00') : null));
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Step 1</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Route and rental period</h3>
            </div>
            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                    :class="routeMode === 'route' ? 'bg-slate-900 text-white' : 'text-slate-600'"
                    @click="emit('update:routeMode', 'route')"
                >
                    Existing route
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                    :class="routeMode === 'ports' ? 'bg-slate-900 text-white' : 'text-slate-600'"
                    @click="emit('update:routeMode', 'ports')"
                >
                    Select ports
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div v-if="routeMode === 'route'" class="md:col-span-2">
                <label for="route_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Route</label>
                <select
                    id="route_id"
                    :value="routeId"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    @change="emit('update:routeId', $event.target.value)"
                >
                    <option value="">Select route</option>
                    <option v-for="route in routes" :key="route.id" :value="route.id">
                        {{ route.label }} · {{ route.distance }} km
                    </option>
                </select>
            </div>

            <template v-else>
                <div>
                    <label for="origin_port_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origin port</label>
                    <select
                        id="origin_port_id"
                        :value="originPortId"
                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                        :disabled="!originPorts.length"
                        @change="emit('update:originPortId', $event.target.value)"
                    >
                        <option value="">Select origin port</option>
                        <option v-for="port in originPorts" :key="`origin-${port.id}`" :value="port.id">
                            {{ port.label }}
                        </option>
                    </select>
                    <p v-if="!originPorts.length" class="mt-1.5 text-xs text-amber-700">
                        No available container is currently at any port. You cannot start a new rental from origin until stock is available.
                    </p>
                </div>

                <div>
                    <label for="destination_port_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destination port</label>
                    <select
                        id="destination_port_id"
                        :value="destinationPortId"
                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                        @change="emit('update:destinationPortId', $event.target.value)"
                    >
                        <option value="">Select destination port</option>
                        <option v-for="port in destinationPortsOptions" :key="`dest-${port.id}`" :value="port.id">
                            {{ port.label }}
                        </option>
                    </select>
                </div>
            </template>

            <div>
                <label for="start_date" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Start date</label>
                <DatePicker
                    id="start_date"
                    v-model="startDateModel"
                    mode="date"
                    :min-date="minStartDateModel"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white text-sm shadow-sm transition focus-within:border-blue-700 focus-within:ring-2 focus-within:ring-blue-700/20"
                    :popover="{ visibility: 'click', placement: 'bottom-start' }"
                />
            </div>

            <div>
                <label for="end_date" class="text-xs font-semibold uppercase tracking-wide text-slate-500">End date</label>
                <DatePicker
                    id="end_date"
                    v-model="endDateModel"
                    mode="date"
                    :min-date="minEndDateModel"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white text-sm shadow-sm transition focus-within:border-blue-700 focus-within:ring-2 focus-within:ring-blue-700/20"
                    :popover="{ visibility: 'click', placement: 'bottom-start' }"
                />
            </div>

            <div class="md:col-span-2">
                <label for="requested_weight" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested cargo weight</label>
                <div class="mt-1.5 flex w-full items-stretch rounded-xl border border-slate-200 bg-white text-sm shadow-sm focus-within:border-blue-700 focus-within:ring-2 focus-within:ring-blue-700/20">
                    <input
                        id="requested_weight"
                        type="number"
                        min="0"
                        step="0.01"
                        :value="requestedWeight"
                        class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:ring-0"
                        placeholder="e.g. 24000"
                        @input="emit('update:requestedWeight', $event.target.value)"
                    >
                    <span class="inline-flex items-center border-l border-slate-200 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        kg
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
