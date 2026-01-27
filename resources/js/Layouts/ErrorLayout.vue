<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: 'Error',
    },
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-gray-50">
        <Head :title="title || 'Error'" />
        <slot />
    </div>
</template>
