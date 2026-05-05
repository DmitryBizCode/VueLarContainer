<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import { useFlashToToast } from '@/composables/useFlashToToast';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';

useFlashToToast();

const page = usePage();

const activeRoute = computed(() => {
    return page.url.split('/').filter(Boolean)[0] || 'home';
});

const showTopMessage = ref(false);
const showBottomMessage = ref(false);

const handleScroll = () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const documentHeight = document.documentElement.scrollHeight;
    const windowHeight = window.innerHeight;
    
    // Повідомлення над хедером (тільки при дуже сильному скролі вверх - більше 100px над початком)
    if (scrollTop < -100) {
        showTopMessage.value = true;
    } else {
        showTopMessage.value = false;
    }
    
    // Повідомлення під футером (тільки при дуже сильному скролі вниз - більше 100px після кінця)
    const scrollBottom = scrollTop + windowHeight;
    if (scrollBottom > documentHeight + 100) {
        showBottomMessage.value = true;
    } else {
        showBottomMessage.value = false;
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col relative">
        <!-- Navbar з повідомленням над ним -->
        <div class="sticky top-0 z-40">
            <!-- Повідомлення над хедером (всередині навігації) -->
            <div
                v-show="showTopMessage"
                class="bg-gray-900 border-b border-gray-800 py-3 px-6"
            >
                <div class="max-w-7xl mx-auto text-center">
                    <p class="text-sm md:text-base font-medium text-gray-300">
                        <span class="inline-block mr-2 text-xl">👀</span>
                        А що ти тут шукаєш? Повертайся до контенту!
                    </p>
                </div>
            </div>
            <Navbar :active-route="activeRoute" />
        </div>

        <!-- Main Content з плавними переходами -->
        <main class="app-shell-main flex-grow transition-all duration-500 ease-in-out">
            <div class="animate-fade-in">
                <slot />
            </div>
        </main>

        <!-- Footer з повідомленням під ним -->
        <div>
            <Footer />
            <!-- Повідомлення під футером (всередині футера) -->
            <div
                v-show="showBottomMessage"
                class="bg-gray-900 border-t border-gray-800 py-3 px-6"
            >
                <div class="max-w-7xl mx-auto text-center">
                    <p class="text-sm md:text-base font-medium text-gray-300">
                        <span class="inline-block mr-2 text-xl">🚢</span>
                        Це кінець сторінки! Повертайся до початку або зв'яжись з нами!
                    </p>
                </div>
            </div>
        </div>

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
