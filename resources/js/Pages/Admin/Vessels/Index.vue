<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    vessels: { type: Object, required: true },
    ports: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = useForm({
    q: props.filters.q ?? '',
});
const applyFilters = () => filterForm.get(route('admin.vessels.index'), { preserveState: true });

const maxLastInspectionDate = new Date().toISOString().slice(0, 10);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingVessel = ref(null);
const deleteForm = useForm({ password: '' });

const createForm = useForm({
    name: '',
    imo_number: '',
    capacity_teu: '',
    status: 'active',
    last_inspection_date: '',
    current_port_id: '',
});
const editForm = useForm({
    name: '',
    imo_number: '',
    capacity_teu: '',
    status: 'active',
    last_inspection_date: '',
    current_port_id: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.status = 'active';
    createForm.current_port_id = props.ports?.length ? String(props.ports[0].id) : '';
    createForm.last_inspection_date = '';
    showCreateModal.value = true;
};
const openEdit = (v) => {
    editingVessel.value = v;
    editForm.name = v.name ?? '';
    editForm.imo_number = v.imo_number ?? '';
    editForm.capacity_teu = v.capacity_teu ?? '';
    editForm.status = v.status || 'active';
    editForm.last_inspection_date = v.last_inspection_date ?? '';
    editForm.current_port_id = v.current_port_id ? String(v.current_port_id) : '';
    editForm.clearErrors();
    showEditModal.value = true;
};
const openDelete = (v) => {
    editingVessel.value = v;
    deleteForm.reset();
    deleteForm.clearErrors();
    showDeleteModal.value = true;
};

watch(editingVessel, (v) => {
    if (v) {
        editForm.name = v.name ?? '';
        editForm.imo_number = v.imo_number ?? '';
        editForm.capacity_teu = v.capacity_teu ?? '';
        editForm.status = v.status || 'active';
        editForm.last_inspection_date = v.last_inspection_date ?? '';
        editForm.current_port_id = v.current_port_id ? String(v.current_port_id) : '';
    }
});

const submitCreate = () => {
    createForm.post(route('admin.vessels.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreateModal.value = false; createForm.reset(); },
    });
};
const submitEdit = () => {
    if (!editingVessel.value?.id) return;
    editForm.put(route('admin.vessels.update', editingVessel.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; editingVessel.value = null; },
    });
};
const confirmDelete = () => {
    if (!editingVessel.value?.id) return;
    deleteForm.transform((data) => ({ ...data, _method: 'delete' }));
    deleteForm.post(route('admin.vessels.destroy', editingVessel.value.id), {
        preserveScroll: true,
        onSuccess: () => { showDeleteModal.value = false; editingVessel.value = null; },
    });
};
</script>

<template>
    <Head title="Admin – Vessels" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Vessels</h1>
                <div class="inline-flex items-center gap-2">
                    <Link
                        :href="route('admin.vessels.archive')"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        Archive
                    </Link>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700" @click="openCreate">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add vessel
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
                        placeholder="Name, IMO, status…"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                </div>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">IMO</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Capacity TEU</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Current port</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Last inspection</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="v in vessels.data" :key="v.id" class="bg-white hover:bg-slate-50/50">
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ v.name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ v.imo_number }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ v.capacity_teu }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ v.current_port_name || '—' }}</td>
                                <td class="px-4 py-2 text-sm"><span class="rounded-full border border-slate-200 px-2 py-0.5 text-xs">{{ v.status }}</span></td>
                                <td class="px-4 py-2 text-sm text-slate-600">{{ v.last_inspection_date || '—' }}</td>
                                <td class="px-4 py-2 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click.stop="openEdit(v)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click.stop="openDelete(v)"
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
                <div v-if="vessels.links && vessels.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in vessels.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <div class="flex max-h-[85vh] flex-col bg-white">
                <header class="shrink-0 border-b border-slate-200 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Add vessel</h2>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showCreateModal = false">Close</button>
                    </div>
                </header>
                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitCreate">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Vessel details</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Name *</label>
                                    <input v-model="createForm.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="createForm.errors.name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">IMO number *</label>
                                    <input v-model="createForm.imo_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="createForm.errors.imo_number" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Capacity TEU *</label>
                                    <input v-model="createForm.capacity_teu" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="createForm.errors.capacity_teu" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Current port</label>
                                    <select v-model="createForm.current_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                                    </select>
                                    <InputError :message="createForm.errors.current_port_id" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Status *</label>
                                    <input v-model="createForm.status" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="createForm.errors.status" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Last inspection date</label>
                                    <input v-model="createForm.last_inspection_date" type="date" :max="maxLastInspectionDate" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <p class="mt-1 text-xs text-slate-500">Only past dates allowed.</p>
                                    <InputError :message="createForm.errors.last_inspection_date" />
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-4">
                        <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="createForm.processing">Create</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" max-width="md" @close="showEditModal = false; editingVessel = null">
            <div class="flex max-h-[85vh] flex-col bg-white">
                <header class="shrink-0 border-b border-slate-200 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Edit vessel {{ editingVessel?.name || '' }}</h2>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showEditModal = false; editingVessel = null">Close</button>
                    </div>
                </header>
                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitEdit">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Vessel details</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Name *</label>
                                    <input v-model="editForm.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="editForm.errors.name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">IMO number *</label>
                                    <input v-model="editForm.imo_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="editForm.errors.imo_number" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Capacity TEU *</label>
                                    <input v-model="editForm.capacity_teu" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="editForm.errors.capacity_teu" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Current port</label>
                                    <select v-model="editForm.current_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                                    </select>
                                    <InputError :message="editForm.errors.current_port_id" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Status *</label>
                                    <input v-model="editForm.status" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                    <InputError :message="editForm.errors.status" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Last inspection date</label>
                                    <input v-model="editForm.last_inspection_date" type="date" :max="maxLastInspectionDate" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <p class="mt-1 text-xs text-slate-500">Only past dates allowed.</p>
                                    <InputError :message="editForm.errors.last_inspection_date" />
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-4">
                        <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="editForm.processing">Save changes</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" max-width="sm" @close="showDeleteModal = false; editingVessel = null">
            <form autocomplete="off" @submit.prevent>
                <div class="p-6">
                    <h2 class="text-lg font-bold text-slate-900">Move vessel to archive</h2>
                    <p class="mt-2 text-sm text-slate-600">Move vessel <strong>{{ editingVessel?.name }}</strong> to archive? You can restore it from the archive later. Confirm with your admin password.</p>
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-slate-500">Admin password</label>
                        <input
                            :key="'delete-pwd-' + (editingVessel?.id ?? '')"
                            v-model="deleteForm.password"
                            type="password"
                            autocomplete="new-password"
                            name="admin_delete_confirm"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        />
                        <InputError :message="deleteForm.errors.password" />
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showDeleteModal = false; editingVessel = null">Cancel</button>
                        <button type="button" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing || !deleteForm.password" @click="confirmDelete">Delete</button>
                    </div>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
