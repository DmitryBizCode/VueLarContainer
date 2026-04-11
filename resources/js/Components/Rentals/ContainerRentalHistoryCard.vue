<script setup>
const props = defineProps({
    history: {
        type: Array,
        default: () => [],
    },
});

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const statusLabel = (value) =>
    String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Container history</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Previous rentals</h3>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                {{ history.length }}
            </span>
        </div>

        <div v-if="!history.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2 text-xs text-slate-600">
            No previous rentals found for this container.
        </div>

        <div v-else class="space-y-2 text-xs text-slate-700">
            <div
                v-for="item in history"
                :key="item.id"
                class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2"
            >
                <div>
                    <p class="text-xs font-semibold text-slate-900">
                        Rental #{{ item.id }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ item.origin_port_name || 'Origin' }}
                        <span class="text-slate-400"> → </span>
                        {{ item.destination_port_name || 'Destination' }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                        {{ statusLabel(item.status) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        {{ statusLabel(item.payment_status) }}
                    </p>
                    <p v-if="item.price" class="mt-1 text-[11px] font-semibold text-slate-800">
                        {{
                            new Intl.NumberFormat('en-US', {
                                style: 'currency',
                                currency: 'USD',
                                maximumFractionDigits: 0,
                            }).format(Number(item.price || 0))
                        }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

