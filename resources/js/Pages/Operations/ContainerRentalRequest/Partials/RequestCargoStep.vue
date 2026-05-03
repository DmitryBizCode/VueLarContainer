<script setup>
import InputError from '@/Components/InputError.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    cargoTypes: {
        type: Array,
        default: () => [],
    },
    priorityLevels: {
        type: Array,
        default: () => [],
    },
    incoterms: {
        type: Array,
        default: () => [],
    },
    loadingTypes: {
        type: Array,
        default: () => [],
    },
    sustainabilityOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['toggle-cargo-type']);

const onToggleCargo = (value) => {
    emit('toggle-cargo-type', value);
};
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Cargo details</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900">Shipment profile</h3>
        </div>

        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo type</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <label
                v-for="cargoType in cargoTypes"
                :key="cargoType.value"
                class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700"
            >
                <input
                    type="checkbox"
                    class="rounded border-slate-300 text-blue-700 focus:ring-blue-700"
                    :checked="form.cargo_types.includes(cargoType.value)"
                    @change="onToggleCargo(cargoType.value)"
                >
                <span>{{ cargoType.label }}</span>
            </label>
        </div>

        <InputError class="mt-2" :message="form.errors.cargo_types" />

        <div class="mt-4 border-t border-slate-100 pt-4">
            <label for="cargo_details" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Additional cargo details</label>
            <textarea
                id="cargo_details"
                v-model="form.cargo_details"
                rows="3"
                class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                placeholder="Special handling, package details, compliance notes"
            />
            <InputError class="mt-2" :message="form.errors.cargo_details" />
        </div>

        <div class="mt-4">
            <label for="description" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request note</label>
            <textarea
                id="description"
                v-model="form.description"
                rows="2"
                class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                placeholder="Optional note for operations team"
            />
            <InputError class="mt-2" :message="form.errors.description" />
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label for="priority" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Service priority</label>
                <select
                    id="priority"
                    v-model="form.priority"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
                    <option v-for="level in priorityLevels" :key="level.value" :value="level.value">
                        {{ level.label }}
                    </option>
                </select>
            </div>

            <div>
                <label for="incoterm" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Incoterm</label>
                <select
                    id="incoterm"
                    v-model="form.incoterm"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
                    <option value="">Select incoterm</option>
                    <option v-for="incoterm in incoterms" :key="incoterm" :value="incoterm">
                        {{ incoterm }}
                    </option>
                </select>
            </div>

            <div>
                <label for="loading_type" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loading type</label>
                <select
                    id="loading_type"
                    v-model="form.loading_type"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
                    <option v-for="loading in loadingTypes" :key="loading.value" :value="loading.value">
                        {{ loading.label }}
                    </option>
                </select>
            </div>

            <div>
                <label for="sustainability_pref" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sustainability preference</label>
                <select
                    id="sustainability_pref"
                    v-model="form.sustainability_pref"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
                    <option v-for="option in sustainabilityOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div>
                <label for="cargo_value" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo value (USD)</label>
                <input
                    id="cargo_value"
                    v-model="form.cargo_value"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Optional"
                >
            </div>

            <div>
                <label for="cargo_volume_cbm" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo volume (CBM)</label>
                <input
                    id="cargo_volume_cbm"
                    v-model="form.cargo_volume_cbm"
                    type="number"
                    min="0"
                    step="0.001"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Optional"
                >
            </div>

            <div>
                <label for="package_count" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Package count</label>
                <input
                    id="package_count"
                    v-model="form.package_count"
                    type="number"
                    min="1"
                    step="1"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Optional"
                >
            </div>
        </div>

        <div class="mt-4 grid gap-2 md:grid-cols-3">
            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                <input v-model="form.insurance_required" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                Insurance required
            </label>
            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                <input v-model="form.requires_customs_clearance" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                Customs clearance
            </label>
            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                <input v-model="form.hazardous_material" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                Hazardous material
            </label>
            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                <input v-model="form.requires_escort" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                Escort required
            </label>
            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                <input v-model="form.seal_required" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-700">
                Security seal required
            </label>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label for="un_number" class="text-xs font-semibold uppercase tracking-wide text-slate-500">UN number</label>
                <input
                    id="un_number"
                    v-model="form.un_number"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Required for hazardous cargo"
                >
            </div>
            <div>
                <label for="dangerous_goods_class" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dangerous goods class</label>
                <select
                    id="dangerous_goods_class"
                    v-model="form.dangerous_goods_class"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
                    <option value="">Select class</option>
                    <option value="1">Class 1 – Explosives</option>
                    <option value="2">Class 2 – Gases</option>
                    <option value="3">Class 3 – Flammable liquids</option>
                    <option value="4">Class 4 – Flammable solids</option>
                    <option value="5">Class 5 – Oxidizing substances</option>
                    <option value="6">Class 6 – Toxic & infectious</option>
                    <option value="7">Class 7 – Radioactive</option>
                    <option value="8">Class 8 – Corrosive</option>
                    <option value="9">Class 9 – Miscellaneous</option>
                </select>
            </div>
            <div>
                <label for="origin_customs_code" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origin customs code</label>
                <input
                    id="origin_customs_code"
                    v-model="form.origin_customs_code"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
            </div>
            <div>
                <label for="destination_customs_code" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destination customs code</label>
                <input
                    id="destination_customs_code"
                    v-model="form.destination_customs_code"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                >
            </div>
        </div>
    </section>
</template>
