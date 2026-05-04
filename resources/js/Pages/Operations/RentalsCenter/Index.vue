<script setup>
import PortsMapLeaflet from '@/Components/Logistics/PortsMapLeaflet.vue';
import Modal from '@/Components/Modal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { formatDateGb, formatMoneyLocale } from '@/utils/formatLocale';
import { computed, reactive } from 'vue';
import RentalsCenterFiltersForm from './Partials/RentalsCenterFiltersForm.vue';
import RentalsCenterOverviewCards from './Partials/RentalsCenterOverviewCards.vue';
import RentalsCenterRentalsList from './Partials/RentalsCenterRentalsList.vue';

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

const formatDate = formatDateGb;
const formatMoney = formatMoneyLocale;

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
            <PageHeader eyebrow="Operations" title="Rentals center">
                <template #aside>
                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                    Live view of all rental operations
                </span>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <RentalsCenterOverviewCards :overview="props.overview" />

                <section class="mb-6">
                    <PortsMapLeaflet />
                </section>

                <RentalsCenterFiltersForm
                    :filter-state="filterState"
                    :list-scope-options="listScopeOptions"
                    :rental-status-options="rentalStatusOptions"
                    :payment-status-options="paymentStatusOptions"
                    :shipment-status-options="shipmentStatusOptions"
                    @apply-filters="applyFilters"
                    @reset-filters="resetFilters"
                />

                <RentalsCenterRentalsList
                    :rows="rows"
                    :pagination-links="paginationLinks"
                    :format-date="formatDate"
                    :format-money="formatMoney"
                    :status-label="statusLabel"
                    :status-dot-class="statusDotClass"
                    :build-rental-hint="buildRentalHint"
                    :segment-label="segmentLabel"
                    :status-detail-eligible="statusDetailEligible"
                    :is-expired="isExpired"
                    :iot-unavailable-label="iotUnavailableLabel"
                    @open-route-details="openRouteDetails"
                    @open-status-detail="openStatusDetail"
                />
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
