<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';

const props = defineProps({
    inquiries: { type: Object, required: true },
    handlingStatusOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ handling_status: '' }) },
});

const filterForm = useForm({
    handling_status: props.filters.handling_status ?? '',
});

const applyFilter = () => {
    filterForm.get(route('admin.inquiries.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const drafts = reactive({});

/** Sync drafts from server only when the page/data actually changes (avoid clobbering edits on re-renders). */
const inquiriesSyncKey = computed(
    () =>
        `${props.inquiries.current_page ?? 1}|${(props.inquiries.data || [])
            .map((r) => `${r.id}:${r.handling_status}:${r.admin_notes ?? ''}:${r.updated_at ?? ''}`)
            .join(';')}`,
);

watch(
    inquiriesSyncKey,
    () => {
        for (const row of props.inquiries.data || []) {
            drafts[row.id] = {
                handling_status: row.handling_status,
                admin_notes: row.admin_notes ?? '',
            };
        }
    },
    { immediate: true },
);

const savingId = reactive({});
const rowErrors = reactive({});
/** Debounced PATCH timers per row (status or notes). */
const autoSaveTimers = {};

const saveRow = (inquiryId) => {
    const payload = drafts[inquiryId];
    if (!payload) {
        return;
    }

    const serverRow = (props.inquiries.data || []).find((r) => r.id === inquiryId);
    if (
        serverRow &&
        serverRow.handling_status === payload.handling_status &&
        String(serverRow.admin_notes ?? '') === String(payload.admin_notes ?? '')
    ) {
        return;
    }

    savingId[inquiryId] = true;
    delete rowErrors[inquiryId];
    router.patch(
        route('admin.inquiries.update', inquiryId),
        {
            handling_status: payload.handling_status,
            admin_notes: payload.admin_notes,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingId[inquiryId] = false;
            },
            onError: (errors) => {
                const messages = Object.values(errors).flat();
                rowErrors[inquiryId] = messages[0] || 'Could not save.';
            },
        },
    );
};

/** Debounce saves while editing status or notes. */
const scheduleAutoSaveRow = (inquiryId) => {
    if (autoSaveTimers[inquiryId]) {
        clearTimeout(autoSaveTimers[inquiryId]);
    }
    autoSaveTimers[inquiryId] = setTimeout(() => {
        autoSaveTimers[inquiryId] = null;
        saveRow(inquiryId);
    }, 450);
};

onBeforeUnmount(() => {
    Object.keys(autoSaveTimers).forEach((key) => {
        if (autoSaveTimers[key]) {
            clearTimeout(autoSaveTimers[key]);
        }
    });
});

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
};

const truncate = (s, n = 48) => {
    const t = String(s || '');
    return t.length <= n ? t : `${t.slice(0, n)}…`;
};

const submitterLabel = (row) => {
    const s = row.submitter;
    if (!s) return '—';
    const name = [s.first_name, s.last_name].filter(Boolean).join(' ').trim();
    return name || s.email || `#${s.id}`;
};
</script>

<template>
    <Head title="Admin – Inquiries" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Admin</p>
                <h1 class="mt-1 text-xl font-bold text-slate-900">Contact inquiries</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Website and other leads — changes to status or internal notes are saved automatically shortly after you edit them.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        id="filter-status"
                        v-model="filterForm.handling_status"
                        class="mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    >
                        <option value="">All</option>
                        <option v-for="opt in handlingStatusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    :disabled="filterForm.processing"
                    @click="applyFilter"
                >
                    Apply
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Received</th>
                                <th class="px-4 py-3">From</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3 min-w-[260px] max-w-md">Message</th>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3 min-w-[200px]">Handling</th>
                                <th class="px-4 py-3 min-w-[220px]">Internal notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in inquiries.data" :key="row.id" class="align-top hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">#{{ row.id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ formatDate(row.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.name }}</div>
                                    <div class="text-xs text-slate-500">{{ row.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ truncate(row.subject, 40) }}</td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="row.message && String(row.message).trim()"
                                        class="max-h-36 max-w-md overflow-y-auto rounded-lg border border-slate-100 bg-slate-50/90 px-2.5 py-2 text-xs leading-relaxed whitespace-pre-wrap break-words text-slate-800"
                                    >
                                        {{ row.message }}
                                    </div>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-4 py-3 capitalize text-slate-600">{{ String(row.source || '').replace('_', ' ') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ submitterLabel(row) }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="drafts[row.id]"
                                        v-model="drafts[row.id].handling_status"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
                                        @change="scheduleAutoSaveRow(row.id)"
                                    >
                                        <option v-for="opt in handlingStatusOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="drafts[row.id]" class="space-y-1">
                                        <textarea
                                            v-model="drafts[row.id].admin_notes"
                                            rows="2"
                                            placeholder="Optional notes for your team…"
                                            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-800 placeholder:text-slate-400"
                                            @input="scheduleAutoSaveRow(row.id)"
                                        />
                                        <p v-if="savingId[row.id]" class="text-xs text-slate-500">Saving…</p>
                                        <p v-if="rowErrors[row.id]" class="text-xs text-rose-600">{{ rowErrors[row.id] }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!inquiries.data?.length">
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">No inquiries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="inquiries.links && inquiries.links.length" class="flex flex-wrap justify-center gap-2 border-t border-slate-100 px-4 py-3">
                    <Link
                        v-for="(link, i) in inquiries.links"
                        :key="i"
                        :href="link.url || '#'"
                        class="rounded-lg border border-slate-200 px-3 py-1 text-sm"
                        :class="{ 'bg-slate-100 font-semibold': link.active }"
                        preserve-state
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
