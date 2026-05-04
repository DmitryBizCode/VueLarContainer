<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { formatDateGb, formatMoneyLocale } from '@/utils/formatLocale';
import { computed } from 'vue';
import DashboardHeroSection from './Partials/DashboardHeroSection.vue';
import DashboardStatsCards from './Partials/DashboardStatsCards.vue';

const user = usePage().props.auth.user;

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    profileCompletion: {
        type: Number,
        required: true,
    },
    recentRental: {
        type: Object,
        default: null,
    },
    orderHistory: {
        type: Array,
        required: true,
    },
    latestNotifications: {
        type: Array,
        default: () => [],
    },
    recentActivities: {
        type: Array,
        default: () => [],
    },
    userCountryName: {
        type: String,
        default: null,
    },
    profileReadiness: {
        type: Object,
        default: () => ({
            items: [],
            missingFields: [],
        }),
    },
    financialOverview: {
        type: Object,
        default: () => ({
            earnedRevenueAmount: 0,
            earnedRentalsCount: 0,
            paidTransactionsAmount: 0,
            paidTransactionsCount: 0,
            paidAmount: 0,
            pendingAmount: 0,
            failedAmount: 0,
            pendingCount: 0,
            failedCount: 0,
            lastTransactionAt: null,
        }),
    },
    shipmentOverview: {
        type: Object,
        default: () => ({
            inTransitCount: 0,
            upcomingArrivalsCount: 0,
            delayedCount: 0,
            arrivedThisWeekCount: 0,
        }),
    },
    incidentOverview: {
        type: Object,
        default: () => ({
            openCount: 0,
            highSeverityOpenCount: 0,
        }),
    },
    topRoutes: {
        type: Array,
        default: () => [],
    },
    upcomingMilestones: {
        type: Array,
        default: () => [],
    },
    transactionsByStatus: {
        type: Object,
        default: () => ({}),
    },
    rentalsByStatus: {
        type: Object,
        default: () => ({}),
    },
    rentalsByPaymentStatus: {
        type: Object,
        default: () => ({}),
    },
    rejectedApproval: {
        type: Object,
        default: () => ({ count: 0, lostRevenuePriceSum: 0, txAmountSum: 0 }),
    },
});

const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ');
const initials = [user.first_name?.[0], user.last_name?.[0]].filter(Boolean).join('').toUpperCase() || 'U';

const profileItems = computed(() => [
    { label: 'Full name', value: fullName || 'Not set yet' },
    { label: 'Email', value: user.email || 'Not set yet' },
    { label: 'Phone', value: user.phone_number || 'Not set yet' },
    { label: 'Company', value: user.company_name || 'Not set yet' },
    { label: 'Address', value: user.address || 'Not set yet' },
    { label: 'Country', value: props.userCountryName || 'Not set yet' },
]);

const completionChecks = computed(() =>
    props.profileReadiness.items?.length
        ? props.profileReadiness.items.map((item) => ({ label: item.label, done: item.done }))
        : []
);
const missingReadinessFields = computed(() => props.profileReadiness.missingFields ?? []);
const operationalAttentionCount = computed(
    () => Number(props.shipmentOverview.delayedCount || 0) + Number(props.incidentOverview.highSeverityOpenCount || 0)
);

const formatDate = formatDateGb;
const formatMoney = (value) => formatMoneyLocale(value, 'USD');

const statusClass = (status) => {
    return 'border-slate-200 bg-slate-50 text-slate-700';
};

const notificationClass = (type) => {
    return 'border-slate-200 bg-slate-50 text-slate-700';
};

const notificationBadge = (type) => {
    const normalized = String(type || '').toLowerCase();
    if (normalized === 'error') return 'Critical';
    if (normalized === 'warning') return 'Attention';
    if (normalized === 'success') return 'Update';
    return 'Info';
};

const markNotificationRead = (note) => {
    if (!note?.id || note?.is_read) return;

    router.patch(
        route('notifications.read', { notification: note.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['stats', 'latestNotifications'] });
            },
        }
    );
};

const markAllNotificationsRead = () => {
    router.post(
        route('notifications.read-all'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['stats', 'latestNotifications'] });
            },
        }
    );
};

const milestoneBadgeClass = (type) => {
    return 'border-slate-200 bg-slate-50 text-slate-700';
};

