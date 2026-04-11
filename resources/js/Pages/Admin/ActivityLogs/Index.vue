<script setup>
import ActivityLogDetailModal from '@/Components/Admin/ActivityLogDetailModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    logs: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    modelNames: { type: Array, default: () => [] },
});

const filterForm = useForm({
    model_name: props.filters.model_name ?? '',
    user_id: props.filters.user_id ?? '',
    action: props.filters.action ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    sort_by: props.filters.sort_by ?? 'created_at',
    sort_order: props.filters.sort_order ?? 'desc',
});

const applyFilters = () => filterForm.get(route('admin.activity-logs.index'), { preserveState: true });
const clearFilters = () => {
    filterForm.model_name = '';
    filterForm.user_id = '';
    filterForm.action = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.sort_by = 'created_at';
    filterForm.sort_order = 'desc';
    filterForm.get(route('admin.activity-logs.index'), { preserveState: true });
};

const formatDate = (v) =>
    v
        ? new Intl.DateTimeFormat('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
          }).format(new Date(v))
        : '—';

const relativeTime = (v) => {
    if (!v) return '—';
    const d = new Date(v);
    const now = new Date();
    const diffMs = now - d;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);
    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHr < 24) return `${diffHr}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return formatDate(v);
};

const changePreview = (log) => {
    const old = log?.old_values || {};
    const newV = log?.new_values || {};
    const keys = [...new Set([...Object.keys(old), ...Object.keys(newV)])].filter((k) => JSON.stringify(old[k]) !== JSON.stringify(newV[k]));
    return keys.slice(0, 3).map((k) => k.replace(/_/g, ' '));
};

const actionBadge = (action) => {
    const a = String(action || '').toLowerCase();
    if (a === 'logged_in') return { label: 'Logged in', bg: 'bg-indigo-100 text-indigo-800', border: 'border-indigo-200' };
    if (a === 'logged_out') return { label: 'Logged out', bg: 'bg-slate-100 text-slate-800', border: 'border-slate-200' };
    if (a === 'registered') return { label: 'Registered', bg: 'bg-teal-100 text-teal-800', border: 'border-teal-200' };
    if (a === 'account_deleted') return { label: 'Account deleted', bg: 'bg-rose-100 text-rose-800', border: 'border-rose-200' };
    if (a.includes('profile_updated')) return { label: 'Profile updated', bg: 'bg-blue-100 text-blue-800', border: 'border-blue-200' };
    if (a.includes('create') || a.includes('store')) return { label: 'Created', bg: 'bg-emerald-100 text-emerald-800', border: 'border-emerald-200' };
    if (a.includes('update') || a.includes('edit')) return { label: 'Updated', bg: 'bg-blue-100 text-blue-800', border: 'border-blue-200' };
    if (a.includes('delete') || a.includes('destroy')) return { label: 'Deleted', bg: 'bg-rose-100 text-rose-800', border: 'border-rose-200' };
    if (a.includes('restore')) return { label: 'Restored', bg: 'bg-amber-100 text-amber-800', border: 'border-amber-200' };
    return { label: action || '—', bg: 'bg-slate-100 text-slate-800', border: 'border-slate-200' };
};

const detailLog = ref(null);
const showDetailModal = ref(false);
const openDetail = (log) => {
    detailLog.value = log;
    showDetailModal.value = true;
};
</script>

<template>
    <Head title="Admin – Activity logs" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Activity logs</h1>
                <p class="text-sm text-slate-500">
                    <span class="font-medium text-slate-700">{{ logs.total ?? logs.data?.length }}</span> entries
                </p>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Filters</h2>
                        <button
                            type="button"
                            class="text-xs font-medium text-slate-500 hover:text-slate-700"
                            @click="clearFilters"
                        >
                            Clear all
                        </button>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Model</label>
                            <select
                                v-model="filterForm.model_name"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="">All models</option>
                                <option v-for="m in modelNames" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">User</label>
                            <select
                                v-model="filterForm.user_id"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="">All users</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Action</label>
                            <input
                                v-model="filterForm.action"
                                type="text"
                                placeholder="e.g. update"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">From date</label>
                            <input
                                v-model="filterForm.date_from"
                                type="date"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">To date</label>
                            <input
                                v-model="filterForm.date_to"
                                type="date"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Sort by</label>
                            <select
                                v-model="filterForm.sort_by"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="created_at">Date</option>
                                <option value="user_id">User</option>
                                <option value="action">Action</option>
                                <option value="model_name">Model</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">Order</label>
                            <select
                                v-model="filterForm.sort_order"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="desc">Descending</option>
                                <option value="asc">Ascending</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <!-- Block chain -->
                <div class="space-y-2">
                    <button
                        v-for="log in logs.data"
                        :key="log.id"
                        type="button"
                        class="group w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300 hover:bg-slate-50/50 focus:outline-none focus:ring-1 focus:ring-slate-300"
                        @click="openDetail(log)"
                    >
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-mono text-[10px] text-slate-400">#{{ log.id }}</span>
                            <span
                                class="rounded px-2 py-0.5 text-[11px] font-medium"
                                :class="[actionBadge(log.action).bg, actionBadge(log.action).border]"
                            >
                                {{ actionBadge(log.action).label }}
                            </span>
                            <span class="text-sm font-medium text-slate-800">{{ log.model_name }} #{{ log.model_id }}</span>
                            <span class="text-xs text-slate-500">{{ log.user_name }} · {{ log.user_email }}</span>
                            <span class="text-[11px] text-slate-400">{{ relativeTime(log.created_at) }}</span>
                            <span v-if="log.country_code" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600" :title="'Geo'">{{ log.country_code }}</span>
                            <span v-if="log.device_type_label || log.device_type" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">{{ log.device_type_label || log.device_type }}</span>
                            <span v-if="log.browser" class="text-[10px] text-slate-500">{{ log.browser }}</span>
                            <span v-if="log.request_path" class="max-w-[120px] truncate text-[10px] text-slate-400" :title="log.request_path">{{ log.request_path }}</span>
                            <span
                                v-if="changePreview(log).length"
                                class="text-[10px] text-slate-400"
                            >
                                {{ changePreview(log).join(', ') }}
                            </span>
                            <span class="ml-auto shrink-0 text-slate-300 group-hover:text-slate-500">›</span>
                        </div>
                    </button>
                </div>

                <!-- Empty -->
                <div
                    v-if="!logs.data?.length"
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 py-16 text-center"
                >
                    <p class="text-slate-500">No activity logs found.</p>
                    <button
                        type="button"
                        class="mt-3 text-sm font-medium text-blue-600 hover:underline"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length" class="mt-6 flex flex-wrap justify-center gap-2">
                    <template v-for="(link, i) in logs.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex min-w-[2.25rem] items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium transition"
                            :class="link.active ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="inline-flex min-w-[2.25rem] items-center justify-center rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <ActivityLogDetailModal :show="showDetailModal" :log="detailLog" @close="showDetailModal = false" />
    </AuthenticatedLayout>
</template>
