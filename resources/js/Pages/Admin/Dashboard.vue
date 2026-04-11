<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    pendingApprovals: {
        type: Array,
        default: () => [],
    },
});

const formatMoney = (v) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) =>
    v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—';
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">Dashboard</h1>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Back to app
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Rentals (total)</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ Object.values(props.stats.rentalsByStatus || {}).reduce((a, b) => a + Number(b), 0) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600">By status: {{ Object.entries(props.stats.rentalsByStatus || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Containers</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.stats.containersTotal ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-600">By status: {{ Object.entries(props.stats.containersByStatus || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Routes / Ports / Vessels / Owners</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ props.stats.routesCount ?? 0 }} / {{ props.stats.portsCount ?? 0 }} / {{ props.stats.vesselsCount ?? 0 }} / {{ props.stats.ownersCount ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Users</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ props.stats.usersTotal ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-600">By role: {{ Object.entries(props.stats.usersByRole || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '—' }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Finance (paid)</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700">{{ formatMoney(props.stats.paidAmount) }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ props.stats.paidCount ?? 0 }} transactions</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Finance (pending)</p>
                        <p class="mt-1 text-xl font-bold text-amber-700">{{ formatMoney(props.stats.pendingAmount) }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ props.stats.pendingCount ?? 0 }} transactions</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Activity logs</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ props.stats.activityLogsTotal ?? 0 }} total</p>
                        <p class="mt-1 text-xs text-slate-600">{{ props.stats.activityLogsToday ?? 0 }} today</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-slate-500">Page views (request logs)</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ props.stats.requestLogsTotal ?? 0 }} total</p>
                        <p class="mt-1 text-xs text-slate-600">{{ props.stats.requestLogsToday ?? 0 }} today</p>
                        <Link :href="route('admin.request-logs.index')" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:underline">View logs & analytics →</Link>
                    </div>
                </div>

                <!-- Traffic & analytics (request logs, last 30 days) -->
                <section
                    v-if="(props.stats.topCountries && Object.keys(props.stats.topCountries).length) || (props.stats.topDevicesOrdered && props.stats.topDevicesOrdered.length) || (props.stats.topBrowsersOrdered && props.stats.topBrowsersOrdered.length) || (props.stats.popularPathsUser && props.stats.popularPathsUser.length) || (props.stats.popularPathsAdmin && props.stats.popularPathsAdmin.length)"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Traffic &amp; analytics</h2>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    Last 30 days — geography, devices, browsers, popular sections.
                                </p>
                            </div>
                            <Link
                                :href="route('admin.request-logs.index')"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                Full analytics →
                            </Link>
                        </div>
                    </div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-if="props.stats.topCountries && Object.keys(props.stats.topCountries).length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Geography</p>
                            <ul class="mt-3 space-y-2">
                                <li v-for="(cnt, code) in props.stats.topCountries" :key="code" class="flex justify-between text-sm">
                                    <span class="text-slate-700">{{ code }}</span>
                                    <span class="tabular-nums font-medium text-slate-600">{{ cnt }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="props.stats.topBrowsersOrdered && props.stats.topBrowsersOrdered.length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Browsers</p>
                            <ol class="mt-3 space-y-1.5 text-sm text-slate-700">
                                <li v-for="(name, i) in props.stats.topBrowsersOrdered" :key="name">{{ i + 1 }}. {{ name || 'Other' }}</li>
                            </ol>
                        </div>
                        <div v-if="props.stats.topDevicesOrdered && props.stats.topDevicesOrdered.length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Devices</p>
                            <ol class="mt-3 space-y-1.5 text-sm text-slate-700">
                                <li v-for="(type, i) in props.stats.topDevicesOrdered" :key="type">{{ i + 1 }}. {{ type }}</li>
                            </ol>
                        </div>
                        <div class="space-y-4">
                            <div v-if="props.stats.popularPathsUser && props.stats.popularPathsUser.length" class="rounded-lg border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">User panel</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    <li v-for="label in props.stats.popularPathsUser" :key="label" class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" />
                                        {{ label }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="props.stats.popularPathsAdmin && props.stats.popularPathsAdmin.length" class="rounded-lg border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Admin panel</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    <li v-for="label in props.stats.popularPathsAdmin" :key="label" class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" />
                                        {{ label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-900">Pending approvals</h2>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ props.pendingApprovals.length }}
                        </span>
                    </div>
                    <p v-if="!props.pendingApprovals.length" class="text-sm text-slate-600">No requests pending approval.</p>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="item in props.pendingApprovals"
                            :key="item.id"
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2"
                        >
                            <div>
                                <span class="font-semibold text-slate-900">#{{ item.id }}</span>
                                <span class="ml-2 text-sm text-slate-600">{{ item.customer || 'Customer' }} · {{ item.container_serial || 'N/A' }}</span>
                                <span class="ml-2 text-xs text-slate-500">{{ item.origin }} → {{ item.destination }} · {{ formatDate(item.created_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-slate-700">{{ formatMoney(item.price) }}</span>
                                <Link
                                    :href="route('admin.rentals.show', item.id)"
                                    class="rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-700"
                                >
                                    Review
                                </Link>
                            </div>
                        </li>
                    </ul>
                    <p v-if="props.pendingApprovals.length" class="mt-3 text-xs text-slate-500">
                        <Link :href="route('admin.rentals.index', { status: 'pending_approval' })" class="font-semibold text-blue-600 hover:underline">View all rentals</Link> to approve or reject.
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