const markerState = (state) => {
    const normalized = String(state || '').toLowerCase();

    if (['error', 'failed', 'cancelled', 'blocked'].includes(normalized)) {
        return 'failed';
    }

    if (['warning', 'pending', 'in_review', 'in progress', 'in_progress'].includes(normalized)) {
        return 'pending';
    }

    if (['info', 'submitted', 'sent', 'queued'].includes(normalized)) {
        return 'submitted';
    }

    if (['processing', 'in transit', 'in_transit'].includes(normalized)) {
        return 'in_progress';
    }

    if (['expired', 'timeout', 'overdue'].includes(normalized)) {
        return 'expired';
    }

    if (['success', 'completed', 'delivered', 'closed', 'active', 'scheduled'].includes(normalized)) {
        return 'success';
    }

    return 'in_progress';
};

const markerIconPath = (state) => {
    const kind = markerState(state);

    if (kind === 'pending') {
        return 'M8 2.75l6 10.5H2l6-10.5zm0 3.25a.75.75 0 0 0-.75.75v2.75a.75.75 0 1 0 1.5 0V6.75A.75.75 0 0 0 8 6zm0 6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8z';
    }

    if (kind === 'submitted') {
        return 'M13.8 2.5 2.9 7.1c-.6.25-.58 1.11.03 1.34l3.15 1.17 1.17 3.16c.23.6 1.09.62 1.34.02L13.5 3.2c.23-.48-.22-.93-.7-.7z';
    }

    if (kind === 'success') {
        return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm2.35 3.95a.75.75 0 1 0-1.2-.9L7.55 7.7l-.72-.68a.75.75 0 1 0-1.04 1.08l1.35 1.3c.33.31.85.29 1.15-.06l2.06-2.89z';
    }

    if (kind === 'failed') {
        return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm-2.1 3.4a.75.75 0 0 0 0 1.06L6.94 8 5.9 9.04a.75.75 0 1 0 1.06 1.06L8 9.06l1.04 1.04a.75.75 0 0 0 1.06-1.06L9.06 8l1.04-1.04A.75.75 0 1 0 9.04 5.9L8 6.94 6.96 5.9a.75.75 0 0 0-1.06 0z';
    }

    if (kind === 'expired') {
        return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm.75 1.5h-1.5v2.8c0 .2.08.39.22.53l1.7 1.7 1.06-1.06-1.48-1.48V5.5z';
    }

    return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8z';
};

