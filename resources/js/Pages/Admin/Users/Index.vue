<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    users: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    roleOptions: { type: Array, default: () => [] },
});

const filterForm = useForm({
    role: props.filters.role ?? '',
    account_status: props.filters.account_status ?? '',
    q: props.filters.q ?? '',
});
const applyFilters = () => filterForm.get(route('admin.users.index'), { preserveState: true });

const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');
const currentUserId = computed(() => usePage().props.auth?.user?.id ?? null);

const isFile = (v) => v instanceof File;
const profilePhotoUrl = (path) => {
    const value = path ? String(path) : '';
    if (!value) return null;
    if (value.startsWith('http://') || value.startsWith('https://')) return value;
    const fullPath = value.includes('/') ? value : `image/profile/${value}`;
    return fullPath.startsWith('image/') ? `/${fullPath}` : `/storage/${fullPath}`;
};
const userInitials = (u) => {
    if (!u) return '—';
    const init = [u.first_name?.[0], u.last_name?.[0]].filter(Boolean).join('').toUpperCase();
    return init || (u.email?.[0]?.toUpperCase() ?? '?');
};

const showEditModal = ref(false);
const editingUser = ref(null);
const editPhotoPreviewUrl = ref(null);
const editForm = useForm({
    first_name: '',
    last_name: '',
    company_name: '',
    email: '',
    phone_number: '',
    address: '',
    photo: null,
    role: 'client',
    account_status: '',
    country_id: '',
});

const openEdit = (u) => {
    if (editPhotoPreviewUrl.value) {
        URL.revokeObjectURL(editPhotoPreviewUrl.value);
        editPhotoPreviewUrl.value = null;
    }
    editingUser.value = u;
    editForm.first_name = u.first_name ?? '';
    editForm.last_name = u.last_name ?? '';
    editForm.company_name = u.company_name ?? '';
    editForm.email = u.email ?? '';
    editForm.phone_number = u.phone_number ?? '';
    editForm.address = u.address ?? '';
    editForm.photo = null;
    editForm.role = u.role || 'client';
    editForm.account_status = u.account_status ?? '';
    editForm.country_id = u.country_id ? String(u.country_id) : '';
    editForm.clearErrors();
    showEditModal.value = true;
};

const onEditPhotoChange = (e) => {
    const f = e.target.files?.[0];
    if (!f) return;
    if (editPhotoPreviewUrl.value) URL.revokeObjectURL(editPhotoPreviewUrl.value);
    editPhotoPreviewUrl.value = URL.createObjectURL(f);
    editForm.photo = f;
    e.target.value = '';
};

const closeEditModal = () => {
    if (editPhotoPreviewUrl.value) {
        URL.revokeObjectURL(editPhotoPreviewUrl.value);
        editPhotoPreviewUrl.value = null;
    }
    showEditModal.value = false;
    editingUser.value = null;
};

watch(editingUser, (u) => {
    if (u) {
        if (editPhotoPreviewUrl.value) {
            URL.revokeObjectURL(editPhotoPreviewUrl.value);
            editPhotoPreviewUrl.value = null;
        }
        editForm.first_name = u.first_name ?? '';
        editForm.last_name = u.last_name ?? '';
        editForm.company_name = u.company_name ?? '';
        editForm.email = u.email ?? '';
        editForm.phone_number = u.phone_number ?? '';
        editForm.address = u.address ?? '';
        editForm.photo = null;
        editForm.role = u.role || 'client';
        editForm.account_status = u.account_status ?? '';
        editForm.country_id = u.country_id ? String(u.country_id) : '';
    }
});

const submitEdit = () => {
    if (!editingUser.value?.id) return;
    const url = route('admin.users.update', editingUser.value.id);
    if (isFile(editForm.photo)) {
        editForm.transform((data) => ({ ...data, _method: 'patch' }));
        editForm.post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { showEditModal.value = false; editingUser.value = null; },
        });
    } else {
        editForm.patch(url, {
            preserveScroll: true,
            onSuccess: () => { showEditModal.value = false; editingUser.value = null; },
        });
    }
};

const deleteModalOpen = ref(false);
const deleteTarget = ref(null);
const deleteForm = useForm({ password: '' });

const openDeleteModal = (u) => {
    deleteTarget.value = u;
    deleteForm.reset();
    deleteForm.clearErrors();
    deleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    deleteModalOpen.value = false;
    deleteTarget.value = null;
    deleteForm.reset();
};

