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
    /** ISO date string (YYYY-MM-DD) used to compute per-leg ETAs. */
    startDate: {
        type: String,
        default: '',
    },
    /** Pre-assigned vessel/barge for the first leg (nullable). */
    assignedVessel: {
        type: Object,
        default: null,
    },
    /** Feasibility plan for the selected routing (nullable). */
    routePlan: {
        type: Object,
        default: null,
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

const addUtcCalendarDays = (iso, days) => {
    const [y, m, d] = String(iso || '').split('-').map((x) => parseInt(x, 10));
    if (!y || !m || !d) {
        return '';
    }
    const base = Date.UTC(y, m - 1, d);
    return new Date(base + Number(days) * 86400000).toISOString().slice(0, 10);
};

const formatLegDate = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
};

const routingLegRows = computed(() => {
    const legs = Array.isArray(props.routeContext?.route_legs) ? props.routeContext.route_legs : [];
    let cumulativeDays = 0;
    return legs.map((leg, i) => {
        const legDays = Number(leg.estimated_days) || 0;
        const departISO = props.startDate ? addUtcCalendarDays(props.startDate, cumulativeDays) : '';
        cumulativeDays += legDays;
        const arriveISO = props.startDate ? addUtcCalendarDays(props.startDate, cumulativeDays) : '';
        return {
            key: `${i}-${leg.route_id || ''}`,
            from: portLabel(leg.origin_port_id),
            to: portLabel(leg.destination_port_id),
            days: leg.estimated_days,
            km: leg.distance,
            depart: formatLegDate(departISO),
            arrive: formatLegDate(arriveISO),
        };
    });
});

const routingSummary = computed(() => {
    const legs = Array.isArray(props.routeContext?.route_legs) ? props.routeContext.route_legs : [];
    if (legs.length <= 1) {
        return legs.length === 1 ? 'Direct sea route' : '';
    }
    const intermediates = legs
        .slice(0, -1)
        .map((leg) => portLabel(leg.destination_port_id))
        .filter((label) => label && label !== '—');
    const via = intermediates.length ? ` via ${intermediates.join(', ')}` : '';
    return `Transshipment route — ${legs.length} legs${via}`;
});

const plan = computed(() => props.routePlan || null);
const planSegments = computed(() => (Array.isArray(plan.value?.segments) ? plan.value.segments : []));
const planWarnings = computed(() => (Array.isArray(plan.value?.warnings) ? plan.value.warnings : []));
const planHints = computed(() => (Array.isArray(plan.value?.hints) ? plan.value.hints : []));

const fmtHours = (h) => {
    const n = Number(h || 0);
    if (!Number.isFinite(n) || n <= 0) return '0h';
    const d = Math.floor(n / 24);
    const r = n % 24;
    return d > 0 ? `${d}d ${r}h` : `${r}h`;
};

const fmtIso = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
    } catch {
        return String(iso);
    }
};

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
            v-if="assignedVessel"
            class="mb-3 rounded-2xl border border-sky-200 bg-sky-50/70 px-3 py-2"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Assigned vessel</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ assignedVessel.name }}</p>
            <p class="text-[11px] text-slate-600">
                <span v-if="assignedVessel.status">{{ String(assignedVessel.status).replaceAll('_', ' ') }}</span>
                <span v-if="assignedVessel.capacity_teu"> · {{ assignedVessel.capacity_teu }} TEU</span>
            </p>
        </div>

        <div
            v-if="routingLegRows.length"
            class="mb-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Routing</p>
                <span
                    v-if="routingSummary"
                    class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                    :class="isMultiHopRouting ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                >
                    {{ routingSummary }}
                </span>
            </div>
            <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
                <li
                    v-for="(row, idx) in routingLegRows"
                    :key="row.key"
                    class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-1.5 last:border-0 last:pb-0"
                >
                    <span class="font-medium text-slate-800"
                        >Leg {{ idx + 1 }}: {{ row.from }} <span class="text-slate-500">-></span> {{ row.to }}</span
                    >
                    <span class="shrink-0 text-slate-600">
                        <span v-if="row.depart && row.arrive">{{ row.depart }} → {{ row.arrive }} · </span>
                        {{ row.days }} d · {{ Number(row.km || 0).toFixed(0) }} km
                    </span>
                </li>
            </ul>
        </div>

        <div
            v-if="plan && planSegments.length"
            class="mb-3 rounded-2xl border border-slate-200 bg-white p-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Feasibility plan</p>
                <span
                    class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                    :class="plan.can_create_rental ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'"
                >
                    {{ plan.can_create_rental ? 'Feasible' : 'Not feasible' }}
                </span>
            </div>
            <div class="mt-2 space-y-1.5 text-xs text-slate-700">
                <div
                    v-for="seg in planSegments"
                    :key="`plan-${seg.order}-${seg.route_id}`"
                    class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2"
                >
                    <p class="font-semibold text-slate-800">
                        Leg {{ seg.order }}: {{ seg.from_port_name || `#${seg.from_port_id}` }}
                        <span class="text-slate-500">-></span>
                        {{ seg.to_port_name || `#${seg.to_port_id}` }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-600">
                        Vessel: {{ seg.vessel?.name || '—' }}
                        <span v-if="seg.planned_departure && seg.planned_arrival"> · {{ fmtIso(seg.planned_departure) }} → {{ fmtIso(seg.planned_arrival) }}</span>
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Travel {{ fmtHours(seg.travel_duration_hours) }}
                        · Wait before {{ fmtHours(seg.waiting_time_before_departure_hours) }}
                        <span v-if="seg.waiting_time_after_arrival_hours"> · Handling after {{ fmtHours(seg.waiting_time_after_arrival_hours) }}</span>
                    </p>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-600">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
                    Total travel: {{ fmtHours(plan.total_travel_time_hours) }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
                    Total waiting: {{ fmtHours(plan.total_waiting_time_hours) }}
                </span>
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 font-semibold text-blue-800">
                    Minimum rental: {{ plan.minimum_rental_days }} day(s)
                </span>
                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">
                    Recommended: {{ plan.recommended_rental_days }} day(s)
                </span>
            </div>
            <div v-if="planWarnings.length" class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] text-rose-800">
                <p class="font-semibold">Warnings</p>
                <ul class="mt-1 list-disc pl-4">
                    <li v-for="(w, i) in planWarnings" :key="`w-${i}`">{{ w }}</li>
                </ul>
            </div>
            <div v-if="planHints.length" class="mt-2 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-700">
                <p class="font-semibold">Hints</p>
                <ul class="mt-1 list-disc pl-4">
                    <li v-for="(h, i) in planHints" :key="`h-${i}`">{{ h }}</li>
                </ul>
            </div>
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
