<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, watch, computed, onBeforeUnmount, onMounted } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RentalIoTOverviewCard from '@/Components/Rentals/RentalIoTOverviewCard.vue';
import ContainerIoTChartsCard from '@/Components/Rentals/ContainerIoTChartsCard.vue';
import ContainerIoTFallbackCard from '@/Components/Rentals/ContainerIoTFallbackCard.vue';
import IotAuditChainPanel from '@/Components/Rentals/IotAuditChainPanel.vue';
import MonitorChartProfileToolbar from '@/Components/Rentals/MonitorChartProfileToolbar.vue';
import MonitorDateRangePicker from '@/Components/Rentals/MonitorDateRangePicker.vue';
import ContainerRentalHistoryCard from '@/Components/Rentals/ContainerRentalHistoryCard.vue';
import IoTOpsSummaryCard from '@/Components/Rentals/IoTOpsSummaryCard.vue';
import { Head } from '@inertiajs/vue3';
import { primeSanctumCsrfOnce } from '@/utils/spaApi';

const POLL_INTERVAL_MS = 10000;

/** Web routes (same session as Inertia) — kept here so Vite never serves a stale cached `spaApi` without these exports. */
function webMonitorChartsDataPath(rentalId) {
    return `/rentals/${encodeURIComponent(String(rentalId))}/monitor-charts-data`;
}

function webTelemetryTogglePath(rentalId) {
    return `/rentals/${encodeURIComponent(String(rentalId))}/telemetry-toggle`;
}

const props = defineProps({
    rental: {
        type: Object,
        required: true,
    },
    container: {
        type: Object,
        default: () => null,
    },
    iot_enabled: {
        type: Boolean,
        default: false,
    },
    iot_charts: {
        type: Object,
        default: () => ({
            period_hours: 24,
            step_hours: 2,
            sensors: [],
            door_events: [],
        }),
    },
    history_rentals: {
        type: Array,
        default: () => [],
    },
    ops_summary: {
        type: Object,
        default: () => null,
    },
    iot_audit: {
        type: Array,
        default: () => [],
    },
    iot_latest: {
        type: Object,
        default: () => null,
    },
});

const chartSeriesMode = ref(props.iot_charts?.series_mode === 'raw_tail' ? 'raw_tail' : 'window');
const iotCharts = ref({ ...props.iot_charts, series_mode: chartSeriesMode.value });

const rawTailCapLabel = computed(() => {
    const caps = (iotCharts.value?.sensors ?? []).map((s) => s.chart_max_points).filter((n) => n > 0);
    return caps.length ? Math.max(...caps) : 30;
});
const iotLatest = ref(props.iot_latest ? { ...props.iot_latest } : null);
const chartsRevision = ref(0);
/** Mirrors rental.is_telemetry_active — cron/simulation writes metrics only when true. */
const telemetryStreaming = ref(props.rental?.is_telemetry_active !== false);
const toggleTelemetryLoading = ref(false);
let pollTimer = null;
let pollLoopActive = false;

function shouldPollCharts() {
    return Boolean(props.iot_enabled && props.container);
}

/**
 * Inertia may pass a trimmed `iot_charts` (empty series). Merging only meta avoids wiping
 * data after `router.get(..., { preserveState: true })` on the date picker — full series come from the API.
 */
function mergeIotChartsMetaFromProps() {
    const v = props.iot_charts;
    if (!v || typeof v !== 'object') {
        return;
    }
    iotCharts.value = {
        ...iotCharts.value,
        period_hours: v.period_hours ?? iotCharts.value?.period_hours,
        step_hours: v.step_hours ?? iotCharts.value?.step_hours,
        date_from: v.date_from ?? iotCharts.value?.date_from,
        date_to: v.date_to ?? iotCharts.value?.date_to,
        series_mode: v.series_mode ?? iotCharts.value?.series_mode ?? chartSeriesMode.value,
    };
}

watch(
    () => props.iot_charts,
    () => mergeIotChartsMetaFromProps(),
    { deep: true }
);

watch(
    () => props.iot_latest,
    (v) => {
        if (v) {
            iotLatest.value = { ...v };
        }
    },
    { deep: true }
);

watch(
    () => props.rental?.is_telemetry_active,
    (v) => {
        telemetryStreaming.value = v !== false;
    }
);

function defaultChartRangeIso() {
    const end = new Date();
    const start = new Date(end.getTime() - 24 * 60 * 60 * 1000);

    return { from: start.toISOString(), to: end.toISOString() };
}

