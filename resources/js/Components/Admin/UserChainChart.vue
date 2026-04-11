<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Title,
    Tooltip,
    Legend,
);

const MAX_CHART_DAYS = 400;

const props = defineProps({
    chain: { type: Array, default: () => [] },
    /** Server-side counts per day when date filters are applied (ignores 500-row timeline cap). */
    chainDaily: { type: Array, default: () => [] },
    /** Inclusive YYYY-MM-DD from admin filter; fills axis when chain_daily is empty. */
    dateFrom: { type: String, default: '' },
    dateTo: { type: String, default: '' },
});

/** Calendar days as UTC YYYY-MM-DD (matches bucketing of ISO timestamps from the API). */
function utcDayKeysInclusive(fromStr, toStr) {
    const parse = (s) => {
        const [y, m, d] = String(s).split('-').map((x) => parseInt(x, 10));
        if (!y || !m || !d) return null;
        return Date.UTC(y, m - 1, d);
    };
    const t0 = parse(fromStr);
    const t1 = parse(toStr);
    if (t0 == null || t1 == null || t1 < t0) {
        return [];
    }
    const keys = [];
    const span = Math.floor((t1 - t0) / 86400000) + 1;
    const n = Math.min(span, MAX_CHART_DAYS);
    for (let i = 0; i < n; i += 1) {
        const ts = t0 + i * 86400000;
        keys.push(new Date(ts).toISOString().slice(0, 10));
    }
    return keys;
}

const chartData = computed(() => {
    if (props.chainDaily && props.chainDaily.length > 0) {
        return {
            labels: props.chainDaily.map((row) => formatLabel(row.day)),
            pages: props.chainDaily.map((row) => Number(row.pages ?? 0)),
            activities: props.chainDaily.map((row) => Number(row.activities ?? 0)),
        };
    }

    const byDay = {};
    for (const item of props.chain) {
        if (!item.created_at) continue;
        const d = new Date(item.created_at);
        const key = d.toISOString().slice(0, 10);
        if (!byDay[key]) {
            byDay[key] = { pages: 0, activities: 0 };
        }
        if (item.type === 'request') byDay[key].pages += 1;
        else if (item.type === 'activity') byDay[key].activities += 1;
    }

    const dataKeys = Object.keys(byDay).sort();
    let keys = [];

    if (props.dateFrom && props.dateTo) {
        keys = utcDayKeysInclusive(props.dateFrom, props.dateTo);
    } else if (dataKeys.length >= 2) {
        keys = utcDayKeysInclusive(dataKeys[0], dataKeys[dataKeys.length - 1]);
    } else {
        keys = dataKeys;
    }

    if (!keys.length && dataKeys.length === 1) {
        keys = dataKeys;
    }

    return {
        labels: keys.map((k) => formatLabel(k)),
        pages: keys.map((k) => (byDay[k] ? byDay[k].pages : 0)),
        activities: keys.map((k) => (byDay[k] ? byDay[k].activities : 0)),
    };
});

function formatLabel(isoDate) {
    const [y, m, d] = String(isoDate).split('-').map((x) => parseInt(x, 10));
    if (!y || !m || !d) return String(isoDate);
    const utc = new Date(Date.UTC(y, m - 1, d));
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(utc);
}

const data = computed(() => ({
    labels: chartData.value.labels,
    datasets: [
        {
            label: 'Page views',
            data: chartData.value.pages,
            borderColor: 'rgb(71, 85, 105)',
            backgroundColor: 'rgba(71, 85, 105, 0.08)',
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
        {
            label: 'Activities',
            data: chartData.value.activities,
            borderColor: 'rgb(180, 83, 9)',
            backgroundColor: 'rgba(180, 83, 9, 0.08)',
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            position: 'top',
            labels: { usePointStyle: true, padding: 16 },
        },
        tooltip: { mode: 'index', intersect: false },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 24,
                autoSkipPadding: 12,
            },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.06)' },
            ticks: { stepSize: 1 },
        },
    },
};
</script>

<template>
    <div v-if="chartData.labels.length" class="h-64">
        <Line :data="data" :options="options" />
    </div>
    <div v-else class="flex h-48 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 text-sm text-slate-500">
        No data to display
    </div>
</template>
