<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import StatusTag from '@/Components/StatusTag.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
const props = defineProps({
    containers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    owners: { type: Array, default: () => [] },
    ports: { type: Array, default: () => [] },
    containerTypePresets: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
});

const filterForm = useForm({
    q: props.filters.q ?? '',
    type: props.filters.type ?? '',
    owner_id: props.filters.owner_id ?? '',
    current_port_id: props.filters.current_port_id ?? '',
    current_status: props.filters.current_status ?? '',
});
const applyFilters = () => filterForm.get(route('admin.containers.index'), { preserveState: true });

const quickPatch = (c, field, value) => {
    const payload = {};
    if (field === 'current_port_id') payload.current_port_id = value || null;
    if (field === 'current_status') payload.current_status = value;
    if (field === 'iot_active') payload.iot_active = !!value;
    router.patch(route('admin.containers.quick', c.id), payload, { preserveScroll: true });
};

// Delete confirmation modal
const deleteModalOpen = ref(false);
const deleteTarget = ref(null);
const deleteForm = useForm({
    password: '',
});

const openDeleteModal = (id, serial) => {
    deleteForm.clearErrors();
    deleteForm.reset();
    deleteForm.password = '';
    deleteTarget.value = { id, serial };
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
    deleteForm.delete(route('admin.containers.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
        },
    });
};


const statusLabel = (s) => {
    const raw = String(s || '').replace(/_/g, ' ').toLowerCase();
    if (!raw) return '';
    return raw
        .split(' ')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};
const typePresetKeys = Object.keys(props.containerTypePresets || {});

// Form modal: create | edit
const formModalOpen = ref(false);
const formMode = ref('create'); // 'create' | 'edit'
const formContainerId = ref(null);
const formLoading = ref(false);
const formContainerData = ref(null);

const presetKeys = computed(() => Object.keys(props.containerTypePresets || {}));
const typeSelect = ref('');

const form = useForm({
    serial_number: '',
    type: '',
    width: '',
    length: '',
    height: '',
    max_weight: '',
    manufacture_date: '',
    photo: '',
    iot_active: false,
    current_status: 'available',
    owner_id: '',
    current_port_id: props.ports?.length ? String(props.ports[0].id) : '',
});

watch(
    () => typeSelect.value,
    (key) => {
        if (key === 'custom') {
            form.type = 'custom';
            return;
        }
        if (key && props.containerTypePresets[key]) {
            const p = props.containerTypePresets[key];
            form.type = key;
            form.width = p.width ?? '';
            form.length = p.length ?? '';
            form.height = p.height ?? '';
            form.max_weight = p.max_weight ?? '';
        }
    }
);

const openCreateModal = () => {
    formMode.value = 'create';
    formContainerId.value = null;
    formContainerData.value = null;
    form.reset();
    form.clearErrors();
    photoPreview.value = null;
    form.current_status = 'available';
    form.current_port_id = props.ports?.length ? String(props.ports[0].id) : '';
    // Default type to first preset so dimensions are filled and validation passes
    const firstPreset = typePresetKeys[0];
    if (firstPreset && props.containerTypePresets[firstPreset]) {
        const p = props.containerTypePresets[firstPreset];
        typeSelect.value = firstPreset;
        form.type = firstPreset;
        form.width = p.width ?? '';
        form.length = p.length ?? '';
        form.height = p.height ?? '';
        form.max_weight = p.max_weight ?? '';
    } else {
        typeSelect.value = '';
    }
    formModalOpen.value = true;
};

const openEditModal = async (c) => {
  formMode.value = 'edit';
  formContainerId.value = c.id;
  formLoading.value = true;
  formModalOpen.value = true;
  form.reset();
  form.clearErrors();
  photoPreview.value = null;
  try {
    const { data } = await axios.get(route('admin.containers.full', c.id));
    formContainerData.value = data;
    form.serial_number = data.serial_number;
    form.type = data.type || '';
    form.width = data.width;
    form.length = data.length;
    form.height = data.height;
    form.max_weight = data.max_weight;
    form.manufacture_date = data.manufacture_date || '';
    form.photo = null;
    form.iot_active = data.iot_active ?? false;
    form.current_status = data.current_status || 'available';
    form.owner_id = data.owner_id ? String(data.owner_id) : '';
    form.current_port_id = data.current_port_id ? String(data.current_port_id) : (props.ports?.length ? String(props.ports[0].id) : '');
    typeSelect.value = (props.containerTypePresets && props.containerTypePresets[data.type]) ? data.type : 'custom';
  } finally {
    formLoading.value = false;
  }
};