const MAX_RANGE_MS = 168 * 3600 * 1000;
function onDateRangeReset() {
    if (chartSeriesMode.value !== 'window') {
        chartSeriesMode.value = 'window';
    }
}

/** Toggle raw tail vs window; changing the date range always switches back to window (see watch below). */
function toggleRawTailChartMode() {
    chartSeriesMode.value = chartSeriesMode.value === 'raw_tail' ? 'window' : 'raw_tail';
    void fetchCharts();
    startPolling();
}

function slideLiveChartRangeIfStale(fromIso, toIso) {
    const fromMs = new Date(fromIso).getTime();
    const toMs = new Date(toIso).getTime();
    const nowMs = Date.now();
    if (!Number.isFinite(fromMs) || !Number.isFinite(toMs)) {
        return { from: fromIso, to: toIso };
    }
    let span = toMs - fromMs;
    span = Math.min(Math.max(span, 3600_000), MAX_RANGE_MS);
    if (nowMs <= toMs) {
        return { from: fromIso, to: toIso };
    }
    const lagMs = nowMs - toMs;
    const deepHistoryLagMs = 72 * 3600 * 1000;
    if (lagMs >= deepHistoryLagMs) {
        return { from: fromIso, to: toIso };
    }
    const newTo = nowMs;
    const newFrom = newTo - span;

    return { from: new Date(newFrom).toISOString(), to: new Date(newTo).toISOString() };
}

async function fetchCharts() {
    if (!props.rental?.id || !props.iot_enabled) return;
    let from = iotCharts.value?.date_from || props.iot_charts?.date_from || '';
    let to = iotCharts.value?.date_to || props.iot_charts?.date_to || '';
    if (!from || !to) {
        const d = defaultChartRangeIso();
        from = d.from;
        to = d.to;
        iotCharts.value = { ...iotCharts.value, date_from: from, date_to: to };
    } else {
        const slid = slideLiveChartRangeIfStale(from, to);
        from = slid.from;
        to = slid.to;
    }
    try {
        const url = webMonitorChartsDataPath(props.rental.id);
        const { data } = await axios.get(url, {
            params: { from, to, series_mode: chartSeriesMode.value, _poll: Date.now() },
            headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
        });
        const { _debug, iot_latest: latestFromApi, ...chartsPayload } = data || {};
        const mode = chartsPayload.series_mode === 'raw_tail' ? 'raw_tail' : 'window';
        chartSeriesMode.value = mode;
        iotCharts.value = { ...chartsPayload, series_mode: mode };
        chartsRevision.value += 1;
        if (latestFromApi && typeof latestFromApi === 'object') {
            iotLatest.value = { ...latestFromApi };
        }
        if (_debug && import.meta.env.DEV) {
            console.debug('monitor-charts debug', _debug);
        }
    } catch (e) {
        const st = e?.response?.status;
        const msg = e?.response?.data?.message || e?.message;
        console.warn('Monitor charts poll failed:', st ?? '', msg);
    }
}

function startPolling() {
    if (!shouldPollCharts()) return;
    stopPolling();
    pollLoopActive = true;
    const tick = async () => {
        if (!pollLoopActive || !shouldPollCharts()) {
            return;
        }
        await fetchCharts();
        if (!pollLoopActive || !shouldPollCharts()) {
            return;
        }
        pollTimer = setTimeout(tick, POLL_INTERVAL_MS);
    };
    void tick();
}

async function toggleTelemetryStreaming() {
    if (!props.rental?.id || toggleTelemetryLoading.value) return;
    toggleTelemetryLoading.value = true;
    try {
        await primeSanctumCsrfOnce();
        const { data } = await axios.post(webTelemetryTogglePath(props.rental.id));
        if (data && typeof data.is_telemetry_active === 'boolean') {
            telemetryStreaming.value = data.is_telemetry_active;
        }
        stopPolling();
        startPolling();
    } catch (e) {
        console.warn('Telemetry toggle failed:', e?.message);
    } finally {
        toggleTelemetryLoading.value = false;
    }
}

function stopPolling() {
    pollLoopActive = false;
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible' && shouldPollCharts()) {
        fetchCharts();
    }
}

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});

watch(
    () => [props.iot_enabled, props.container?.id, props.rental?.id],
    () => {
        mergeIotChartsMetaFromProps();
        startPolling();
    },
    { immediate: true }
);

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
});

