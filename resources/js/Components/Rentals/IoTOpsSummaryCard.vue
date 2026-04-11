<script setup>
const props = defineProps({
    summary: {
        type: Object,
        default: () => null,
    },
});
</script>

<template>
    <section v-if="summary" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">IoT fleet overview</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Operator analytics</h3>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Active IoT rentals</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900">
                    {{ summary.active_iot_rentals }}
                </p>
                <p class="mt-1 text-[11px] text-slate-500">With sensors online</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Share of active rentals</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900">
                    {{ summary.iot_share_percent.toFixed(1) }}%
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    Out of {{ summary.total_active_rentals }} active rentals
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Top IoT ports</p>
                <ul v-if="summary.ports_distribution?.length" class="mt-1 space-y-0.5 text-[11px] text-slate-700">
                    <li v-for="item in summary.ports_distribution" :key="item.port_id" class="flex items-center justify-between">
                        <span>{{ item.port_name }}</span>
                        <span class="font-semibold">{{ item.total }}</span>
                    </li>
                </ul>
                <p v-else class="mt-1 text-[11px] text-slate-500">
                    No IoT-enabled containers assigned to ports yet.
                </p>
            </div>
        </div>
    </section>
</template>

