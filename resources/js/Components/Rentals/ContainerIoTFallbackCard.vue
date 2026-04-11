<script setup>
const props = defineProps({
    rental: {
        type: Object,
        required: true,
    },
    container: {
        type: Object,
        default: () => null,
    },
});
</script>

<template>
    <section class="rounded-3xl border border-dashed border-slate-300 bg-slate-50/80 p-5 text-sm text-slate-700">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-900 text-slate-50 shadow-sm">
                <span class="text-xs font-semibold">IoT</span>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">IoT sensors are not connected yet</p>
                <p class="text-xs text-slate-500">
                    This container is operating without live telemetry. You can still track the shipment using rental details below.
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white/80 px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Container</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ container?.serial_number || 'Not assigned' }}
                </p>
                <p v-if="container?.type" class="mt-0.5 text-[11px] text-slate-500">
                    {{ container.type }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Route</p>
                <p class="mt-1 text-xs text-slate-700">
                    {{ rental.origin_port?.name || 'Origin' }}
                    <span class="text-slate-400"> → </span>
                    {{ rental.destination_port?.name || 'Destination' }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Shipment profile</p>
                <p class="mt-1 text-xs text-slate-700">
                    Request weight:
                    <span class="font-semibold">
                        {{
                            rental.requested_weight
                                ? `${Number(rental.requested_weight).toLocaleString('en-US')} kg`
                                : 'not specified'
                        }}
                    </span>
                </p>
                <p class="mt-0.5 text-xs text-slate-700">
                    Volume:
                    <span class="font-semibold">
                        {{
                            rental.cargo_volume_cbm
                                ? `${Number(rental.cargo_volume_cbm).toLocaleString('en-US')} cbm`
                                : 'not specified'
                        }}
                    </span>
                </p>
            </div>
        </div>
    </section>
</template>

