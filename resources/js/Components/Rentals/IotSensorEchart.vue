<script setup>
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent]);

const props = defineProps({
    series: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    unit: { type: String, default: '' },
    color: { type: String, default: '#2563eb' },
    height: { type: [Number, String], default: 220 },
    /** Bumped each poll so ECharts remounts even when timestamps/values repeat (buffer vs DB). */
    pollRevision: { type: Number, default: 0 },
    /** 0/1 discrete sensors: step line, no spline smoothing */
    discrete: { type: Boolean, default: false },
});

/** [[timestampMs, value], ...] — only finite points */
const chartData = computed(() => {
    return (props.series || [])
        .map((p) => {
            const t = new Date(p?.timestamp).getTime();
            const v = Number(p?.value ?? 0);
            if (!Number.isFinite(t) || !Number.isFinite(v)) {
                return null;
            }
            return [t, v];
        })
        .filter(Boolean);
});

/** Force ECharts to remount when polling updates the series */
const chartKey = computed(() => {
    const pts = chartData.value;
    const rev = props.pollRevision ?? 0;
    if (!pts.length) {
        return `empty-${rev}`;
    }
    return `${rev}|${pts.map((d) => `${d[0]}:${d[1]}`).join('|')}`;
});

const option = computed(() => {
    const data = chartData.value;
    const discrete = props.discrete === true;
    let spanMs = 60_000;
    if (data.length >= 1) {
        const times = data.map((d) => d[0]);
        const minT = Math.min(...times);
        const maxT = Math.max(...times);
        spanMs = Math.max(maxT - minT, 60_000);
    }

    const showDateOnAxis = spanMs > 36 * 3600_000;
    const xAxis = {
        type: 'time',
        axisLabel: {
            fontSize: 11,
            hideOverlap: true,
            rotate: spanMs > 6 * 3600_000 ? 32 : 0,
            margin: 10,
            formatter: showDateOnAxis ? '{MM}-{dd} {HH}:{mm}' : '{HH}:{mm}',
        },
        splitLine: { show: false },
    };
    if (data.length >= 1) {
        const times = data.map((d) => d[0]);
        const minT = Math.min(...times);
        const maxT = Math.max(...times);
        const pad = spanMs * 0.08;
        xAxis.min = minT - pad;
        xAxis.max = maxT + pad;
    }

    const bottomPad = xAxis.axisLabel.rotate ? 44 : 36;

    return {
        grid: { left: 48, right: 16, top: 12, bottom: bottomPad },
        xAxis,
        yAxis: {
            type: 'value',
            axisLabel: { fontSize: 11 },
            splitLine: { lineStyle: { color: '#e2e8f0' } },
        },
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                const p = params?.[0];
                if (!p) return '';
                const dt = new Date(p.value[0]);
                const v = p.value[1];
                return `${dt.toLocaleString('en-US')}<br/>${props.label}: ${v} ${props.unit}`;
            },
        },
        series: [
            {
                type: 'line',
                data,
                smooth: !discrete,
                step: discrete ? 'end' : false,
                symbol: discrete ? 'none' : 'circle',
                symbolSize: 6,
                lineStyle: { color: props.color, width: discrete ? 2 : 1.5 },
                areaStyle: discrete ? undefined : { color: props.color, opacity: 0.15 },
            },
        ],
    };
});
</script>

<template>
    <div :style="{ height: typeof height === 'number' ? height + 'px' : height }" class="relative">
        <v-chart
            v-if="chartData.length"
            :key="chartKey"
            :option="option"
            :update-options="{ notMerge: true, lazyUpdate: false }"
            autoresize
        />
        <div
            v-else-if="series?.length"
            class="flex h-full min-h-[120px] items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 text-center text-xs text-slate-500"
        >
            No valid points to plot (check timestamps and values).
        </div>
    </div>
</template>
