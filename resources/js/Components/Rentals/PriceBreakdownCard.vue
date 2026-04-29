<script setup>
import { computed } from 'vue';

const props = defineProps({
    estimatedPrice: {
        type: Number,
        default: 0,
    },
    routeContext: {
        type: Object,
        default: () => ({}),
    },
    priceBreakdown: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    /** Port list from rental request page (id + label) for leg labels. */
    ports: {
        type: Array,
        default: () => [],
    },
});

const formatMoney = (value) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

const isMultiHopRouting = computed(() => {
    const ctx = props.routeContext || {};
    if (Array.isArray(ctx.route_legs) && ctx.route_legs.length > 1) {
        return true;
    }
    return Boolean(ctx.multi_hop);
});

const portLabel = (id) => {
    if (id == null) {
        return '—';
    }
    const p = (props.ports || []).find((x) => String(x.id) === String(id));
    if (p) {
        return p.label || p.name || `#${p.id}`;
    }
    return `#${id}`;
};

const routingLegRows = computed(() => {
    const legs = Array.isArray(props.routeContext?.route_legs) ? props.routeContext.route_legs : [];
    return legs.map((leg, i) => ({
        key: `${i}-${leg.route_id || ''}`,
        from: portLabel(leg.origin_port_id),
        to: portLabel(leg.destination_port_id),
        days: leg.estimated_days,
        km: leg.distance,
    }));
});

const rows = computed(() => {
    if (!props.priceBreakdown) return [];

    const all = [
        { label: 'Base price', value: props.priceBreakdown.base_price },
        { label: 'Distance component', value: props.priceBreakdown.distance_component },
        { label: 'Daily component', value: props.priceBreakdown.daily_component },
        { label: 'Cargo component', value: props.priceBreakdown.cargo_component },
        { label: 'Weight component', value: props.priceBreakdown.weight_component },
        { label: 'Volume component', value: props.priceBreakdown.volume_component },
        { label: 'Package component', value: props.priceBreakdown.package_component },
        { label: 'IoT surcharge', value: props.priceBreakdown.iot_surcharge },
        { label: 'Transit urgency surcharge', value: props.priceBreakdown.transit_urgency_surcharge },
        { label: 'Priority SLA surcharge', value: props.priceBreakdown.priority_surcharge },
        { label: 'Hazardous handling surcharge', value: props.priceBreakdown.hazardous_surcharge },
        { label: 'Customs handling fee', value: props.priceBreakdown.customs_handling_fee },
        { label: 'Delivery mode fee', value: props.priceBreakdown.delivery_mode_fee },
        { label: 'LCL handling fee', value: props.priceBreakdown.lcl_handling_fee },
        { label: 'Escort fee', value: props.priceBreakdown.escort_fee },
        { label: 'Seal fee', value: props.priceBreakdown.seal_fee },
        { label: 'Sustainability fee', value: props.priceBreakdown.sustainability_fee },
        { label: 'Insurance cost', value: props.priceBreakdown.insurance_cost },
        { label: 'Long-term discount', value: -Math.abs(Number(props.priceBreakdown.long_term_discount || 0)) },
        { label: `Tax (${props.priceBreakdown.tax_rate || 0}%)`, value: props.priceBreakdown.tax_amount },
    ];

    return all.filter((row) => Math.abs(Number(row.value)) > 0);
});
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Step 3</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Auto price calculator</h3>
            </div>
            <span v-if="loading" class="rounded-full border border-slate-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                Calculating…
            </span>
            <span v-else class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                Distance {{ Number(routeContext.distance || 0).toFixed(0) }} km
            </span>
        </div>

        <div
            v-if="routingLegRows.length"
            class="mb-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Routing{{ isMultiHopRouting ? ' (multi-segment)' : '' }}
            </p>
            <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
                <li
                    v-for="(row, idx) in routingLegRows"
                    :key="row.key"
                    class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-1.5 last:border-0 last:pb-0"
                >
                    <span class="font-medium text-slate-800"
                        >Leg {{ idx + 1 }}: {{ row.from }} <span class="text-slate-500">-></span> {{ row.to }}</span
                    >
                    <span class="shrink-0 text-slate-600">{{ row.days }} d · {{ Number(row.km || 0).toFixed(0) }} km</span>
                </li>
            </ul>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimated total</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ formatMoney(estimatedPrice) }}</p>
            <p v-if="priceBreakdown?.days" class="mt-1 text-xs text-slate-600">
                Rental period: {{ priceBreakdown.days }} days
            </p>
        </div>

        <div v-if="rows.length" class="mt-4 space-y-2">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/40 px-3 py-2 text-xs"
            >
                <span class="font-medium text-slate-600">{{ row.label }}</span>
                <span class="font-semibold text-slate-900">{{ formatMoney(row.value) }}</span>
            </div>
        </div>
    </section>
</template>
