<script setup>
defineProps({
    filterState: {
        type: Object,
        required: true,
    },
    listScopeOptions: {
        type: Array,
        required: true,
    },
    rentalStatusOptions: {
        type: Array,
        required: true,
    },
    paymentStatusOptions: {
        type: Array,
        required: true,
    },
    shipmentStatusOptions: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['apply-filters', 'reset-filters']);
</script>

<template>
    <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-8">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="list_scope">List</label>
                <select id="list_scope" v-model="filterState.scope" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700" @change="emit('apply-filters')">
                    <option v-for="option in listScopeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="status">Rental status</label>
                <select id="status" v-model="filterState.status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                    <option v-for="option in rentalStatusOptions" :key="option.value || 'all'" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="payment_status">Payment</label>
                <select id="payment_status" v-model="filterState.payment_status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                    <option v-for="option in paymentStatusOptions" :key="option.value || 'all'" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="shipment_status">Shipment</label>
                <select id="shipment_status" v-model="filterState.shipment_status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                    <option v-for="option in shipmentStatusOptions" :key="option.value || 'all'" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_from">Start from</label>
                <input id="date_from" v-model="filterState.date_from" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_to">Start to</label>
                <input id="date_to" v-model="filterState.date_to" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
            </div>

            <div class="lg:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="query">Search</label>
                <input
                    id="query"
                    v-model="filterState.q"
                    type="text"
                    placeholder="Rental ID, container serial, tracking"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    @keyup.enter="emit('apply-filters')"
                >
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" @click="emit('apply-filters')">
                Apply filters
            </button>
            <button type="button" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="emit('reset-filters')">
                Reset
            </button>
        </div>
    </section>
</template>
