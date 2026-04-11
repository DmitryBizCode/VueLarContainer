<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    log: { type: Object, required: true },
});

const formatDate = (v) =>
    v
        ? new Intl.DateTimeFormat('en-GB', {
              weekday: 'long',
              day: '2-digit',
              month: 'long',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
          }).format(new Date(v))
        : '—';

const formatValue = (v) => {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'boolean') return v ? 'Yes' : 'No';
    if (typeof v === 'object') return JSON.stringify(v, null, 2);
    try {
        const d = new Date(v);
        if (!isNaN(d.getTime()) && typeof v === 'string') return formatDate(v);
    } catch (_) {}
    return String(v);
};

const actionBadge = (action) => {
    const a = String(action || '').toLowerCase();
    if (a === 'logged_in') return { label: 'Logged in', bg: 'bg-indigo-50 text-indigo-700' };
    if (a === 'logged_out') return { label: 'Logged out', bg: 'bg-slate-100 text-slate-700' };
    if (a === 'registered') return { label: 'Registered', bg: 'bg-teal-50 text-teal-700' };
    if (a === 'account_deleted') return { label: 'Account deleted', bg: 'bg-rose-50 text-rose-700' };
    if (a.includes('profile_updated')) return { label: 'Profile updated', bg: 'bg-blue-50 text-blue-700' };
    if (a.includes('create') || a.includes('store')) return { label: 'Created', bg: 'bg-emerald-50 text-emerald-700' };
    if (a.includes('update') || a.includes('edit')) return { label: 'Updated', bg: 'bg-blue-50 text-blue-700' };
    if (a.includes('delete') || a.includes('destroy')) return { label: 'Deleted', bg: 'bg-rose-50 text-rose-700' };
    if (a.includes('restore')) return { label: 'Restored', bg: 'bg-amber-50 text-amber-700' };
    return { label: action || '—', bg: 'bg-slate-100 text-slate-700' };
};

const diffRows = (log) => {
    const old = log?.old_values || {};
    const newV = log?.new_values || {};
    const keys = [...new Set([...Object.keys(old), ...Object.keys(newV)])].sort();
    return keys.map((key) => {
        const o = old[key];
        const n = newV[key];
        const changed = JSON.stringify(o) !== JSON.stringify(n);
        const added = !(key in old) && key in newV;
        const removed = key in old && !(key in newV);
        return { key, old: o, new: n, changed, added, removed };
    });
};

const humanKey = (k) =>
    k
        .replace(/_/g, ' ')
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, (s) => s.toUpperCase())
        .trim();
</script>

<template>
    <Head :title="`Admin – Activity log #${log.id}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.activity-logs.index')"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        ← Back
                    </Link>
                    <h1 class="text-xl font-bold text-slate-900">Activity log #{{ log.id }}</h1>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Meta -->
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="actionBadge(log.action).bg">{{ actionBadge(log.action).label }}</span>
                        <span class="text-sm font-medium text-slate-800">{{ log.model_name }} #{{ log.model_id }}</span>
                        <span class="text-sm text-slate-500">{{ log.action }}</span>
                    </div>
                    <div class="text-right text-sm text-slate-600">
                        {{ log.user_name }} · {{ log.user_email }} · {{ formatDate(log.created_at) }}
                    </div>
                </div>

                <!-- Connection & context -->
                <div v-if="log.ip_address || log.user_agent || log.description || log.request_path || log.country_code || log.timezone || log.browser || log.device_type" class="mb-6 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Connection &amp; context</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div v-if="log.description">
                            <dt class="font-semibold text-slate-600">Description</dt>
                            <dd class="mt-0.5 text-slate-700">{{ log.description }}</dd>
                        </div>
                        <div v-if="log.request_path">
                            <dt class="font-semibold text-slate-600">Page path</dt>
                            <dd class="mt-0.5 font-mono text-slate-700">{{ log.request_path }}</dd>
                        </div>
                        <div v-if="log.ip_address">
                            <dt class="font-semibold text-slate-600">IP address</dt>
                            <dd class="mt-0.5 font-mono text-slate-700">{{ log.ip_address }}</dd>
                        </div>
                        <div v-if="log.country_code">
                            <dt class="font-semibold text-slate-600">Geo</dt>
                            <dd class="mt-0.5 text-slate-700">{{ log.country_code }}</dd>
                        </div>
                        <div v-if="log.timezone">
                            <dt class="font-semibold text-slate-600">Timezone</dt>
                            <dd class="mt-0.5 text-slate-700">{{ log.timezone }}{{ log.gmt_offset_minutes != null ? ' (UTC' + (log.gmt_offset_minutes >= 0 ? '+' : '') + (log.gmt_offset_minutes / 60) + ')' : '' }}</dd>
                        </div>
                        <div v-if="log.browser">
                            <dt class="font-semibold text-slate-600">Browser</dt>
                            <dd class="mt-0.5 text-slate-700">{{ log.browser }}</dd>
                        </div>
                        <div v-if="log.device_type_label || log.device_type">
                            <dt class="font-semibold text-slate-600">Device</dt>
                            <dd class="mt-0.5 text-slate-700">{{ log.device_type_label || log.device_type }}</dd>
                        </div>
                        <div v-if="log.user_agent" class="sm:col-span-2">
                            <dt class="font-semibold text-slate-600">User agent</dt>
                            <dd class="mt-0.5 max-h-24 overflow-auto break-all font-mono text-xs text-slate-600">{{ log.user_agent }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Changes -->
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-slate-600">Field</th>
                                <th class="w-2/5 px-4 py-2.5 text-left text-xs font-medium text-slate-600">Before</th>
                                <th class="w-2/5 px-4 py-2.5 text-left text-xs font-medium text-slate-600">After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="row in diffRows(log)" :key="row.key">
                                <td class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-700">
                                    {{ humanKey(row.key) }}
                                    <span v-if="row.added" class="ml-1 text-[10px] text-slate-400">(new)</span>
                                    <span v-else-if="row.removed" class="ml-1 text-[10px] text-slate-400">(removed)</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="max-h-32 overflow-auto rounded border border-slate-100 bg-slate-50/50 px-2.5 py-2 font-mono text-xs text-slate-700">{{ formatValue(row.old) }}</div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="max-h-32 overflow-auto rounded border border-slate-100 bg-slate-50/50 px-2.5 py-2 font-mono text-xs text-slate-700">{{ formatValue(row.new) }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!diffRows(log).length" class="py-10 text-center text-sm text-slate-500">No change details.</p>
                </div>

                <!-- Raw JSON -->
                <details class="mt-6 rounded-lg border border-slate-200">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50">Raw JSON</summary>
                    <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2">
                        <pre class="max-h-48 overflow-auto rounded border border-slate-200 bg-slate-50 p-3 font-mono text-[11px] text-slate-600">{{ JSON.stringify(log.old_values, null, 2) || '—' }}</pre>
                        <pre class="max-h-48 overflow-auto rounded border border-slate-200 bg-slate-50 p-3 font-mono text-[11px] text-slate-600">{{ JSON.stringify(log.new_values, null, 2) || '—' }}</pre>
                    </div>
                </details>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
