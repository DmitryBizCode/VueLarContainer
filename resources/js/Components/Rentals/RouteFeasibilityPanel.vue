<script setup>
import { computed } from 'vue';

const props = defineProps({
    routePlan: {
        type: Object,
        default: null,
    },
    routeContext: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const show = computed(() => props.loading || !!props.routePlan || !!props.error);

const plan = computed(() => props.routePlan || null);

const segments = computed(() => (Array.isArray(plan.value?.segments) ? plan.value.segments : []));
const warnings = computed(() => (Array.isArray(plan.value?.warnings) ? plan.value.warnings : []));
const hints = computed(() => (Array.isArray(plan.value?.hints) ? plan.value.hints : []));

const statusBadge = computed(() => {
    if (props.loading) {
        return { label: 'Checking route…', cls: 'border-slate-200 bg-slate-50 text-slate-500' };
    }
    if (props.error || (plan.value && !plan.value.can_create_rental)) {
        return { label: 'Route unavailable', cls: 'border-rose-200 bg-rose-50 text-rose-700' };
    }
    if (!plan.value) {
        return null;
    }
    const legs = segments.value.length;
    const label = legs > 1 ? `Route available — Multi-hop (${legs} legs)` : 'Route available — Direct';
    return { label, cls: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
});

const fmtIso = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat('en-GB', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(iso));
    } catch {
        return String(iso);
    }
};

const fmtHours = (h) => {
    const n = Number(h || 0);
    if (!Number.isFinite(n) || n <= 0) return '0 h';
    const d = Math.floor(n / 24);
    const r = n % 24;
    return d > 0 ? `${d}d ${r}h` : `${r}h`;
};
</script>

<template>
    <div v-if="show" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <!-- Header row -->
        <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b border-slate-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Route feasibility</p>
            <span
                v-if="statusBadge"
                class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                :class="statusBadge.cls"
            >
                {{ statusBadge.label }}
            </span>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading && !plan" class="space-y-2 p-4">
            <div class="h-3 w-3/4 animate-pulse rounded bg-slate-100" />
            <div class="h-3 w-1/2 animate-pulse rounded bg-slate-100" />
        </div>

        <!-- Error state (preview 422) -->
        <div
            v-else-if="error && !plan"
            class="m-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"
        >
            {{ error }}
        </div>

        <!-- Feasibility plan detail -->
        <div v-else-if="plan" class="p-4 space-y-3">
            <!-- Segment timeline -->
            <div v-if="segments.length" class="space-y-2">
                <template v-for="(seg, idx) in segments" :key="`seg-${seg.order}`">
                    <!-- Waiting chip between segments -->
                    <div
                        v-if="idx > 0 && segments[idx - 1].waiting_time_after_arrival_hours > 0"
                        class="flex items-center gap-2 pl-4"
                    >
                        <span class="h-4 w-px bg-slate-200" />
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            Waiting at {{ seg.from_port_name || `#${seg.from_port_id}` }}: {{ fmtHours(segments[idx - 1].waiting_time_after_arrival_hours) }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5">
                        <!-- Port pair -->
                        <p class="text-xs font-semibold text-slate-800">
                            {{ seg.from_port_name || `#${seg.from_port_id}` }}
                            <span class="mx-1 text-slate-400">→</span>
                            {{ seg.to_port_name || `#${seg.to_port_id}` }}
                        </p>
                        <!-- Vessel + dates -->
                        <p class="mt-0.5 text-[11px] text-slate-600">
                            <span class="font-medium">{{ seg.vessel?.name || 'Vessel TBD' }}</span>
                            <span v-if="seg.planned_departure && seg.planned_arrival">
                                &nbsp;·&nbsp;{{ fmtIso(seg.planned_departure) }} → {{ fmtIso(seg.planned_arrival) }}
                            </span>
                        </p>
                        <!-- Duration + wait before -->
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Travel {{ fmtHours(seg.travel_duration_hours) }}
                            <template v-if="seg.waiting_time_before_departure_hours > 0">
                                &nbsp;· Wait before departure {{ fmtHours(seg.waiting_time_before_departure_hours) }}
                            </template>
                        </p>
                    </div>
                </template>
            </div>

            <!-- Duration summary chips -->
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[11px] text-slate-600">
                    Total travel: {{ fmtHours(plan.total_travel_time_hours) }}
                </span>
                <span
                    v-if="plan.total_waiting_time_hours > 0"
                    class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] text-amber-700"
                >
                    Port handling: {{ fmtHours(plan.total_waiting_time_hours) }}
                </span>
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-800">
                    Min rental: {{ plan.minimum_rental_days }} day(s)
                </span>
                <span class="rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-[11px] text-slate-600">
                    Recommended: {{ plan.recommended_rental_days }} day(s)
                </span>
            </div>

            <!-- Warnings -->
            <div
                v-if="warnings.length"
                class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] text-rose-800"
            >
                <p class="font-semibold">Warnings</p>
                <ul class="mt-1 list-disc pl-4 space-y-0.5">
                    <li v-for="(w, i) in warnings" :key="`w-${i}`">{{ w }}</li>
                </ul>
            </div>

            <!-- Hints -->
            <div
                v-if="hints.length"
                class="rounded-xl border border-sky-200 bg-sky-50/70 px-3 py-2 text-[11px] text-sky-800"
            >
                <ul class="space-y-0.5">
                    <li v-for="(h, i) in hints" :key="`h-${i}`">{{ h }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>
