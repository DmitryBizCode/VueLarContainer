<script setup>
import { useToast } from '@/composables/useToast';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const { info: toastInfo } = useToast();

const open = ref(false);
const items = ref([]);
const loadingList = ref(false);
const lastKnownCount = ref(0);

const count = ref(0);

let pollTimer = null;

const fetchUnreadCount = async () => {
    try {
        const { data } = await axios.get(route('notifications.unread-count'));
        const c = Number(data?.count ?? 0);
        if (lastKnownCount.value > 0 && c > lastKnownCount.value) {
            toastInfo('You have a new notification');
        }
        lastKnownCount.value = c;
        count.value = c;
    } catch {
        /* polling: fail silently */
    }
};

const fetchList = async () => {
    loadingList.value = true;
    try {
        const { data } = await axios.get(route('notifications.index'), { params: { per_page: 10 } });
        items.value = data?.data ?? [];
    } catch {
        items.value = [];
    } finally {
        loadingList.value = false;
    }
};

const toggle = async () => {
    open.value = !open.value;
    if (open.value && items.value.length === 0) {
        await fetchList();
    }
};

const markRead = async (note) => {
    if (!note?.id || note.is_read) {
        if (note?.action_url) {
            window.location.href = note.action_url;
        }
        return;
    }
    try {
        await axios.patch(route('notifications.read', { notification: note.id }));
        note.is_read = true;
        await fetchUnreadCount();
    } catch {
        /* ignore */
    }
    if (note.action_url) {
        window.location.href = note.action_url;
    }
};

const markAllRead = async () => {
    try {
        await axios.post(route('notifications.read-all'));
        items.value = items.value.map((n) => ({ ...n, is_read: true }));
        await fetchUnreadCount();
    } catch {
        /* ignore */
    }
};

watch(
    () => page.props.unreadNotificationCount,
    (v) => {
        const n = Number(v ?? 0);
        count.value = n;
        if (lastKnownCount.value === 0) {
            lastKnownCount.value = n;
        }
    },
    { immediate: true },
);

onMounted(() => {
    pollTimer = window.setInterval(fetchUnreadCount, 25000);
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
            :class="open ? 'ring-2 ring-blue-500/30' : ''"
            aria-label="Notifications"
            @click="toggle"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
            </svg>
            <span
                v-if="count > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
            >
                {{ count > 99 ? '99+' : count }}
            </span>
        </button>

        <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-show="open"
                class="absolute right-0 z-50 mt-2 w-[min(100vw-2rem,22rem)] origin-top-right rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/80"
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">Notifications</p>
                    <button
                        v-if="count > 0"
                        type="button"
                        class="text-xs font-semibold text-blue-700 hover:text-blue-900"
                        @click="markAllRead"
                    >
                        Mark all read
                    </button>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <p v-if="loadingList" class="px-4 py-6 text-center text-sm text-slate-500">Loading…</p>
                    <template v-else>
                        <p v-if="items.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">No notifications yet.</p>
                        <ul v-else class="divide-y divide-slate-100">
                            <li v-for="note in items" :key="note.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col gap-1 px-4 py-3 text-left transition hover:bg-slate-50"
                                    :class="note.is_read ? 'opacity-75' : 'bg-blue-50/40'"
                                    @click="markRead(note)"
                                >
                                    <span class="text-xs font-semibold text-slate-900">{{ note.title }}</span>
                                    <span class="line-clamp-2 text-xs text-slate-600">{{ note.message }}</span>
                                    <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ note.type }}</span>
                                </button>
                            </li>
                        </ul>
                    </template>
                </div>
            </div>
        </transition>
    </div>
</template>
