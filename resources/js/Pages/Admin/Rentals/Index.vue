<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import StatusTag from '@/Components/StatusTag.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    rentals: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    paymentStatusOptions: { type: Array, default: () => [] },
});

const detailRental = ref(null);
const detailLoading = ref(false);
const openDetail = async (r) => {
    detailRental.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.rentals.full', r.id));
        detailRental.value = data;
    } finally {
        detailLoading.value = false;
    }
};

const filterForm = useForm({
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    q: props.filters.q ?? '',
});
const applyFilters = () => filterForm.get(route('admin.rentals.index'), { preserveState: true });

const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');
const formatDateTime = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '—');

const yesNo = (v) => (v ? 'Yes' : 'No');

const detailSections = (d) => {
    if (!d) return [];
    return [
        { title: 'Main', entries: [
            ['ID', d.id],
            ['Status', d.status],
            ['Payment status', d.payment_status],
            ['Description', d.description],
        ]},
        { title: 'Dates', entries: [
            ['Start date', formatDate(d.start_date)],
            ['End date', formatDate(d.end_date)],
            ['Actual return date', formatDate(d.actual_return_date)],
            ['Rental days', d.rental_days],
            ['Pickup window', formatDateTime(d.pickup_window_start) + ' – ' + formatDateTime(d.pickup_window_end)],
            ['Quote expires', formatDate(d.quote_expires_at)],
            ['Created', formatDateTime(d.created_at)],
            ['Updated', formatDateTime(d.updated_at)],
        ]},
        { title: 'Cargo', entries: [
            ['Cargo types', Array.isArray(d.cargo_types) ? d.cargo_types.join(', ') : d.cargo_types],
            ['Cargo details', d.cargo_details],
            ['Requested weight', d.requested_weight],
            ['Cargo volume (cbm)', d.cargo_volume_cbm],
            ['Package count', d.package_count],
            ['Cargo value', d.cargo_value],
            ['Priority', d.priority],
        ]},
        { title: 'Contact & address', entries: [
            ['Contact name', d.contact_name],
            ['Contact phone', d.contact_phone],
            ['Pickup address', d.pickup_address],
            ['Delivery address', d.delivery_address],
        ]},
        { title: 'Options', entries: [
            ['Incoterm', d.incoterm],
            ['Loading type', d.loading_type],
            ['Delivery mode', d.delivery_mode],
            ['Insurance required', yesNo(d.insurance_required)],
            ['Requires customs clearance', yesNo(d.requires_customs_clearance)],
            ['Hazardous material', yesNo(d.hazardous_material)],
            ['Requires escort', yesNo(d.requires_escort)],
            ['Seal required', yesNo(d.seal_required)],
            ['UN number', d.un_number],
            ['Dangerous goods class', d.dangerous_goods_class],
            ['Temperature min', d.temperature_min],
            ['Temperature max', d.temperature_max],
            ['Terms accepted', yesNo(d.terms_accepted)],
            ['Special requirements', d.special_requirements],
        ]},
        { title: 'Finance & review', entries: [
            ['Price', formatMoney(d.price)],
            ['Price breakdown', d.price_breakdown ? JSON.stringify(d.price_breakdown, null, 2) : null],
            ['Estimated distance', d.estimated_distance],
            ['Reviewed at', formatDateTime(d.reviewed_at)],
            ['Rejection reason', d.rejection_reason],
            ['Contract PDF', d.contract_pdf],
        ]},
    ].map(s => ({ ...s, entries: s.entries.filter(([, v]) => v != null && v !== '') }));
};
</script>

