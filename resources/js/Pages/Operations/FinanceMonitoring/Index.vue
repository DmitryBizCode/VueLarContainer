<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    synthetic_ledger_prefix: {
        type: String,
        default: '',
    },
    filters: {
        type: Object,
        default: () => ({
            status: null,
            payment_method: null,
            date_from: null,
            date_to: null,
            q: null,
        }),
    },
    overview: {
        type: Object,
        default: () => ({
            paidAmount: 0,
            pendingAmount: 0,
            failedAmount: 0,
            paidCount: 0,
            pendingCount: 0,
            failedCount: 0,
            totalTransactions: 0,
            lastTransactionAt: null,
        }),
    },
    transactions: {
        type: Object,
        required: true,
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
    syntheticTransactions: {
        type: Array,
        default: () => [],
    },
});

const filterState = reactive({
    status: props.filters.status ?? '',
    payment_method: props.filters.payment_method ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    q: props.filters.q ?? '',
});

const transactionRows = computed(() => [
    ...(props.syntheticTransactions ?? []),
    ...(props.transactions?.data ?? []),
]);
const paginationLinks = computed(() => props.transactions?.links ?? []);

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'paid', label: 'Paid' },
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
    { value: 'success', label: 'Success' },
];

const paymentMethodOptions = [
    { value: '', label: 'All methods' },
    { value: 'card', label: 'Card' },
    { value: 'wire', label: 'Wire transfer' },
    { value: 'bank_transfer', label: 'Bank transfer' },
];

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

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const statusBadgeClass = (status) => {
    const normalized = String(status || '').toLowerCase();

    if (['paid', 'completed', 'success', 'succeeded'].includes(normalized)) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (['pending', 'processing'].includes(normalized)) {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (normalized === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    if (normalized === 'cancelled') {
        return 'border-orange-200 bg-orange-50 text-orange-800';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
};

const applyFilters = () => {
    const payload = {
        status: filterState.status || undefined,
        payment_method: filterState.payment_method || undefined,
        date_from: filterState.date_from || undefined,
        date_to: filterState.date_to || undefined,
        q: filterState.q?.trim() || undefined,
    };

    router.get(route('finance.monitoring'), payload, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterState.status = '';
    filterState.payment_method = '';
    filterState.date_from = '';
    filterState.date_to = '';
    filterState.q = '';
    applyFilters();
};

const isSyntheticLedgerRef = (externalId) =>
    Boolean(props.synthetic_ledger_prefix && String(externalId || '').startsWith(props.synthetic_ledger_prefix));

const txNotesModal = reactive({
    show: false,
    title: '',
    body: '',
});

const hasTransactionNotes = (item) =>
    Boolean(
        item.status_note?.trim()
        || item.refund_reason?.trim()
        || item.rental_rejection_reason?.trim()
    );

const openTransactionNotes = (item) => {
    const parts = [];
    if (item.status_note?.trim()) {
        parts.push(`Operations note: ${item.status_note.trim()}`);
    }
    if (item.refund_reason?.trim()) {
        parts.push(`Refund / adjustment: ${item.refund_reason.trim()}`);
    }
    if (item.rental_rejection_reason?.trim()) {
        parts.push(`Linked rental note: ${item.rental_rejection_reason.trim()}`);
    }
    txNotesModal.title = `Transaction #${item.id}`;
    txNotesModal.body = parts.length ? parts.join('\n\n') : 'No additional notes on file for this transaction.';
    txNotesModal.show = true;
};

const closeTransactionNotes = () => {
    txNotesModal.show = false;
};
</script>

<template>
    <Head title="Finance Monitoring" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader eyebrow="Operations" title="Finance monitoring">
                <template #aside>
                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                        Last transaction: {{ formatDate(props.overview.lastTransactionAt) }}
                    </span>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid amount</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ formatMoney(props.overview.paidAmount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ props.overview.paidCount }} transactions</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending amount</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ formatMoney(props.overview.pendingAmount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ props.overview.pendingCount }} transactions</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed amount</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ formatMoney(props.overview.failedAmount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ props.overview.failedCount }} transactions</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total transactions</p>
                            <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ props.overview.totalTransactions }}</p>
                            <p class="mt-1 text-xs text-slate-500">After current filters</p>
                        </div>
                    </div>
                </section>

                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Rejected (approval)</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Tracked separately from payment failures.</p>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ props.rejectedApproval.count ?? 0 }} rentals
                        </span>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lost revenue (price)</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">{{ formatMoney(props.rejectedApproval.lostRevenuePriceSum ?? 0) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tx volume</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">{{ formatMoney(props.rejectedApproval.txAmountSum ?? 0) }}</p>
                        </div>
                    </div>
                </section>

                <section class="mb-6 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-bold text-slate-900">Transactions by status</h2>
                        <p class="mt-1 text-xs text-slate-500">After current filters.</p>
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
                        <h2 class="text-sm font-bold text-slate-900">Rentals by status</h2>
                        <p class="mt-1 text-xs text-slate-500">Your rentals distribution.</p>
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
                        <h2 class="text-sm font-bold text-slate-900">Rentals by payment status</h2>
                        <p class="mt-1 text-xs text-slate-500">Payment state across rentals.</p>
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

                <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-3 lg:grid-cols-6">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="status">Status</label>
                            <select id="status" v-model="filterState.status" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                                <option v-for="option in statusOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="payment_method">Payment method</label>
                            <select id="payment_method" v-model="filterState.payment_method" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                                <option v-for="option in paymentMethodOptions" :key="option.value || 'all'" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_from">Date from</label>
                            <input id="date_from" v-model="filterState.date_from" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="date_to">Date to</label>
                            <input id="date_to" v-model="filterState.date_to" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="query">Search</label>
                            <input
                                id="query"
                                v-model="filterState.q"
                                type="text"
                                placeholder="Rental ID or provider ID"
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
                                    <th class="px-3 py-3">Date</th>
                                    <th class="px-3 py-3">Transaction</th>
                                    <th class="px-3 py-3">Rental</th>
                                    <th class="px-3 py-3">Amount</th>
                                    <th class="px-3 py-3">Method</th>
                                    <th class="px-3 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in transactionRows"
                                    :key="item.id"
                                    class="border-b border-slate-50 transition-colors hover:bg-slate-50/70"
                                >
                                    <td class="px-3 py-3 text-slate-600">{{ formatDate(item.transaction_date) }}</td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-800">#{{ item.id }}</p>
                                        <p class="text-xs text-slate-500">{{ item.external_provider_id || 'No provider id' }}</p>
                                        <span
                                            v-if="isSyntheticLedgerRef(item.external_provider_id)"
                                            class="mt-1 inline-block rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800"
                                        >
                                            Manual ledger
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">#{{ item.rental_id }}</td>
                                    <td class="px-3 py-3 font-semibold text-slate-900">{{ formatMoney(item.amount, item.currency) }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ item.payment_method || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold capitalize" :class="statusBadgeClass(item.status)">
                                                {{ item.status || 'unknown' }}
                                            </span>
                                            <button
                                                v-if="hasTransactionNotes(item)"
                                                type="button"
                                                class="text-xs font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900"
                                                @click="openTransactionNotes(item)"
                                            >
                                                Notes
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3 lg:hidden">
                        <div v-for="item in transactionRows" :key="`mobile-${item.id}`" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold text-slate-800">Transaction #{{ item.id }}</p>
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusBadgeClass(item.status)">
                                    {{ item.status || 'unknown' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Date: {{ formatDate(item.transaction_date) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Rental: #{{ item.rental_id }}</p>
                            <p class="mt-1 text-xs text-slate-500">Method: {{ item.payment_method || '—' }}</p>
                            <p v-if="item.external_provider_id" class="mt-1 text-xs text-slate-500">Ref: {{ item.external_provider_id }}</p>
                            <span
                                v-if="isSyntheticLedgerRef(item.external_provider_id)"
                                class="mt-1 inline-block rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800"
                            >
                                Manual ledger
                            </span>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatMoney(item.amount, item.currency) }}</p>
                            <button
                                v-if="hasTransactionNotes(item)"
                                type="button"
                                class="mt-2 text-xs font-semibold text-blue-700 underline decoration-blue-200"
                                @click="openTransactionNotes(item)"
                            >
                                Payment notes
                            </button>
                        </div>
                    </div>

                    <div v-if="!transactionRows.length" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-center text-sm text-slate-500">
                        No transactions found for current filters.
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

        <Modal :show="txNotesModal.show" max-width="md" @close="closeTransactionNotes">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ txNotesModal.title }}</h3>
                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ txNotesModal.body }}</p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="closeTransactionNotes"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
