<script setup>
import Modal from '@/Components/Modal.vue';
import { Link } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    pendingApprovals: {
        type: Array,
        default: () => [],
    },
    myRecentRequests: {
        type: Array,
        default: () => [],
    },
    processingId: {
        type: [Number, String, null],
        default: null,
    },
});

const emit = defineEmits(['change-status']);

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const formatMoney = (value) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

const statusLabel = (value) =>
    String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const reasonModal = reactive({
    show: false,
    title: '',
    body: '',
});

const statusDetailEligible = (item) => {
    const st = String(item.status || '').toLowerCase();

    return (
        ['draft', 'pending_approval', 'rejected', 'cancelled'].includes(st)
        || Boolean(item.rejection_reason?.trim())
        || Boolean(item.cancellation_reason?.trim())
    );
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
        lines.push('Awaiting operations review.');
    } else if (st === 'draft') {
        lines.push('Draft — submit the rental request when ready.');
    } else if (item.rejection_reason?.trim()) {
        lines.push(`Note: ${item.rejection_reason.trim()}`);
    } else if (item.cancellation_reason?.trim()) {
        lines.push(`Note: ${item.cancellation_reason.trim()}`);
    }
    reasonModal.title = `Request #${item.id} · ${statusLabel(item.status)}`;
    reasonModal.body = lines.join('\n\n');
    reasonModal.show = true;
};

const closeReasonModal = () => {
    reasonModal.show = false;
};
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-6 xl:grid-cols-2">
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">Pending approvals</h3>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        {{ pendingApprovals.length }}
                    </span>
                </div>

                <div v-if="!pendingApprovals.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs text-slate-600">
                    No requests pending approval.
                </div>

                <div v-else class="space-y-2">
                    <div v-for="item in pendingApprovals" :key="`pending-${item.id}`" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">Request #{{ item.id }}</p>
                            <span class="text-xs font-semibold text-slate-600">{{ formatMoney(item.price) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ item.customer || 'Customer' }} · {{ item.container_serial || 'N/A' }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ item.origin || 'N/A' }} -> {{ item.destination || 'N/A' }} · {{ formatDate(item.created_at) }}
                        </p>
                        <div class="mt-2 flex gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                :disabled="processingId === item.id"
                                @click="emit('change-status', { id: item.id, status: 'approved' })"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                                :disabled="processingId === item.id"
                                @click="emit('change-status', { id: item.id, status: 'rejected' })"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">My latest requests</h3>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        {{ myRecentRequests.length }}
                    </span>
                </div>
                <div v-if="!myRecentRequests.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs text-slate-600">
                    No submitted requests yet.
                </div>
                <div v-else class="space-y-2">
                    <div v-for="item in myRecentRequests" :key="`mine-${item.id}`" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">Request #{{ item.id }}</p>
                            <span class="text-xs font-semibold text-slate-600">{{ formatMoney(item.price) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">{{ item.container_serial || 'Container not assigned' }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Equipment:
                            {{ item.container_operational_status ? statusLabel(item.container_operational_status) : '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ formatDate(item.start_date) }} -> {{ formatDate(item.end_date) }}
                        </p>
                        <button
                            v-if="statusDetailEligible(item)"
                            type="button"
                            class="mt-1 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200"
                            @click="openStatusDetail(item)"
                        >
                            Status notes
                        </button>
                        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">
                                {{ statusLabel(item.status) }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">
                                {{ statusLabel(item.payment_status) }}
                            </span>
                            <Link
                                v-if="item.can_view_iot_monitor"
                                :href="route('rentals.monitor', item.id)"
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
    </section>
</template>
