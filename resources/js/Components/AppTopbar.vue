<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isAdminContext = computed(() => (page.url || '').startsWith('/admin'));
const profileHref = computed(() => (isAdminContext.value ? route('admin.profile.edit') : route('profile.edit')));

defineProps({
    userName: {
        type: String,
        required: true,
    },
    userEmail: {
        type: String,
        required: true,
    },
    userInitials: {
        type: String,
        required: true,
    },
    userPhotoUrl: {
        type: String,
        default: null,
    },
});

defineEmits(['toggle-mobile-sidebar']);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
        <div class="flex h-16 items-center justify-between px-4 sm:px-5 lg:px-6">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 lg:hidden"
                    @click="$emit('toggle-mobile-sidebar')"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z" clip-rule="evenodd" />
                    </svg>
                </button>
                <Link
                    v-if="isAdminContext"
                    :href="route('dashboard')"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                >
                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to app
                </Link>
                <div class="hidden pl-1 sm:block lg:pl-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Operations panel</p>
                    <p class="text-sm font-semibold text-slate-700">Unified logistics workspace</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="hidden min-w-[240px] items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 lg:flex">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.5 3a6.5 6.5 0 0 0-5.197 10.405l-2.151 2.151a.75.75 0 0 0 1.06 1.061l2.152-2.152A6.5 6.5 0 1 0 9.5 3zm-5 6.5a5 5 0 1 1 10 0 5 5 0 0 1-10 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-2 text-xs font-medium text-slate-500">Search modules and records</span>
                </div>

                <NotificationBell />

                <Dropdown align="right" width="56">
                    <template #trigger>
                        <span class="inline-flex rounded-md">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-600 bg-slate-700 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:border-slate-500 hover:bg-slate-600 focus:outline-none"
                            >
                                <img
                                    v-if="userPhotoUrl"
                                    :src="userPhotoUrl"
                                    :alt="userName"
                                    class="h-8 w-8 rounded-lg object-cover ring-1 ring-white/20"
                                />
                                <span
                                    v-else
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-500/80 text-xs font-bold text-white"
                                >
                                    {{ userInitials }}
                                </span>
                                <span class="hidden max-w-[160px] truncate text-left sm:inline-block">
                                    {{ userName }}
                                </span>
                                <svg class="h-4 w-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </span>
                    </template>

                    <template #content>
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-400">Signed in</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ userName }}</p>
                            <p class="truncate text-xs text-slate-500">{{ userEmail }}</p>
                        </div>
                        <DropdownLink :href="profileHref">
                            Profile
                        </DropdownLink>
                        <div class="my-1 border-t border-slate-100"></div>
                        <DropdownLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Log Out
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </div>
    </header>
</template>
