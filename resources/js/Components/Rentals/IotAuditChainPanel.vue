<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

const ACTUATOR_LABELS = {
    acStatus: 'AC',
    acTemp: 'AC temp',
    humidifier: 'Humidifier',
    heater: 'Heater',
    ventilation: 'Ventilation',
    mainLight: 'Main light',
    irLamp: 'IR lamp',
    pump: 'Drain pump',
    doorOpen: 'Cargo door',
    freshenerOn: 'Air freshener',
};

const props = defineProps({
    rentalId: { type: [Number, String], required: true },
    initialEvents: { type: Array, default: () => [] },
});

const events = ref([...props.initialEvents]);
const loadingMore = ref(false);
const hasMore = ref(props.initialEvents.length >= 50);

const detailOpen = ref(false);
const detailEvent = ref(null);
const popoverRef = ref(null);
const blocksContainerRef = ref(null);
const wrapperStyle = ref({});
/** Arrow points down at the block; horizontal offset from modal left */
const arrowLeftPx = ref(120);

watch(
    () => props.initialEvents,
    (v) => {
        events.value = [...(v || [])];
    },
    { deep: true }
);

function eventLabel(ev) {
    const t = ev?.event_type;
    if (t == null || t === '') {
        return 'Event';
    }
    const p = ev.payload || {};
    if (t === 'actuator_updated') {
        const keys = p.changed_keys || [];
        return keys.length ? `Changes: ${keys.map((k) => ACTUATOR_LABELS[k] || k).join(', ')}` : 'Actuator update';
    }
    if (t === 'sensor_toggled') {
        const added = p.added || [];
        const removed = p.removed || [];
        const parts = [];
        if (added.length) parts.push(`+ ${added.join(', ')}`);
        if (removed.length) parts.push(`− ${removed.join(', ')}`);
        return parts.length ? `Sensors: ${parts.join(' | ')}` : 'Sensor toggled';
    }
    return String(t).replace(/_/g, ' ');
}

/** Structured rows for blockchain-style diff */
function structuredChanges(ev) {
    const p = ev.payload || {};
    if (ev.event_type === 'actuator_updated' && p.changed_keys?.length) {
        return p.changed_keys.map((k) => {
            const label = ACTUATOR_LABELS[k] || k;
            const oldVal = p.old?.[k];
            const newVal = p.new?.[k];
            const oldStr = typeof oldVal === 'boolean' ? (oldVal ? 'ON' : 'OFF') : String(oldVal ?? '—');
            const newStr = typeof newVal === 'boolean' ? (newVal ? 'ON' : 'OFF') : String(newVal ?? '—');
            return { label, oldStr, newStr };
        });
    }
    if (ev.event_type === 'sensor_toggled') {
        const rows = [];
        if (p.added?.length) {
            rows.push({ label: 'Enabled sensors', oldStr: '—', newStr: p.added.join(', ') });
        }
        if (p.removed?.length) {
            rows.push({ label: 'Disabled sensors', oldStr: p.removed.join(', '), newStr: '—' });
        }
        return rows;
    }
    return [];
}

function eventDetailsLines(ev) {
    const rows = structuredChanges(ev);
    if (rows.length) {
        return rows.map((r) => `${r.label}: ${r.oldStr} → ${r.newStr}`);
    }
    return [];
}

function shortHash(h) {
    if (!h) return '—';
    return h.substring(0, 8) + '…';
}

function payloadJson(ev) {
    try {
        return JSON.stringify(ev?.payload ?? {}, null, 2);
    } catch {
        return String(ev?.payload);
    }
}

function positionPopover(el) {
    if (!el?.getBoundingClientRect || !blocksContainerRef.value) return;
    const blockRect = el.getBoundingClientRect();
    const containerRect = blocksContainerRef.value.getBoundingClientRect();
    const w = Math.min(340, containerRect.width - 24);
    const gap = 10;
    const estH = Math.min(400, window.innerHeight - 80);

    // Modal above the blocks list, bottom edge just above the container top
    const top = Math.max(12, containerRect.top - gap - estH);
    const left = Math.max(12, containerRect.left + (containerRect.width - w) / 2);
    const leftClamped = Math.min(Math.max(left, 12), window.innerWidth - w - 12);

    // Arrow: point down at the clicked block
    const blockCenterX = blockRect.left + blockRect.width / 2;
    const modalLeft = leftClamped;
    arrowLeftPx.value = Math.max(24, Math.min(blockCenterX - modalLeft, w - 48));

    wrapperStyle.value = {
        position: 'fixed',
        left: `${leftClamped}px`,
        top: `${top}px`,
        width: `${w}px`,
        maxHeight: `${estH}px`,
        zIndex: 90,
    };
}

