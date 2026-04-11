<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.chartData.map((d) => d.label),
    datasets: [
        {
            label: 'Revenue',
            data: props.chartData.map((d) => d.amount),
            backgroundColor: 'rgba(99, 102, 241, 0.7)',
            borderRadius: 6,
        },
    ],
}));

const options = {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true },
        x: { grid: { display: false } },
    },
};
</script>

<template>
    <Bar v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
