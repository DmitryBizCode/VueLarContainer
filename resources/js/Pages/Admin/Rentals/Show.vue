<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusTag from '@/Components/StatusTag.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    rental: { type: Object, required: true },
});

const confirmDelete = () => { if (confirm('Delete this rental?')) router.delete(route('admin.rentals.destroy', props.rental.id)); };

const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');
</script>

<template>
    <Head title="Admin – Rental" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-slate-900">Rental #{{ rental.id }}</h1>
                <Link :href="route('admin.rentals.index')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to list</Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-amber-100 bg-amber-50/80 p-4 text-sm text-amber-950">
                    <p class="font-semibold">Status and payment are not edited here.</p>
                    <p class="mt-1 text-amber-900/90">
                        Use
                        <Link :href="route('admin.approvals')" class="font-semibold underline decoration-amber-400 underline-offset-2 hover:text-amber-950">Approvals</Link>
                        for workflow status, and
                        <Link :href="route('admin.finance.index')" class="font-semibold underline decoration-amber-400 underline-offset-2 hover:text-amber-950">Finance</Link>
                        for payment.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-slate-900">Details</h2>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div><dt class="text-slate-500">Customer</dt><dd class="font-medium text-slate-900">{{ rental.customer || rental.email }}</dd></div>
                        <div><dt class="text-slate-500">Container</dt><dd class="font-medium text-slate-900">{{ rental.container?.serial_number || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Origin → Destination</dt><dd class="font-medium text-slate-900">{{ rental.origin_port?.name }} ({{ rental.origin_port?.country }}) → {{ rental.destination_port?.name }} ({{ rental.destination_port?.country }})</dd></div>
                        <div><dt class="text-slate-500">Dates</dt><dd class="font-medium text-slate-900">{{ formatDate(rental.start_date) }} – {{ formatDate(rental.end_date) }}</dd></div>
                        <div><dt class="text-slate-500">Price</dt><dd class="font-medium text-slate-900">{{ formatMoney(rental.price) }}</dd></div>
                        <div class="flex flex-wrap items-center gap-2">
                            <dt class="text-slate-500">Rental status</dt>
                            <dd class="font-medium"><StatusTag :status="rental.status" size="sm" /></dd>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <dt class="text-slate-500">Payment</dt>
                            <dd class="font-medium">
                                <StatusTag v-if="rental.payment_status" :status="rental.payment_status" size="sm" />
                                <span v-else class="text-slate-500">—</span>
                            </dd>
                        </div>
                        <div><dt class="text-slate-500">Created</dt><dd class="font-medium text-slate-900">{{ formatDate(rental.created_at) }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-bold text-slate-900">Danger zone</h2>
                    <p class="mt-1 text-sm text-slate-600">Deleting a rental is permanent.</p>
                    <button
                        type="button"
                        class="mt-3 rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                        @click="confirmDelete"
                    >
                        Delete rental
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