function openDetail(eventData, el) {
    eventData && (detailEvent.value = eventData);
    detailOpen.value = true;
    nextTick(() => {
        requestAnimationFrame(() => positionPopover(el));
    });
}

function closeDetail() {
    detailOpen.value = false;
    detailEvent.value = null;
}

function onDocumentClick(e) {
    if (!detailOpen.value) return;
    if (popoverRef.value?.contains(e.target)) return;
    if (e.target.closest?.('.audit-block')) return;
    closeDetail();
}

function onKeydown(e) {
    if (e.key === 'Escape') closeDetail();
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onDocumentClick, true);
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onDocumentClick, true);
});

async function loadMore() {
    if (loadingMore.value || events.value.length === 0) return;
    const lastId = Math.min(...events.value.map((e) => e.id));
    loadingMore.value = true;
    try {
        const { data } = await axios.get(`/api/rentals/${encodeURIComponent(String(props.rentalId))}/iot-audit`, {
            params: { limit: 30, before_id: lastId },
        });
        if (data.data?.length) {
            events.value.push(...data.data);
            hasMore.value = data.data.length >= 30;
        } else {
            hasMore.value = false;
        }
    } finally {
        loadingMore.value = false;
    }
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-600">IoT event chain</h3>
        <p class="mb-3 text-[11px] text-slate-500">
            Click a chain block — a container-shaped detail opens with a pointer to the block (blockchain-style diff: what changed, who, when).
        </p>
        <div ref="blocksContainerRef" class="max-h-[480px] overflow-y-auto pr-1">
            <div v-if="events.length === 0" class="py-6 text-center text-xs text-slate-400">No events yet</div>
            <div v-else class="space-y-2">
                <div
                    v-for="(ev, idx) in events"
                    :key="ev.id"
                    class="audit-block cursor-pointer rounded-lg border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-2.5 text-xs shadow-sm transition hover:border-sky-300/80 hover:shadow-md"
                    role="button"
                    tabindex="0"
                    @click.stop="openDetail(ev, $event.currentTarget)"
                    @keydown.enter.stop="openDetail(ev, $event.currentTarget)"
                >
                    <div class="mb-1 flex items-center justify-between gap-1">
                        <span class="font-mono font-semibold text-slate-700">#{{ ev.sequence }}</span>
                        <span class="text-[10px] text-slate-500">
                            {{
                                new Date(ev.created_at).toLocaleString('en-US', {
                                    day: '2-digit',
                                    month: 'short',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })
                            }}
                        </span>
                    </div>
                    <p class="font-medium text-slate-800 line-clamp-2">{{ eventLabel(ev) }}</p>
                    <p v-if="ev.user" class="mt-0.5 truncate text-[11px] text-slate-500">By: {{ ev.user.name }}</p>
                    <div class="mt-1 flex items-center gap-2 font-mono text-[10px] text-slate-400">
                        <span>hash: {{ shortHash(ev.row_hash) }}</span>
                    </div>
                    <div v-if="idx < events.length - 1" class="mx-auto mt-1.5 h-2 w-px bg-gradient-to-b from-slate-300 to-transparent" />
                </div>
            </div>
            <button
                v-if="hasMore && !loadingMore"
                type="button"
                class="mt-3 w-full rounded-lg border border-slate-200 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
                @click="loadMore"
            >
                Load more
            </button>
            <p v-if="loadingMore" class="mt-2 text-center text-xs text-slate-400">Loading…</p>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-[0.96] -translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition duration-120 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-[0.96]"
            >
                <div
                    v-if="detailOpen && detailEvent"
                    ref="popoverRef"
                    class="iot-audit-flyout relative rounded-xl border border-slate-200 bg-white shadow-xl ring-1 ring-slate-900/5"
                    :style="wrapperStyle"
                    @click.stop
                >
                    <!-- Triangle pointing down at the clicked block -->
                    <div
                        class="pointer-events-none absolute -bottom-2.5 left-0"
                        aria-hidden="true"
                        :style="{ transform: `translateX(${arrowLeftPx}px) translateX(-50%)` }"
                    >
                        <div
                            class="h-0 w-0 border-l-[10px] border-l-transparent border-r-[10px] border-r-transparent border-t-[12px] border-t-slate-200"
                        />
                    </div>

                    <div class="relative overflow-hidden rounded-xl bg-white">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-3 py-2">
                            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">IoT ledger</span>
                            <span class="font-mono text-[11px] font-semibold text-slate-800">#{{ detailEvent.sequence }}</span>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                                @click="closeDetail"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="max-h-[min(360px,calc(100vh-120px))] overflow-y-auto p-3 text-xs text-slate-700">
                            <!-- Meta: when + who -->
                            <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Committed</span>
                                    <time class="font-mono text-[11px] text-slate-600">
                                        {{ new Date(detailEvent.created_at).toLocaleString('en-US') }}
                                    </time>
                                </div>
                                <p v-if="detailEvent.user" class="mt-2 border-t border-slate-200/80 pt-2">
                                    <span class="text-[10px] font-semibold uppercase text-slate-500">Signer</span>
                                    <span class="ml-2 font-medium text-slate-800">{{ detailEvent.user.name }}</span>
                                </p>
                                <p v-else class="mt-2 border-t border-slate-200/80 pt-2 text-[11px] text-slate-500">System / automated</p>
                            </div>

                            <!-- State transition -->
                            <div class="relative pl-3">
                                <div
                                    class="absolute bottom-1 left-[5px] top-1 w-px bg-gradient-to-b from-sky-300/80 via-slate-300 to-slate-200"
                                    aria-hidden="true"
                                />
                                <div class="relative mb-3">
                                    <div
                                        class="absolute -left-3 top-1.5 h-2 w-2 rounded-full border-2 border-sky-400 bg-white shadow-sm"
                                    />
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Transition</p>
                                    <p class="mt-0.5 text-[13px] font-semibold leading-snug text-slate-900">{{ eventLabel(detailEvent) }}</p>
                                </div>

                                <div v-if="structuredChanges(detailEvent).length" class="relative space-y-2 pb-1">
                                    <div
                                        v-for="(row, i) in structuredChanges(detailEvent)"
                                        :key="i"
                                        class="relative rounded-lg border border-slate-200 bg-slate-50/70 p-2 pl-3"
                                    >
                                        <div
                                            class="absolute -left-3 top-3 h-1.5 w-1.5 rounded-full bg-sky-500"
                                            aria-hidden="true"
                                        />
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ row.label }}</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2 font-mono text-[11px]">
                                            <span
                                                class="rounded border border-rose-200 bg-rose-50/80 px-2 py-1 text-rose-700 line-through decoration-rose-400/60"
                                            >
                                                {{ row.oldStr }}
                                            </span>
                                            <span class="text-slate-400" aria-hidden="true">→</span>
                                            <span
                                                class="rounded border border-emerald-200 bg-emerald-50/80 px-2 py-1 text-emerald-700"
                                            >
                                                {{ row.newStr }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="eventDetailsLines(detailEvent).length" class="relative space-y-1 pb-1">
                                    <p
                                        v-for="(line, i) in eventDetailsLines(detailEvent)"
                                        :key="i"
                                        class="rounded-lg border border-slate-200 bg-slate-50/60 py-1.5 pl-3 pr-2 font-mono text-[11px] text-slate-600"
                                    >
                                        {{ line }}
                                    </p>
                                </div>
                            </div>

                            <!-- Hash chain -->
                            <div class="mt-4 space-y-2 border-t border-slate-200 pt-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Hash chain</p>
                                <div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50/50 p-2 font-mono text-[10px] leading-relaxed text-slate-600">
                                    <p v-if="detailEvent.prev_hash">
                                        <span class="text-slate-400">prev</span>
                                        <span class="ml-1 break-all text-slate-500">{{ detailEvent.prev_hash }}</span>
                                    </p>
                                    <p>
                                        <span class="text-slate-400">row</span>
                                        <span class="ml-1 break-all text-sky-600">{{ detailEvent.row_hash }}</span>
                                    </p>
                                </div>
                            </div>

                            <details class="mt-3 group">
                                <summary
                                    class="cursor-pointer text-[10px] font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-600"
                                >
                                    Raw payload (JSON)
                                </summary>
                                <pre
                                    class="mt-2 max-h-32 overflow-auto whitespace-pre-wrap break-all rounded-lg border border-slate-200 bg-slate-50/50 p-2 font-mono text-[9px] text-slate-600"
                                >{{ payloadJson(detailEvent) }}</pre>
                            </details>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>
