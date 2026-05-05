<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import RevenueDynamicsChart from '@/Components/Finance/RevenueDynamicsChart.vue';
import PaymentMethodsChart from '@/Components/Finance/PaymentMethodsChart.vue';
import FailedTrendChart from '@/Components/Finance/FailedTrendChart.vue';
import BarChartHorizontal from '@/Components/Finance/BarChartHorizontal.vue';
import RentalsVsRevenueChart from '@/Components/Finance/RentalsVsRevenueChart.vue';
import DailyBreakdownChart from '@/Components/Finance/DailyBreakdownChart.vue';
import FinanceHistoryModal from '@/Components/Finance/FinanceHistoryModal.vue';
import SimpleLineChart from '@/Components/Finance/SimpleLineChart.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    synthetic_ledger_prefix: { type: String, default: '' },
    syntheticTransactions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    overview: { type: Object, required: true },
    rentalsSummary: { type: Object, default: () => ({}) },
    containersSummary: { type: Object, default: () => ({}) },
    transactionsByStatus: { type: Object, default: () => ({}) },
    rentalsByStatus: { type: Object, default: () => ({}) },
    rentalsByPaymentStatus: { type: Object, default: () => ({}) },
    rejectedApproval: { type: Object, default: () => ({ count: 0, lostRevenuePriceSum: 0, txAmountSum: 0 }) },
    chartData: { type: Array, default: () => [] },
    chartPaymentMethods: { type: Array, default: () => [] },
    chartByRoute: { type: Array, default: () => [] },
    chartTopContainers: { type: Array, default: () => [] },
    failedTrend: { type: Array, default: () => [] },
    yoyMom: { type: Object, default: () => ({}) },
    dailyBreakdown: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
    pendingOrders: { type: Array, default: () => [] },
    pendingTransactions: { type: Array, default: () => [] },
    awaitingCaptureRentals: { type: Array, default: () => [] },
    transactions: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    paymentStatusOptions: { type: Array, default: () => [] },
    staffStats: { type: Array, default: () => [] },
    pendingPaymentApprovals: { type: Array, default: () => [] },
    reportData: { type: Object, default: null },
    reportDateFrom: { type: String, default: null },
    reportDateTo: { type: String, default: null },
    awaitingCaptureSummary: { type: Object, default: () => ({ count: 0, amount: 0 }) },
    kpiFormulas: { type: Object, default: () => ({}) },
    successRateTrend: { type: Array, default: () => [] },
    avgTicketTrend: { type: Array, default: () => [] },
    paymentMethodTrend: { type: Array, default: () => [] },
    routeTrend: { type: Array, default: () => [] },
});

const filterForm = useForm({
    status: props.filters.status ?? '',
    payment_method: props.filters.payment_method ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
    staff_sort_by: props.filters.staff_sort_by ?? 'approved_sum',
    staff_sort_order: props.filters.staff_sort_order ?? 'desc',
    transaction_sort_by: props.filters.transaction_sort_by ?? 'transaction_date',
    transaction_sort_order: props.filters.transaction_sort_order ?? 'desc',
});
const applyFilters = () =>
    router.get(route('admin.finance.index'), { ...filterForm.data(), section: activeSection.value }, { preserveState: true, preserveScroll: true });

const reportForm = useForm({
    report_date_from: props.reportDateFrom ?? '',
    report_date_to: props.reportDateTo ?? '',
});
const applyReport = () =>
    router.get(route('admin.finance.index'), { ...reportForm.data(), section: activeSection.value }, { preserveState: true, preserveScroll: true });