const markerIconColor = (state) => {
    const kind = markerState(state);

    if (kind === 'pending') return 'text-amber-500';
    if (kind === 'in_progress') return 'text-blue-600';
    if (kind === 'submitted') return 'text-violet-600';
    if (kind === 'success') return 'text-emerald-600';
    if (kind === 'failed') return 'text-rose-600';
    if (kind === 'expired') return 'text-slate-500';

    return 'text-slate-500';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader eyebrow="Control center" title="Operations dashboard">
                <template #aside>
                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                        Profile readiness {{ props.profileCompletion }}%
                    </span>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <DashboardHeroSection
                    :shipment-overview="props.shipmentOverview"
                    :operational-attention-count="operationalAttentionCount"
                />

                <div class="grid gap-6 xl:grid-cols-12">
                    <aside class="space-y-6 xl:col-span-4">
                        <div class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-700 to-slate-900 text-xl font-bold text-white">
                                    {{ initials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-lg font-bold text-slate-900">{{ fullName || 'User account' }}</p>
                                    <p class="truncate text-sm text-slate-500">{{ user.email }}</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Profile readiness</p>
                                    <span class="text-sm font-extrabold text-slate-900">{{ props.profileCompletion }}%</span>
                                </div>
                                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-700 to-slate-900 transition-all duration-1000 ease-out" :style="{ width: `${props.profileCompletion}%` }" />
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <span
                                        v-for="item in completionChecks"
                                        :key="item.label"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-slate-700"
                                    >
                                        <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                            <svg class="h-2.5 w-2.5" :class="markerIconColor(item.done ? 'completed' : 'pending')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path :d="markerIconPath(item.done ? 'completed' : 'pending')" />
                                            </svg>
                                        </span>
                                        {{ item.label }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <div
                                    v-for="item in profileItems"
                                    :key="item.label"
                                    class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 text-xs"
                                >
                                    <span class="font-medium uppercase tracking-wide text-slate-500">{{ item.label }}</span>
                                    <span class="ml-3 max-w-[55%] truncate text-right font-semibold text-slate-800">{{ item.value }}</span>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profile readiness gaps</p>
                                <div v-if="missingReadinessFields.length" class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="field in missingReadinessFields"
                                        :key="field"
                                        class="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600"
                                    >
                                        {{ field }}
                                    </span>
                                </div>
                                <p v-else class="mt-2 text-xs font-medium text-slate-700">
                                    Profile is complete. You can use all account operations.
                                </p>
                                <Link
                                    :href="route('profile.edit')"
                                    class="mt-3 inline-flex items-center text-xs font-semibold text-slate-700 hover:text-slate-900"
                                >
                                    Open profile settings →
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <div class="space-y-6 xl:col-span-8">
                        <DashboardStatsCards :stats="props.stats" />

                        <div class="grid gap-4 xl:grid-cols-2">
                            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Finance pulse</h3>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                        Last transaction: {{ formatDate(props.financialOverview.lastTransactionAt) }}
                                    </span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('completed')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('completed')" />
                                                </svg>
                                            </span>
                                            Earned (completed)
                                        </p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(props.financialOverview.earnedRevenueAmount ?? props.financialOverview.paidAmount) }}</p>
                                        <p class="text-xs text-slate-500">{{ props.financialOverview.earnedRentalsCount ?? 0 }} rentals</p>
                                        <p class="mt-1 text-[11px] leading-snug text-slate-500">
                                            Payment rails: {{ formatMoney(props.financialOverview.paidTransactionsAmount ?? 0) }}
                                            <span class="tabular-nums">({{ props.financialOverview.paidTransactionsCount ?? 0 }} tx)</span>
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('pending')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('pending')" />
                                                </svg>
                                            </span>
                                            Pending
                                        </p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(props.financialOverview.pendingAmount) }}</p>
                                        <p class="text-xs text-slate-500">{{ props.financialOverview.pendingCount }} transactions</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('failed')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('failed')" />
                                                </svg>
                                            </span>
                                            Failed
                                        </p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(props.financialOverview.failedAmount) }}</p>
                                        <p class="text-xs text-slate-500">{{ props.financialOverview.failedCount }} transactions</p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Shipment health</h3>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                        Fleet signal
                                    </span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('in_progress')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('in_progress')" />
                                                </svg>
                                            </span>
                                            In transit
                                        </p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.shipmentOverview.inTransitCount }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('completed')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('completed')" />
                                                </svg>
                                            </span>
                                            Arrived this week
                                        </p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.shipmentOverview.arrivedThisWeekCount }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('scheduled')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('scheduled')" />
                                                </svg>
                                            </span>
                                            Upcoming arrivals
                                        </p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.shipmentOverview.upcomingArrivalsCount }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-600">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor('failed')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath('failed')" />
                                                </svg>
                                            </span>
                                            Delayed / high risk
                                        </p>
                                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ operationalAttentionCount }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">
                                        Open incidents: {{ props.incidentOverview.openCount }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700">
                                        <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                            <svg class="h-2.5 w-2.5" :class="markerIconColor('failed')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path :d="markerIconPath('failed')" />
                                            </svg>
                                        </span>
                                        High severity: {{ props.incidentOverview.highSeverityOpenCount }}
                                    </span>
                                </div>
                            </section>
                        </div>

                        <section class="grid gap-4 lg:grid-cols-3">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-bold text-slate-900">Transactions by status</h3>
                                <p class="mt-1 text-xs text-slate-500">Your payments across all statuses.</p>
                                <div v-if="Object.keys(props.transactionsByStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.transactionsByStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.amount_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No transactions yet.</p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-bold text-slate-900">Rentals by status</h3>
                                <p class="mt-1 text-xs text-slate-500">Your rental lifecycle distribution.</p>
                                <div v-if="Object.keys(props.rentalsByStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.rentalsByStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.price_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No rentals yet.</p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-bold text-slate-900">Rentals by payment status</h3>
                                <p class="mt-1 text-xs text-slate-500">Payment states for your rentals.</p>
                                <div v-if="Object.keys(props.rentalsByPaymentStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.rentalsByPaymentStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.price_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No rentals yet.</p>
                            </div>
                        </section>

                        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Rejected (approval)</h3>
                                    <p class="mt-0.5 text-xs text-slate-500">Separate from payment failures.</p>
                                </div>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {{ props.rejectedApproval.count ?? 0 }} rentals
                                </span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                    <p class="text-xs font-semibold text-slate-500">Lost revenue (price)</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(props.rejectedApproval.lostRevenuePriceSum ?? 0) }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                    <p class="text-xs font-semibold text-slate-500">Tx volume</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(props.rejectedApproval.txAmountSum ?? 0) }}</p>
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-6 2xl:grid-cols-5">
                            <section class="2xl:col-span-2 space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Notifications</h3>
                                    <p class="mt-1 text-xs font-medium text-slate-500">Critical account and shipment updates</p>
                                    <div v-if="(props.stats?.unreadNotifications ?? 0) > 0" class="mt-2 flex justify-end">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="markAllNotificationsRead"
                                        >
                                            Mark all as read
                                        </button>
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <div
                                            v-for="note in props.latestNotifications"
                                            :key="`n-${note.id}`"
                                            class="rounded-xl border px-3 py-2.5"
                                            :class="[
                                                notificationClass(note.type),
                                                note.is_read
                                                    ? 'bg-slate-50/40 text-slate-500 opacity-80'
                                                    : 'cursor-pointer bg-white ring-1 ring-amber-200/60 border-amber-200/70 hover:bg-amber-50/30',
                                            ]"
                                            @click="!note.is_read && markNotificationRead(note)"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                            <p class="inline-flex items-center gap-1.5 text-sm font-semibold">
                                                <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                    <svg class="h-2.5 w-2.5" :class="markerIconColor(note.type)" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                        <path :d="markerIconPath(note.type)" />
                                                    </svg>
                                                </span>
                                                {{ note.title }}
                                            </p>
                                                <div class="flex items-center gap-2">
                                                    <span v-if="!note.is_read" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                                                        Unread
                                                    </span>
                                                    <span class="rounded-full border border-current/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                                                        {{ notificationBadge(note.type) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <p class="mt-1 line-clamp-2 text-xs opacity-80">{{ note.message }}</p>
                                        </div>
                                        <div v-if="!props.latestNotifications.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2.5 text-xs text-slate-500">
                                            No notifications yet.
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-bold uppercase tracking-wide text-slate-600">Recent actions</h4>
                                    <div class="mt-2 space-y-2">
                                        <div
                                            v-for="entry in props.recentActivities"
                                            :key="`a-${entry.id}`"
                                            class="flex items-start justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                        >
                                            <div>
                                                <p class="font-semibold text-slate-800">{{ entry.action }}</p>
                                                <p class="text-slate-500">{{ entry.model_name }} #{{ entry.model_id }}</p>
                                            </div>
                                            <span class="ml-2 shrink-0 text-slate-400">{{ formatDate(entry.created_at) }}</span>
                                        </div>
                                        <div v-if="!props.recentActivities.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2.5 text-xs text-slate-500">
                                            No activity log entries.
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="2xl:col-span-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Recent container activity</h3>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Latest rental</span>
                                </div>

                                <template v-if="props.recentRental">
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Container</p>
                                            <p class="mt-1 font-semibold text-slate-900">{{ props.recentRental.container_serial_number }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Type</p>
                                            <p class="mt-1 font-semibold text-slate-900">{{ props.recentRental.container_type }}</p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Dimensions</p>
                                            <p class="mt-1 font-semibold text-slate-900">
                                                {{ props.recentRental.width }} x {{ props.recentRental.length }} x {{ props.recentRental.height }} m
                                            </p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Route</p>
                                            <p class="mt-1 font-semibold text-slate-900">
                                                {{ props.recentRental.origin_port_name || 'N/A' }} -> {{ props.recentRental.destination_port_name || 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700">Rental: {{ props.recentRental.rental_status || 'unknown' }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700">Shipment: {{ props.recentRental.shipment_status || 'unknown' }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700">Tracking: {{ props.recentRental.tracking_number || 'not assigned' }}</span>
                                    </div>
                                </template>
                                <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500">
                                    No rental activity yet. Start your first rental to see live details here.
                                </div>
                            </section>
                        </div>

                        <div class="grid gap-6 2xl:grid-cols-5">
                            <section class="2xl:col-span-2 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Upcoming milestones</h3>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">10 days</span>
                                </div>
                                <div class="mt-4 space-y-2">
                                    <div
                                        v-for="event in props.upcomingMilestones"
                                        :key="event.id"
                                        class="rounded-xl border px-3 py-2.5"
                                        :class="milestoneBadgeClass(event.type)"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="inline-flex items-center gap-1.5 text-sm font-semibold">
                                                <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                    <svg class="h-2.5 w-2.5" :class="markerIconColor(event.type === 'payment' ? 'pending' : 'scheduled')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                        <path :d="markerIconPath(event.type === 'payment' ? 'pending' : 'scheduled')" />
                                                    </svg>
                                                </span>
                                                {{ event.title }}
                                            </p>
                                            <span class="text-xs font-medium opacity-80">{{ formatDate(event.date) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="!props.upcomingMilestones.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2.5 text-xs text-slate-500">
                                        No scheduled milestones in the next 10 days.
                                    </div>
                                </div>
                            </section>

                            <section class="2xl:col-span-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Top sea routes</h3>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">By shipments</span>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div
                                        v-for="(route, index) in props.topRoutes"
                                        :key="`${route.origin_port_name}-${route.destination_port_name}-${index}`"
                                        class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                                    >
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Route {{ index + 1 }}</p>
                                        <p class="mt-1 text-sm font-bold text-slate-900">
                                            {{ route.origin_port_name || 'N/A' }} -> {{ route.destination_port_name || 'N/A' }}
                                        </p>
                                        <p class="mt-2 text-xs font-semibold text-blue-700">{{ route.shipments_count }} shipments</p>
                                    </div>
                                    <div v-if="!props.topRoutes.length" class="sm:col-span-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500">
                                        No route analytics yet. Route patterns appear after shipment activity.
                                    </div>
                                </div>
                            </section>
                        </div>

                        <section id="order-history" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-slate-900">Order history</h3>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ props.orderHistory.length }} records
                                </span>
                            </div>

                            <div class="mt-4 hidden overflow-x-auto lg:block">
                                <table class="min-w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                            <th class="px-3 py-3">Order</th>
                                            <th class="px-3 py-3">Container</th>
                                            <th class="px-3 py-3">Start</th>
                                            <th class="px-3 py-3">End</th>
                                            <th class="px-3 py-3">Price</th>
                                            <th class="px-3 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="order in props.orderHistory"
                                            :key="order.id"
                                            class="border-b border-slate-50 transition-colors hover:bg-slate-50/70"
                                        >
                                            <td class="px-3 py-3 font-semibold text-slate-800">#{{ order.id }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ order.container_serial_number }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ formatDate(order.start_date) }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ formatDate(order.end_date) }}</td>
                                            <td class="px-3 py-3 font-medium text-slate-800">{{ formatMoney(order.price) }}</td>
                                            <td class="px-3 py-3">
                                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(order.rental_status)">
                                                    <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                        <svg class="h-2.5 w-2.5" :class="markerIconColor(order.rental_status)" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                            <path :d="markerIconPath(order.rental_status)" />
                                                        </svg>
                                                    </span>
                                                    {{ order.rental_status || 'unknown' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 space-y-3 lg:hidden">
                                <div
                                    v-for="order in props.orderHistory"
                                    :key="`mobile-${order.id}`"
                                    class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-bold text-slate-800">Order #{{ order.id }}</p>
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusClass(order.rental_status)">
                                            <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border border-slate-300/80 bg-white text-slate-500">
                                                <svg class="h-2.5 w-2.5" :class="markerIconColor(order.rental_status)" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <path :d="markerIconPath(order.rental_status)" />
                                                </svg>
                                            </span>
                                            {{ order.rental_status || 'unknown' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Container: {{ order.container_serial_number }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Period: {{ formatDate(order.start_date) }} to {{ formatDate(order.end_date) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ formatMoney(order.price) }}</p>
                                </div>
                                <div v-if="!props.orderHistory.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-center text-sm text-slate-500">
                                    Your order history is empty right now.
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
