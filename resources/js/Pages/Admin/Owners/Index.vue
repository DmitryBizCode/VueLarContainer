<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ owners: { type: Object, required: true }, filters: { type: Object, default: () => ({}) } });

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingOwner = ref(null);
const deleteForm = useForm({ password: '' });

const filterForm = useForm({
    q: props.filters.q ?? '',
});
const applyFilters = () => filterForm.get(route('admin.owners.index'), { preserveState: true });

const createForm = useForm({ name: '', email: '', phone_number: '' });
const editForm = useForm({ name: '', email: '', phone_number: '' });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};
const openEdit = (o) => {
    editingOwner.value = o;
    editForm.name = o.name;
    editForm.email = o.email;
    editForm.phone_number = o.phone_number || '';
    editForm.clearErrors();
    showEditModal.value = true;
};
const openDelete = (o) => {
    editingOwner.value = o;
    deleteForm.reset();
    deleteForm.clearErrors();
    showDeleteModal.value = true;
};

watch(editingOwner, (o) => {
    if (o) {
        editForm.name = o.name;
        editForm.email = o.email;
        editForm.phone_number = o.phone_number || '';
    }
});

const submitCreate = () => {
    createForm.post(route('admin.owners.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreateModal.value = false; createForm.reset(); },
    });
};
const submitEdit = () => {
    if (!editingOwner.value) return;
    editForm.put(route('admin.owners.update', editingOwner.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; editingOwner.value = null; },
    });
};
const confirmDelete = () => {
    if (!editingOwner.value) return;
    deleteForm.transform((data) => ({ ...data, _method: 'delete' }));
    deleteForm.post(route('admin.owners.destroy', editingOwner.value.id), {
        preserveScroll: true,
        onSuccess: () => { showDeleteModal.value = false; editingOwner.value = null; },
    });
};
</script>

<template>
    <Head title="Admin – Owners" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Owners</h1>
                <div class="inline-flex items-center gap-2">
                    <Link
                        :href="route('admin.owners.archive')"
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
                        Add owner
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
                        placeholder="Search name, email, phone…"
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
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Phone</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="o in owners.data" :key="o.id" class="bg-white hover:bg-slate-50/50">
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ o.name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ o.email }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ o.phone_number || '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click="openEdit(o)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openDelete(o)"
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
                <div v-if="owners.links && owners.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in owners.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900">Add owner</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Name *</label>
                        <input v-model="createForm.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Email *</label>
                        <input v-model="createForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="createForm.errors.email" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Phone *</label>
                        <input v-model="createForm.phone_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="createForm.errors.phone_number" />
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
                <h2 class="text-lg font-bold text-slate-900">Edit owner</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Name *</label>
                        <input v-model="editForm.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Email *</label>
                        <input v-model="editForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500">Phone *</label>
                        <input v-model="editForm.phone_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                        <InputError :message="editForm.errors.phone_number" />
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
                    <h2 class="text-lg font-bold text-slate-900">Move owner to archive</h2>
                    <p class="mt-2 text-sm text-slate-600">Move owner <strong>{{ editingOwner?.name }}</strong> to archive? You can restore them from the archive later. Confirm with your admin password.</p>
                    <div class="mt-4">
                        <label class="block text-xs font-semibold text-slate-500">Admin password</label>
                        <input
                            :key="'delete-pwd-' + (editingOwner?.id ?? '')"
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
