<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    chartData: { type: Array, default: () => [] },
});

const data = computed(() => ({
    labels: props.chartData.map((d) => d.label),
    datasets: [{
        data: props.chartData.map((d) => d.value),
        backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444'],
    }],
}));

const options = {
    responsive: true,
    plugins: {
        legend: {
            position: 'bottom',
            onClick(e, legendItem, legend) {
                const idx = legendItem.index;
                const chart = legend.chart;
                chart.toggleDataVisibility(idx);
                chart.update();
            },
        },
    },
};
</script>

<template>
    <Doughnut v-if="chartData.length" :data="data" :options="options" />
    <div v-else class="flex h-32 items-center justify-center text-sm text-slate-500">No data</div>
</template>
