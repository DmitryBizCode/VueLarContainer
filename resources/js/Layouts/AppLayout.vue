<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const activeRoute = computed(() => {
    return page.url.split('/').filter(Boolean)[0] || 'home';
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50">
        <!-- Navbar -->
        <Navbar :active-route="activeRoute" />

        <!-- Main Content -->
        <main class="flex-grow">
            <slot />
        </main>

        <!-- Footer -->
        <Footer />
    </div>
</template>
