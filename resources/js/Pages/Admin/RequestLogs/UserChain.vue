<script setup>
import UserChainChart from '@/Components/Admin/UserChainChart.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
    chain: { type: Array, default: () => [] },
    chain_daily: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const applyFilters = () =>
    filterForm.get(route('admin.request-logs.user-chain', props.user.id), { preserveState: true });

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

const gmtLabel = (min) => {
    if (min == null) return '';
    const h = Math.floor(Math.abs(min) / 60);
    const m = Math.abs(min) % 60;
    const sign = min >= 0 ? '+' : '-';
    return `UTC${sign}${h}${m ? ':' + String(m).padStart(2, '0') : ''}`;
};
</script>

<template>
    <Head :title="`Chain: ${user.name || user.email}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">User chain</h1>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Timeline for <strong>{{ user.name || user.email }}</strong> — page views and activity logs.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.request-logs.index')"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        ← Request logs
                    </Link>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-10 px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50/50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filter by date</p>
                    </div>
                    <form class="flex flex-wrap items-end gap-4 p-4" @submit.prevent="applyFilters">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">From</label>
                            <input v-model="filterForm.date_from" type="date" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">To</label>
                            <input v-model="filterForm.date_to" type="date" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-slate-400 focus:ring-1 focus:ring-slate-400" />
                        </div>
                        <button type="submit" class="rounded-lg border border-slate-300 bg-slate-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Apply
                        </button>
                    </form>
                </div>

                <!-- Chart -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
                    <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Activity overview</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Page views and activities per day</p>
                    </div>
                    <div class="p-5">
                        <UserChainChart
                            :chain="chain"
                            :chain-daily="chain_daily"
                            :date-from="filters.date_from ?? ''"
                            :date-to="filters.date_to ?? ''"
                        />
                    </div>
                </div>

                <!-- Timeline -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow">
                    <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-4">
                        <h2 class="text-base font-semibold text-slate-900">Timeline</h2>
                        <p class="text-xs text-slate-500">{{ chain.length }} entries</p>
                    </div>
                    <div class="relative p-6">
                        <div class="absolute left-6 top-0 bottom-0 w-px bg-slate-200/80 sm:left-8" aria-hidden="true" />
                        <ul class="space-y-0">
                            <li
                                v-for="(item, index) in chain"
                                :key="item.id"
                                class="relative flex gap-5 pb-8 last:pb-0"
                            >
                                <div
                                    class="absolute left-6 mt-3 h-3 w-3 shrink-0 rounded-full ring-2 ring-white shadow-sm sm:left-8"
                                    :class="item.type === 'activity' ? 'bg-amber-500' : 'bg-slate-500'"
                                    aria-hidden="true"
                                />
                                <div
                                    class="ml-10 flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-slate-300 hover:shadow"
                                    :class="item.type === 'activity' ? 'bg-amber-50/50' : 'bg-slate-50/50'"
                                >
                                    <div class="flex flex-wrap items-start gap-x-3 gap-y-2 p-4">
                                        <span class="shrink-0 font-mono text-[11px] text-slate-400">{{ formatDate(item.created_at) }}</span>
                                        <span
                                            v-if="item.type === 'activity'"
                                            class="shrink-0 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800"
                                        >
                                            Activity
                                        </span>
                                        <span v-else class="shrink-0 rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                            Page
                                        </span>

                                        <template v-if="item.type === 'request'">
                                            <span class="font-mono text-sm font-medium text-slate-800">{{ item.method }} {{ item.path }}</span>
                                            <span v-if="item.referer" class="hidden truncate text-xs text-slate-500 sm:inline max-w-[200px]" :title="item.referer">← {{ item.referer }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="font-medium text-slate-800">{{ item.action }}</span>
                                            <span v-if="item.model_name" class="text-slate-600">{{ item.model_name }} #{{ item.model_id }}</span>
                                            <span v-if="item.description" class="text-slate-500">{{ item.description }}</span>
                                            <span v-if="item.request_path" class="text-xs text-slate-400">path: {{ item.request_path }}</span>
                                        </template>

                                        <div class="ml-auto flex shrink-0 flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                            <span v-if="item.country_code" class="rounded border border-slate-200 bg-white px-1.5 py-0.5">{{ item.country_code }}{{ item.region ? ', ' + item.region : '' }}</span>
                                            <span v-if="item.timezone" class="rounded border border-slate-200 bg-white px-1.5 py-0.5">{{ item.timezone }} {{ gmtLabel(item.gmt_offset_minutes) }}</span>
                                            <span v-if="item.device_type_label || item.device_type" class="rounded border border-slate-200 bg-white px-1.5 py-0.5">{{ item.device_type_label || item.device_type }}</span>
                                            <span v-if="item.browser" class="rounded border border-slate-200 bg-white px-1.5 py-0.5">{{ item.browser }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="!chain.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-12 text-center">
                    <p class="text-slate-500">No entries in this period.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
