<script setup>
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/composables/useToast';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import RequestCargoStep from './Partials/RequestCargoStep.vue';
import RequestContainerStep from './Partials/RequestContainerStep.vue';
import RequestPickupStep from './Partials/RequestPickupStep.vue';
import RequestRecentList from './Partials/RequestRecentList.vue';
import RequestRouteStep from './Partials/RequestRouteStep.vue';
import RequestSidebarPricing from './Partials/RequestSidebarPricing.vue';
import RequestSidebarSubmit from './Partials/RequestSidebarSubmit.vue';
import RequestStepperNav from './Partials/RequestStepperNav.vue';

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
            post_arrival_min_days: 2,
            post_arrival_max_days: 3,
            loading_buffer_days: 3,
            time_load_days: 2,
            time_unload_days: 3,
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
    assignedVessel: null,
    routePlan: null,
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

const minStartDate = computed(() => new Date().toISOString().slice(0, 10));

const selectedOriginPort = computed(() => {
    if (!form.origin_port_id || form.route_mode !== 'ports') return null;
    return (props.origin_ports || []).find((p) => String(p.id) === String(form.origin_port_id)) ?? null;
});

const selectedRoute = computed(() => {
    if (form.route_mode !== 'route' || !form.route_id) return null;
    return props.routes.find((r) => String(r.id) === String(form.route_id)) ?? null;
});

const maxStartDate = computed(() => {
    const timeLoadDays = props.logistics_config?.time_load_days ?? 2;
    let vesselDepartureAt = null;
    if (form.route_mode === 'ports') {
        vesselDepartureAt = selectedOriginPort.value?.vessel_departure_at ?? null;
    } else if (form.route_mode === 'route') {
        vesselDepartureAt = selectedRoute.value?.origin_vessel_departure_at ?? null;
    }
    if (!vesselDepartureAt) return '';
    const departureDate = new Date(vesselDepartureAt).toISOString().slice(0, 10);
    return addUtcCalendarDays(departureDate, -timeLoadDays);
});

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
    const unloadDays =
        props.logistics_config?.time_unload_days ??
        Math.max(
            Number(props.logistics_config?.post_arrival_min_days ?? 2),
            Number(props.logistics_config?.post_arrival_max_days ?? 3),
        );
    const segments = previewState.routePlan?.segments;
    if (Array.isArray(segments) && segments.length > 0) {
        const last = segments[segments.length - 1];
        const arrival = last?.planned_arrival;
        if (arrival) {
            const d = new Date(arrival).toISOString().slice(0, 10);
            return addUtcCalendarDays(d, unloadDays);
        }
    }
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
        previewState.assignedVessel = data.assigned_vessel || null;
        previewState.routePlan = data.route_plan || null;
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
        previewState.assignedVessel = null;
        previewState.routePlan = null;
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
        if (maxStartDate.value && start > maxStartDate.value) {
            form.start_date = maxStartDate.value;
        }
    }
);

watch(
    () => maxStartDate.value,
    (maxS) => {
        if (maxS && form.start_date && form.start_date > maxS) {
            form.start_date = maxS;
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
            <PageHeader eyebrow="Operations" title="Container rental request">
                <template #aside>
                    <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                    End-to-end request and approval workflow
                </span>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <RequestStepperNav
                    :steps="STEPS"
                    :current-step="currentStep"
                    @go-step="currentStep = $event"
                />

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="space-y-6 xl:col-span-2">
                        <div v-show="currentStep === 1">
                            <RequestRouteStep
                                :form="form"
                                :routes="props.routes"
                                :ports="props.ports"
                                :origin-ports="props.origin_ports"
                                :routing-priority-options="props.routing_priority_options"
                                :min-start-date="minStartDate"
                                :max-start-date="maxStartDate"
                                :min-end-date="minEndDate"
                            />
                        </div>

                        <RequestCargoStep
                            v-show="currentStep === 2"
                            :form="form"
                            :cargo-types="props.cargo_types"
                            :priority-levels="props.priority_levels"
                            :incoterms="props.incoterms"
                            :loading-types="props.loading_types"
                            :sustainability-options="props.sustainability_options"
                            @toggle-cargo-type="toggleCargoType"
                        />

                        <RequestPickupStep
                            v-show="currentStep === 3"
                            :form="form"
                                        :countries="props.countries"
                                        :user-country-id="props.user_country_id"
                            :show-pickup-window="showPickupWindow"
                            @update:show-pickup-window="showPickupWindow = $event"
                        />

                        <RequestContainerStep
                            v-show="currentStep === 4"
                            :form="form"
                            :containers="previewState.availableContainers"
                            :loading="previewState.loading"
                        />
                    </div>

                    <div class="space-y-6">
                        <RequestSidebarPricing
                            :form="form"
                            :ports="props.ports"
                            :estimated-price="previewState.estimatedPrice"
                            :route-context="previewState.routeContext"
                            :price-breakdown="previewState.priceBreakdown"
                            :loading="previewState.loading"
                            :assigned-vessel="previewState.assignedVessel"
                            :route-plan="previewState.routePlan"
                        />

                        <RequestSidebarSubmit
                            v-show="currentStep === 4"
                            :form="form"
                            :preview-loading="previewState.loading"
                            :preview-error="previewState.previewError"
                            :has-price-breakdown="Boolean(previewState.priceBreakdown)"
                            @submit="submit"
                        />
                    </div>
                </div>

                <RequestRecentList
                    :items="props.my_recent_requests"
                    :status-label="statusLabelRequest"
                    :status-detail-eligible="statusDetailEligibleRequest"
                    @open-status-detail="openStatusDetailRequest"
                />
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
