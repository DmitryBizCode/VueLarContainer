<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const formatMoney = (value) => {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(amount);
};

const statusClass = (status) => {
    const normalized = String(status || '').toLowerCase();

    if (['completed', 'delivered', 'closed'].includes(normalized)) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (['active', 'in_progress', 'scheduled'].includes(normalized)) {
        return 'border-blue-200 bg-blue-50 text-blue-700';
    }

    if (['cancelled', 'failed', 'blocked'].includes(normalized)) {
        return 'border-red-200 bg-red-50 text-red-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
};

const notificationClass = (type) => {
    const normalized = String(type || '').toLowerCase();

    if (normalized === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (normalized === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (normalized === 'error') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    return 'border-blue-200 bg-blue-50 text-blue-700';
};

const notificationBadge = (type) => {
    const normalized = String(type || '').toLowerCase();
    if (normalized === 'error') return 'Critical';
    if (normalized === 'warning') return 'Attention';
    if (normalized === 'success') return 'Update';
    return 'Info';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Control center</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">Operations dashboard</h1>
                </div>
                <span class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 sm:inline-flex">
                    Profile readiness {{ props.profileCompletion }}%
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="mb-6 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 p-6 text-white shadow-sm">
                    <div class="grid gap-5 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <p class="text-xs uppercase tracking-[0.16em] text-blue-100/80">Live overview</p>
                            <h2 class="mt-2 text-2xl font-bold">Track rentals, payments and shipment events in one view</h2>
                            <p class="mt-2 max-w-2xl text-sm text-blue-100/85">
                                This workspace keeps critical account and operational data synchronized. Focus on alerts first, then continue with active rentals.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                            <Link :href="route('services')" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-blue-50">
                                Create rental
                            </Link>
                            <Link :href="route('contact')" class="inline-flex items-center justify-center rounded-xl border border-white/35 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                                Contact support
                            </Link>
                        </div>
                    </div>
                </section>

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
                                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :class="item.done ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white/80 text-slate-500'"
                                    >
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
                                <p v-else class="mt-2 text-xs font-medium text-emerald-700">
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
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active rentals</p>
                                <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ props.stats.activeRentals }}</p>
                                <p class="mt-1 text-sm text-slate-500">Current running contracts.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed rentals</p>
                                <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ props.stats.completedRentals }}</p>
                                <p class="mt-1 text-sm text-slate-500">Finished rental cycles.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unread notifications</p>
                                <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ props.stats.unreadNotifications }}</p>
                                <p class="mt-1 text-sm text-slate-500">Need your attention now.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activity logs</p>
                                <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ props.stats.recentActivityCount }}</p>
                                <p class="mt-1 text-sm text-slate-500">Recorded user operations.</p>
                            </div>
                        </div>

                        <div class="grid gap-6 2xl:grid-cols-5">
                            <section class="2xl:col-span-2 space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Notifications</h3>
                                    <p class="mt-1 text-xs font-medium text-slate-500">Critical account and shipment updates</p>
                                    <div class="mt-3 space-y-2">
                                        <div
                                            v-for="note in props.latestNotifications"
                                            :key="`n-${note.id}`"
                                            class="rounded-xl border px-3 py-2.5"
                                            :class="notificationClass(note.type)"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-semibold">{{ note.title }}</p>
                                                <span class="rounded-full border border-current/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                                                    {{ notificationBadge(note.type) }}
                                                </span>
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
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(order.rental_status)">
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
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize" :class="statusClass(order.rental_status)">
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
