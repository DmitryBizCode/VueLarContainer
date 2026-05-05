<script setup>
import RouteSelectorCard from '@/Components/Rentals/RouteSelectorCard.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    routes: {
        type: Array,
        default: () => [],
    },
    ports: {
        type: Array,
        default: () => [],
    },
    originPorts: {
        type: Array,
        default: () => [],
    },
    routingPriorityOptions: {
        type: Array,
        default: () => [],
    },
    minStartDate: {
        type: String,
        required: true,
    },
    maxStartDate: {
        type: String,
        default: '',
    },
    minEndDate: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div>
        <RouteSelectorCard
            :routes="routes"
            :ports="ports"
            :origin-ports="originPorts"
            :route-mode="form.route_mode"
            :route-id="form.route_id"
            :origin-port-id="form.origin_port_id"
            :destination-port-id="form.destination_port_id"
            :start-date="form.start_date"
            :end-date="form.end_date"
            :requested-weight="form.requested_weight"
            :min-start-date="minStartDate"
            :max-start-date="maxStartDate"
            :min-end-date="minEndDate"
            @update:route-mode="form.route_mode = $event"
            @update:route-id="form.route_id = $event"
            @update:origin-port-id="form.origin_port_id = $event"
            @update:destination-port-id="form.destination_port_id = $event"
            @update:start-date="form.start_date = $event"
            @update:end-date="form.end_date = $event"
            @update:requested-weight="form.requested_weight = $event"
        />

        <div
            v-if="routingPriorityOptions.length"
            class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sea routing</p>
            <p class="mt-1 text-sm text-slate-600">
                When no direct lane exists, we search the open route graph. Override how that path is chosen.
            </p>
            <select
                v-model="form.routing_priority"
                class="mt-3 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
            >
                <option
                    v-for="opt in routingPriorityOptions"
                    :key="opt.value === '' || opt.value == null ? 'auto' : opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
        </div>
    </div>
</template>
