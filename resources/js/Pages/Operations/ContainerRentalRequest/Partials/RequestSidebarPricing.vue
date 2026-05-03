<script setup>
import PriceBreakdownCard from '@/Components/Rentals/PriceBreakdownCard.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    ports: {
        type: Array,
        default: () => [],
    },
    estimatedPrice: {
        type: Number,
        default: 0,
    },
    routeContext: {
        type: Object,
        default: () => ({}),
    },
    priceBreakdown: {
        type: [Object, Array, null],
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    assignedVessel: {
        type: [Object, null],
        default: null,
    },
    routePlan: {
        type: [Object, Array, null],
        default: null,
    },
});
</script>

<template>
    <PriceBreakdownCard
        :ports="ports"
        :estimated-price="estimatedPrice"
        :route-context="routeContext"
        :price-breakdown="priceBreakdown"
        :loading="loading"
        :start-date="form.start_date"
        :assigned-vessel="assignedVessel"
        :route-plan="routePlan"
    />
</template>
