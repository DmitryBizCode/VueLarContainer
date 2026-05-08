<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    sensorTypes: { type: Array, default: () => [] },
    enabledSensorTypeIds: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
    ports: { type: Array, default: () => [] },
    containerTypePresets: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
});

const presetKeys = computed(() => Object.keys(props.containerTypePresets));
const typeSelect = ref('');
const optionalSensorTypes = computed(() => (props.sensorTypes || []).filter((st) => st.is_optional));
const mandatorySensorTypes = computed(() => (props.sensorTypes || []).filter((st) => !st.is_optional));

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
    sensor_enabled: props.enabledSensorTypeIds || [],
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

const submit = () => form.post(route('admin.containers.store'));
const statusLabel = (s) => String(s || '').replace(/_/g, ' ');
</script>

<template>
    <Head title="Admin – Add container" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Admin – Add container</h1>
                <Link :href="route('admin.containers.index')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to list</Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 space-y-6">
                <form class="space-y-6" @submit.prevent="submit">
                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 border-b border-slate-100 pb-2 text-sm font-bold uppercase tracking-wider text-slate-500">Identity & location</h2>
                        <div class="space-y-4">
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

                    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 border-b border-slate-100 pb-2 text-sm font-bold uppercase tracking-wider text-slate-500">Type & dimensions</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Type</label>
                                <select v-model="typeSelect" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">— Select preset or custom —</option>
                                    <option v-for="k in presetKeys" :key="k" :value="k">{{ k }}</option>
                                    <option value="custom">Custom (enter dimensions manually)</option>
                                </select>
                                <InputError :message="form.errors.type" />
                            </div>
                            <div class="grid grid-cols-3 gap-4">
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
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Max weight (kg) *</label>
                                <input v-model="form.max_weight" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                <InputError :message="form.errors.max_weight" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Manufacture date</label>
                                <input v-model="form.manufacture_date" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500">Photo URL</label>
                                <input v-model="form.photo" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Optional" />
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                <input v-model="form.iot_active" type="checkbox" class="rounded border-slate-300" />
                                IoT active
                            </label>
                            <div v-if="form.iot_active" class="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                <h4 class="mb-3 text-sm font-semibold text-slate-700">IoT Sensors</h4>
                                <p class="mb-3 text-xs text-slate-500">Required sensors (doors, pump, water level, ventilation) are added automatically. Optional ones can be selected below.</p>
                                <div class="space-y-2">
                                    <template v-for="st in optionalSensorTypes" :key="st.id">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                            <input v-model="form.sensor_enabled" type="checkbox" :value="st.id" class="rounded border-slate-300" />
                                            <span>{{ st.name }}</span>
                                            <span v-if="st.category" class="rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-500">{{ st.category }}</span>
                                        </label>
                                    </template>
                                    <template v-if="mandatorySensorTypes.length">
                                        <p class="mt-2 text-xs font-medium text-slate-600">Required (read-only):</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="st in mandatorySensorTypes" :key="st.id" class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-800">
                                                <span>✓</span> {{ st.name }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50" :disabled="form.processing">Create container</button>
                        <Link :href="route('admin.containers.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