const photoPreview = ref(null);

const onPhotoChange = (event) => {
    const file = event.target.files?.[0] || null;
    form.photo = file;
    photoPreview.value = null;
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target?.result;
        };
        reader.readAsDataURL(file);
    }
};

const submitForm = () => {
    form.transform((data) => {
        const { photo, manufacture_date, ...rest } = data;
        return {
            ...rest,
            manufacture_date: manufacture_date || null,
            ...(photo instanceof File ? { photo } : {}),
            ...(formMode.value === 'edit' ? { _method: 'put' } : {}),
        };
    });
    if (formMode.value === 'create') {
        form.post(route('admin.containers.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => formModalOpen.value = false,
        });
    } else {
        form.post(route('admin.containers.update', formContainerId.value), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => formModalOpen.value = false,
        });
    }
};

const closeFormModal = () => {
  formModalOpen.value = false;
  formContainerId.value = null;
  // Don't clear formContainerData — avoids flash of default photo during modal close transition
};

// Detail modal (read-only, Rentals-style)
const detailContainer = ref(null);
const detailLoading = ref(false);
const openDetail = async (c) => {
    // Avoid opening when clicking inline controls
    if (!c?.id) return;
    detailContainer.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.containers.full', c.id));
        detailContainer.value = data;
    } finally {
        detailLoading.value = false;
    }
};

const formatDate = (v) => (v ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v)) : '—');
const formatMoney = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(v || 0));

const DEFAULT_CONTAINER_IMAGE = '/image/containers/defaultcontainers.png';

const photoUrl = (path) => {
    const value = path ? String(path) : '';
    if (!value) return DEFAULT_CONTAINER_IMAGE;
    if (value.startsWith('http://') || value.startsWith('https://')) return value;
    // DB stores filename only; folder image/containers/ is fixed in code
    const fullPath = value.includes('/') ? value : `image/containers/${value}`;
    return fullPath.startsWith('image/') ? `/${fullPath}` : `/storage/${fullPath}`;
};

// When user edits dimensions after selecting a preset, auto-switch to custom
watch(
    () => [form.width, form.length, form.height, form.max_weight],
    ([w, l, h, mw]) => {
        const presets = props.containerTypePresets || {};
        let matchedKey = null;

        for (const key in presets) {
            if (!Object.prototype.hasOwnProperty.call(presets, key)) continue;
            const p = presets[key];
            const same =
                Number(w) === Number(p.width ?? '') &&
                Number(l) === Number(p.length ?? '') &&
                Number(h) === Number(p.height ?? '') &&
                Number(mw) === Number(p.max_weight ?? '');
            if (same) {
                matchedKey = key;
                break;
            }
        }

        if (matchedKey) {
            typeSelect.value = matchedKey;
            form.type = matchedKey;
        } else {
            typeSelect.value = 'custom';
            form.type = 'custom';
        }
    }
);
</script>

