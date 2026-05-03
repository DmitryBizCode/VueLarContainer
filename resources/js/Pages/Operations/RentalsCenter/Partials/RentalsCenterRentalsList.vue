<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
    rows: {
        type: Array,
        required: true,
    },
    paginationLinks: {
        type: Array,
        required: true,
    },
    formatDate: {
        type: Function,
        required: true,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
    statusLabel: {
        type: Function,
        required: true,
    },
    statusDotClass: {
        type: Function,
        required: true,
    },
    buildRentalHint: {
        type: Function,
        required: true,
    },
    segmentLabel: {
        type: Function,
        required: true,
    },
    statusDetailEligible: {
        type: Function,
        required: true,
    },
    isExpired: {
        type: Function,
        required: true,
    },
    iotUnavailableLabel: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['open-route-details', 'open-status-detail']);
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="hidden overflow-x-auto lg:block">
            <table class="min-w-full table-fixed text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <th class="w-[16rem] px-3 py-3">Rental</th>
                        <th class="w-[14rem] px-3 py-3">Container</th>
                        <th class="w-[11rem] px-3 py-3">Timeline</th>
                        <th class="w-[15rem] px-3 py-3">Statuses</th>
                        <th class="w-[15rem] px-3 py-3">Shipment</th>
                        <th class="w-[7.5rem] px-3 py-3 text-right">Amount</th>
                        <th class="px-3 py-3">Hint</th>
                        <th class="w-[8rem] px-3 py-3 text-right">IoT</th>
                    </tr>
                </thead>
                <caption class="sr-only">Operations rentals list</caption>
                <tbody>
                    <tr
                        v-for="item in rows"
                        :key="item.id"
                        class="border-b border-slate-50 align-top transition-colors hover:bg-slate-50/70"
                    >
                        <td class="px-3 py-3">
                            <p class="font-semibold text-slate-800">#{{ item.id }}</p>
                            <p v-if="item.route_summary?.label" class="text-xs text-slate-500">
                                {{ item.route_summary.label }}
                            </p>
                            <p v-else-if="item.origin_port_name || item.destination_port_name" class="text-xs text-slate-500">
                                {{ item.origin_port_name || '—' }} → {{ item.destination_port_name || '—' }}
                            </p>
                            <p v-else class="text-xs text-slate-500">Created operation</p>
                            <span
                                v-if="item.route_summary?.is_multi_hop"
                                class="mt-1 inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                            >
                                Transshipment · {{ item.route_summary.leg_count }} legs
                            </span>
                            <span
                                v-else-if="item.route_summary?.leg_count === 1"
                                class="mt-1 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                            >
                                Direct route
                            </span>
                            <button
                                v-if="Array.isArray(item.route_summary?.legs) && item.route_summary.legs.length"
                                type="button"
                                class="mt-1 ml-1 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900"
                                @click="emit('open-route-details', item)"
                            >
                                Route details
                            </button>
                        </td>
                        <td class="px-3 py-3">
                            <p class="font-semibold text-slate-800">{{ item.container_serial_number }}</p>
                            <p class="text-xs text-slate-500">{{ statusLabel(item.container_type) }}</p>
                            <p class="mt-1 inline-flex items-center gap-1.5 text-[11px] text-slate-600">
                                <span
                                    v-if="item.container_operational_status"
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="statusDotClass('container', item.container_operational_status)"
                                />
                                <span>Equipment: {{ item.container_operational_status ? statusLabel(item.container_operational_status) : '—' }}</span>
                            </p>
                            <p class="mt-1 text-[11px] text-slate-500">IoT: {{ item.container_iot_active ? 'enabled' : 'disabled' }}</p>
                            <button
                                v-if="statusDetailEligible(item)"
                                type="button"
                                class="mt-2 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900"
                                @click="emit('open-status-detail', item)"
                            >
                                Status notes
                            </button>
                        </td>
                        <td class="px-3 py-3 text-slate-600">
                            <p class="text-xs">Start: {{ formatDate(item.start_date) }}</p>
                            <p class="text-xs inline-flex items-center gap-2">
                                <span>End: {{ formatDate(item.end_date) }}</span>
                                <span
                                    v-if="isExpired(item)"
                                    class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
                                >
                                    Expired
                                </span>
                            </p>
                        </td>
                        <td class="px-3 py-3">
                            <div class="space-y-1.5 text-xs text-slate-700">
                                <p class="inline-flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full" :class="statusDotClass('rental', item.status)" />
                                    <span class="font-semibold">Rental</span>: {{ statusLabel(item.status) }}
                                </p>
                                <p class="inline-flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full" :class="statusDotClass('payment', item.payment_status)" />
                                    <span class="font-semibold">Payment</span>: {{ statusLabel(item.payment_status) }}
                                </p>
                                <p class="inline-flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full" :class="statusDotClass('shipment', item.shipment_status)" />
                                    <span class="font-semibold">Shipment</span>: {{ statusLabel(item.shipment_status) }}
                                </p>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-slate-600">
                            <p class="text-xs font-semibold text-slate-800">{{ item.tracking_number || 'No tracking yet' }}</p>
                            <p class="text-xs">ETA: {{ formatDate(item.shipment_arrival_date) }}</p>
                            <div v-if="Array.isArray(item.segment_summary) && item.segment_summary.length" class="mt-1 space-y-0.5">
                                <p
                                    v-for="seg in item.segment_summary.slice(0, 2)"
                                    :key="`seg-${item.id}-${seg.shipment_id}-${seg.leg_sequence}`"
                                    class="text-[11px] text-slate-500"
                                >
                                    {{ segmentLabel(seg) }}
                                </p>
                                <p v-if="item.segment_summary.length > 2" class="text-[11px] text-slate-400">
                                    +{{ item.segment_summary.length - 2 }} more segment(s)
                                </p>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ formatMoney(item.price) }}</td>
                        <td class="px-3 py-3">
                            <p class="text-xs text-slate-600">{{ buildRentalHint(item) }}</p>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <Link
                                v-if="item.can_view_iot_monitor && !isExpired(item)"
                                :href="route('rentals.monitor', item.id)"
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold"
                                :class="item.container_iot_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="item.container_iot_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                />
                                <span>{{ item.container_iot_active ? 'IoT view' : 'Details' }}</span>
                            </Link>
                            <span
                                v-else
                                class="inline-flex rounded-full border border-dashed border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500"
                            >
                                {{ iotUnavailableLabel(item) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-3 lg:hidden">
            <div v-for="item in rows" :key="`mobile-${item.id}`" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-800">Rental #{{ item.id }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ item.container_serial_number }} · {{ statusLabel(item.container_type) }}</p>
                        <p v-if="item.route_summary?.label" class="mt-0.5 text-xs text-slate-400">
                            {{ item.route_summary.label }}
                        </p>
                        <p v-else-if="item.origin_port_name || item.destination_port_name" class="mt-0.5 text-xs text-slate-400">
                            {{ item.origin_port_name || '—' }} → {{ item.destination_port_name || '—' }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span
                                v-if="item.route_summary?.is_multi_hop"
                                class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                            >
                                Transshipment · {{ item.route_summary.leg_count }} legs
                            </span>
                            <span
                                v-else-if="item.route_summary?.leg_count === 1"
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                            >
                                Direct route
                            </span>
                            <button
                                v-if="Array.isArray(item.route_summary?.legs) && item.route_summary.legs.length"
                                type="button"
                                class="text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2"
                                @click="emit('open-route-details', item)"
                            >
                                Route details
                            </button>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-slate-900">{{ formatMoney(item.price) }}</p>
                </div>

                <p class="mt-2 inline-flex flex-wrap items-center gap-1.5 text-[11px] text-slate-600">
                    <span
                        v-if="item.container_operational_status"
                        class="h-2 w-2 shrink-0 rounded-full"
                        :class="statusDotClass('container', item.container_operational_status)"
                    />
                    <span>Equipment: {{ item.container_operational_status ? statusLabel(item.container_operational_status) : '—' }}</span>
                </p>
                <button
                    v-if="statusDetailEligible(item)"
                    type="button"
                    class="mt-1 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2"
                    @click="emit('open-status-detail', item)"
                >
                    Status notes
                </button>
                <div class="mt-3 grid grid-cols-1 gap-1.5 text-xs text-slate-700">
                    <p class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :class="statusDotClass('rental', item.status)" />
                        Rental: {{ statusLabel(item.status) }}
                    </p>
                    <p class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :class="statusDotClass('payment', item.payment_status)" />
                        Payment: {{ statusLabel(item.payment_status) }}
                    </p>
                    <p class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :class="statusDotClass('shipment', item.shipment_status)" />
                        Shipment: {{ statusLabel(item.shipment_status) }}
                    </p>
                </div>

                <p class="mt-2 text-xs text-slate-500">Start: {{ formatDate(item.start_date) }} · End: {{ formatDate(item.end_date) }}</p>
                <p class="mt-1 text-xs text-slate-500">Tracking: {{ item.tracking_number || 'No tracking yet' }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ buildRentalHint(item) }}</p>
                <div class="mt-2 flex justify-end">
                    <Link
                        v-if="item.can_view_iot_monitor && !isExpired(item)"
                        :href="route('rentals.monitor', item.id)"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                        :class="item.container_iot_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="item.container_iot_active ? 'bg-emerald-500' : 'bg-slate-400'"
                        />
                        <span>{{ item.container_iot_active ? 'IoT view' : 'Details' }}</span>
                    </Link>
                    <span
                        v-else
                        class="inline-flex rounded-full border border-dashed border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-500"
                    >
                        {{ iotUnavailableLabel(item) }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="!rows.length" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-center text-sm text-slate-500">
            No rentals found for current filters.
        </div>

        <div v-if="paginationLinks.length > 3" class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="link in paginationLinks"
                :key="link.label"
                type="button"
                class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm"
                :class="link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true, replace: true })"
                v-html="link.label"
            />
        </div>
    </section>
</template>