<template>
    <Head title="Admin – Rentals" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-slate-900">Admin – Rentals</h1>
                <p class="max-w-xl text-xs text-slate-500">
                    Status changes belong on
                    <Link :href="route('admin.approvals')" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Approvals</Link>.
                    Payment updates belong on
                    <Link :href="route('admin.finance.index')" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Finance</Link>.
                </p>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2">
                    <input v-model="filterForm.q" type="search" placeholder="Search ID, customer, container…" autocomplete="off" name="rental-search" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" @keyup.enter="applyFilters" />
                    <select v-model="filterForm.status" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All statuses</option>
                        <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <select v-model="filterForm.payment_status" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All payment statuses</option>
                        <option v-for="s in paymentStatusOptions" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Container</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Route</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Price</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Payment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="r in rentals.data"
                                :key="r.id"
                                class="cursor-pointer bg-white transition-colors hover:bg-slate-50/50"
                                @click="openDetail(r)"
                            >
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ r.id }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.customer || r.email }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.container_serial || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.origin }} → {{ r.destination }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ formatMoney(r.price) }}</td>
                                <td class="px-4 py-2" @click.stop>
                                    <StatusTag :status="r.status" size="sm" />
                                </td>
                                <td class="px-4 py-2" @click.stop>
                                    <StatusTag v-if="r.payment_status" :status="r.payment_status" size="sm" />
                                    <span v-else class="text-sm text-slate-500">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="rentals.links && rentals.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in rentals.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Full detail modal (refined report style) -->
        <Modal :show="!!detailRental || detailLoading" max-width="xl" @close="detailRental = null; detailLoading = false">
            <div class="max-h-[80vh] overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white p-5">
                <div v-if="detailLoading" class="flex flex-col items-center justify-center py-12">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700" />
                    <p class="mt-3 text-sm font-medium text-slate-500">Loading rental…</p>
                </div>
                <template v-else-if="detailRental">
                    <header class="mb-4 flex items-center justify-between rounded-xl bg-slate-800 px-4 py-3 text-white shadow-lg">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold tracking-tight">Rental #{{ detailRental.id }}</span>
                            <StatusTag
                                class="shadow-none"
                                size="sm"
                                :status="detailRental.status"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-white/90 transition hover:bg-white/10" @click="detailRental = null">Close</button>
                        </div>
                    </header>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <section v-for="sec in detailSections(detailRental)" :key="sec.title" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">{{ sec.title }}</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <template v-for="[label, value] in sec.entries" :key="label">
                                    <dt class="shrink-0 font-medium text-slate-400">{{ label }}</dt>
                                    <dd
                                        class="min-w-0 break-all font-medium text-slate-700"
                                        :class="{ 'max-h-20 overflow-y-auto whitespace-pre-wrap rounded bg-slate-50 px-2 py-1 font-mono text-[11px]': label === 'Price breakdown' }"
                                    >
                                        <StatusTag
                                            v-if="label === 'Status' || label === 'Payment status'"
                                            :status="value"
                                        />
                                        <span v-else>{{ value }}</span>
                                    </dd>
                                </template>
                            </dl>
                        </section>
                        <section v-if="detailRental.user" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Client</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">Name</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.user.first_name }} {{ detailRental.user.last_name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Email</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.user.email }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Company</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.user.company_name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Phone</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.user.phone_number }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Address</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.user.address }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Country</dt><dd class="font-medium text-slate-700">{{ detailRental.user.country_name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Role</dt><dd class="font-medium text-slate-700">{{ detailRental.user.role }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Status</dt><dd class="font-medium text-slate-700">{{ detailRental.user.account_status }}</dd>
                            </dl>
                        </section>
                        <section v-if="detailRental.container" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Container</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">Serial</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.container.serial_number }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Type</dt><dd class="font-medium text-slate-700">{{ detailRental.container.type }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Dimensions</dt><dd class="font-medium text-slate-700">{{ detailRental.container.width }}×{{ detailRental.container.length }}×{{ detailRental.container.height }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Owner</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.container.owner_name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Port</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailRental.container.current_port_name }}</dd>
                            </dl>
                        </section>
                        <section v-if="detailRental.route" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Route</h3>
                            <p class="text-xs font-medium leading-relaxed text-slate-700"><span class="text-slate-500">→</span> {{ detailRental.route.origin_name }} <span class="text-slate-400">→</span> {{ detailRental.route.destination_name }} <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ detailRental.route.estimated_days }} d</span></p>
                        </section>
                        <section v-if="detailRental.origin_port || detailRental.destination_port" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Ports</h3>
                            <p class="text-xs font-medium text-slate-700 break-words">{{ detailRental.origin_port?.name }} → {{ detailRental.destination_port?.name }}</p>
                        </section>
                        <section v-if="detailRental.reviewer" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Reviewer</h3>
                            <p class="text-xs font-medium text-slate-700">{{ detailRental.reviewer.name }}</p>
                        </section>
                    </div>
                </template>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
