<script setup>
import ContainerMatchListCard from '@/Components/Rentals/ContainerMatchListCard.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    containers: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div>
        <ContainerMatchListCard
            :containers="containers"
            :selected-container-id="form.container_id"
            :loading="loading"
            @update:selected-container-id="form.container_id = $event"
        />
    </div>
</template>