/** Date range from URL / presets updates props while preserveState keeps local refs — refetch immediately. */
watch(
    () => [props.iot_charts?.date_from, props.iot_charts?.date_to],
    (pair, prev) => {
        const [from, to] = pair;
        if (!from || !to || !props.iot_enabled || !props.container) {
            return;
        }
        if (prev === undefined) {
            return;
        }
        if (prev[0] === from && prev[1] === to) {
            return;
        }
        chartSeriesMode.value = 'window';
        mergeIotChartsMetaFromProps();
        startPolling();
    }
);
</script>

<template>
    <Head title="IoT Rental Monitor" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Operations</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">IoT rental monitoring</h1>
                </div>
                <span
                    v-if="props.iot_enabled && props.container && telemetryStreaming"
                    class="hidden items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 sm:inline-flex"
                >
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                        />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    </span>
                    Live · auto telemetry · charts every 10s
                </span>
                <span
                    v-else-if="props.iot_enabled && props.container && !telemetryStreaming"
                    class="hidden items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-800 sm:inline-flex"
                >
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-sky-500" />
                    Charts refresh every 10s · auto telemetry paused
                </span>
                <span
                    v-else
                    class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 sm:inline-flex"
                >
                    Synthetic / demo data
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <RentalIoTOverviewCard
                    :rental="props.rental"
                    :container="props.container"
                    :iot-latest="iotLatest"
                    :poll-revision="chartsRevision"
                />

                <div
                    v-if="props.iot_enabled && props.container"
                    class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="text-sm text-slate-600">
                        <p class="font-semibold text-slate-900">Automated sensor simulation</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Pausing stops cron/worker from generating new samples (saves load). Charts still poll the API every 10s (DB + Redis buffer). Use refresh if you changed the date range elsewhere.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="toggleTelemetryLoading"
                            @click="toggleTelemetryStreaming"
                        >
                            {{ telemetryStreaming ? 'Pause auto telemetry' : 'Resume auto telemetry' }}
                        </button>
                        <button
                            v-if="!telemetryStreaming"
                            type="button"
                            class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900 disabled:opacity-50"
                            :disabled="toggleTelemetryLoading"
                            @click="fetchCharts"
                        >
                            Refresh charts
                        </button>
                    </div>
                </div>

                <div v-if="props.container" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <MonitorDateRangePicker
                            :rental-id="props.rental.id"
                            :date-from="iotCharts?.date_from ?? props.iot_charts?.date_from"
                            :date-to="iotCharts?.date_to ?? props.iot_charts?.date_to"
                            @mode-reset="onDateRangeReset"
                        />
                        <button
                            type="button"
                            class="rounded-xl border px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                            :class="
                                chartSeriesMode === 'raw_tail'
                                    ? 'border-sky-400 bg-sky-50 text-sky-900 ring-1 ring-sky-200/80'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            "
                            :aria-pressed="chartSeriesMode === 'raw_tail'"
                            :title="
                                chartSeriesMode === 'raw_tail'
                                    ? 'Click to show charts for the selected date range again'
                                    : 'Latest points from DB and buffer, not tied to the date range'
                            "
                            @click="toggleRawTailChartMode"
                        >
                            Latest readings
                        </button>
                    </div>
                    <MonitorChartProfileToolbar
                        :rental-id="props.rental.id"
                        :date-from="iotCharts?.date_from ?? props.iot_charts?.date_from"
                        :date-to="iotCharts?.date_to ?? props.iot_charts?.date_to"
                        :iot-charts-config="{ sensors: iotCharts?.sensors?.map(s => s.key), date_from: iotCharts?.date_from, date_to: iotCharts?.date_to }"
                    />
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <Link
                            v-if="props.container"
                            :href="route('rentals.container3d', props.rental.id)"
                            class="block rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50/80 to-blue-50/40 p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600/80">3D view</p>
                                        <h3 class="mt-0.5 text-lg font-bold text-slate-900">Maritime container</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ props.container.serial_number }} · {{ props.container.width }} × {{ props.container.length }} × {{ props.container.height }} m</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                    Open 3D
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </span>
                            </div>
                        </Link>

                        <ContainerIoTChartsCard
                            v-if="props.container"
                            :iot-charts="iotCharts"
                            :demo-mode="!props.iot_enabled"
                            :charts-revision="chartsRevision"
                        />
                        <ContainerIoTFallbackCard v-else :rental="props.rental" :container="props.container" />

                        <ContainerRentalHistoryCard :history="props.history_rentals" />
                    </div>

                    <div class="space-y-6">
                        <IotAuditChainPanel
                            v-if="props.container"
                            :rental-id="props.rental.id"
                            :initial-events="props.iot_audit"
                        />
                        <IoTOpsSummaryCard :summary="props.ops_summary" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

