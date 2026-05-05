<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

/** Revenue bars use successful transaction totals (`paid`) — same basis as Revenue dynamics; avoids empty bars when rentals are not yet completed. */
const revenueVals = computed(() => props.chartData.map((m) => Number(m.paid ?? 0)));

const hasActivity = computed(() =>
    props.chartData.some((m) => (Number(m.rentalCount) || 0) > 0 || (Number(m.paid) || 0) > 0),
);

const data = computed(() => ({
    labels: props.chartData.map((m) => m.label),
    datasets: [
        {
            label: 'Rentals count',
            data: props.chartData.map((m) => Number(m.rentalCount ?? 0)),
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            yAxisID: 'yCount',
        },
        {
            label: 'Revenue (paid tx)',
            data: revenueVals.value,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            yAxisID: 'yRevenue',
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
            onClick(e, legendItem, legend) {
                const idx = legendItem.datasetIndex;
                const chart = legend.chart;
                const meta = chart.getDatasetMeta(idx);
                meta.hidden = !meta.hidden;
                chart.update();
            },
        },
        tooltip: {
            callbacks: {
                label(ctx) {
                    const label = ctx.dataset.label || '';
                    const v = ctx.parsed.y;
                    if (ctx.dataset.yAxisID === 'yRevenue') {
                        return `${label}: ${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(v)}`;
                    }

                    return `${label}: ${v}`;
                },
            },
        },
    },
    datasets: {
        bar: {
            categoryPercentage: 0.65,
            barPercentage: 0.85,
            maxBarThickness: 56,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 24 },
        },
        yCount: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { precision: 0 },
            title: { display: true, text: 'Rentals created' },
        },
        yRevenue: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Paid tx total (USD)' },
        },
    },
};
</script>

<template>
    <div v-if="chartData.length" class="flex h-full min-h-[14rem] flex-col">
        <Bar v-if="hasActivity" :data="data" :options="options" />
        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-1 px-4 text-center text-sm text-slate-500"
        >
            <p>No rentals created and no successful payments in the rolling window.</p>
            <p class="text-xs text-slate-400">Charts use the last 24 calendar months. Seed or record transactions with <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[11px]">transaction_date</code> in range.</p>
        </div>
    </div>
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
