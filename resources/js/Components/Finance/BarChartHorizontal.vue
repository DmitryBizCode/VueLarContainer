<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.chartData.map((d) => d.label),
    datasets: [{ label: 'Revenue', data: props.chartData.map((d) => d.value), backgroundColor: 'rgba(59, 130, 246, 0.8)' }],
}));

const options = {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true } },
};
</script>

<template>
    <Bar v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
