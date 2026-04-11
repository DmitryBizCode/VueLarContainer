<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ routes: { type: Object, required: true }, ports: { type: Array, default: () => [] } });

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingRoute = ref(null);
const deleteForm = useForm({ password: '' });

const createForm = useForm({
    origin_port_id: '',
    destination_port_id: '',
    estimated_days: '',
    distance: '',
    route_status: 'open',
});
const editForm = useForm({
    origin_port_id: '',
    destination_port_id: '',
    estimated_days: '',
    distance: '',
    route_status: 'open',
});

const openCreate = () => {
    createForm.reset('origin_port_id', 'destination_port_id', 'estimated_days', 'distance', 'route_status');
    createForm.origin_port_id = '';
    createForm.destination_port_id = '';
    createForm.estimated_days = '';
    createForm.distance = '';
    createForm.route_status = 'open';
    createForm.clearErrors();
    showCreateModal.value = true;
};
const openEdit = (r) => {
    editingRoute.value = r;
    editForm.origin_port_id = r.origin_port_id ? String(r.origin_port_id) : '';
    editForm.destination_port_id = r.destination_port_id ? String(r.destination_port_id) : '';
    editForm.estimated_days = r.estimated_days ?? '';
    editForm.distance = r.distance ?? '';
    editForm.route_status = r.route_status || 'open';
    editForm.clearErrors();
    showEditModal.value = true;
};
const openDelete = (r) => {
    editingRoute.value = r;
    deleteForm.reset();
    deleteForm.clearErrors();
    showDeleteModal.value = true;
};

watch(editingRoute, (r) => {
    if (r) {
        editForm.origin_port_id = r.origin_port_id ? String(r.origin_port_id) : '';
        editForm.destination_port_id = r.destination_port_id ? String(r.destination_port_id) : '';
        editForm.estimated_days = r.estimated_days ?? '';
        editForm.distance = r.distance ?? '';
        editForm.route_status = r.route_status || 'open';
    }
});

const submitCreate = () => {
    createForm.post(route('admin.routes.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreateModal.value = false; createForm.reset(); },
    });
};
const submitEdit = () => {
    if (!editingRoute.value) return;
    editForm.put(route('admin.routes.update', editingRoute.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; editingRoute.value = null; },
    });
};
const confirmDelete = () => {
    if (!editingRoute.value) return;
    deleteForm.transform((data) => ({ ...data, _method: 'delete' }));
    deleteForm.post(route('admin.routes.destroy', editingRoute.value.id), {
        preserveScroll: true,
        onSuccess: () => { showDeleteModal.value = false; editingRoute.value = null; },
    });
};
</script>

<template>
    <Head title="Admin – Routes" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Routes</h1>
                <div class="inline-flex items-center gap-2">
                    <Link
                        :href="route('admin.routes.archive')"
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
                        Add route
                    </button>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Origin</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Destination</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Days</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Distance</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
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
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click="openEdit(r)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openDelete(r)"
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
                <div v-if="routes.links && routes.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in routes.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900">Add route</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Origin port *</label>
                        <select v-model="createForm.origin_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select</option>
                            <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                        </select>
                        <InputError :message="createForm.errors.origin_port_id" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Destination port *</label>
                        <select v-model="createForm.destination_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select</option>
                            <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                        </select>
                        <InputError :message="createForm.errors.destination_port_id" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Estimated days *</label>
                        <input v-model="createForm.estimated_days" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                        <InputError :message="createForm.errors.estimated_days" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Distance</label>
                        <input v-model="createForm.distance" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Status *</label>
                        <select v-model="createForm.route_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="showCreateModal = false">Cancel</button>
                        <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="createForm.processing">Create</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" max-width="md" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900">Edit route</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Origin port *</label>
                        <select v-model="editForm.origin_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select</option>
                            <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                        </select>
                        <InputError :message="editForm.errors.origin_port_id" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Destination port *</label>
                        <select v-model="editForm.destination_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select</option>
                            <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                        </select>
                        <InputError :message="editForm.errors.destination_port_id" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Estimated days *</label>
                        <input v-model="editForm.estimated_days" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                        <InputError :message="editForm.errors.estimated_days" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Distance</label>
                        <input v-model="editForm.distance" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Status *</label>
                        <select v-model="editForm.route_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="showEditModal = false">Cancel</button>
                        <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="editForm.processing">Update</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" max-width="sm" @close="showDeleteModal = false">
            <form autocomplete="off" @submit.prevent>
                <div class="p-6">
                    <h2 class="text-lg font-bold text-slate-900">Move route to archive</h2>
                    <p class="mt-2 text-sm text-slate-600">Move route <strong>{{ editingRoute?.origin_name }} → {{ editingRoute?.destination_name }}</strong> to archive? You can restore it later. Confirm with your admin password.</p>
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-slate-500">Admin password</label>
                        <input
                            :key="'delete-pwd-' + (editingRoute?.id ?? '')"
                            v-model="deleteForm.password"
                            type="password"
                            autocomplete="new-password"
                            name="admin_delete_confirm"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        />
                        <InputError :message="deleteForm.errors.password" />
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="showDeleteModal = false">Cancel</button>
                        <button type="button" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing || !deleteForm.password" @click="confirmDelete">Delete</button>
                    </div>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
