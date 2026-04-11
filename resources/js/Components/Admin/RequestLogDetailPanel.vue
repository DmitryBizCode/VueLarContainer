<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
    log: { type: Object, default: null },
});

const emit = defineEmits(['close']);

function gmtLabel(min) {
    if (min == null) return '—';
    const h = Math.floor(Math.abs(min) / 60);
    const m = Math.abs(min) % 60;
    const sign = min >= 0 ? '+' : '-';
    return `UTC${sign}${h}${m ? ':' + String(m).padStart(2, '0') : ''}`;
}

const formatDate = (v) =>
    v
        ? new Intl.DateTimeFormat('en-GB', {
              weekday: 'short',
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
          }).format(new Date(v))
        : '—';

function onKeydown(e) {
    if (e.key === 'Escape') emit('close');
}

watch(
    () => props.show,
    (visible) => {
        if (visible) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-250 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="show"
                class="fixed inset-0 z-50 flex justify-end"
                aria-modal="true"
                role="dialog"
            >
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 backdrop-blur-none"
                    enter-to-class="opacity-100 backdrop-blur-sm"
                    leave-active-class="transition duration-250 ease-in"
                    leave-from-class="opacity-100 backdrop-blur-sm"
                    leave-to-class="opacity-0 backdrop-blur-none"
                >
                    <div
                        v-show="show"
                        class="absolute inset-0 bg-slate-900/20 backdrop-blur-sm transition-all duration-300 ease-out"
                        aria-hidden="true"
                        @click="emit('close')"
                    />
                </Transition>
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-250 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div
                        v-show="show"
                        class="relative flex w-full max-w-lg flex-col bg-white shadow-2xl"
                    >
                        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Request log</h2>
                                <p v-if="log" class="mt-0.5 font-mono text-xs text-slate-500">#{{ log.id }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Close"
                                @click="emit('close')"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="log" class="flex-1 overflow-y-auto">
                            <!-- Block: Timestamp -->
                            <div class="border-b border-slate-100 px-6 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Timestamp</p>
                                <p class="mt-1 font-mono text-sm text-slate-800">{{ formatDate(log.created_at) }}</p>
                            </div>

                            <!-- Block: Request -->
                            <div class="border-b border-slate-100 px-6 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Request</p>
                                <div class="mt-2 space-y-1.5">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">Method</span>
                                        <span class="font-mono text-sm font-medium text-slate-800">{{ log.method }}</span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-slate-500">Path</span>
                                        <span class="break-all font-mono text-sm text-slate-800">{{ log.path }}</span>
                                    </div>
                                    <div v-if="log.referer" class="flex flex-col gap-1">
                                        <span class="text-slate-500">Referer</span>
                                        <span class="break-all text-xs text-slate-600">{{ log.referer }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Block: User -->
                            <div class="border-b border-slate-100 px-6 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">User</p>
                                <div class="mt-2">
                                    <template v-if="log.user_id">
                                        <Link
                                            :href="route('admin.request-logs.user-chain', log.user_id)"
                                            class="font-medium text-slate-800 hover:text-slate-600 hover:underline"
                                        >
                                            {{ log.user_name || log.user_email || 'User #' + log.user_id }}
                                        </Link>
                                        <p v-if="log.user_email" class="mt-0.5 text-sm text-slate-500">{{ log.user_email }}</p>
                                        <Link
                                            :href="route('admin.request-logs.user-chain', log.user_id)"
                                            class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900"
                                        >
                                            View full chain
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </template>
                                    <p v-else class="text-sm text-slate-500">— Guest</p>
                                </div>
                            </div>

                            <!-- Block: Location -->
                            <div class="border-b border-slate-100 px-6 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Location</p>
                                <div class="mt-2 space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">IP</span>
                                        <span class="font-mono text-slate-800">{{ log.ip_address || '—' }}</span>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">Country / Region</span>
                                        <span class="text-slate-800">{{ log.country_code || '—' }} {{ log.region ? '· ' + log.region : '' }}</span>
                                    </div>
                                    <div v-if="log.city" class="flex justify-between gap-4">
                                        <span class="text-slate-500">City</span>
                                        <span class="text-slate-800">{{ log.city }}</span>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">Timezone</span>
                                        <span class="text-slate-800">{{ log.timezone || '—' }} {{ log.timezone && log.gmt_offset_minutes != null ? gmtLabel(log.gmt_offset_minutes) : '' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Block: Client -->
                            <div class="border-b border-slate-100 px-6 py-4">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Client</p>
                                <div class="mt-2 space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">Device</span>
                                        <span class="text-slate-800">{{ log.device_type_label || log.device_type || '—' }}</span>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <span class="text-slate-500">Browser</span>
                                        <span class="text-slate-800">{{ log.browser || '—' }}{{ log.browser_version ? ' ' + log.browser_version : '' }}</span>
                                    </div>
                                    <div v-if="log.platform" class="flex justify-between gap-4">
                                        <span class="text-slate-500">Platform</span>
                                        <span class="text-slate-800">{{ log.platform }}</span>
                                    </div>
                                    <div v-if="log.accept_language" class="flex flex-col gap-1">
                                        <span class="text-slate-500">Accept-Language</span>
                                        <span class="break-all text-xs text-slate-600">{{ log.accept_language }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Raw: User-Agent (collapsed) -->
                            <details class="border-b border-slate-100 px-6 py-4">
                                <summary class="cursor-pointer text-[10px] font-semibold uppercase tracking-widest text-slate-400 hover:text-slate-600">
                                    User-Agent
                                </summary>
                                <p class="mt-2 max-h-24 overflow-auto break-all font-mono text-[11px] text-slate-500">{{ log.user_agent || '—' }}</p>
                            </details>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
