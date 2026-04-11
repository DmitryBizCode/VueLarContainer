<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    routes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = useForm({
    q: props.filters.q ?? '',
});
const applyFilters = () => filterForm.get(route('admin.routes.archive'), { preserveState: true });

const formatDate = (v) =>
    v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '—';

const restoreRoute = (id) => {
    router.post(route('admin.routes.restore', id), {}, { preserveScroll: true });
};

const forceDeleteModalOpen = ref(false);
const forceDeleteTarget = ref(null);
const forceDeleteForm = useForm({ password: '' });

const openForceDeleteModal = (r) => {
    forceDeleteForm.reset();
    forceDeleteForm.clearErrors();
    forceDeleteTarget.value = r;
    forceDeleteModalOpen.value = true;
};

const closeForceDeleteModal = () => {
    forceDeleteModalOpen.value = false;
    forceDeleteTarget.value = null;
    forceDeleteForm.reset();
};

const confirmForceDelete = () => {
    if (!forceDeleteTarget.value?.id) return;
    forceDeleteForm.post(route('admin.routes.force-destroy', forceDeleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => closeForceDeleteModal(),
    });
};
</script>

<template>
    <Head title="Admin – Route archive" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Route archive</h1>
                <Link
                    :href="route('admin.routes.index')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to routes
                </Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-slate-600">Routes that were deleted. You can restore them or remove permanently.</p>
                <div class="mb-4 flex flex-wrap gap-2">
                    <input
                        v-model="filterForm.q"
                        type="search"
                        placeholder="Search by origin or destination…"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">
                        Filter
                    </button>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Origin</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Destination</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Days</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Distance</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Deleted at</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="r in routes.data" :key="r.id" class="bg-white hover:bg-slate-50/50">
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ r.origin_name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.destination_name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.estimated_days }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ r.distance }}</td>
                                <td class="px-4 py-2 text-sm"><span class="rounded-full border border-slate-200 px-2 py-0.5 text-xs">{{ r.route_status }}</span></td>
                                <td class="px-4 py-2 text-sm text-slate-600">{{ formatDate(r.deleted_at) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 shadow-sm ring-1 ring-emerald-200 transition hover:bg-emerald-100"
                                            @click="restoreRoute(r.id)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Restore
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openForceDeleteModal(r)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="10" cy="10" r="5.5" stroke="currentColor" stroke-width="1.4" />
                                                <path d="M8.2 8.2 11.8 11.8M11.8 8.2 8.2 11.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            </svg>
                                            Delete permanently
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!routes.data?.length" class="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-500">
                    No archived routes.
                </div>
                <div v-if="routes.links && routes.links.length" class="mt-4 flex justify-center gap-2">
                    <Link
                        v-for="(link, i) in routes.links"
                        :key="i"
                        :href="link.url || '#'"
                        class="rounded-lg border border-slate-200 px-3 py-1 text-sm"
                        :class="{ 'bg-slate-100 font-semibold': link.active }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <Modal :show="forceDeleteModalOpen" @close="closeForceDeleteModal">
            <div class="rounded-xl bg-white p-6 shadow-lg">
                <h3 class="text-lg font-bold text-slate-900">Permanently delete route?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Route <strong>{{ forceDeleteTarget?.origin_name }} → {{ forceDeleteTarget?.destination_name }}</strong> will be removed forever. This cannot be undone.
                </p>
                <p class="mt-2 text-sm text-slate-600">Enter your password to confirm.</p>
                <form class="mt-4 space-y-3" @submit.prevent="confirmForceDelete">
                    <div>
                        <input
                            v-model="forceDeleteForm.password"
                            type="password"
                            placeholder="Password"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            required
                        />
                        <InputError :message="forceDeleteForm.errors.password" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeForceDeleteModal">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                            :disabled="forceDeleteForm.processing"
                        >
                            Delete permanently
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