const confirmDelete = () => {
    if (!deleteTarget.value?.id) return;
    deleteForm.transform((data) => ({ ...data, _method: 'delete' }));
    deleteForm.post(route('admin.users.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};
</script>

<template>
    <Head title="Admin – Users" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Users</h1>
                <Link
                    :href="route('admin.users.archive')"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    Archive
                </Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2">
                    <input v-model="filterForm.q" type="search" placeholder="Search…" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" autocomplete="off" @keyup.enter="applyFilters" />
                    <select v-model="filterForm.role" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All roles</option>
                        <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                    </select>
                    <input v-model="filterForm.account_status" type="text" placeholder="Account status" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" @keyup.enter="applyFilters" />
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                </div>
                <div class="overflow-x-auto overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Photo</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Company</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Phone</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Address</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Country</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Role</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Verified</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Created</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="u in users.data" :key="u.id" class="bg-white hover:bg-slate-50/50">
                                <td class="px-4 py-2">
                                    <img v-if="profilePhotoUrl(u.photo)" :src="profilePhotoUrl(u.photo)" :alt="u.email" class="h-10 w-10 rounded-full object-cover" />
                                    <span v-else class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600">{{ userInitials(u) }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ [u.first_name, u.last_name].filter(Boolean).join(' ') || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.email }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.company_name || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.phone_number || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700 max-w-[180px] truncate" :title="u.address || ''">{{ u.address || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.country_name || '—' }}</td>
                                <td class="px-4 py-2 text-sm"><span class="rounded-full border border-slate-200 px-2 py-0.5 text-xs">{{ u.role }}</span></td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.account_status || '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ u.email_verified_at ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-600">{{ formatDate(u.created_at) }}</td>
                                <td class="px-4 py-2 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click.stop="openEdit(u)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            v-if="u.id !== currentUserId"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openDeleteModal(u)"
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
                <div v-if="users.links && users.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in users.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <Modal :show="deleteModalOpen" max-width="sm" @close="closeDeleteModal">
            <form autocomplete="off" @submit.prevent>
                <div class="p-6">
                    <h2 class="text-lg font-bold text-slate-900">Move user to archive</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Move user <strong>{{ deleteTarget?.email }}</strong> to archive? You can restore them from the archive later. Confirm with your admin password.
                    </p>
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
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeDeleteModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing || !deleteForm.password" @click="confirmDelete">Delete</button>
                    </div>
                </div>
            </form>
        </Modal>

        <Modal :show="showEditModal" max-width="md" @close="closeEditModal">
            <div class="flex max-h-[85vh] flex-col bg-white">
                <header class="shrink-0 border-b border-slate-200 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Edit user {{ editingUser?.email || '' }}</h2>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeEditModal">Close</button>
                    </div>
                </header>
                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitEdit">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <div v-if="editingUser" class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 text-sm text-slate-600">
                            <p>Email verified: <strong>{{ editingUser.email_verified_at ? 'Yes' : 'No' }}</strong></p>
                            <p>Created: {{ formatDate(editingUser.created_at) }} · Updated: {{ formatDate(editingUser.updated_at) }}</p>
                        </div>
                        <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Profile photo</h3>
                            <div class="flex items-center gap-4">
                                <img
                                    v-if="editPhotoPreviewUrl || profilePhotoUrl(editingUser?.photo)"
                                    :src="editPhotoPreviewUrl || profilePhotoUrl(editingUser?.photo)"
                                    :alt="editingUser?.email"
                                    class="h-20 w-20 rounded-full object-cover border-2 border-slate-200"
                                />
                                <span
                                    v-else
                                    class="inline-flex h-20 w-20 items-center justify-center rounded-full border-2 border-slate-200 bg-slate-100 text-2xl font-semibold text-slate-600"
                                >
                                    {{ userInitials(editingUser) }}
                                </span>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Change photo</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-slate-700"
                                        @change="onEditPhotoChange"
                                    />
                                </div>
                            </div>
                        </section>
                        <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">User details</h3>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500">First name</label>
                                        <input v-model="editForm.first_name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <InputError :message="editForm.errors.first_name" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500">Last name</label>
                                        <input v-model="editForm.last_name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <InputError :message="editForm.errors.last_name" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Email</label>
                                    <input v-model="editForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="editForm.errors.email" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Company</label>
                                    <input v-model="editForm.company_name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="editForm.errors.company_name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Phone</label>
                                    <input v-model="editForm.phone_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="editForm.errors.phone_number" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Address</label>
                                    <textarea v-model="editForm.address" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="editForm.errors.address" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Country</label>
                                    <select v-model="editForm.country_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <option value="">—</option>
                                        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <InputError :message="editForm.errors.country_id" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Role *</label>
                                    <select v-model="editForm.role" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                                        <option v-for="r in roleOptions" :key="r" :value="r">{{ r }}</option>
                                    </select>
                                    <InputError :message="editForm.errors.role" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Account status</label>
                                    <input v-model="editForm.account_status" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. active, pending_verification" />
                                    <InputError :message="editForm.errors.account_status" />
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
    </AuthenticatedLayout>
</template>
