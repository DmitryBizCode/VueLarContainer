<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import QuickActionsNav from '@/Components/QuickActionsNav.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    quickActions: {
        type: Array,
        required: true,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    mobile: {
        type: Boolean,
        default: false,
    },
    userName: {
        type: String,
        default: 'User',
    },
    userEmail: {
        type: String,
        default: '',
    },
    userInitials: {
        type: String,
        default: 'U',
    },
    userPhotoUrl: {
        type: String,
        default: null,
    },
});

defineEmits(['close-mobile']);
</script>

<template>
    <aside class="relative flex h-auto flex-col overflow-hidden bg-white text-slate-100">
        <div class="flex h-16 items-center justify-between bg-white px-5">
            <Link :href="route('dashboard')" class="inline-flex items-center gap-2">
                <ApplicationLogo class="h-8 w-8 rounded-lg bg-blue-100 p-1.5 text-blue-700" />
                <span
                    class="overflow-hidden whitespace-nowrap text-sm font-bold tracking-wide text-slate-800 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="collapsed && !mobile ? 'max-w-0 opacity-0' : 'max-w-[11rem] opacity-100'"
                >
                    Romeo Logistics
                </span>
            </Link>

            <button
                v-if="mobile"
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                @click="$emit('close-mobile')"
            >
                <span class="sr-only">Close sidebar</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <div
            class="bg-gradient-to-b from-slate-900 via-blue-950 to-slate-900 pb-3 pt-4 shadow-inner shadow-slate-950/30"
            :class="mobile ? 'rounded-br-[2.75rem] rounded-tr-[2.75rem]' : 'rounded-br-[3.25rem] rounded-tr-[3.25rem]'"
        >
            <div class="px-4 pb-3">
            <div
                class="rounded-2xl border border-white/20 bg-white/10 p-3.5 shadow-sm shadow-slate-950/30 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                :class="collapsed && !mobile ? 'flex items-center justify-center px-2.5 py-3' : ''"
            >
                <div
                    class="flex items-center"
                    :class="collapsed && !mobile ? '' : 'gap-3'"
                >
                    <img
                        v-if="userPhotoUrl"
                        :src="userPhotoUrl"
                        :alt="userName"
                        class="h-10 w-10 shrink-0 rounded-xl object-cover"
                    />
                    <span
                        v-else
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/20 text-sm font-bold text-white"
                    >
                        {{ userInitials }}
                    </span>
                    <div
                        class="min-w-0 overflow-hidden whitespace-nowrap transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :class="collapsed && !mobile ? 'max-w-0 opacity-0' : 'max-w-[12rem] opacity-100'"
                    >
                        <p class="truncate text-sm font-semibold text-white">{{ userName }}</p>
                        <p class="truncate text-xs text-slate-300">{{ userEmail }}</p>
                    </div>
                </div>
            </div>
            </div>

            <div class="px-4 py-2">
                <QuickActionsNav
                    :actions="quickActions"
                    :collapsed="collapsed && !mobile"
                    :compact="collapsed && !mobile"
                />
            </div>

            <div class="px-4 pb-4 pt-1">
                <p
                    class="mx-auto overflow-hidden whitespace-nowrap text-center text-[11px] font-medium text-slate-300 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="collapsed && !mobile ? 'max-w-0 opacity-0' : 'max-w-[14rem] opacity-100'"
                >
                    Company operations workspace
                </p>
            </div>
        </div>
    </aside>
</template>
