<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.chartData.map((d) => d.label),
    datasets: [
        { label: 'Failed count', data: props.chartData.map((d) => d.count), borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.2)', fill: true },
    ],
}));

const options = {
    responsive: true,
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
    },
    scales: { y: { beginAtZero: true } },
};
</script>

<template>
    <Line v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
