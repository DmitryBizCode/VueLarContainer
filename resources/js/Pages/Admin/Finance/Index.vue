<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import RevenueDynamicsChart from '@/Components/Finance/RevenueDynamicsChart.vue';
import PaymentMethodsChart from '@/Components/Finance/PaymentMethodsChart.vue';
import FailedTrendChart from '@/Components/Finance/FailedTrendChart.vue';
import BarChartHorizontal from '@/Components/Finance/BarChartHorizontal.vue';
import RentalsVsRevenueChart from '@/Components/Finance/RentalsVsRevenueChart.vue';
import DailyBreakdownChart from '@/Components/Finance/DailyBreakdownChart.vue';
import FinanceHistoryModal from '@/Components/Finance/FinanceHistoryModal.vue';

const props = defineProps({
    synthetic_ledger_prefix: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    overview: { type: Object, required: true },
    rentalsSummary: { type: Object, default: () => ({}) },
    containersSummary: { type: Object, default: () => ({}) },
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
    transactions: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    paymentStatusOptions: { type: Array, default: () => [] },
    staffStats: { type: Array, default: () => [] },
    pendingPaymentApprovals: { type: Array, default: () => [] },
    reportData: { type: Object, default: null },
    reportDateFrom: { type: String, default: null },
    reportDateTo: { type: String, default: null },
});

const activeSection = ref('overview');

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
const applyFilters = () => filterForm.get(route('admin.finance.index'), { preserveState: true });

const reportForm = useForm({
    report_date_from: props.reportDateFrom ?? '',
    report_date_to: props.reportDateTo ?? '',
});
const applyReport = () => reportForm.get(route('admin.finance.index'), { preserveState: true });
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
    filterForm.get(route('admin.finance.index'), { preserveState: true });
};
const setStaffSort = (by) => {
    const next = filterForm.staff_sort_by === by && filterForm.staff_sort_order === 'desc' ? 'asc' : 'desc';
    filterForm.staff_sort_by = by;
    filterForm.staff_sort_order = next;
    filterForm.get(route('admin.finance.index'), { preserveState: true });
};

const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');

const isSyntheticLedgerRef = (externalId) =>
    Boolean(props.synthetic_ledger_prefix && String(externalId || '').startsWith(props.synthetic_ledger_prefix));

const setTransactionStatus = (txId, status) => {
    router.patch(route('admin.finance.transactions.update', txId), { status });
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
                        @click="activeSection = item.id"
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
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-emerald-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.paidAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.paidCount }} tx</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.pendingAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.pendingCount }} tx</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-rose-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(overview.failedAmount) }}</p>
                                <p class="text-xs text-slate-600">{{ overview.failedCount }} tx</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 border-l-4 border-l-blue-500 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue (rentals)</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenuePaid) }}</p>
                                <p class="text-xs text-slate-600">{{ rentalsSummary.rentalsPaidCount }} rentals</p>
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
                                <p class="text-xs font-semibold text-slate-500">Rentals revenue (paid)</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(rentalsSummary.revenuePaid) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Containers revenue</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(containersSummary.revenue_from_rentals) }}</p>
                                <p class="text-xs text-slate-500">{{ containersSummary.total }} containers</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Pending revenue</p>
                                <p class="mt-1 text-2xl font-bold text-amber-600">{{ formatMoney(rentalsSummary.revenuePending) }}</p>
                                <p class="text-xs text-slate-500">{{ rentalsSummary.rentalsPendingCount }} rentals</p>
                            </div>
                        </section>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue dynamics (12 months)</h3>
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
                                                    <button type="button" class="text-xs text-slate-500 underline hover:text-slate-700" @click="openHistory('rental', r.id)">History</button>
                                                    <select class="rounded-lg border border-slate-200 text-xs" :value="r.payment_status" @change="(e) => setRentalPaymentStatus(r.id, e.target.value)">
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
                            <h2 class="border-b border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-900">Pending transactions</h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">ID</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Rental</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Customer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Amount</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Set status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="t in pendingTransactions" :key="t.id">
                                            <td class="px-5 py-3 text-sm font-mono">{{ t.id }}</td>
                                            <td class="px-5 py-3 text-sm">#{{ t.rental_id }}</td>
                                            <td class="px-5 py-3 text-sm">{{ t.rental?.customer }}</td>
                                            <td class="px-5 py-3 text-sm font-medium">{{ formatMoney(t.amount) }} {{ t.currency }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" class="text-xs text-slate-500 underline hover:text-slate-700" @click="openHistory('transaction', t.id)">History</button>
                                                    <select class="rounded-lg border border-slate-200 text-xs" :value="t.status" @change="(e) => setTransactionStatus(t.id, e.target.value)">
                                                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="!pendingTransactions.length" class="py-8 text-center text-sm text-slate-500">No pending transactions</p>
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
                                            <button type="button" class="hover:underline" @click="setStaffSort('approved_count')">Approved count {{ filterForm.staff_sort_by === 'approved_count' ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setStaffSort('approved_sum')">Approved sum {{ filterForm.staff_sort_by === 'approved_sum' ? (filterForm.staff_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
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
                                        <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.approved_count }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.approved_sum) }}</td>
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
                                                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700" @click="approvePayment(r.id)">Approve payment</button>
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
                            <div class="mb-4 flex justify-end">
                                <a :href="route('admin.finance.report.export', { format: 'csv', date_from: reportDateFrom, date_to: reportDateTo })" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Export CSV</a>
                            </div>
                            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                                <h3 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Staff stats (period)</h3>
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">Staff</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Approved count</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Approved sum</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Commission</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-600">Bonus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <tr v-for="s in reportData.staffStats" :key="s.user_id">
                                            <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ s.name }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-slate-700">{{ s.approved_count }}</td>
                                            <td class="px-5 py-3 text-right text-sm font-medium text-slate-800">{{ formatMoney(s.approved_sum) }}</td>
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
                            <input v-model="filterForm.date_from" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <input v-model="filterForm.date_to" type="date" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" />
                            <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-600">
                                            <button type="button" class="hover:underline" @click="setTransactionSort('id')">ID {{ filterForm.transaction_sort_by === 'id' ? (filterForm.transaction_sort_order === 'desc' ? '↓' : '↑') : '' }}</button>
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
                                        <td class="px-5 py-3 text-sm"><span class="rounded px-2 py-0.5 text-xs" :class="t.status === 'paid' || t.status === 'completed' || t.status === 'succeeded' || t.status === 'success' ? 'bg-emerald-100 text-emerald-800' : t.status === 'pending' || t.status === 'processing' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'">{{ t.status }}</span></td>
                                        <td class="px-5 py-3 text-sm text-slate-700">{{ formatDate(t.transaction_date) }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" class="text-xs text-slate-500 underline hover:text-slate-700" @click="openHistory('transaction', t.id)">History</button>
                                                <select class="rounded border border-slate-200 text-xs" :value="t.status" @change="(e) => setTransactionStatus(t.id, e.target.value)">
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
                                <h3 class="mb-4 text-sm font-semibold text-slate-900">Revenue dynamics (12 months)</h3>
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
                        </div>
                    </template>
                </main>
            </div>
        </div>
        <FinanceHistoryModal
            :show="historyModalShow"
            :type="historyType"
            :entity-id="historyId"
            @close="historyModalShow = false"
        />
    </AuthenticatedLayout>
</template>
