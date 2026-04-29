<script setup>
import Modal from '@/Components/Modal.vue';
import RentalStatusPaymentForm from '@/Components/Admin/RentalStatusPaymentForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rentals: { type: Object, required: true },
    activeTab: { type: String, default: 'pending' },
    statusOptions: { type: Array, default: () => [] },
});

const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');
const formatDateTime = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '—');
const actionLabel = (a) => String(a || '').replace(/status_changed_to_/g, '').replace(/submitted_for_approval/g, 'Submitted').replace(/_/g, ' ');

const statusBadge = (status) => {
    if (status === 'approved') return { label: 'Approved', class: 'bg-emerald-100 text-emerald-700' };
    if (status === 'rejected') return { label: 'Rejected', class: 'bg-rose-100 text-rose-700' };
    return { label: 'Pending', class: 'bg-amber-100 text-amber-700' };
};

const summary = (r) => {
    const parts = [];
    if (r.container?.serial_number) parts.push(r.container.serial_number);
    if (r.origin_port?.name) parts.push(r.origin_port.name);
    if (r.destination_port?.name) parts.push('→', r.destination_port.name);
    if (r.price) parts.push(formatMoney(r.price));
    return parts.filter(Boolean).join(' · ') || '—';
};

const emptyMessage = () => {
    if (props.activeTab === 'pending') return 'No requests pending approval.';
    if (props.activeTab === 'approved') return 'No approved requests.';
    return 'No rejected requests.';
};

const selectedRental = ref(null);
const openModal = (r) => { selectedRental.value = r; };
const closeModal = () => { selectedRental.value = null; };

const returningToPending = ref(false);
const returnToPending = () => {
    if (!selectedRental.value) return;
    returningToPending.value = true;
    router.patch(route('admin.rentals.status', selectedRental.value.id), { status: 'pending_approval' }, {
        preserveScroll: true,
        onFinish: () => { returningToPending.value = false; },
        onSuccess: () => { closeModal(); },
    });
};
</script>

