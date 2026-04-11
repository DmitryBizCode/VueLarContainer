<script setup>
import RequestLogDetailPanel from '@/Components/Admin/RequestLogDetailPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    logs: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    analytics: {
        type: Object,
        default: () => ({
            period: {},
            total_requests: 0,
            unique_sessions: 0,
            authenticated_requests: 0,
            top_countries: {},
            top_devices_ordered: [],
            top_browsers_ordered: [],
            popular_paths_user: [],
            popular_paths_admin: [],
        }),
    },
});

const filterForm = useForm({
    user_id: props.filters.user_id ?? '',
    path: props.filters.path ?? '',
    country_code: props.filters.country_code ?? '',
    device_type: props.filters.device_type ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    sort: props.filters.sort ?? 'created_at',
    order: props.filters.order ?? 'desc',
});

const applyFilters = () => filterForm.get(route('admin.request-logs.index'), { preserveState: true });
const clearFilters = () => {
    filterForm.user_id = '';
    filterForm.path = '';
    filterForm.country_code = '';
    filterForm.device_type = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.sort = 'created_at';
    filterForm.order = 'desc';
    filterForm.get(route('admin.request-logs.index'), { preserveState: true });
};

const formatDate = (v) =>
    v
        ? new Intl.DateTimeFormat('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(v))
        : '—';

const gmtLabel = (min) => {
    if (min == null) return '—';
    const h = Math.floor(Math.abs(min) / 60);
    const m = Math.abs(min) % 60;
    const sign = min >= 0 ? '+' : '-';
    return `UTC${sign}${h}${m ? ':' + String(m).padStart(2, '0') : ''}`;
};

const selectedLog = ref(null);
const detailOpen = ref(false);
const openLogDetail = (log) => {
    selectedLog.value = log;
    detailOpen.value = true;
};
const closeLogDetail = () => {
    detailOpen.value = false;
    selectedLog.value = null;
};

/** Build URL for a page; omit ?page= for page 1 so URL stays /admin/request-logs */
function paginationUrl(page) {
    const query = { ...props.filters };
    if (page > 1) query.page = page;
    return route('admin.request-logs.index', query);
}

/** Go to page without full reload (Inertia soft navigation), and without adding page=1 to URL */
function goToPage(page) {
    if (page < 1 || page > props.logs.last_page) return;
    router.get(paginationUrl(page), {}, { preserveState: true });
}

/** Page numbers to show in pagination, with '...' for gaps */
const paginationPages = computed(() => {
    const current = props.logs.current_page;
    const last = props.logs.last_page;
    if (last <= 1) return [];
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [1];
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);
    for (let p = start; p <= end; p++) {
        if (!pages.includes(p)) pages.push(p);
    }
    if (current < last - 2) pages.push('...');
    if (last > 1 && !pages.includes(last)) pages.push(last);
    return pages;
});
</script>

