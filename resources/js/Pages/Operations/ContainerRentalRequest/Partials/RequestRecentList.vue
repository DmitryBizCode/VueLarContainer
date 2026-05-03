<script setup>
import { Link } from '@inertiajs/vue3';
import { formatDateGb, formatMoneyLocale } from '@/utils/formatLocale';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    statusLabel: {
        type: Function,
        required: true,
    },
    statusDetailEligible: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['open-status-detail']);
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">My latest requests</h3>
        <div v-if="!items.length" class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs text-slate-600">
            No submitted requests yet.
        </div>
        <div v-else class="mt-3 space-y-2">
            <div v-for="item in items" :key="item.id" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">Request #{{ item.id }}</p>
                    <span class="text-xs font-semibold text-slate-600">{{ formatMoneyLocale(item.price) }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-600">{{ item.container_serial || 'Container not assigned' }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">
                    Equipment:
                    {{ item.container_operational_status ? statusLabel(item.container_operational_status) : '—' }}
                </p>
                            <p class="mt-1 text-xs text-slate-500">{{ item.start_date && item.end_date ? `${formatDateGb(item.start_date)} → ${formatDateGb(item.end_date)}` : '—' }}</p>
                <button
                    v-if="statusDetailEligible(item)"
                    type="button"
                    class="mt-1 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2"
                    @click="emit('open-status-detail', item)"
                >
                    Status notes
                </button>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                    <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">{{ statusLabel(item.status) }}</span>
                    <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">{{ statusLabel(item.payment_status) }}</span>
                    <Link
                        v-if="item.can_view_iot_monitor"
                        :href="route('rentals.monitor', item.id, false)"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        <span>IoT view</span>
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-500"
                    >
                        IoT after approval
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