const setReportPreset = (preset) => {
    const now = new Date();
    const y = now.getFullYear();
    let from, to;
    if (preset === 'q1') { from = `${y}-01-01`; to = `${y}-03-31`; }
    else if (preset === 'q2') { from = `${y}-04-01`; to = `${y}-06-30`; }
    else if (preset === 'q3') { from = `${y}-07-01`; to = `${y}-09-30`; }
    else if (preset === 'q4') { from = `${y}-10-01`; to = `${y}-12-31`; }
    else { from = ''; to = ''; }
    reportForm.report_date_from = from;
    reportForm.report_date_to = to;
    reportForm.get(route('admin.finance.index'), { preserveState: true });
};
const setTransactionSort = (by) => {
    const next = filterForm.transaction_sort_by === by && filterForm.transaction_sort_order === 'desc' ? 'asc' : 'desc';
    filterForm.transaction_sort_by = by;
    filterForm.transaction_sort_order = next;
    router.get(route('admin.finance.index'), { ...filterForm.data(), section: activeSection.value }, { preserveState: true, preserveScroll: true, replace: true });
};
const setStaffSort = (by) => {
    const next = filterForm.staff_sort_by === by && filterForm.staff_sort_order === 'desc' ? 'asc' : 'desc';
    filterForm.staff_sort_by = by;
    filterForm.staff_sort_order = next;
    router.get(route('admin.finance.index'), { ...filterForm.data(), section: activeSection.value }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');

const txStatusBadgeClass = (status) => {
    const s = String(status || '').toLowerCase();
    if (['paid', 'completed', 'succeeded', 'success'].includes(s)) {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (['pending', 'processing', 'awaiting_capture'].includes(s)) {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-rose-100 text-rose-800';
};

const isSyntheticLedgerRef = (externalId) =>
    Boolean(props.synthetic_ledger_prefix && String(externalId || '').startsWith(props.synthetic_ledger_prefix));

const txStatusModal = reactive({
    show: false,
    txId: null,
    status: '',
    note: '',
    error: '',
});

const onTransactionStatusChange = (tx, event) => {
    const newStatus = String(event.target.value || '').toLowerCase();
    if (['failed', 'cancelled'].includes(newStatus)) {
        event.target.value = tx.status;
        txStatusModal.txId = tx.id;
        txStatusModal.status = event.target.value;
        txStatusModal.note = '';
        txStatusModal.error = '';
        txStatusModal.show = true;

        return;
    }

    router.patch(
        route('admin.finance.transactions.update', tx.id),
        { status: event.target.value, status_note: null },
        { preserveScroll: true }
    );
};

const confirmTxStatusWithNote = () => {
    const n = txStatusModal.note?.trim() || '';
    if (!n) {
        txStatusModal.error = 'A note is required for failed or cancelled transactions.';

        return;
    }

    txStatusModal.error = '';
    router.patch(
        route('admin.finance.transactions.update', txStatusModal.txId),
        { status: txStatusModal.status, status_note: txStatusModal.note },
        {
            preserveScroll: true,
            onSuccess: () => {
                txStatusModal.show = false;
            },
            onError: (errors) => {
                txStatusModal.error = errors.status_note?.[0] || errors.status?.[0] || 'Could not update.';
            },
        }
    );
};

const closeTxStatusModal = () => {
    txStatusModal.show = false;
};

const setRentalPaymentStatus = (rentalId, paymentStatus) => {
    router.patch(route('admin.finance.rentals.payment-status', rentalId), { payment_status: paymentStatus });
};
const approvePayment = (rentalId) => {
    router.post(route('admin.finance.rentals.approve-payment', rentalId), {}, { preserveScroll: true });
};

const historyModalShow = ref(false);
const historyType = ref('transaction');
const historyId = ref(null);
const openHistory = (type, id) => {
    historyType.value = type;
    historyId.value = id;
    historyModalShow.value = true;
};

const historyButtonClass =
    'inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50';

const navItems = [
    { id: 'overview', label: 'Overview', group: 'main' },
    { id: 'revenue', label: 'Revenue', group: 'main' },
    { id: 'performance', label: 'Performance', group: 'main' },
    { id: 'staff', label: 'Staff stats', group: 'main' },
    { id: 'payment-approvals', label: 'Payment approvals', group: 'ops' },
    { id: 'pending', label: 'Pending orders', group: 'ops' },
    { id: 'transactions', label: 'Transactions', group: 'ops' },
    { id: 'reports', label: 'Reports', group: 'insights' },
    { id: 'analytics', label: 'Analytics & charts', group: 'insights' },
];

const setActiveSection = (id) => {
    if (!navItems.some((item) => item.id === id)) {
        return;
    }
    activeSection.value = id;
    router.get(route('admin.finance.index'), { ...filterForm.data(), section: id }, { preserveState: true, preserveScroll: true, replace: true });
};

const sectionFromServer = props.filters?.section;
const activeSection = ref(
    sectionFromServer && navItems.some((item) => item.id === sectionFromServer) ? sectionFromServer : 'overview',
);
</script>

<template>
    <Head title="Admin – Finance" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4">
                <h1 class="text-xl font-semibold text-slate-900">Finance</h1>
                <!-- Top nav bar -->
                <nav class="-mx-4 -mb-1 flex gap-0.5 overflow-x-auto border-b border-slate-200 bg-slate-50/50 px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                    <button
                        v-for="item in navItems"
                        :key="item.id"
                        type="button"
                        class="shrink-0 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors"
                        :class="activeSection === item.id
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                        @click.stop="setActiveSection(item.id)"
                    >
                        {{ item.label }}
                    </button>
                </nav>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <main>
                    <!-- Overview -->
                    <template v-if="activeSection === 'overview'">
                        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-blue-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Earned revenue</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">Completed rentals, paid (sum of rental price).</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenuePaid) }}</p>
                                <p class="text-xs text-slate-600">{{ rentalsSummary.rentalsPaidCount }} rentals</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-emerald-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid (transactions)</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">PSP / ledger successful tx amounts.</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.paidAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.paidCount }} tx</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.pendingAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.pendingCount }} tx</p>
                                <p v-if="(awaitingCaptureSummary?.count ?? 0) > 0" class="mt-1 text-[11px] text-slate-500">
                                    Awaiting capture: <span class="font-semibold text-slate-700">{{ awaitingCaptureSummary.count }}</span> · {{ formatMoney(awaitingCaptureSummary.amount) }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-rose-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.failedAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.failedCount }} tx</p>
                            </div>
                        </section>
                        <section
                            v-if="(rentalsSummary.revenueBooked ?? 0) > 0 || (rentalsSummary.rentalsBookedCount ?? 0) > 0"
                            class="mt-4 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Booked (paid, not completed)</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">Cash in before rental lifecycle completes (prepaid / in progress).</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenueBooked) }}</p>
                            <p class="text-xs text-slate-600">{{ rentalsSummary.rentalsBookedCount }} rentals</p>
                        </section>
                        <section class="mt-4 rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Rejected (approval)</h3>
                                    <p class="mt-0.5 text-xs text-slate-500">Separate from non-payment rejects.</p>
                                </div>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {{ rejectedApproval.count ?? 0 }} rentals
                                </span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                    <p class="text-xs font-semibold text-slate-500">Lost revenue (price)</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(rejectedApproval.lostRevenuePriceSum ?? 0) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                    <p class="text-xs font-semibold text-slate-500">Tx volume</p>
                                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(rejectedApproval.txAmountSum ?? 0) }}</p>
                                </div>
                            </div>
                        </section>
                        <section class="mt-6 grid gap-4 lg:grid-cols-3">
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-900">Transactions by status</h3>
                                <p class="mt-1 text-xs text-slate-500">Global breakdown (all statuses).</p>
                                <div v-if="Object.keys(props.transactionsByStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.transactionsByStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.amount_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No transaction rows yet.</p>
                            </div>

                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-900">Rentals by status</h3>
                                <p class="mt-1 text-xs text-slate-500">Lifecycle coverage including rejected/pending.</p>
                                <div v-if="Object.keys(props.rentalsByStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.rentalsByStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.price_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No rentals yet.</p>
                            </div>

                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-900">Rentals by payment status</h3>
                                <p class="mt-1 text-xs text-slate-500">Paid/pending/unpaid/failed/cancelled.</p>
                                <div v-if="Object.keys(props.rentalsByPaymentStatus || {}).length" class="mt-3 space-y-2">
                                    <div
                                        v-for="(row, status) in props.rentalsByPaymentStatus"
                                        :key="status"
                                        class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs"
                                    >
                                        <span class="font-semibold text-slate-700">{{ status }}</span>
                                        <span class="tabular-nums text-slate-600">{{ row.count ?? 0 }} · {{ formatMoney(row.price_sum ?? 0) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-3 text-sm text-slate-600">No rentals yet.</p>
                            </div>
                        </section>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">YoY & MoM</h3>
                                <div class="flex flex-wrap gap-4">
                                    <div class="rounded-lg bg-slate-50/80 px-4 py-3">
                                        <p class="text-xs text-slate-500">YoY change</p>
                                        <p class="text-xl font-bold" :class="(yoyMom.yoyChangePercent ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                            {{ (yoyMom.yoyChangePercent ?? 0) >= 0 ? '+' : '' }}{{ yoyMom.yoyChangePercent ?? 0 }}%
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50/80 px-4 py-3">
                                        <p class="text-xs text-slate-500">MoM change</p>
                                        <p class="text-xl font-bold" :class="(yoyMom.momChangePercent ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                            {{ (yoyMom.momChangePercent ?? 0) >= 0 ? '+' : '' }}{{ yoyMom.momChangePercent ?? 0 }}%
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-slate-50/80 px-4 py-3">
                                        <p class="text-xs text-slate-500">This month</p>
                                        <p class="text-xl font-bold text-slate-800">{{ formatMoney(yoyMom.thisMonth) }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">QoQ change</p>
                                        <p class="mt-1 text-lg font-bold" :class="(kpiFormulas.qoqChangePercent ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                            {{ (kpiFormulas.qoqChangePercent ?? 0) >= 0 ? '+' : '' }}{{ kpiFormulas.qoqChangePercent ?? 0 }}%
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">This quarter vs previous quarter</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">Rolling 30 days</p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(kpiFormulas.rolling30dAmount) }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">Successful transactions</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">YTD total</p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(kpiFormulas.ytdAmount) }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">From Jan 1 to today</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">Avg / P95 ticket (30d)</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">Avg (this month): {{ formatMoney(kpiFormulas.avgTicketThisMonth) }}</p>
                                        <p class="mt-0.5 text-sm font-semibold text-slate-900">P95 (30d): {{ formatMoney(kpiFormulas.p95Ticket30d) }}</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">Success rate (30d)</p>
                                        <p class="mt-1 text-lg font-bold text-slate-900">{{ kpiFormulas.successRate30d ?? 0 }}%</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">Success tx / all tx</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">Gap (12m): collected − earned</p>
                                        <p class="mt-1 text-lg font-bold" :class="(kpiFormulas.gapCollectedMinusEarned12m ?? 0) >= 0 ? 'text-slate-900' : 'text-rose-700'">
                                            {{ formatMoney(kpiFormulas.gapCollectedMinusEarned12m) }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">Paid (tx) minus Earned revenue</p>
                                    </div>
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Daily revenue (14 days)</h3>
                                <div class="h-40">
                                    <DailyBreakdownChart :chart-data="dailyBreakdown" />
                                </div>
                            </section>
                        </div>
                    </template>

                    <!-- Revenue -->
                    <template v-if="activeSection === 'revenue'">
                        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Earned (completed & paid)</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenuePaid) }}</p>
                                <p class="text-xs text-slate-500">{{ rentalsSummary.rentalsPaidCount }} rentals</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Containers (earned revenue)</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(containersSummary.revenue_from_rentals) }}</p>
                                <p class="text-xs text-slate-500">{{ containersSummary.total }} containers</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Booked (paid, not completed)</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenueBooked) }}</p>
                                <p class="text-xs text-slate-500">{{ rentalsSummary.rentalsBookedCount }} rentals</p>
                            </div>
                        </section>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue dynamics (24 months)</h3>
                                <div class="h-64">
                                    <RevenueDynamicsChart :chart-data="chartData" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Payment methods</h3>
                                <div class="h-64">
                                    <PaymentMethodsChart :chart-data="chartPaymentMethods" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue by route</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="chartByRoute" />
                                </div>
                            </section>
                        </div>
                    </template>

                    <!-- Performance -->
                    <template v-if="activeSection === 'performance'">
                        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Success rate</p>
                                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ metrics.successRate ?? 0 }}%</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Failed rate</p>
                                <p class="mt-1 text-3xl font-bold text-rose-600">{{ metrics.failedRate ?? 0 }}%</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Avg transaction</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(metrics.avgTransaction) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Total transactions</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ overview.totalTransactions ?? 0 }}</p>
                            </div>
                        </section>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Rentals vs Revenue</h3>
                                <div class="h-72">
                                    <RentalsVsRevenueChart :chart-data="chartData" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Failed transactions trend</h3>
                                <div class="h-72">
                                    <FailedTrendChart :chart-data="failedTrend" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Top containers by revenue</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="chartTopContainers" />
                                </div>
                            </section>
                        </div>
                    </template>

                    <!-- Pending orders -->
                    <template v-if="activeSection === 'pending'">
                        <section class="space-y-6">
                        <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                            <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-900">Rentals awaiting payment</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">#</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Customer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Route</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Amount</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Set status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="r in pendingOrders" :key="r.id">
                                            <td class="px-5 py-3 text-sm font-mono text-slate-700">{{ r.id }}</td>
                                            <td class="px-5 py-3 text-sm text-slate-700">{{ r.customer }}</td>
                                            <td class="px-5 py-3 text-sm text-slate-600">{{ r.origin }} → {{ r.destination }}</td>
                                            <td class="px-5 py-3 text-sm font-medium">{{ formatMoney(r.price) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" :class="historyButtonClass" @click="openHistory('rental', r.id)">
                                                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 1 6.32-3.09.75.75 0 1 1-1.18-.92A6.5 6.5 0 1 0 10 16.5a.75.75 0 0 1 0 1.5Zm-.75-11a.75.75 0 0 1 1.5 0v3.19l2.03 1.17a.75.75 0 1 1-.75 1.3l-2.4-1.39A.75.75 0 0 1 9.25 10V7Z" clip-rule="evenodd" />
                                                        </svg>
                                                        History
                                                    </button>
                                                    <span v-if="!r.payment_approved_at" class="text-[11px] font-medium text-amber-700">Authorize capture first</span>
                                                    <select
                                                        class="rounded-lg border border-slate-200 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                                        :title="!r.payment_approved_at ? 'Authorize capture in Payment approvals first' : ''"
                                                        :disabled="!r.payment_approved_at"
                                                        :value="r.payment_status"
                                                        @change="(e) => setRentalPaymentStatus(r.id, e.target.value)"
                                                    >
                                                        <option v-for="s in paymentStatusOptions" :key="s" :value="s">{{ s }}</option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!pendingOrders.length" class="py-8 text-center text-sm text-slate-500">No pending orders</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                            <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-900">Pending transactions (PSP)</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Type</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">ID</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Rental</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Customer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Amount</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Set status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="t in pendingTransactions" :key="t.id">
                                            <td class="px-5 py-3 text-sm"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">PSP</span></td>
                                            <td class="px-5 py-3 text-sm font-mono">{{ t.id }}</td>
                                            <td class="px-5 py-3 text-sm">#{{ t.rental_id }}</td>
                                            <td class="px-5 py-3 text-sm">{{ t.rental?.customer }}</td>
                                            <td class="px-5 py-3 text-sm font-medium">{{ formatMoney(t.amount) }} {{ t.currency }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" :class="historyButtonClass" @click="openHistory('transaction', t.id)">
                                                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 1 6.32-3.09.75.75 0 1 1-1.18-.92A6.5 6.5 0 1 0 10 16.5a.75.75 0 0 1 0 1.5Zm-.75-11a.75.75 0 0 1 1.5 0v3.19l2.03 1.17a.75.75 0 1 1-.75 1.3l-2.4-1.39A.75.75 0 0 1 9.25 10V7Z" clip-rule="evenodd" />
                                                        </svg>
                                                        History
                                                    </button>
                                                    <select class="rounded-lg border border-slate-200 text-xs" :value="t.status" @change="(e) => onTransactionStatusChange(t, e)">
                                                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!pendingTransactions.length" class="py-8 text-center text-sm text-slate-500">No PSP transactions in pending or processing</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                            <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-900">Awaiting capture</h2>
                            <div class="border-b border-slate-100 bg-slate-50/60 px-5 py-4">
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Internal payment capture is already authorized for these bookings. When your bank or PSP confirms funds, set
                                    <span class="font-medium text-slate-700">paid</span>
                                    in the table above — same rows appear under
                                    <span class="font-medium text-slate-700">Rentals awaiting payment</span>.
                                </p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Customer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Route</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="t in (awaitingCaptureRentals || [])" :key="t.id">
                                            <td class="px-5 py-3 text-sm text-slate-700">{{ t.rental?.customer }}</td>
                                            <td class="px-5 py-3 text-sm text-slate-600">{{ t.rental?.origin || '—' }} → {{ t.rental?.destination || '—' }}</td>
                                            <td class="px-5 py-3 text-sm font-medium text-slate-800">{{ formatMoney(t.amount) }} {{ t.currency }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!(awaitingCaptureRentals || []).length" class="py-8 text-center text-sm text-slate-500">No bookings in this state.</p>
                        </div>
                        </section>
                    </template>

                    <!-- Staff stats -->
                    <template v-if="activeSection === 'staff'">
                        <div class="mb-6 flex flex-wrap gap-3">
                            <input v-model="filterForm.date_from" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <input v-model="filterForm.date_to" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Apply period</button>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Staff</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('rental_review_count')">Rental reviews (n) {{ ['rental_review_count', 'approved_count'].includes(filterForm.staff_sort_by) ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('rental_review_sum')">Rental reviews ($) {{ ['rental_review_sum', 'approved_sum'].includes(filterForm.staff_sort_by) ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('payment_auth_count')">Payment auth (n) {{ filterForm.staff_sort_by === 'payment_auth_count' ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('payment_auth_sum')">Payment auth ($) {{ filterForm.staff_sort_by === 'payment_auth_sum' ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Rate %</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Commission</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('bonus')">Bonus {{ filterForm.staff_sort_by === 'bonus' ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr v-for="s in staffStats" :key="s.user_id">
                                        <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ s.name }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.rental_review_count ?? s.approved_count }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.rental_review_sum ?? s.approved_sum) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.payment_auth_count ?? 0 }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.payment_auth_sum ?? 0) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-slate-600">{{ s.commission_rate }}%</td>
                                        <td class="px-5 py-3 text-right text-sm text-slate-700">{{ formatMoney(s.commission) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-slate-700">{{ formatMoney(s.bonus) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="!staffStats.length" class="py-8 text-center text-sm text-slate-500">No staff stats for the selected period.</p>
                    </template>

                    <!-- Payment approvals -->
                    <template v-if="activeSection === 'payment-approvals'">
                        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                            <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-900">Rentals awaiting payment approval</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">#</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Customer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Route</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Amount</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="r in pendingPaymentApprovals" :key="r.id">
                                            <td class="px-5 py-3 text-sm font-mono text-slate-700">{{ r.id }}</td>
                                            <td class="px-5 py-3 text-sm text-slate-700">{{ r.customer }}</td>
                                            <td class="px-5 py-3 text-sm text-slate-600">{{ r.origin }} → {{ r.destination }}</td>
                                            <td class="px-5 py-3 text-sm font-medium">{{ formatMoney(r.price) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700" @click="approvePayment(r.id)">Authorize capture</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!pendingPaymentApprovals.length" class="py-8 text-center text-sm text-slate-500">No rentals awaiting payment approval.</p>
                        </div>
                    </template>

                    <!-- Reports -->
                    <template v-if="activeSection === 'reports'">
                        <div class="mb-6 flex flex-wrap items-center gap-3">
                            <input v-model="reportForm.report_date_from" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <input v-model="reportForm.report_date_to" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyReport">Generate report</button>
                            <span class="text-sm text-slate-500">Presets:</span>
                            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="setReportPreset('q1')">Q1</button>
                            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="setReportPreset('q2')">Q2</button>
                            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="setReportPreset('q3')">Q3</button>
                            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="setReportPreset('q4')">Q4</button>
                        </div>
                        <template v-if="reportData">
                            <section class="mb-6 grid gap-4 sm:grid-cols-3">
                                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold text-slate-500">Paid (period)</p>
                                    <p class="mt-1 text-xl font-bold text-slate-900">{{ formatMoney(reportData.overview.paidAmount) }}</p>
                                    <p class="text-xs text-slate-600">{{ reportData.overview.paidCount }} tx</p>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold text-slate-500">Pending (period)</p>
                                    <p class="mt-1 text-xl font-bold text-slate-900">{{ formatMoney(reportData.overview.pendingAmount) }}</p>
                                    <p class="text-xs text-slate-600">{{ reportData.overview.pendingCount }} tx</p>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold text-slate-500">Failed (period)</p>
                                    <p class="mt-1 text-xl font-bold text-slate-900">{{ formatMoney(reportData.overview.failedAmount) }}</p>
                                    <p class="text-xs text-slate-600">{{ reportData.overview.failedCount }} tx</p>
                                </div>
                            </section>
                            <div
                                v-if="(reportData.awaitingCaptureSummary?.count ?? 0) > 0"
                                class="mb-6 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Awaiting capture (period)</p>
                                <p class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney(reportData.awaitingCaptureSummary.amount) }}</p>
                                <p class="text-xs text-slate-600">{{ reportData.awaitingCaptureSummary.count }} bookings</p>
                            </div>
                            <div class="mb-4 flex justify-end">
                                <a :href="route('admin.finance.report.export', { format: 'csv', date_from: reportDateFrom, date_to: reportDateTo })" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Export CSV</a>
                            </div>
                            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                                <h3 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Staff stats (period)</h3>
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Staff</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Rental reviews (n)</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Rental reviews ($)</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Payment auth (n)</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Payment auth ($)</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Commission</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Bonus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="s in reportData.staffStats" :key="s.user_id">
                                            <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ s.name }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.rental_review_count ?? s.approved_count }}</td>
                                            <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.rental_review_sum ?? s.approved_sum) }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.payment_auth_count ?? 0 }}</td>
                                            <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.payment_auth_sum ?? 0) }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-slate-700">{{ formatMoney(s.commission) }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-slate-700">{{ formatMoney(s.bonus) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <p v-else class="text-sm text-slate-500">Select a date range and click Generate report.</p>
                    </template>

                    <!-- Transactions -->
                    <template v-if="activeSection === 'transactions'">
                        <div class="mb-6 flex flex-wrap gap-3">
                            <input v-model="filterForm.q" type="search" placeholder="Rental ID, provider ID…" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" @keyup.enter="applyFilters" />
                            <select v-model="filterForm.status" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                                <option value="">All statuses</option>
                                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="() => { filterForm.status = (filterForm.status === 'pending' ? '' : 'pending'); applyFilters(); }"
                            >
                                {{ filterForm.status === 'pending' ? 'Show all' : 'Show pending only' }}
                            </button>
                            <input v-model="filterForm.date_from" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <input v-model="filterForm.date_to" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('id')">ID / reference {{ filterForm.transaction_sort_by === 'id' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('rental_id')">Rental {{ filterForm.transaction_sort_by === 'rental_id' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('amount')">Amount {{ filterForm.transaction_sort_by === 'amount' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Reference</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('status')">Status {{ filterForm.transaction_sort_by === 'status' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('transaction_date')">Date {{ filterForm.transaction_sort_by === 'transaction_date' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr v-for="t in (props.syntheticTransactions || [])" :key="`synthetic-${t.id}`" class="bg-indigo-50/40">
                                        <td class="px-5 py-3 text-sm text-slate-900">
                                            <p class="text-sm font-medium text-slate-800">{{ t.display_title || `Rental #${t.rental_id}` }}</p>
                                            <p class="mt-0.5 font-mono text-[10px] text-slate-400" :title="t.id">{{ t.id }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-sm">
                                            <Link :href="route('admin.rentals.show', t.rental_id)" class="font-medium text-slate-700 hover:underline">#{{ t.rental_id }}</Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-700">{{ formatMoney(t.amount) }} {{ t.currency }}</td>
                                        <td class="px-5 py-3 text-sm text-slate-600">
                                            <p v-if="t.external_provider_id" class="font-mono text-[10px] text-slate-500">{{ t.external_provider_id }}</p>
                                            <span class="mt-1 inline-block rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800">
                                                {{ t.display_kind === 'rejected_by_approval' ? 'Rejected' : t.display_kind === 'pending_approval' ? 'Awaiting approval' : 'Synthetic' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-sm">
                                            <span class="rounded px-2 py-0.5 text-xs" :class="txStatusBadgeClass(t.status)">{{ t.status }}</span>
                                            <p v-if="t.status_note" class="mt-1 text-xs text-slate-500">{{ t.status_note }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-700">{{ formatDate(t.transaction_date) }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <button type="button" :class="historyButtonClass" @click="openHistory('rental', t.rental_id)">
                                                <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 1 6.32-3.09.75.75 0 1 1-1.18-.92A6.5 6.5 0 1 0 10 16.5a.75.75 0 0 1 0 1.5Zm-.75-11a.75.75 0 0 1 1.5 0v3.19l2.03 1.17a.75.75 0 1 1-.75 1.3l-2.4-1.39A.75.75 0 0 1 9.25 10V7Z" clip-rule="evenodd" />
                                                </svg>
                                                History
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-for="t in transactions.data" :key="t.id">
                                        <td class="px-5 py-3 text-sm text-slate-900">{{ t.id }}</td>
                                        <td class="px-5 py-3 text-sm">
                                            <Link :href="route('admin.rentals.show', t.rental_id)" class="font-medium text-slate-700 hover:underline">#{{ t.rental_id }}</Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-slate-700">{{ formatMoney(t.amount) }} {{ t.currency }}</td>
                                        <td class="px-5 py-3 text-sm text-slate-600">
                                            <p class="font-mono text-xs">{{ t.external_provider_id || '—' }}</p>
                                            <span
                                                v-if="isSyntheticLedgerRef(t.external_provider_id)"
                                                class="mt-1 inline-block rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800"
                                            >
                                                Manual ledger
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-sm"><span class="rounded px-2 py-0.5 text-xs" :class="txStatusBadgeClass(t.status)">{{ t.status }}</span></td>
                                        <td class="px-5 py-3 text-sm text-slate-700">{{ formatDate(t.transaction_date) }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" :class="historyButtonClass" @click="openHistory('transaction', t.id)">
                                                    <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 1 6.32-3.09.75.75 0 1 1-1.18-.92A6.5 6.5 0 1 0 10 16.5a.75.75 0 0 1 0 1.5Zm-.75-11a.75.75 0 0 1 1.5 0v3.19l2.03 1.17a.75.75 0 1 1-.75 1.3l-2.4-1.39A.75.75 0 0 1 9.25 10V7Z" clip-rule="evenodd" />
                                                    </svg>
                                                    History
                                                </button>
                                                <select class="rounded border border-slate-200 text-xs" :value="t.status" @change="(e) => onTransactionStatusChange(t, e)">
                                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                                                </select>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <nav v-if="transactions.links?.length" class="mt-6 flex justify-center gap-2">
                            <Link v-for="(link, i) in transactions.links" :key="i" :href="link.url || '#'" class="rounded border px-3 py-1.5 text-sm" :class="link.active ? 'border-slate-300 bg-slate-100' : 'border-slate-200 hover:bg-slate-50'" v-html="link.label" />
                        </nav>
                    </template>

                    <!-- Analytics & charts -->
                    <template v-if="activeSection === 'analytics'">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue dynamics (24 months)</h3>
                                <div class="h-64">
                                    <RevenueDynamicsChart :chart-data="chartData" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Payment methods</h3>
                                <div class="h-64">
                                    <PaymentMethodsChart :chart-data="chartPaymentMethods" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Success rate trend (26 weeks)</h3>
                                <div class="h-64">
                                    <SimpleLineChart :chart-data="successRateTrend" label="Success rate (%)" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Avg ticket trend (26 weeks)</h3>
                                <div class="h-64">
                                    <SimpleLineChart :chart-data="avgTicketTrend" label="Avg ticket (success tx)" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Rentals vs Revenue</h3>
                                <div class="h-64">
                                    <RentalsVsRevenueChart :chart-data="chartData" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Failed transactions trend</h3>
                                <div class="h-64">
                                    <FailedTrendChart :chart-data="failedTrend" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Top containers by revenue</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="chartTopContainers" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue by route</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="chartByRoute" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Payment methods (12 months totals)</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="paymentMethodTrend" />
                                </div>
                            </section>
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Top routes (totals)</h3>
                                <div class="h-64">
                                    <BarChartHorizontal :chart-data="routeTrend" />
                                </div>
                            </section>
                        </div>
                    </template>
                </main>
            </div>
        </div>
        <Modal :show="txStatusModal.show" max-width="md" @close="closeTxStatusModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Transaction status</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Set status to <strong class="capitalize">{{ txStatusModal.status }}</strong>. A short note is required for the customer and audit trail.
                </p>
                <label class="mt-4 block text-xs font-semibold text-slate-500" for="tx-status-note">Status note</label>
                <textarea
                    id="tx-status-note"
                    v-model="txStatusModal.note"
                    rows="3"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    placeholder="Explain why the payment failed or was cancelled"
                />
                <p v-if="txStatusModal.error" class="mt-2 text-sm text-rose-600">{{ txStatusModal.error }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeTxStatusModal">
                        Cancel
                    </button>
                    <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="confirmTxStatusWithNote">
                        Save
                    </button>
                </div>
            </div>
        </Modal>

        <FinanceHistoryModal
            :show="historyModalShow"
            :type="historyType"
            :entity-id="historyId"
            @close="historyModalShow = false"
        />
    </AuthenticatedLayout>
</template>