<template>
    <Head title="Admin – Request logs &amp; analytics" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Request logs &amp; analytics</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Page views, geography, devices and browsers for diagnostics and statistics.</p>
                </div>
                <Link
                    :href="route('admin.dashboard')"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    ← Dashboard
                </Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <!-- Analytics -->
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Analytics</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ analytics.period?.from }} – {{ analytics.period?.to }}
                        </p>
                    </div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="min-h-[5.5rem] rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total requests</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ analytics.total_requests ?? 0 }}</p>
                        </div>
                        <div class="min-h-[5.5rem] rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Unique sessions</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ analytics.unique_sessions ?? 0 }}</p>
                        </div>
                        <div class="min-h-[5.5rem] rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Authenticated</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ analytics.authenticated_requests ?? 0 }}</p>
                        </div>
                        <div class="min-h-[5.5rem] rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Coverage</p>
                            <p class="mt-1 text-sm text-slate-600">All web requests in period</p>
                        </div>
                    </div>
                    <div class="grid gap-4 border-t border-slate-200 bg-slate-50/30 px-6 py-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-if="Object.keys(analytics.top_countries || {}).length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Geography</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li v-for="(cnt, code) in analytics.top_countries" :key="code" class="flex justify-between">
                                    <span class="text-slate-700">{{ code }}</span>
                                    <span class="tabular-nums font-medium text-slate-600">{{ cnt }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="(analytics.top_browsers_ordered || []).length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Browsers</p>
                            <ol class="mt-3 space-y-1.5 text-sm text-slate-700">
                                <li v-for="(name, i) in analytics.top_browsers_ordered" :key="name">{{ i + 1 }}. {{ name || 'Other' }}</li>
                            </ol>
                        </div>
                        <div v-if="(analytics.top_devices_ordered || []).length" class="rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Devices</p>
                            <ol class="mt-3 space-y-1.5 text-sm text-slate-700">
                                <li v-for="(type, i) in analytics.top_devices_ordered" :key="type">{{ i + 1 }}. {{ type }}</li>
                            </ol>
                        </div>
                        <div class="min-h-[11rem] space-y-4">
                            <div v-if="(analytics.popular_paths_user || []).length" class="rounded-lg border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">User panel</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    <li v-for="label in analytics.popular_paths_user" :key="label" class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" />
                                        {{ label }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="(analytics.popular_paths_admin || []).length" class="rounded-lg border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Admin panel</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    <li v-for="label in analytics.popular_paths_admin" :key="label" class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" />
                                        {{ label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Filters -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters</h2>
                        <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-700" @click="clearFilters">Clear all</button>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">User</label>
                            <select
                                v-model="filterForm.user_id"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            >
                                <option value="">All</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Path</label>
                            <input
                                v-model="filterForm.path"
                                type="text"
                                placeholder="e.g. dashboard"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Country</label>
                            <input
                                v-model="filterForm.country_code"
                                type="text"
                                placeholder="e.g. UA"
                                maxlength="10"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Device</label>
                            <select
                                v-model="filterForm.device_type"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            >
                                <option value="">All</option>
                                <option value="desktop_windows">Windows (PC)</option>
                                <option value="desktop_mac">Mac (MacBook / iMac)</option>
                                <option value="desktop_linux">Linux (PC)</option>
                                <option value="desktop">Desktop</option>
                                <option value="mobile">Mobile</option>
                                <option value="tablet">Tablet</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">From</label>
                            <input v-model="filterForm.date_from" type="date" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">To</label>
                            <input v-model="filterForm.date_to" type="date" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Sort</label>
                            <select
                                v-model="filterForm.sort"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            >
                                <option value="created_at">Date</option>
                                <option value="path">Path</option>
                                <option value="country_code">Country</option>
                                <option value="device_type">Device</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Order</label>
                            <select
                                v-model="filterForm.order"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400"
                            >
                                <option value="desc">Desc</option>
                                <option value="asc">Asc</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-slate-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <!-- Logs table -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50/50 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Request logs</h2>
                        <p class="text-xs text-slate-500">Click a row to view details</p>
                    </div>
                    <div class="overflow-x-auto overflow-y-auto max-h-[36rem]">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Time</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Path</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Geo</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">TZ / GMT</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Device / Browser</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Chain</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="cursor-pointer transition hover:bg-slate-50/80"
                                    @click="openLogDetail(log)"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ formatDate(log.created_at) }}</td>
                                    <td class="px-4 py-3">
                                        <template v-if="log.user_id">
                                            <span class="font-medium text-slate-800">{{ log.user_name || log.user_email || '#' + log.user_id }}</span>
                                            <span v-if="log.user_email" class="block text-xs text-slate-500">{{ log.user_email }}</span>
                                        </template>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td class="max-w-[200px] truncate px-4 py-3 font-mono text-sm text-slate-700" :title="log.path">{{ log.method }} {{ log.path }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                        <span v-if="log.country_code">{{ log.country_code }}{{ log.region ? ', ' + log.region : '' }}{{ log.city ? ', ' + log.city : '' }}</span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                        <span v-if="log.timezone" :title="'Offset: ' + log.gmt_offset_minutes + ' min'">{{ log.timezone }} ({{ gmtLabel(log.gmt_offset_minutes) }})</span>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <span v-if="log.device_type_label || log.device_type" class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-xs">{{ log.device_type_label || log.device_type }}</span>
                                        <span v-if="log.browser" class="ml-1 text-xs text-slate-600">{{ log.browser }}{{ log.browser_version ? ' ' + log.browser_version : '' }}</span>
                                        <span v-if="!log.device_type && !log.browser" class="text-slate-400">—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3" @click.stop>
                                        <Link
                                            v-if="log.user_id"
                                            :href="route('admin.request-logs.user-chain', log.user_id)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                                        >
                                            View chain
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                        <span v-else class="text-slate-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="!logs.data?.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-12 text-center">
                    <p class="text-slate-500">No request logs found.</p>
                    <button type="button" class="mt-2 text-sm font-medium text-slate-600 hover:text-slate-800" @click="clearFilters">Clear filters</button>
                </div>

                <div v-if="logs.last_page > 1" class="flex flex-wrap items-center justify-center gap-2 pt-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:pointer-events-none disabled:opacity-50"
                        :disabled="logs.current_page <= 1"
                        @click="goToPage(logs.current_page - 1)"
                    >
                        Previous
                    </button>
                    <span class="flex items-center gap-1">
                        <template v-for="(p, i) in paginationPages" :key="i">
                            <span
                                v-if="p === '...'"
                                class="px-2 py-1 text-sm text-slate-400"
                            >…</span>
                            <button
                                v-else
                                type="button"
                                class="inline-flex min-w-[2.25rem] items-center justify-center rounded-lg border px-2.5 py-1.5 text-sm font-medium transition"
                                :class="p === logs.current_page ? 'border-slate-400 bg-slate-100 text-slate-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="goToPage(p)"
                            >
                                {{ p }}
                            </button>
                        </template>
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:pointer-events-none disabled:opacity-50"
                        :disabled="logs.current_page >= logs.last_page"
                        @click="goToPage(logs.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <RequestLogDetailPanel :show="detailOpen" :log="selectedLog" @close="closeLogDetail" />
    </AuthenticatedLayout>
</template>
