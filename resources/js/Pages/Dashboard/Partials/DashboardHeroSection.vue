<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    shipmentOverview: {
        type: Object,
        required: true,
    },
    operationalAttentionCount: {
        type: Number,
        required: true,
    },
});
</script>

<template>
    <section class="mb-6 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 p-6 text-white shadow-sm">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <p class="text-xs uppercase tracking-[0.16em] text-blue-100/80">Live overview</p>
                <h2 class="mt-2 text-2xl font-bold">Maritime operations with clear status, finance and routing signals</h2>
                <p class="mt-2 max-w-2xl text-sm text-blue-100/85">
                    Dashboard combines rental lifecycle, shipment timing, payment state and incident load from your current database records.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                        In transit: {{ shipmentOverview.inTransitCount }}
                    </span>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                        Upcoming arrivals: {{ shipmentOverview.upcomingArrivalsCount }}
                    </span>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                        Attention items: {{ operationalAttentionCount }}
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                <Link :href="route('rentals.request.create')" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-blue-50">
                    Create rental
                </Link>
                <Link :href="route('contact')" class="inline-flex items-center justify-center rounded-xl border border-white/35 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    Contact support
                </Link>
            </div>
        </div>
    </section>
</template>
