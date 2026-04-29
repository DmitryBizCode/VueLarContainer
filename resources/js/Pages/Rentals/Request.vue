<script setup>
import ContainerMatchListCard from '@/Components/Rentals/ContainerMatchListCard.vue';
import InputError from '@/Components/InputError.vue';
import PhoneInputWithCountry from '@/Components/Rentals/PhoneInputWithCountry.vue';
import PriceBreakdownCard from '@/Components/Rentals/PriceBreakdownCard.vue';
import RouteSelectorCard from '@/Components/Rentals/RouteSelectorCard.vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/composables/useToast';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const STEPS = [
    { id: 1, label: 'Route & dates', short: 'Route' },
    { id: 2, label: 'Cargo details', short: 'Cargo' },
    { id: 3, label: 'Pickup & consignee', short: 'Pickup' },
    { id: 4, label: 'Container & submit', short: 'Review' },
];
const currentStep = ref(1);
const showPickupWindow = ref(false);

const { success, error: showError } = useToast();

const props = defineProps({
    countries: {
        type: Array,
        default: () => [],
    },
    user_country_id: {
        type: [Number, String, null],
        default: null,
    },
    routes: {
        type: Array,
        default: () => [],
    },
    ports: {
        type: Array,
        default: () => [],
    },
    origin_ports: {
        type: Array,
        default: () => [],
    },
    cargo_types: {
        type: Array,
        default: () => [],
    },
    priority_levels: {
        type: Array,
        default: () => [],
    },
    incoterms: {
        type: Array,
        default: () => [],
    },
    delivery_modes: {
        type: Array,
        default: () => [],
    },
    loading_types: {
        type: Array,
        default: () => [],
    },
    sustainability_options: {
        type: Array,
        default: () => [],
    },
    my_recent_requests: {
        type: Array,
        default: () => [],
    },
    logistics_config: {
        type: Object,
        default: () => ({
            port_operations_max_days: 4,
            post_arrival_min_days: 1,
            loading_buffer_days: 3,
        }),
    },
    routing_priority_options: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    route_mode: 'route',
    route_id: '',
    origin_port_id: '',
    destination_port_id: '',
    container_id: '',
    start_date: '',
    end_date: '',
    cargo_types: [],
    cargo_details: '',
    requested_weight: '',
    cargo_volume_cbm: '',
    package_count: '',
    cargo_value: '',
    priority: 'normal',
    routing_priority: '',
    incoterm: '',
    loading_type: 'fcl',
    delivery_mode: 'port_to_port',
    sustainability_pref: 'standard',
    insurance_required: false,
    requires_customs_clearance: false,
    hazardous_material: false,
    requires_escort: false,
    seal_required: false,
    un_number: '',
    dangerous_goods_class: '',
    origin_customs_code: '',
    destination_customs_code: '',
    contact_name: '',
    contact_phone: '',
    pickup_address: '',
    delivery_address: '',
    pickup_window_start: '',
    pickup_window_end: '',
    terms_accepted: false,
    special_requirements: '',
    description: '',
});

const previewState = reactive({
    loading: false,
    availableContainers: [],
    routeContext: {},
    estimatedPrice: 0,
    priceBreakdown: null,
    previewError: '',
    processingApprovalId: null,
});

