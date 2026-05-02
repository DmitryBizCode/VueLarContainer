<script setup>
import PortsMapLeaflet from '@/Components/Logistics/PortsMapLeaflet.vue';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            scope: 'successful',
            status: null,
            payment_status: null,
            shipment_status: null,
            date_from: null,
            date_to: null,
            q: null,
        }),
    },
    overview: {
        type: Object,
        default: () => ({
            activeCount: 0,
            completedCount: 0,
            overduePaymentsCount: 0,
            upcomingStartsCount: 0,
        }),
    },
    rentals: {
        type: Object,
        required: true,
    },
});

const filterState = reactive({
    scope: props.filters.scope === 'all' ? 'all' : 'successful',
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    shipment_status: props.filters.shipment_status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
});

const listScopeOptions = [
    { value: 'successful', label: 'Trackable (paid / in progress)' },
    { value: 'all', label: 'All my rentals' },
];

const rows = computed(() => props.rentals?.data ?? []);
const paginationLinks = computed(() => props.rentals?.links ?? []);

const rentalStatusOptions = [
    { value: '', label: 'All rental statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending_approval', label: 'Pending approval' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'active', label: 'Active' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const paymentStatusOptions = [
    { value: '', label: 'All payment statuses' },
    { value: 'paid', label: 'Paid' },
    { value: 'pending', label: 'Pending' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'failed', label: 'Failed' },
];

const shipmentStatusOptions = [
    { value: '', label: 'All shipment statuses' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'arrived', label: 'Arrived' },
    { value: 'completed', label: 'Completed' },
];

const statusLabel = (value) => {
    if (!value) return 'Unknown';

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const isExpired = (item) => {
    const end = item?.end_date ? new Date(item.end_date).getTime() : NaN;
    if (!Number.isFinite(end)) return false;
    return end < Date.now();
};

const iotUnavailableLabel = (item) => {
    if (isExpired(item)) return 'Rental expired';

    const rentalStatus = String(item?.status || '').toLowerCase();
    const paymentStatus = String(item?.payment_status || '').toLowerCase();
    const hasIot = Boolean(item?.container_iot_active);

    if (rentalStatus === 'rejected') {
        return paymentStatus === 'rejected_by_approval' ? 'Rejected (approval)' : 'Rejected';
    }
    if (rentalStatus === 'cancelled') return 'Cancelled';
    if (rentalStatus === 'draft') return 'Draft';
    if (rentalStatus === 'pending_approval') return 'Awaiting approval';
    if (!hasIot) return 'IoT disabled';

    return 'IoT unavailable';
};

const formatMoney = (value, currency = 'USD') => {
    const amount = Number(value ?? 0);
    const safeCurrency = String(currency || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: safeCurrency,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount.toFixed(2)} ${safeCurrency}`;
    }
};

const statusDotClass = (kind, value) => {
    const normalized = String(value || '').toLowerCase();

    if (kind === 'rental') {
        if (['active', 'in_progress'].includes(normalized)) return 'bg-emerald-500';
        if (['approved'].includes(normalized)) return 'bg-sky-500';
        if (['scheduled'].includes(normalized)) return 'bg-blue-500';
        if (['pending_approval'].includes(normalized)) return 'bg-amber-500';
        if (['draft'].includes(normalized)) return 'bg-slate-400';
        if (['rejected'].includes(normalized)) return 'bg-rose-600';
        if (['completed'].includes(normalized)) return 'bg-slate-500';
        if (['cancelled'].includes(normalized)) return 'bg-orange-600';
    }

    if (kind === 'payment') {
        if (['paid'].includes(normalized)) return 'bg-emerald-500';
        if (['pending', 'unpaid'].includes(normalized)) return 'bg-amber-500';
        if (['failed'].includes(normalized)) return 'bg-rose-500';
        if (['cancelled'].includes(normalized)) return 'bg-orange-600';
    }

    if (kind === 'container') {
        if (['available', 'idle'].includes(normalized)) return 'bg-slate-400';
        if (['in_use', 'in_transit', 'assigned'].includes(normalized)) return 'bg-blue-500';
        if (['maintenance', 'damaged'].includes(normalized)) return 'bg-amber-600';
    }

    if (kind === 'shipment') {
        if (['in_transit', 'in_progress', 'scheduled'].includes(normalized)) return 'bg-blue-500';
        if (['arrived', 'completed'].includes(normalized)) return 'bg-emerald-500';
    }

    return 'bg-slate-400';
};

const buildRentalHint = (item) => {
    const paymentStatus = String(item.payment_status || '').toLowerCase();
    const shipmentStatus = String(item.shipment_status || '').toLowerCase();

    if (['failed', 'unpaid'].includes(paymentStatus)) {
        return 'Payment requires attention';
    }

    if (shipmentStatus === 'in_transit') {
        return 'Shipment is currently in transit';
    }

    if (!item.tracking_number) {
        return 'Tracking is not assigned yet';
    }

    return 'Operations look stable';
};

const segmentLabel = (seg) => {
    if (!seg) return '';
    const o = seg.origin_port_name || '';
    const d = seg.destination_port_name || '';
    const base = o && d ? `${o} → ${d}` : `Route #${seg.route_id || '—'}`;
    const seq = Number(seg.leg_sequence || 0);
    const st = statusLabel(seg.status || '');
    return seq ? `Leg ${seq}: ${base} · ${st}` : `${base} · ${st}`;
};

const applyFilters = () => {
    const payload = {
        scope: filterState.scope === 'all' ? 'all' : undefined,
        status: filterState.status || undefined,
        payment_status: filterState.payment_status || undefined,
        shipment_status: filterState.shipment_status || undefined,
        date_from: filterState.date_from || undefined,
        date_to: filterState.date_to || undefined,
        q: filterState.q?.trim() || undefined,
    };

    router.get(route('rentals.center'), payload, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterState.scope = 'successful';
    filterState.status = '';
    filterState.payment_status = '';
    filterState.shipment_status = '';
    filterState.date_from = '';
    filterState.date_to = '';
    filterState.q = '';
    applyFilters();
};

const statusDetailEligible = (item) => {
    const st = String(item.status || '').toLowerCase();
    return (
        ['draft', 'pending_approval', 'rejected', 'cancelled'].includes(st)
        || Boolean(item.rejection_reason?.trim())
        || Boolean(item.cancellation_reason?.trim())
    );
};

const reasonModal = reactive({
    show: false,
    title: '',
    body: '',
});

const routeModal = reactive({
    show: false,
    title: '',
    summary: null,
});

const openRouteDetails = (item) => {
    routeModal.title = `Rental #${item.id} · Route details`;
    routeModal.summary = item.route_summary || null;
    routeModal.show = true;
};

const closeRouteModal = () => {
    routeModal.show = false;
};

const openStatusDetail = (item) => {
    const st = String(item.status || '').toLowerCase();
    const lines = [];

    if (item.container_operational_status) {
        lines.push(`Equipment status: ${statusLabel(item.container_operational_status)}`);
    }

    if (st === 'rejected') {
        lines.push(item.rejection_reason?.trim() || 'No rejection reason was recorded.');
    } else if (st === 'cancelled') {
        lines.push(item.cancellation_reason?.trim() || 'No cancellation notes were recorded.');
    } else if (st === 'pending_approval') {
        lines.push('Awaiting operations review — you will be notified when the request is approved or rejected.');
    } else if (st === 'draft') {
        lines.push('Draft — complete and submit the rental request when ready.');
    } else if (item.rejection_reason?.trim()) {
        lines.push(`Previous rejection note: ${item.rejection_reason.trim()}`);
    } else if (item.cancellation_reason?.trim()) {
        lines.push(`Cancellation note: ${item.cancellation_reason.trim()}`);
    }

    reasonModal.title = `Rental #${item.id} · ${statusLabel(item.status)}`;
    reasonModal.body = lines.join('\n\n');
    reasonModal.show = true;
};

const closeReasonModal = () => {
    reasonModal.show = false;
};
</script>

<template>
    <Head title="Rentals Center" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Operations</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">Rentals center</h1>
                </div>
                <span class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 sm:inline-flex">
                    Live view of all rental operations
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active rentals</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ props.overview.activeCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Scheduled and in progress</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed rentals</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ props.overview.completedCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Closed operations</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overdue payments</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ props.overview.overduePaymentsCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Needs immediate follow-up</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Upcoming starts</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ props.overview.upcomingStartsCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Next 7 days</p>
                        </div>
                    </div>
                </section>

                <section class="mb-6">
                    <PortsMapLeaflet />
                </section>

                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-3 lg:grid-cols-8">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="list_scope">List</label>
                            <select id="list_scope" v-model="filterState.scope" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700" @change="applyFilters">
                                <option v-for="option in listScopeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="status">Rental status</label>
                            <select id="status" v-model="filterState.status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                                <option v-for="option in rentalStatusOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="payment_status">Payment</label>
                            <select id="payment_status" v-model="filterState.payment_status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                                <option v-for="option in paymentStatusOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="shipment_status">Shipment</label>
                            <select id="shipment_status" v-model="filterState.shipment_status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                                <option v-for="option in shipmentStatusOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_from">Start from</label>
                            <input id="date_from" v-model="filterState.date_from" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_to">Start to</label>
                            <input id="date_to" v-model="filterState.date_to" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="query">Search</label>
                            <input
                                id="query"
                                v-model="filterState.q"
                                type="text"
                                placeholder="Rental ID, container serial, tracking"
                                class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                @keyup.enter="applyFilters"
                            >
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" @click="applyFilters">
                            Apply filters
                        </button>
                        <button type="button" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="resetFilters">
                            Reset
                        </button>
                    </div>
                </section>

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
                                            @click="openRouteDetails(item)"
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
                                            @click="openStatusDetail(item)"
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
                                            @click="openRouteDetails(item)"
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
                                @click="openStatusDetail(item)"
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
            </div>
        </div>

        <Modal :show="reasonModal.show" max-width="md" @close="closeReasonModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ reasonModal.title }}</h3>
                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ reasonModal.body }}</p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="closeReasonModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="routeModal.show" max-width="lg" @close="closeRouteModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ routeModal.title }}</h3>
                <p v-if="routeModal.summary?.label" class="mt-1 text-sm text-slate-600">{{ routeModal.summary.label }}</p>
                <p class="mt-2 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                    :class="routeModal.summary?.is_multi_hop ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                >
                    {{ routeModal.summary?.is_multi_hop
                        ? `Transshipment · ${routeModal.summary.leg_count} legs`
                        : 'Direct route' }}
                </p>
                <div v-if="Array.isArray(routeModal.summary?.legs) && routeModal.summary.legs.length" class="mt-4 space-y-2">
                    <div
                        v-for="(leg, idx) in routeModal.summary.legs"
                        :key="`${idx}-${leg.route_id || ''}`"
                        class="rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2"
                    >
                        <p class="text-sm font-semibold text-slate-800">
                            Leg {{ idx + 1 }}: {{ leg.origin_name || `#${leg.origin_port_id}` }}
                            <span class="text-slate-500">-&gt;</span>
                            {{ leg.destination_name || `#${leg.destination_port_id}` }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ leg.estimated_days ?? '—' }} d · {{ Number(leg.distance || 0).toFixed(0) }} km
                        </p>
                    </div>
                </div>
                <div v-if="routeModal.summary?.intermediate_ports?.length" class="mt-4 text-xs text-slate-600">
                    <span class="font-semibold text-slate-700">Transfer ports:</span>
                    {{ routeModal.summary.intermediate_ports.join(', ') }}
                </div>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="closeRouteModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
