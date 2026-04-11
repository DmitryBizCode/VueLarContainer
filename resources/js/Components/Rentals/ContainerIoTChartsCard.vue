<script setup>
import { computed } from 'vue';
import IotSensorEchart from './IotSensorEchart.vue';

const props = defineProps({
    iotCharts: {
        type: Object,
        required: true,
    },
    /** When true, data is synthetic / container has no active IoT */
    demoMode: {
        type: Boolean,
        default: false,
    },
    /** Bumped on each poll so chart cards remount when series timestamps repeat */
    chartsRevision: {
        type: Number,
        default: 0,
    },
});

const sensors = computed(() => props.iotCharts?.sensors ?? []);
const doorEvents = computed(() => props.iotCharts?.door_events ?? []);
const periodHours = computed(() => props.iotCharts?.period_hours ?? 24);

/** Config cap from backend (same for all panels unless env changed mid-request). */
const monitorChartCap = computed(() => {
    const caps = sensors.value.map((s) => s.chart_max_points).filter((n) => n != null && n > 0);
    return caps[0] ?? 30;
});

function formatStat(value, decimals) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }
    return Number(value).toFixed(decimals ?? 1);
}

function formatVariance(v) {
    if (v === null || v === undefined || Number.isNaN(v)) {
        return '—';
    }
    return Number(v).toFixed(4);
}

/** Points drawn on the line (time buckets), not stats sample count (DB+buffer can be much larger). */
function pointsOnChart(sensor) {
    return sensor.series?.length ?? 0;
}

function chartPointsTitle(sensor) {
    const cap = sensor.chart_max_points ?? 30;
    const drawn = pointsOnChart(sensor);
    const db = sensor.samples_in_range;
    const buf = sensor.buffer_samples_in_range;
    const mode = props.iotCharts?.series_mode ?? 'window';
    if (mode === 'raw_tail') {
        const bufPart =
            buf != null && buf > 0
                ? ` Of the ${drawn} drawn point(s), ${buf} came from the Redis buffer (same timestamp wins over DB).`
                : '';
        return `Raw tail mode: last up to ${cap} merged samples (DB + buffer), not tied to the date range above. Merged pool had ${db ?? '—'} point(s). Stats below use only these ${drawn} point(s).${bufPart}`;
    }
    if (db != null) {
        const bufPart =
            buf != null && buf > 0
                ? ` Pending in Redis (not flushed): ${buf} sample(s) in the same window are merged into the chart.`
                : '';
        return `${db} sample(s) in the window (DB + buffer). The line shows ${drawn} bucket point(s) (max ${cap}); min / max / mean below use all loaded samples in the window (or the newest tail if stats were truncated).${bufPart}`;
    }
    return 'Demo / IoT inactive: the series is synthetic (fixed time grid), not loaded from metrics — n reflects grid points, not DB row count.';
}

