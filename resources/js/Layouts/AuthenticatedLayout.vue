<script setup>
import AppSidebar from '@/Components/AppSidebar.vue';
import AppTopbar from '@/Components/AppTopbar.vue';
import { useFlashToToast } from '@/composables/useFlashToToast';
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';
import { useQuickActions } from '@/composables/useQuickActions';
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

useFlashToToast();

const SIDEBAR_STORAGE_KEY = 'auth-shell-sidebar-collapsed';

const getInitialSidebarCollapsed = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
};

const page = usePage();
const mobileSidebarOpen = ref(false);
const sidebarCollapsed = ref(getInitialSidebarCollapsed());
const transitionsReady = ref(false);

const { quickActions } = useQuickActions();

const user = computed(() => page.props.auth?.user || null);
const userInitials = computed(() => {
    if (!user.value) {
        return 'U';
    }

    return [user.value.first_name?.[0], user.value.last_name?.[0]].filter(Boolean).join('').toUpperCase() || 'U';
});

const userName = computed(() => {
    if (!user.value) {
        return 'User';
    }

    return [user.value.first_name, user.value.last_name].filter(Boolean).join(' ').trim() || user.value.email;
});

const userPhotoUrl = computed(() => {
    const u = user.value;
    if (!u) return null;
    const url = u.photo_url;
    if (url) return url;
    const p = u.photo;
    if (!p) return null;
    const s = String(p);
    if (s.startsWith('http')) return s;
    if (s.includes('/')) return '/' + s.replace(/^\//, '');
    return '/image/profile/' + s;
});

const desktopSidebarClass = computed(() => (sidebarCollapsed.value ? 'w-20' : 'w-72'));

const toggleSidebarCollapsed = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const openMobileSidebar = () => {
    mobileSidebarOpen.value = true;
};

const closeMobileSidebar = () => {
    mobileSidebarOpen.value = false;
};

onMounted(() => {
    transitionsReady.value = true;
});

watch(sidebarCollapsed, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(SIDEBAR_STORAGE_KEY, value ? '1' : '0');
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <div
            class="fixed inset-0 z-50 bg-slate-950/40 transition-opacity duration-200 lg:hidden"
            :class="mobileSidebarOpen ? 'opacity-100' : 'pointer-events-none opacity-0'"
            @click="closeMobileSidebar"
        />

        <div class="fixed inset-y-0 left-0 z-50 w-72 transform transition-transform duration-300 lg:hidden" :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <AppSidebar
                :quick-actions="quickActions"
                :collapsed="false"
                :user-name="userName"
                :user-email="user?.email || ''"
                :user-initials="userInitials"
                :user-photo-url="userPhotoUrl"
                mobile
                @close-mobile="closeMobileSidebar"
            />
        </div>

        <div class="relative flex min-h-screen bg-white">
            <div
                class="relative z-40 hidden self-start lg:sticky lg:block"
                :class="[desktopSidebarClass, transitionsReady ? 'transition-[width] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]' : 'transition-none']"
            >
                <AppSidebar
                    :quick-actions="quickActions"
                    :collapsed="sidebarCollapsed"
                    :user-name="userName"
                    :user-email="user?.email || ''"
                    :user-initials="userInitials"
                    :user-photo-url="userPhotoUrl"
                />

                <button
                    type="button"
                    class="group absolute -right-2 top-24 z-30 inline-flex h-16 w-5 items-center justify-center rounded-full border border-slate-200 bg-gradient-to-b from-white to-slate-100 text-slate-500 shadow-sm shadow-slate-300/60 transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] hover:h-[4.25rem] hover:w-[1.35rem] hover:text-slate-800 focus:outline-none"
                    @click="toggleSidebarCollapsed"
                >
                    <span class="sr-only">Toggle sidebar</span>
                    <svg class="h-3 w-3 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 010 1.06L8.06 10l4.72 4.72a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="min-w-0 flex-1 bg-white">
                <AppTopbar
                    :user-name="userName"
                    :user-email="user?.email || ''"
                    :user-initials="userInitials"
                    :user-photo-url="userPhotoUrl"
                    @toggle-mobile-sidebar="openMobileSidebar"
                />

                <header class="border-b border-slate-200 bg-white/80" v-if="$slots.header">
                    <div class="px-4 py-6 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <main class="app-shell-main px-0 pb-10">
                    <slot />
                </main>
            </div>
        </div>
        <Toaster
            position="top-right"
            :rich-colors="true"
            :expand="true"
            :visible-toasts="5"
            :gap="8"
        />
    </div>
</template>
