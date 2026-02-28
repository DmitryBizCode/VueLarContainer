<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    actions: {
        type: Array,
        required: true,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const iconPath = (icon) => {
    if (icon === 'dashboard') return 'M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5zm6 8.25A2 2 0 0 1 11 15v.25A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25V15a2 2 0 0 1 2-2h4zm8-8.25A1.75 1.75 0 0 0 15.25 3h-1.5A1.75 1.75 0 0 0 12 4.75v1.5A1.75 1.75 0 0 0 13.75 8h1.5A1.75 1.75 0 0 0 17 6.25v-1.5zm-2 6.25h-2a1 1 0 0 0-1 1v3.25A1.75 1.75 0 0 0 13.75 17h1.5A1.75 1.75 0 0 0 17 15.25V12a1 1 0 0 0-1-1z';
    if (icon === 'profile') return 'M10 2.5a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm-5.5 12A3.5 3.5 0 0 1 8 11h4a3.5 3.5 0 0 1 3.5 3.5v.75A1.75 1.75 0 0 1 13.75 17h-7.5A1.75 1.75 0 0 1 4.5 15.25v-.75z';
    if (icon === 'rental') return 'M2.75 6A1.75 1.75 0 0 1 4.5 4.25h11A1.75 1.75 0 0 1 17.25 6v6.25A1.75 1.75 0 0 1 15.5 14H4.5a1.75 1.75 0 0 1-1.75-1.75V6zm3 9h8.5a.75.75 0 0 1 0 1.5h-8.5a.75.75 0 0 1 0-1.5z';
    return 'M9.5 2.75a6.75 6.75 0 1 0 4.596 11.693l2.23 2.23a.75.75 0 1 0 1.061-1.06l-2.23-2.231A6.75 6.75 0 0 0 9.5 2.75zm0 1.5a5.25 5.25 0 1 1 0 10.5 5.25 5.25 0 0 1 0-10.5z';
};
</script>

<template>
    <nav class="space-y-2">
        <Link
            v-for="action in actions"
            :key="action.key"
            :href="action.href"
            :title="collapsed ? action.label : ''"
            class="group relative flex items-center border text-sm font-semibold transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
            :class="[
                action.active
                    ? 'border-slate-100/70 bg-slate-100 text-slate-900 shadow-sm shadow-slate-950/20'
                    : 'border-transparent text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white',
                collapsed || compact
                    ? 'h-10 w-full justify-center rounded-2xl px-0'
                    : 'justify-between rounded-xl px-3 py-2.5',
                action.active && (collapsed || compact) ? 'ring-1 ring-slate-100/80' : '',
            ]"
        >
            <div class="flex items-center" :class="collapsed || compact ? '' : 'gap-2.5'">
                <span
                    class="inline-flex items-center justify-center transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="[
                        collapsed || compact ? 'h-8 w-8 rounded-xl' : 'h-7 w-7 rounded-lg',
                        action.active ? 'bg-slate-900/10 text-slate-900' : 'bg-white/15 text-slate-200 group-hover:bg-white/20',
                    ]"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path :d="iconPath(action.icon)" />
                    </svg>
                </span>
                <span
                    class="overflow-hidden whitespace-nowrap text-sm transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="collapsed || compact ? 'max-w-0 opacity-0' : 'max-w-[10rem] opacity-100'"
                >
                    {{ action.label }}
                </span>
            </div>
            <span v-if="!(collapsed || compact)" class="h-2 w-2 rounded-full transition" :class="action.active ? 'bg-blue-700' : 'bg-white/30 group-hover:bg-white/60'" />
        </Link>
    </nav>
</template>