</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Live conditions</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">IoT sensors (last {{ periodHours }} h)</h3>
                <p class="mt-1 text-xs text-slate-500">
                    <template v-if="iotCharts?.series_mode === 'raw_tail'">
                        Режим <strong>останніх {{ monitorChartCap }}</strong> точок: кожен графік — сирі злиті семпли з БД + Redis (не бакети за діапазоном дат).
                        Діапазон дат лишається для блоку «Door events» та інших розділів.
                    </template>
                    <template v-else>
                        Up to <strong>{{ monitorChartCap }}</strong> time buckets <em>per sensor chart</em> across the selected window (aggregated
                        from all samples in range, not only the last raw rows). Двері: на графіку лише зміни стану (відкр./закр.), без бакетної згладжувальної лінії.
                        Cap: <code class="rounded bg-slate-100 px-0.5">IOT_MONITOR_CHART_MAX_POINTS</code> (default 30). If the DB sample count exceeds
                        <code class="rounded bg-slate-100 px-0.5">IOT_MONITOR_CHART_WINDOW_SAMPLES_MAX</code>, stats may use the newest tail only
                        (see “Stats truncated” on the card). Run <code class="rounded bg-slate-100 px-1 py-0.5 text-[10px]">simulation:worker</code> or
                        <code class="rounded bg-slate-100 px-1 py-0.5 text-[10px]">schedule:work</code>.
                    </template>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <div
                    v-if="demoMode"
                    class="rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-800 ring-1 ring-amber-200/80"
                >
                    Demo / IoT inactive
                </div>
                <div v-else class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 ring-1 ring-emerald-200/60">
                    <span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500" />
                    IoT active
                </div>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-1 lg:grid-cols-2">
            <article
                v-for="(sensor, sIdx) in sensors"
                :key="`${sensor.key ?? 'sensor'}-${sIdx}-${chartsRevision}`"
                class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50/60 p-4 shadow-sm"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ sensor.label }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ sensor.description }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                        :class="
                            sensor.source === 'telemetry'
                                ? 'bg-emerald-100 text-emerald-800'
                                : sensor.source === 'defaults'
                                  ? 'bg-sky-100 text-sky-800'
                                  : 'bg-slate-200/80 text-slate-600'
                        "
                    >
                        {{
                            sensor.source === 'telemetry'
                                ? 'DB'
                                : sensor.source === 'defaults'
                                  ? 'Seed + DB'
                                  : 'demo'
                        }}
                    </span>
                </div>

                <div class="mb-3 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-slate-600 sm:grid-cols-3">
                    <div>
                        <span class="text-slate-400">Last</span>
                        <p class="font-semibold text-slate-900">
                            {{ formatStat(sensor.stats?.last, sensor.decimals) }}{{ sensor.unit }}
                        </p>
                    </div>
                    <div>
                        <span class="text-slate-400">Min / max</span>
                        <p class="font-medium text-slate-800">
                            {{ formatStat(sensor.stats?.min, sensor.decimals) }} / {{ formatStat(sensor.stats?.max, sensor.decimals) }}
                        </p>
                    </div>
                    <div>
                        <span class="text-slate-400">Mean</span>
                        <p class="font-medium text-slate-800">{{ formatStat(sensor.stats?.mean, sensor.decimals) }}</p>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="text-slate-400">Дисперсія коливань (σ²)</span>
                        <p class="font-mono text-xs font-semibold text-slate-800">{{ formatVariance(sensor.stats?.variance) }}</p>
                    </div>
                </div>

                <div class="mt-auto min-h-[14rem] flex-1 rounded-xl border border-slate-200/80 bg-white p-3">
                    <div class="flex flex-wrap items-start justify-between gap-x-2 gap-y-1 text-[10px] text-slate-500">
                        <span>{{ sensor.unit }}</span>
                        <div
                            v-if="sensor.series?.length"
                            class="max-w-[min(100%,22rem)] text-right leading-snug"
                            :title="chartPointsTitle(sensor)"
                        >
                            <span class="font-medium text-slate-600">On chart:</span>
                            {{ pointsOnChart(sensor) }} / max {{ sensor.chart_max_points ?? 30 }}
                            <template v-if="sensor.samples_in_range != null">
                                <span class="text-slate-400"> · </span>
                                <span class="font-medium text-slate-600">In window (DB+buffer):</span>
                                {{ sensor.samples_in_range }}
                                <template v-if="sensor.buffer_samples_in_range > 0">
                                    <span class="text-slate-400"> · </span>
                                    <span class="font-medium text-amber-800/90" title="Samples still in Redis, merged until the minute flush">Buffer:</span>
                                    {{ sensor.buffer_samples_in_range }}
                                </template>
                            </template>
                            <template v-else>
                                <span class="text-slate-400"> · </span>
                                <span class="text-amber-700/90">Synthetic (not DB)</span>
                            </template>
                            <template v-if="sensor.used_extended_lookback">
                                <span class="text-slate-400"> · </span>
                                <span class="font-medium text-sky-700/90" title="No samples in the selected time window; showing the latest points from a longer lookback.">
                                    Extended lookback
                                </span>
                            </template>
                            <template v-if="sensor.stats_truncated">
                                <span class="text-slate-400"> · </span>
                                <span class="font-medium text-rose-700/90" title="Too many DB rows in the window; stats use the newest chunk only (see IOT_MONITOR_CHART_WINDOW_SAMPLES_MAX).">
                                    Stats truncated
                                </span>
                            </template>
                        </div>
                    </div>
                    <div class="mt-2 min-h-[220px]">
                        <IotSensorEchart
                            :series="sensor.series"
                            :label="sensor.label"
                            :unit="sensor.unit"
                            :color="sensor.stroke"
                            :height="220"
                            :poll-revision="chartsRevision"
                            :discrete="Boolean(sensor.discrete)"
                        />
                    </div>
                </div>
            </article>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-1 space-y-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Door events</p>
                    <p class="mt-1 text-[10px] leading-snug text-slate-500">
                        From <code class="rounded bg-white px-0.5">door_open</code> telemetry for this rental (state changes only). Demo mode uses a placeholder timeline.
                    </p>
                    <ul v-if="doorEvents.length" class="mt-2 space-y-1.5 text-xs text-slate-700">
                        <li
                            v-for="event in doorEvents"
                            :key="`${event.timestamp}-${event.status}`"
                            class="flex items-center justify-between"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="event.status === 'open' ? 'bg-amber-500' : 'bg-emerald-500'"
                                />
                                <span class="font-semibold">{{ event.status === 'open' ? 'Open' : 'Closed' }}</span>
                            </span>
                            <span class="text-[11px] text-slate-500">
                                {{
                                    new Date(event.timestamp).toLocaleTimeString('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })
                                }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-slate-500">No door state changes in this period.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 text-xs text-emerald-900 lg:col-span-2">
                <p class="font-semibold">Fluctuation analytics</p>
                <p class="mt-1 leading-relaxed">
                    <template v-if="iotCharts?.series_mode === 'raw_tail'">
                        У режимі «останні N» показники min / max / mean / σ² рахуються <strong>лише по тих точках, що на лінії</strong> (останні злиті семпли).
                    </template>
                    <template v-else>
                        Min, max, mean, and variance use <strong>all samples in the selected window</strong> that were loaded for that sensor (DB plus
                        buffer in range), not only the bucketed line. If “Stats truncated” appears, the window had more DB rows than
                        <code class="rounded bg-white px-0.5">IOT_MONITOR_CHART_WINDOW_SAMPLES_MAX</code> and older rows were omitted from stats.
                    </template>
                </p>
            </div>
        </div>
    </section>
</template>
