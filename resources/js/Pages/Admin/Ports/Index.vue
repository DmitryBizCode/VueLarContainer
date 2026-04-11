<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    ports: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = useForm({
    q: props.filters.q ?? '',
    country_id: props.filters.country_id ?? '',
});
const applyFilters = () => filterForm.get(route('admin.ports.index'), { preserveState: true });

const formModalOpen = ref(false);
const formMode = ref('create');
const formPortId = ref(null);
const formData = ref(null);
const form = useForm({
    country_id: props.countries?.length ? String(props.countries[0].id) : '',
    name: '',
    city: '',
});

const openCreateModal = () => {
    formMode.value = 'create';
    formPortId.value = null;
    formData.value = null;
    form.reset();
    form.clearErrors();
    form.country_id = props.countries?.length ? String(props.countries[0].id) : '';
    formModalOpen.value = true;
};

const openEditModal = async (p) => {
    formMode.value = 'edit';
    formPortId.value = p.id;
    formModalOpen.value = true;
    try {
        const { data } = await axios.get(route('admin.ports.full', p.id));
        formData.value = data;
        form.country_id = data.country_id ? String(data.country_id) : '';
        form.name = data.name ?? '';
        form.city = data.city ?? '';
        form.clearErrors();
    } finally {}
};

const closeFormModal = () => {
    formModalOpen.value = false;
    formPortId.value = null;
};

const submitForm = () => {
    if (formMode.value === 'create') {
        form.post(route('admin.ports.store'), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    } else if (formPortId.value) {
        form.put(route('admin.ports.update', formPortId.value), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    }
};

const detailPort = ref(null);
const detailLoading = ref(false);
const openDetail = async (p) => {
    detailPort.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.ports.full', p.id));
        detailPort.value = data;
    } finally {
        detailLoading.value = false;
    }
};

const deleteModalOpen = ref(false);
const deleteTarget = ref(null);
const deleteForm = useForm({ password: '' });

const openDeleteModal = (p) => {
    deleteForm.clearErrors();
    deleteForm.reset();
    deleteForm.password = '';
    deleteTarget.value = p;
    deleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    deleteModalOpen.value = false;
    deleteForm.reset();
    deleteForm.password = '';
    deleteTarget.value = null;
};

const confirmDelete = () => {
    if (!deleteTarget.value?.id) return;
    deleteForm.transform((data) => ({ ...data, _method: 'delete' }));
    deleteForm.post(route('admin.ports.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};
</script>

<template>
    <Head title="Admin – Ports" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Ports</h1>
                <div class="inline-flex items-center gap-2">
                    <Link
                        :href="route('admin.ports.archive')"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        Archive
                    </Link>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700" @click="openCreateModal">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add port
                    </button>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2">
                    <input
                        v-model="filterForm.q"
                        type="search"
                        placeholder="Name, city…"
                        autocomplete="off"
                        name="port-search"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterForm.country_id" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All countries</option>
                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">City</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Country</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="p in ports.data"
                                :key="p.id"
                                class="cursor-pointer bg-white hover:bg-slate-50/50"
                                @click="openDetail(p)"
                            >
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ p.name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ p.city }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ p.country_name || '—' }}</td>
                                <td class="px-4 py-2 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click="openEditModal(p)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openDeleteModal(p)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="10" cy="10" r="5.5" stroke="currentColor" stroke-width="1.4" />
                                                <path d="M8.2 8.2 11.8 11.8M11.8 8.2 8.2 11.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            </svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="ports.links && ports.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in ports.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <Modal :show="formModalOpen" max-width="md" @close="closeFormModal">
            <div class="flex max-h-[85vh] flex-col bg-white">
                <header class="shrink-0 border-b border-slate-200 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">{{ formMode === 'create' ? 'Add port' : `Edit port ${formData?.name || formPortId}` }}</h2>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeFormModal">Close</button>
                    </div>
                </header>
                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitForm">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Port details</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Country *</label>
                                    <select v-model="form.country_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                                        <option value="">Select country</option>
                                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <InputError :message="form.errors.country_id" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Name *</label>
                                    <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">City *</label>
                                    <input v-model="form.city" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="form.errors.city" />
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-4">
                        <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="form.processing">
                            {{ formMode === 'create' ? 'Create port' : 'Save changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!detailPort || detailLoading" max-width="md" @close="detailPort = null; detailLoading = false">
            <div class="max-h-[80vh] overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white p-5">
                <div v-if="detailLoading" class="flex flex-col items-center justify-center py-12">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700" />
                    <p class="mt-3 text-sm font-medium text-slate-500">Loading port…</p>
                </div>
                <template v-else-if="detailPort">
                    <header class="mb-4 flex items-center justify-between rounded-xl bg-slate-800 px-4 py-3 text-white shadow-lg">
                        <span class="text-lg font-bold tracking-tight">{{ detailPort.name }}</span>
                        <button type="button" class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-white/90 hover:bg-white/10" @click="detailPort = null">Close</button>
                    </header>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 rounded-lg border border-slate-200 bg-white p-4 text-sm">
                        <dt class="font-medium text-slate-400">City</dt>
                        <dd class="font-medium text-slate-700">{{ detailPort.city }}</dd>
                        <dt class="font-medium text-slate-400">Country</dt>
                        <dd class="font-medium text-slate-700">{{ detailPort.country_name || '—' }}</dd>
                    </dl>
                </template>
            </div>
        </Modal>

        <Modal :show="deleteModalOpen" max-width="sm" @close="closeDeleteModal">
            <form autocomplete="off" @submit.prevent>
                <div class="p-6">
                    <h2 class="text-lg font-bold text-slate-900">Delete port</h2>
                    <p class="mt-2 text-sm text-slate-600">Move port <strong>{{ deleteTarget?.name }}</strong> to archive? You can restore it from the archive later. Confirm with your admin password.</p>
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-slate-500">Admin password</label>
                        <input
                            :key="'delete-pwd-' + (deleteTarget?.id ?? '')"
                            v-model="deleteForm.password"
                            type="password"
                            autocomplete="new-password"
                            name="admin_delete_confirm"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        />
                        <InputError :message="deleteForm.errors.password" />
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeDeleteModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing || !deleteForm.password" @click="confirmDelete">Delete</button>
                    </div>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