<template>
    <Head title="Admin – Containers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Containers</h1>
                <div class="inline-flex items-center gap-2">
                    <Link
                        :href="route('admin.containers.archive')"
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
                        Create new container
                    </button>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2">
                    <input v-model="filterForm.q" type="search" placeholder="Serial, type…" autocomplete="off" name="container-search" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm" @keyup.enter="applyFilters" />
                    <select v-model="filterForm.type" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All types</option>
                        <option v-for="k in typePresetKeys" :key="k" :value="k">{{ k }}</option>
                        <option value="custom">Custom</option>
                    </select>
                    <select v-model="filterForm.owner_id" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All owners</option>
                        <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                    <select v-model="filterForm.current_port_id" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All ports</option>
                        <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                    </select>
                    <select v-model="filterForm.current_status" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        <option value="">All statuses</option>
                        <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
                    </select>
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700" @click="applyFilters">Filter</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="w-16 px-2 py-2 text-center text-xs font-semibold text-slate-600">Photo</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Serial</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">Type</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">Dimensions</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">Owner</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">Port</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600">IoT</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="c in containers.data"
                                :key="c.id"
                                class="bg-white hover:bg-slate-50/50 cursor-pointer"
                                @click="openDetail(c)"
                            >
                                <td class="w-16 px-2 py-2 text-center">
                                    <img
                                        :src="photoUrl(c.photo)"
                                        :alt="c.serial_number"
                                        class="mx-auto h-10 w-14 rounded object-cover"
                                    />
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ c.serial_number }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700 text-center">{{ c.type }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700 text-center">{{ c.width }}×{{ c.length }}×{{ c.height }} m</td>
                                <td class="px-4 py-2 text-sm text-slate-700 text-center">{{ c.owner_name || '—' }}</td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <select
                                        :value="c.current_port_id"
                                        class="w-[140px] max-w-[140px] rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 shadow-sm"
                                        @change="quickPatch(c, 'current_port_id', $event.target.value)"
                                    >
                                        <option value="">—</option>
                                        <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <select
                                        :value="c.current_status"
                                        class="w-[132px] max-w-[132px] rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-100"
                                        @change="quickPatch(c, 'current_status', $event.target.value)"
                                    >
                                        <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <select
                                        :value="c.iot_active ? '1' : '0'"
                                        class="w-[84px] max-w-[84px] rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm"
                                        @change="quickPatch(c, 'iot_active', $event.target.value === '1')"
                                    >
                                        <option value="0">Off</option>
                                        <option value="1">On</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-right" @click.stop>
                                    <div class="inline-flex items-center gap-1.5">
                                        <Link
                                            v-if="c.iot_active"
                                            :href="route('admin.containers.edit', c.id)"
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-200 transition hover:bg-blue-100"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>
                                            <span>Sensors</span>
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100"
                                            @click="openEditModal(c)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M5 14.5 6 11l6.5-6.5 2.5 2.5L8.5 13 5 14.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                            </svg>
                                            <span>Edit</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-100"
                                            @click="openDeleteModal(c.id, c.serial_number)"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="10" cy="10" r="5.5" stroke="currentColor" stroke-width="1.4" />
                                                <path d="M8.2 8.2 11.8 11.8M11.8 8.2 8.2 11.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            </svg>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="containers.links && containers.links.length" class="mt-4 flex justify-center gap-2">
                    <Link v-for="(link, i) in containers.links" :key="i" :href="link.url || '#'" class="rounded-lg border border-slate-200 px-3 py-1 text-sm" :class="{ 'bg-slate-100 font-semibold': link.active }" v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <Modal :show="formModalOpen" max-width="xl" @close="closeFormModal">
            <div class="flex max-h-[85vh] flex-col bg-white">
                <header class="shrink-0 border-b border-slate-200 px-5 py-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">
                            {{ formMode === 'create' ? 'Create new container' : `Edit container ${formContainerData?.serial_number || formContainerId}` }}
                        </h2>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeFormModal">Close</button>
                    </div>
                </header>
                <div v-if="formLoading" class="flex flex-1 items-center justify-center py-12">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700" />
                </div>
                <form v-else class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitForm">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Identity & location</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Serial number *</label>
                                <input v-model="form.serial_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                                <InputError :message="form.errors.serial_number" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Current port *</label>
                                <select v-model="form.current_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                                    <option value="">Select port</option>
                                    <option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option>
                                </select>
                                <InputError :message="form.errors.current_port_id" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Owner *</label>
                                <select v-model="form.owner_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                                    <option value="">Select owner</option>
                                    <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                                <InputError :message="form.errors.owner_id" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Current status *</label>
                                <select v-model="form.current_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                                    <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
                                </select>
                                <InputError :message="form.errors.current_status" />
                            </div>
                        </div>
                    </section>
                    <section class="rounded-lg border border-slate-100 bg-slate-50/50 p-4">
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Type & dimensions</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Type</label>
                                <select v-model="typeSelect" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">— Select preset or custom —</option>
                                    <option v-for="k in presetKeys" :key="k" :value="k">{{ k }}</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <InputError :message="form.errors.type" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Width (m) *</label>
                                    <input v-model="form.width" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="form.errors.width" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Length (m) *</label>
                                    <input v-model="form.length" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="form.errors.length" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Height (m) *</label>
                                    <input v-model="form.height" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="form.errors.height" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Max weight (kg) *</label>
                                    <input v-model="form.max_weight" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                    <InputError :message="form.errors.max_weight" />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Manufacture date</label>
                                    <input v-model="form.manufacture_date" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500">Photo</label>
                                    <label class="mt-1 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:border-slate-400 hover:bg-slate-100">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 8.5c0-2.485 2.015-4.5 4.5-4.5 1.824 0 3.403 1.096 4.11 2.671A3.25 3.25 0 0 1 16.75 9.5 3.25 3.25 0 0 1 13.5 12.75H6.25A3.25 3.25 0 0 1 4 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 7v5m0 0-2-2m2 2 2-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>Click to upload container photo</span>
                                        <input
                                            :key="(formContainerId || 'create') + '_photo'"
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="onPhotoChange"
                                        />
                                    </label>
                                    <div class="mt-2 flex items-center gap-3">
                                        <div class="h-16 w-24 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                            <img
                                                v-if="photoPreview"
                                                :src="photoPreview"
                                                alt="Preview"
                                                class="h-full w-full object-cover"
                                            />
                                            <img
                                                v-else
                                                :src="formContainerData ? photoUrl(formContainerData.photo) : DEFAULT_CONTAINER_IMAGE"
                                                alt="Current photo"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>
                                        <p class="text-xs text-slate-500">Recommended: clear side or front view of the container.</p>
                                    </div>
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                <input v-model="form.iot_active" type="checkbox" class="rounded border-slate-300" />
                                IoT active
                            </label>
                        </div>
                    </section>
                    </div>
                    <!-- Sticky footer: buttons always visible at bottom of modal -->
                    <div class="mt-auto shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="rounded-lg border-2 border-emerald-700 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-emerald-700 disabled:opacity-50" :disabled="form.processing">
                                {{ formMode === 'create' ? 'Create container' : 'Save changes' }}
                            </button>
                        <button
                            v-if="formMode === 'edit' && formContainerId"
                            type="button"
                            class="ml-auto rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                            @click="openDeleteModal(formContainerId, formContainerData?.serial_number); closeFormModal()"
                        >
                            Delete
                        </button>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Read-only detail modal -->
        <Modal :show="!!detailContainer || detailLoading" max-width="xl" @close="detailContainer = null; detailLoading = false">
            <div class="max-h-[80vh] overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white p-5">
                <div v-if="detailLoading" class="flex flex-col items-center justify-center py-12">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700" />
                    <p class="mt-3 text-sm font-medium text-slate-500">Loading container…</p>
                </div>
                <template v-else-if="detailContainer">
                    <header class="mb-4 flex items-center justify-between rounded-xl bg-slate-800 px-4 py-3 text-white shadow-lg">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold tracking-tight">{{ detailContainer.serial_number }}</span>
                            <StatusTag
                                class="shadow-none"
                                size="sm"
                                :status="detailContainer.current_status"
                            />
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-white/90 transition hover:bg-white/10"
                            @click="detailContainer = null"
                        >
                            Close
                        </button>
                    </header>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <section class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Main</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">ID</dt><dd class="font-medium text-slate-700">{{ detailContainer.id }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Type</dt><dd class="font-medium text-slate-700">{{ detailContainer.type }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">IoT</dt>
                                <dd class="font-medium text-slate-700">
                                    <span
                                        v-if="detailContainer.iot_active"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                        On
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-500" />
                                        Off
                                    </span>
                                </dd>
                                <dt class="shrink-0 font-medium text-slate-400">Created</dt><dd class="font-medium text-slate-700">{{ formatDate(detailContainer.created_at) }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Updated</dt><dd class="font-medium text-slate-700">{{ formatDate(detailContainer.updated_at) }}</dd>
                            </dl>
                        </section>
                        <section class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Dimensions & weight</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">W × L × H (m)</dt><dd class="font-medium text-slate-700">{{ detailContainer.width }} × {{ detailContainer.length }} × {{ detailContainer.height }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Max weight (kg)</dt><dd class="font-medium text-slate-700">{{ detailContainer.max_weight }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Manufacture date</dt><dd class="font-medium text-slate-700">{{ formatDate(detailContainer.manufacture_date) }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Photo</dt>
                                <dd class="min-w-0 break-all font-medium text-slate-700">
                                    <template v-if="photoUrl(detailContainer.photo)">
                                        <img
                                            :src="photoUrl(detailContainer.photo)"
                                            alt="Container photo"
                                            class="mt-1 h-20 w-32 rounded-lg border border-slate-200 object-cover"
                                        />
                                    </template>
                                    <span v-else>—</span>
                                </dd>
                            </dl>
                        </section>
                        <section v-if="detailContainer.owner" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Owner</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">Name</dt><dd class="font-medium text-slate-700">{{ detailContainer.owner.name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Email</dt><dd class="min-w-0 break-all font-medium text-slate-700">{{ detailContainer.owner.email }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Phone</dt><dd class="font-medium text-slate-700">{{ detailContainer.owner.phone_number }}</dd>
                            </dl>
                        </section>
                        <section v-if="detailContainer.currentPort" class="overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">Current port</h3>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs min-w-0">
                                <dt class="shrink-0 font-medium text-slate-400">Name</dt><dd class="font-medium text-slate-700">{{ detailContainer.currentPort.name }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">City</dt><dd class="font-medium text-slate-700">{{ detailContainer.currentPort.city || '—' }}</dd>
                                <dt class="shrink-0 font-medium text-slate-400">Country</dt><dd class="font-medium text-slate-700">{{ detailContainer.currentPort.country }}</dd>
                            </dl>
                        </section>
                        <section class="sm:col-span-2 overflow-hidden rounded-lg border border-slate-200/80 bg-white px-4 py-3 shadow-sm ring-1 ring-slate-900/5 transition hover:shadow-md">
                            <h3 class="mb-2 border-b border-slate-100 pb-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">
                                Rentals ({{ detailContainer.rentals_count }} total, last 10)
                            </h3>
                            <div v-if="detailContainer.rentals && detailContainer.rentals.length" class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-left font-semibold text-slate-500">
                                            <th class="py-1.5 pr-2">ID</th>
                                            <th class="py-1.5 pr-2">Status</th>
                                            <th class="py-1.5 pr-2">Payment</th>
                                            <th class="py-1.5 pr-2">Start – End</th>
                                            <th class="py-1.5 pr-2">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="r in detailContainer.rentals" :key="r.id" class="border-b border-slate-50">
                                            <td class="py-1.5 pr-2 font-medium text-slate-800">{{ r.id }}</td>
                                            <td class="py-1.5 pr-2">
                                                <StatusTag size="sm" :status="r.status" />
                                            </td>
                                            <td class="py-1.5 pr-2">
                                                <StatusTag size="sm" :status="r.payment_status" />
                                            </td>
                                            <td class="py-1.5 pr-2 text-slate-700">
                                                {{ formatDate(r.start_date) }} – {{ formatDate(r.end_date) }}
                                            </td>
                                            <td class="py-1.5 pr-2 text-slate-700">
                                                {{ formatMoney(r.price) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-xs text-slate-500">No rentals yet.</p>
                        </section>
                    </div>
                </template>
            </div>
        </Modal>

        <!-- Delete confirmation modal -->
        <Modal :show="deleteModalOpen" max-width="sm" @close="closeDeleteModal">
            <form class="p-6" autocomplete="off" @submit.prevent>
                <h2 class="text-lg font-bold text-slate-900">Delete container</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Delete container
                    <span class="font-semibold text-slate-900">{{ deleteTarget?.serial || deleteTarget?.id }}</span>?
                    This action cannot be undone. Please confirm with your admin password.
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
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                        :disabled="deleteForm.processing || !deleteForm.password"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
