<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] }, // [{label, value}]
    label: { type: String, default: 'Value' },
});

const data = computed(() => ({
    labels: props.chartData.map((d) => d.label),
    datasets: [
        {
            label: props.label,
            data: props.chartData.map((d) => d.value),
            borderColor: '#0f172a',
            backgroundColor: 'rgba(15, 23, 42, 0.08)',
            fill: true,
            tension: 0.25,
            pointRadius: 2,
        },
    ],
}));

const options = {
    responsive: true,
    plugins: {
        legend: { position: 'top' },
    },
    scales: { y: { beginAtZero: true } },
};
</script>

<template>
    <Line v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>