const statusLabelRequest = (value) => {
    if (!value) return 'Unknown';

    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

const statusDetailEligibleRequest = (item) => {
    const st = String(item.status || '').toLowerCase();

    return (
        ['draft', 'pending_approval', 'rejected', 'cancelled'].includes(st)
        || Boolean(item.rejection_reason?.trim())
        || Boolean(item.cancellation_reason?.trim())
    );
};

const reasonModalRequest = reactive({
    show: false,
    title: '',
    body: '',
});

const openStatusDetailRequest = (item) => {
    const st = String(item.status || '').toLowerCase();
    const lines = [];

    if (item.container_operational_status) {
        lines.push(`Equipment status: ${statusLabelRequest(item.container_operational_status)}`);
    }

    if (st === 'rejected') {
        lines.push(item.rejection_reason?.trim() || 'No rejection reason was recorded.');
    } else if (st === 'cancelled') {
        lines.push(item.cancellation_reason?.trim() || 'No cancellation notes were recorded.');
    } else if (st === 'pending_approval') {
        lines.push('Awaiting operations review — you will be notified when the request is approved or rejected.');
    } else if (st === 'draft') {
        lines.push('Draft — complete and submit the rental request when ready.');
    } else if (item.rejection_reason?.trim()) {
        lines.push(`Previous rejection note: ${item.rejection_reason.trim()}`);
    } else if (item.cancellation_reason?.trim()) {
        lines.push(`Cancellation note: ${item.cancellation_reason.trim()}`);
    }

    reasonModalRequest.title = `Request #${item.id} · ${statusLabelRequest(item.status)}`;
    reasonModalRequest.body = lines.join('\n\n');
    reasonModalRequest.show = true;
};

const closeReasonModalRequest = () => {
    reasonModalRequest.show = false;
};

const LOADING_BUFFER_DAYS = 3;

/** Match Laravel `CarbonImmutable` in UTC: add whole calendar days to an ISO `YYYY-MM-DD`. */
function addUtcCalendarDays(isoDate, daysToAdd) {
    const parts = String(isoDate || '').split('-').map((x) => parseInt(x, 10));
    const [y, m, d] = parts;
    if (!y || !m || !d) {
        return '';
    }
    const t = Date.UTC(y, m - 1, d);
    return new Date(t + Number(daysToAdd) * 86400000).toISOString().slice(0, 10);
}

const todayISO = computed(() => new Date().toISOString().slice(0, 10));

const estimatedDaysFromRoute = computed(() => {
    if (form.route_mode === 'route' && form.route_id) {
        const route = props.routes.find((r) => String(r.id) === String(form.route_id));
        return route?.estimated_days ?? 1;
    }
    if (form.route_mode === 'ports' && form.origin_port_id && form.destination_port_id) {
        const ctx = previewState.routeContext;
        const d = Number(ctx?.estimated_days);
        if (ctx && ctx.path_found !== false && !Number.isNaN(d) && d > 0) {
            return d;
        }
        const route = props.routes.find(
            (r) =>
                String(r.origin_port_id) === String(form.origin_port_id) &&
                String(r.destination_port_id) === String(form.destination_port_id)
        );
        return route?.estimated_days ?? 1;
    }
    return 1;
});

const minStartDate = computed(() => addUtcCalendarDays(new Date().toISOString().slice(0, 10), LOADING_BUFFER_DAYS));

const minEndSpanDays = computed(() => {
    const span = Number(previewState.routeContext?.min_rental_span_days);
    if (!Number.isNaN(span) && span > 0) {
        return span;
    }
    const lg = props.logistics_config || {};
    return (
        estimatedDaysFromRoute.value +
        Number(lg.port_operations_max_days ?? 4) +
        Number(lg.post_arrival_min_days ?? 1)
    );
});

const minEndDate = computed(() => {
    const baseStart = form.start_date && form.start_date > minStartDate.value ? form.start_date : minStartDate.value;
    return addUtcCalendarDays(baseStart, minEndSpanDays.value);
});

const canPreview = computed(() => {
    if (!form.start_date || !form.end_date || !form.cargo_types.length) {
        return false;
    }

    if (form.route_mode === 'route') {
        return Boolean(form.route_id);
    }

    return Boolean(form.origin_port_id && form.destination_port_id);
});

let previewTimeout = null;

const schedulePreview = () => {
    if (!canPreview.value) {
        return;
    }

    if (previewTimeout) {
        clearTimeout(previewTimeout);
    }

    previewTimeout = setTimeout(() => {
        void runPreview();
    }, 350);
};

const runPreview = async () => {
    if (!canPreview.value) {
        return;
    }

    if (previewState.loading) {
        return;
    }

    previewState.loading = true;
    previewState.previewError = '';

    try {
        const payload = {
            route_mode: form.route_mode,
            route_id: form.route_id || null,
            origin_port_id: form.origin_port_id || null,
            destination_port_id: form.destination_port_id || null,
            container_id: form.container_id || null,
            start_date: form.start_date,
            end_date: form.end_date,
            cargo_types: form.cargo_types,
            requested_weight: form.requested_weight || null,
            cargo_volume_cbm: form.cargo_volume_cbm || null,
            package_count: form.package_count || null,
            cargo_value: form.cargo_value || null,
            priority: form.priority,
            delivery_mode: form.delivery_mode,
            loading_type: form.loading_type,
            sustainability_pref: form.sustainability_pref,
            insurance_required: Boolean(form.insurance_required),
            requires_customs_clearance: Boolean(form.requires_customs_clearance),
            hazardous_material: Boolean(form.hazardous_material),
            requires_escort: Boolean(form.requires_escort),
            seal_required: Boolean(form.seal_required),
            routing_priority: form.routing_priority || null,
        };

        const response = await window.axios.post(route('rentals.request.preview', {}, false), payload);
        const data = response.data || {};

        previewState.routeContext = data.route_context || {};
        previewState.availableContainers = data.available_containers || [];
        previewState.estimatedPrice = Number(data.estimated_price || 0);
        previewState.priceBreakdown = data.price_breakdown || null;

        if (!previewState.availableContainers.length) {
            form.container_id = '';
            return;
        }

        const hasSelectedContainer = previewState.availableContainers.some(
            (container) => String(container.id) === String(form.container_id)
        );

        if (!hasSelectedContainer) {
            form.container_id = String(previewState.availableContainers[0].id);
        }
    } catch (err) {
        const data = err?.response?.data;
        const fromErrors = data?.errors && typeof data.errors === 'object'
            ? Object.values(data.errors).flat().find(Boolean)
            : null;
        const message =
            (typeof data?.message === 'string' && data.message) ||
            fromErrors ||
            'Could not calculate preview for current inputs.';
        previewState.routeContext = {};
        previewState.availableContainers = [];
        previewState.estimatedPrice = 0;
        previewState.priceBreakdown = null;
        previewState.previewError = message;
        showError('Preview failed', message);
    } finally {
        previewState.loading = false;
    }
};

watch(
    () => form.origin_port_id,
    (newOrigin) => {
        if (form.route_mode === 'ports' && String(form.destination_port_id) === String(newOrigin)) {
            form.destination_port_id = '';
        }
    }
);

watch(
    () => form.start_date,
    (start) => {
        if (!start) {
            return;
        }
        if (start < minStartDate.value) {
            form.start_date = minStartDate.value;
        }
    }
);

watch(
    () => [minEndDate.value, form.end_date],
    ([minEnd, end]) => {
        if (end && minEnd && end < minEnd) {
            form.end_date = minEnd;
        }
    }
);

watch(
    () => [
        form.route_mode,
        form.route_id,
        form.origin_port_id,
        form.destination_port_id,
        form.start_date,
        form.end_date,
        form.requested_weight,
        form.cargo_volume_cbm,
        form.package_count,
        form.cargo_value,
        form.priority,
        form.routing_priority,
        form.delivery_mode,
        form.loading_type,
        form.sustainability_pref,
        form.insurance_required,
        form.requires_customs_clearance,
        form.hazardous_material,
        form.requires_escort,
        form.seal_required,
        form.cargo_types.join('|'),
    ],
    () => {
        form.container_id = '';
        previewState.availableContainers = [];
        previewState.priceBreakdown = null;
        previewState.estimatedPrice = 0;
        schedulePreview();
    }
);

watch(
    () => form.container_id,
    () => {
        if (form.container_id) {
            schedulePreview();
        }
    }
);

const toggleCargoType = (cargoType) => {
    const value = String(cargoType);

    if (form.cargo_types.includes(value)) {
        form.cargo_types = form.cargo_types.filter((item) => item !== value);
        return;
    }

    form.cargo_types = [...form.cargo_types, value];
};

const submit = () => {
    form.post(route('rentals.request.store', {}, false), {
        preserveScroll: true,
        onError: (errors) => {
            const firstMessage = Object.values(errors)[0];
            showError('Validation failed', Array.isArray(firstMessage) ? firstMessage[0] : firstMessage);
        },
    });
};

</script>

<template>
    <Head title="Request Rental" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Operations</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">Container rental request</h1>
                </div>
                <span class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 sm:inline-flex">
                    End-to-end request and approval workflow
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Stepper -->
                <nav class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6">
                    <ol class="flex items-center gap-2 sm:gap-4">
                        <li
                            v-for="(step, index) in STEPS"
                            :key="step.id"
                            class="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold transition sm:px-3"
                                :class="currentStep === step.id ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                                @click="currentStep = step.id"
                            >
                                <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-current" :class="currentStep === step.id ? 'border-white' : ''">{{ step.id }}</span>
                                <span class="hidden sm:inline">{{ step.short }}</span>
                            </button>
                            <span v-if="index < STEPS.length - 1" class="hidden h-px w-4 bg-slate-200 sm:block" aria-hidden="true" />
                        </li>
                    </ol>
                    <div class="flex gap-2">
                        <button
                            v-show="currentStep > 1"
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="currentStep--"
                        >
                            Back
                        </button>
                        <button
                            v-show="currentStep < STEPS.length"
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-800"
                            @click="currentStep++"
                        >
                            Next
                        </button>
                    </div>
                </nav>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="space-y-6 xl:col-span-2">
                        <div v-show="currentStep === 1">
                        <RouteSelectorCard
                            :routes="props.routes"
                            :ports="props.ports"
                            :origin-ports="props.origin_ports"
                            :route-mode="form.route_mode"
                            :route-id="form.route_id"
                            :origin-port-id="form.origin_port_id"
                            :destination-port-id="form.destination_port_id"
                            :start-date="form.start_date"
                            :end-date="form.end_date"
                            :requested-weight="form.requested_weight"
                            :min-start-date="minStartDate"
                            :min-end-date="minEndDate"
                            @update:route-mode="form.route_mode = $event"
                            @update:route-id="form.route_id = $event"
                            @update:origin-port-id="form.origin_port_id = $event"
                            @update:destination-port-id="form.destination_port_id = $event"
                            @update:start-date="form.start_date = $event"
                            @update:end-date="form.end_date = $event"
                            @update:requested-weight="form.requested_weight = $event"
                        />

                        <div
                            v-if="props.routing_priority_options.length"
                            class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sea routing</p>
                            <p class="mt-1 text-sm text-slate-600">
                                When no direct lane exists, we search the open route graph. Override how that path is chosen.
                            </p>
                            <select
                                v-model="form.routing_priority"
                                class="mt-3 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                            >
                                <option
                                    v-for="opt in props.routing_priority_options"
                                    :key="opt.value === '' || opt.value == null ? 'auto' : opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        </div>

                        <section v-show="currentStep === 2" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Cargo details</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">Shipment profile</h3>
                            </div>

                            <!-- Cargo types -->
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo type</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="cargoType in props.cargo_types"
                                    :key="cargoType.value"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-blue-700 focus:ring-blue-700"
                                        :checked="form.cargo_types.includes(cargoType.value)"
                                        @change="toggleCargoType(cargoType.value)"
                                    >
                                    <span>{{ cargoType.label }}</span>
                                </label>
                            </div>

                            <InputError class="mt-2" :message="form.errors.cargo_types" />

                            <!-- Additional details -->
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

                            <!-- Service & terms -->
                            <div class="mt-4 border-t border-slate-100 pt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="priority" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Service priority</label>
                                    <select
                                        id="priority"
                                        v-model="form.priority"
                                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option v-for="level in props.priority_levels" :key="level.value" :value="level.value">
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
                                        <option v-for="incoterm in props.incoterms" :key="incoterm" :value="incoterm">
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
                                        <option v-for="loading in props.loading_types" :key="loading.value" :value="loading.value">
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
                                        <option v-for="option in props.sustainability_options" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Dimensions & value -->
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

                        <section v-show="currentStep === 3" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Operations contacts</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">Pickup and consignee details</h3>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="contact_name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact name</label>
                                    <input
                                        id="contact_name"
                                        v-model="form.contact_name"
                                        type="text"
                                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                        placeholder="Responsible person"
                                    >
                                </div>
                                <div>
                                    <label for="contact_phone" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact phone</label>
                                    <PhoneInputWithCountry
                                        id="contact_phone"
                                        :model-value="form.contact_phone"
                                        :countries="props.countries"
                                        :user-country-id="props.user_country_id"
                                        class="mt-1.5"
                                        @update:model-value="form.contact_phone = $event"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="special_requirements" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Special requirements</label>
                                    <textarea
                                        id="special_requirements"
                                        v-model="form.special_requirements"
                                        rows="2"
                                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                        placeholder="Permits, handling equipment, security requests"
                                    />
                                </div>
                            </div>

                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-blue-700 hover:underline"
                                    @click="showPickupWindow = !showPickupWindow"
                                >
                                    {{ showPickupWindow ? 'Hide pickup window' : 'Add pickup window (optional)' }}
                                </button>

                                <div v-if="showPickupWindow" class="mt-3 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="pickup_window_start" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup window start</label>
                                        <input
                                            id="pickup_window_start"
                                            v-model="form.pickup_window_start"
                                            type="datetime-local"
                                            class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                        >
                                        <p class="mt-1 text-xs text-slate-500">Within rental period</p>
                                    </div>
                                    <div>
                                        <label for="pickup_window_end" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup window end</label>
                                        <input
                                            id="pickup_window_end"
                                            v-model="form.pickup_window_end"
                                            type="datetime-local"
                                            class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div v-show="currentStep === 4">
                        <ContainerMatchListCard
                            :containers="previewState.availableContainers"
                            :selected-container-id="form.container_id"
                            :loading="previewState.loading"
                            @update:selected-container-id="form.container_id = $event"
                        />
                        </div>
                    </div>

                    <div class="space-y-6">
                        <PriceBreakdownCard
                            :ports="props.ports"
                            :estimated-price="previewState.estimatedPrice"
                            :route-context="previewState.routeContext"
                            :price-breakdown="previewState.priceBreakdown"
                            :loading="previewState.loading"
                        />

                        <section v-show="currentStep === 4" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-lg font-bold text-slate-900">Submit request</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Price is calculated on the server and revalidated before saving.
                            </p>

                            <p v-if="previewState.previewError" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                                {{ previewState.previewError }}
                            </p>

                            <InputError class="mt-2" :message="form.errors.route_id || form.errors.origin_port_id || form.errors.destination_port_id" />
                            <InputError class="mt-2" :message="form.errors.container_id" />
                            <InputError class="mt-2" :message="form.errors.start_date" />
                            <InputError class="mt-2" :message="form.errors.end_date" />
                            <InputError class="mt-2" :message="form.errors.requested_weight" />
                            <InputError class="mt-2" :message="form.errors.cargo_value" />
                            <InputError class="mt-2" :message="form.errors.priority" />
                            <InputError class="mt-2" :message="form.errors.incoterm" />
                                <InputError class="mt-2" :message="form.errors.delivery_mode" />
                                <InputError class="mt-2" :message="form.errors.loading_type" />
                                <InputError class="mt-2" :message="form.errors.sustainability_pref" />
                                <InputError class="mt-2" :message="form.errors.cargo_volume_cbm" />
                                <InputError class="mt-2" :message="form.errors.package_count" />
                            <InputError class="mt-2" :message="form.errors.contact_name" />
                            <InputError class="mt-2" :message="form.errors.contact_phone" />
                            <InputError class="mt-2" :message="form.errors.pickup_window_start" />
                            <InputError class="mt-2" :message="form.errors.pickup_window_end" />
                            <InputError class="mt-2" :message="form.errors.special_requirements" />
                                <InputError class="mt-2" :message="form.errors.un_number" />
                                <InputError class="mt-2" :message="form.errors.dangerous_goods_class" />
                                <InputError class="mt-2" :message="form.errors.origin_customs_code" />
                                <InputError class="mt-2" :message="form.errors.destination_customs_code" />

                            <div class="mt-4 flex gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                                    :disabled="form.processing || previewState.loading || !form.container_id || !previewState.priceBreakdown || !form.terms_accepted"
                                    @click="submit"
                                >
                                    Submit request
                                </button>
                            </div>

                            <label class="mt-4 inline-flex items-start gap-2 text-xs text-slate-600">
                                <input
                                    v-model="form.terms_accepted"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-700"
                                >
                                <span>I confirm cargo and compliance details are accurate, and accept commercial terms.</span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.terms_accepted" />
                        </section>
                    </div>
                </div>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900">My latest requests</h3>
                    <div v-if="!props.my_recent_requests.length" class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs text-slate-600">
                        No submitted requests yet.
                    </div>
                    <div v-else class="mt-3 space-y-2">
                        <div v-for="item in props.my_recent_requests" :key="item.id" class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">Request #{{ item.id }}</p>
                                <span class="text-xs font-semibold text-slate-600">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(item.price || 0)) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ item.container_serial || 'Container not assigned' }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Equipment:
                                {{ item.container_operational_status ? statusLabelRequest(item.container_operational_status) : '—' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ item.start_date && item.end_date ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(item.start_date)) + ' → ' + new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(item.end_date)) : '—' }}</p>
                            <button
                                v-if="statusDetailEligibleRequest(item)"
                                type="button"
                                class="mt-1 text-left text-[11px] font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2"
                                @click="openStatusDetailRequest(item)"
                            >
                                Status notes
                            </button>
                            <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">{{ statusLabelRequest(item.status) }}</span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700">{{ statusLabelRequest(item.payment_status) }}</span>
                                <Link
                                    v-if="item.can_view_iot_monitor"
                                    :href="route('rentals.monitor', item.id, false)"
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                    <span>IoT view</span>
                                </Link>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-500"
                                >
                                    IoT after approval
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <Modal :show="reasonModalRequest.show" max-width="md" @close="closeReasonModalRequest">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ reasonModalRequest.title }}</h3>
                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ reasonModalRequest.body }}</p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="closeReasonModalRequest"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
