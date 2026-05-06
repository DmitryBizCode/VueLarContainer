<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import { useFlashToToast } from '@/composables/useFlashToToast';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';

useFlashToToast();

const page = usePage();

const activeRoute = computed(() => {
    return page.url.split('/').filter(Boolean)[0] || 'home';
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <div class="sticky top-0 z-40">
            <Navbar :active-route="activeRoute" />
        </div>

        <main class="app-shell-main flex-grow transition-all duration-500 ease-in-out">
            <div class="animate-fade-in">
                <slot />
            </div>
        </main>

        <Footer />

        <Toaster position="top-right" :rich-colors="true" :expand="true" :visible-toasts="5" :gap="8" />
    </div>
</template>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out;
}
</style>
