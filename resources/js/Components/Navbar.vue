<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    activeRoute: {
        type: String,
        default: '',
    },
});

const isMenuOpen = ref(false);
const isScrolled = ref(false);

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

const isActive = (route) => {
    return props.activeRoute === route;
};

const handleScroll = () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    isScrolled.value = scrollTop > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <nav
            :class="[
                'sticky top-0 z-40 transition-all duration-300',
                isScrolled 
                    ? 'bg-gray-900/95 backdrop-blur-md shadow-lg' 
                    : 'bg-gray-900'
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    <!-- Logo з анімацією -->
                    <div class="flex-shrink-0">
                        <Link 
                            href="/" 
                            class="group flex items-center space-x-2"
                        >
                            <div class="relative">
                                <div class="absolute inset-0 bg-blue-600 rounded-lg blur-md opacity-0 group-hover:opacity-75 transition-opacity duration-300 -z-10"></div>
                                <div class="relative bg-gradient-to-br from-blue-600 to-blue-800 px-4 py-2 rounded-lg transition-all duration-300 group-hover:scale-105 group-hover:shadow-lg group-hover:shadow-blue-600/50">
                                    <span class="text-xl font-bold text-white">Logistics Co.</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex md:items-center md:space-x-3">
                        <Link
                            href="/"
                            :class="[
                                'px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300 relative group overflow-hidden',
                                isActive('home') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-600/50' 
                                    : 'text-gray-300 hover:text-white hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700'
                            ]"
                        >
                            <span class="relative z-10 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Home
                            </span>
                            <span
                                v-if="isActive('home')"
                                class="absolute inset-0 bg-blue-600 rounded-lg animate-pulse opacity-30"
                            ></span>
                        </Link>
                        <Link
                            href="/services"
                            :class="[
                                'px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300 relative group overflow-hidden',
                                isActive('services') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-600/50' 
                                    : 'text-gray-300 hover:text-white hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700'
                            ]"
                        >
                            <span class="relative z-10 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                Services
                            </span>
                            <span
                                v-if="isActive('services')"
                                class="absolute inset-0 bg-blue-600 rounded-lg animate-pulse opacity-30"
                            ></span>
                        </Link>
                        <Link
                            href="/contact"
                            :class="[
                                'px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300 relative group overflow-hidden',
                                isActive('contact') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-600/50' 
                                    : 'text-gray-300 hover:text-white hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700'
                            ]"
                        >
                            <span class="relative z-10 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Contact
                            </span>
                            <span
                                v-if="isActive('contact')"
                                class="absolute inset-0 bg-blue-600 rounded-lg animate-pulse opacity-30"
                            ></span>
                        </Link>
                        <Link
                            href="/login"
                            class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 text-white hover:from-blue-700 hover:via-blue-800 hover:to-blue-700 transition-all duration-300 shadow-lg hover:shadow-xl hover:shadow-blue-600/50 transform hover:-translate-y-0.5 flex items-center gap-2 relative overflow-hidden group"
                        >
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="relative z-10">Account Login</span>
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                        </Link>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button
                            @click="toggleMenu"
                            class="inline-flex items-center justify-center p-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            aria-expanded="false"
                        >
                            <span class="sr-only">Open main menu</span>
                            <transition
                                enter-active-class="transition-all duration-300"
                                leave-active-class="transition-all duration-300"
                            >
                                <svg
                                    v-if="!isMenuOpen"
                                    class="block h-6 w-6"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <svg
                                    v-else
                                    class="block h-6 w-6"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </transition>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-4"
            >
                <div
                    v-show="isMenuOpen"
                    class="md:hidden border-t border-gray-800"
                >
                    <div class="px-2 pt-2 pb-3 space-y-2 bg-gray-900/95 backdrop-blur-md">
                        <Link
                            href="/"
                            :class="[
                                'flex items-center gap-3 px-4 py-3 rounded-lg text-base font-semibold transition-all duration-300',
                                isActive('home') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg' 
                                    : 'text-gray-300 hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700 hover:text-white'
                            ]"
                            @click="toggleMenu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Home
                        </Link>
                        <Link
                            href="/services"
                            :class="[
                                'flex items-center gap-3 px-4 py-3 rounded-lg text-base font-semibold transition-all duration-300',
                                isActive('services') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg' 
                                    : 'text-gray-300 hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700 hover:text-white'
                            ]"
                            @click="toggleMenu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Services
                        </Link>
                        <Link
                            href="/contact"
                            :class="[
                                'flex items-center gap-3 px-4 py-3 rounded-lg text-base font-semibold transition-all duration-300',
                                isActive('contact') 
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg' 
                                    : 'text-gray-300 hover:bg-gradient-to-r hover:from-gray-800 hover:to-gray-700 hover:text-white'
                            ]"
                            @click="toggleMenu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Contact
                        </Link>
                        <Link
                            href="/login"
                            class="flex items-center gap-3 px-4 py-3 rounded-lg text-base font-semibold bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 text-white hover:from-blue-700 hover:via-blue-800 hover:to-blue-700 transition-all duration-300 shadow-lg"
                            @click="toggleMenu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Account Login
                        </Link>
                    </div>
                </div>
            </transition>
    </nav>
</template>
