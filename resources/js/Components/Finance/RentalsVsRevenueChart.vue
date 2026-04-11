<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.chartData.map((m) => m.label),
    datasets: [
        { label: 'Rentals count', data: props.chartData.map((m) => m.rentalCount), backgroundColor: 'rgba(59, 130, 246, 0.7)' },
        { label: 'Revenue', data: props.chartData.map((m) => m.rentalRevenue), backgroundColor: 'rgba(16, 185, 129, 0.7)' },
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
    <Bar v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