<template>
    <Head title="Admin – Approvals" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-xl font-semibold text-slate-900">Approvals</h1>
                <nav class="flex gap-1 rounded-lg border border-slate-200 bg-white p-0.5">
                    <Link
                        :href="route('admin.approvals', { tab: 'pending' })"
                        class="rounded-md px-3 py-2 text-sm font-medium"
                        :class="activeTab === 'pending' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        New
                    </Link>
                    <Link
                        :href="route('admin.approvals', { tab: 'approved' })"
                        class="rounded-md px-3 py-2 text-sm font-medium"
                        :class="activeTab === 'approved' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        Approved
                    </Link>
                    <Link
                        :href="route('admin.approvals', { tab: 'rejected' })"
                        class="rounded-md px-3 py-2 text-sm font-medium"
                        :class="activeTab === 'rejected' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        Rejected
                    </Link>
                </nav>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div v-if="!rentals.data?.length" class="rounded-lg border border-slate-200 bg-slate-50/50 py-12 text-center text-sm text-slate-500">
                    {{ emptyMessage() }}
                    <Link :href="route('admin.rentals.index')" class="ml-2 font-medium text-slate-700 hover:underline">View all rentals</Link>
                </div>

                <ul v-else class="space-y-1">
                    <li
                        v-for="r in rentals.data"
                        :key="r.id"
                        class="group flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300 hover:bg-slate-50/50"
                        @click="openModal(r)"
                    >
                        <span class="font-mono text-xs text-slate-400">#{{ r.id }}</span>
                        <span
                            class="shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                            :class="statusBadge(r.status).class"
                        >
                            {{ statusBadge(r.status).label }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ summary(r) }}</span>
                        <span class="text-xs text-slate-400">{{ formatDate(r.created_at) }}</span>
                        <span class="text-slate-300 transition group-hover:text-slate-500">›</span>
                    </li>
                </ul>

                <nav v-if="rentals.links?.length" class="mt-4 flex justify-center gap-1">
                    <Link
                        v-for="(link, i) in rentals.links"
                        :key="i"
                        :href="link.url || '#'"
                        class="min-w-[2rem] rounded border px-2 py-1.5 text-center text-sm"
                        :class="link.active ? 'border-slate-300 bg-slate-100 text-slate-900' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>

        <Modal :show="!!selectedRental" max-width="lg" @close="closeModal">
            <div v-if="selectedRental" class="max-h-[85vh] overflow-y-auto">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                    <span class="font-mono text-sm font-semibold text-slate-900">#{{ selectedRental.id }}</span>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-medium"
                        :class="statusBadge(selectedRental.status).class"
                    >
                        {{ statusBadge(selectedRental.status).label }}
                    </span>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal">×</button>
                </div>

                <div class="space-y-4 p-5">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <template v-if="selectedRental.container">
                            <dt class="text-slate-500">Container</dt>
                            <dd>{{ selectedRental.container.serial_number }} ({{ selectedRental.container.type || '—' }})</dd>
                        </template>
                        <template v-if="selectedRental.origin_port">
                            <dt class="text-slate-500">Origin</dt>
                            <dd>{{ selectedRental.origin_port.name }}{{ selectedRental.origin_port.country ? `, ${selectedRental.origin_port.country}` : '' }}</dd>
                        </template>
                        <template v-if="selectedRental.destination_port">
                            <dt class="text-slate-500">Destination</dt>
                            <dd>{{ selectedRental.destination_port.name }}{{ selectedRental.destination_port.country ? `, ${selectedRental.destination_port.country}` : '' }}</dd>
                        </template>
                        <dt class="text-slate-500">Dates</dt>
                        <dd>{{ formatDate(selectedRental.start_date) }} – {{ formatDate(selectedRental.end_date) }}</dd>
                        <dt class="text-slate-500">Price</dt>
                        <dd class="font-medium">{{ formatMoney(selectedRental.price) }}</dd>
                    </dl>

                    <div class="border-t border-slate-100 pt-3">
                        <p class="text-xs font-medium text-slate-500">Client</p>
                        <p class="mt-0.5 text-sm text-slate-700">
                            {{ [selectedRental.client?.first_name, selectedRental.client?.last_name].filter(Boolean).join(' ') || '—' }}
                            <span v-if="selectedRental.client?.email" class="text-slate-500"> · {{ selectedRental.client.email }}</span>
                        </p>
                    </div>

                    <div v-if="selectedRental.status === 'rejected' && selectedRental.rejection_reason" class="rounded border border-rose-200 bg-rose-50/50 p-3">
                        <p class="text-xs font-medium text-rose-700">Rejection reason</p>
                        <p class="mt-1 text-sm text-rose-900">{{ selectedRental.rejection_reason }}</p>
                    </div>

                    <div v-if="selectedRental.status === 'cancelled' && selectedRental.cancellation_reason" class="rounded border border-amber-200 bg-amber-50/50 p-3">
                        <p class="text-xs font-medium text-amber-800">Cancellation reason</p>
                        <p class="mt-1 text-sm text-amber-950">{{ selectedRental.cancellation_reason }}</p>
                    </div>

                    <div v-if="selectedRental.approval_log?.length" class="border-t border-slate-100 pt-3">
                        <p class="text-xs font-medium text-slate-500">Status history</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            <li
                                v-for="(entry, i) in selectedRental.approval_log"
                                :key="i"
                                class="flex gap-2"
                            >
                                <span class="tabular-nums text-slate-500">{{ formatDateTime(entry.created_at) }}</span>
                                <span class="text-slate-400">·</span>
                                <span class="text-slate-700">{{ actionLabel(entry.action) }}</span>
                                <span class="text-slate-500">by {{ entry.user_name }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="selectedRental.status === 'pending_approval'" class="border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-medium text-slate-500">Decision</p>
                        <RentalStatusPaymentForm
                            :rental-id="selectedRental.id"
                            current-status="pending_approval"
                            :status-options="statusOptions"
                            hide-payment-status
                        />
                    </div>

                    <div v-if="['approved', 'rejected'].includes(selectedRental.status)" class="border-t border-slate-100 pt-4">
                        <p class="mb-2 text-xs font-medium text-slate-500">Change status</p>
                        <p class="mb-3 text-sm text-slate-600">Return this request to the approval queue to approve or reject again.</p>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="returningToPending"
                            @click="returnToPending"
                        >
                            {{ returningToPending ? 'Returning…' : 'Return to pending' }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
