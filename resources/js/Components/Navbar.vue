<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    activeRoute: {
        type: String,
        default: '',
    },
});

const isMenuOpen = ref(false);

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

const isActive = (route) => {
    return props.activeRoute === route;
};
</script>

<template>
    <nav class="bg-gray-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <Link href="/" class="text-xl font-bold text-white hover:text-blue-400 transition-colors">
                        Logistics Co.
                    </Link>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex md:items-center md:space-x-8">
                    <Link
                        href="/"
                        :class="[
                            'px-3 py-2 rounded-md text-sm font-medium transition-colors',
                            isActive('home') 
                                ? 'bg-blue-600 text-white' 
                                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                        ]"
                    >
                        Home
                    </Link>
                    <Link
                        href="/services"
                        :class="[
                            'px-3 py-2 rounded-md text-sm font-medium transition-colors',
                            isActive('services') 
                                ? 'bg-blue-600 text-white' 
                                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                        ]"
                    >
                        Services
                    </Link>
                    <Link
                        href="/contact"
                        :class="[
                            'px-3 py-2 rounded-md text-sm font-medium transition-colors',
                            isActive('contact') 
                                ? 'bg-blue-600 text-white' 
                                : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                        ]"
                    >
                        Contact
                    </Link>
                    <Link
                        href="/login"
                        class="px-3 py-2 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                    >
                        Account Login
                    </Link>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button
                        @click="toggleMenu"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                        aria-expanded="false"
                    >
                        <span class="sr-only">Open main menu</span>
                        <!-- Hamburger icon -->
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
                        <!-- Close icon -->
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
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div
            v-show="isMenuOpen"
            class="md:hidden"
        >
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-gray-800">
                <Link
                    href="/"
                    :class="[
                        'block px-3 py-2 rounded-md text-base font-medium transition-colors',
                        isActive('home') 
                            ? 'bg-blue-600 text-white' 
                            : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                    ]"
                    @click="toggleMenu"
                >
                    Home
                </Link>
                <Link
                    href="/services"
                    :class="[
                        'block px-3 py-2 rounded-md text-base font-medium transition-colors',
                        isActive('services') 
                            ? 'bg-blue-600 text-white' 
                            : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                    ]"
                    @click="toggleMenu"
                >
                    Services
                </Link>
                <Link
                    href="/contact"
                    :class="[
                        'block px-3 py-2 rounded-md text-base font-medium transition-colors',
                        isActive('contact') 
                            ? 'bg-blue-600 text-white' 
                            : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                    ]"
                    @click="toggleMenu"
                >
                    Contact
                </Link>
                <Link
                    href="/login"
                    class="block px-3 py-2 rounded-md text-base font-medium bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                    @click="toggleMenu"
                >
                    Account Login
                </Link>
            </div>
        </div>
    </nav>
</template>
