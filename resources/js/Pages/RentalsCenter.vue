<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
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
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    shipment_status: props.filters.shipment_status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
});

const rows = computed(() => props.rentals?.data ?? []);
const paginationLinks = computed(() => props.rentals?.links ?? []);

const rentalStatusOptions = [
    { value: '', label: 'All rental statuses' },
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
        if (['scheduled'].includes(normalized)) return 'bg-blue-500';
        if (['completed'].includes(normalized)) return 'bg-slate-500';
        if (['cancelled'].includes(normalized)) return 'bg-rose-500';
    }

    if (kind === 'payment') {
        if (['paid'].includes(normalized)) return 'bg-emerald-500';
        if (['pending', 'unpaid'].includes(normalized)) return 'bg-amber-500';
        if (['failed'].includes(normalized)) return 'bg-rose-500';
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

const applyFilters = () => {
    const payload = {
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
    filterState.status = '';
    filterState.payment_status = '';
    filterState.shipment_status = '';
    filterState.date_from = '';
    filterState.date_to = '';
    filterState.q = '';
    applyFilters();
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

                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-3 lg:grid-cols-7">
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
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-3 py-3">Rental</th>
                                    <th class="px-3 py-3">Container</th>
                                    <th class="px-3 py-3">Timeline</th>
                                    <th class="px-3 py-3">Statuses</th>
                                    <th class="px-3 py-3">Shipment</th>
                                    <th class="px-3 py-3">Amount</th>
                                    <th class="px-3 py-3">Hint</th>
                                    <th class="px-3 py-3 text-right">IoT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in rows"
                                    :key="item.id"
                                    class="border-b border-slate-50 align-top transition-colors hover:bg-slate-50/70"
                                >
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-800">#{{ item.id }}</p>
                                        <p class="text-xs text-slate-500">Created operation</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-800">{{ item.container_serial_number }}</p>
                                        <p class="text-xs text-slate-500">{{ statusLabel(item.container_type) }}</p>
                                        <p class="mt-1 text-[11px] text-slate-500">IoT: {{ item.container_iot_active ? 'enabled' : 'disabled' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-slate-600">
                                        <p class="text-xs">Start: {{ formatDate(item.start_date) }}</p>
                                        <p class="text-xs">End: {{ formatDate(item.end_date) }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="space-y-1.5 text-xs text-slate-700">
                                            <p class="inline-flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full" :class="statusDotClass('rental', item.status)" />
                                                {{ statusLabel(item.status) }}
                                            </p>
                                            <p class="inline-flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full" :class="statusDotClass('payment', item.payment_status)" />
                                                {{ statusLabel(item.payment_status) }}
                                            </p>
                                            <p class="inline-flex items-center gap-1.5">
                                                <span class="h-2 w-2 rounded-full" :class="statusDotClass('shipment', item.shipment_status)" />
                                                {{ statusLabel(item.shipment_status) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-slate-600">
                                        <p class="text-xs font-semibold text-slate-800">{{ item.tracking_number || 'No tracking yet' }}</p>
                                        <p class="text-xs">ETA: {{ formatDate(item.shipment_arrival_date) }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-semibold text-slate-900">{{ formatMoney(item.price) }}</td>
                                    <td class="px-3 py-3">
                                        <p class="text-xs text-slate-600">{{ buildRentalHint(item) }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <Link
                                            v-if="item.can_view_iot_monitor"
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
                                            IoT after approval
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
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ formatMoney(item.price) }}</p>
                            </div>

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
                                    v-if="item.can_view_iot_monitor"
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
                                    IoT after approval
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
    </AuthenticatedLayout>
</template>
