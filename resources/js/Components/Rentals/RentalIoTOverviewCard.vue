<script setup>
import Modal from '@/Components/Modal.vue';
import { reactive } from 'vue';

const props = defineProps({
    rental: {
        type: Object,
        required: true,
    },
    container: {
        type: Object,
        default: () => null,
    },
    /** Latest DB snapshot (same scope as charts) for quick sanity check */
    iotLatest: {
        type: Object,
        default: () => null,
    },
    /** Bumped when monitor-charts poll returns so this block re-renders even if timestamps repeat */
    pollRevision: {
        type: Number,
        default: 0,
    },
});

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const statusLabel = (value) =>
    String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const statusNotesModal = reactive({
    show: false,
    title: '',
    body: '',
});

const statusNotesEligible = () => {
    const st = String(props.rental?.status || '').toLowerCase();

    return (
        ['draft', 'pending_approval', 'rejected', 'cancelled'].includes(st)
        || Boolean(props.rental?.rejection_reason?.trim())
        || Boolean(props.rental?.cancellation_reason?.trim())
    );
};

const openStatusNotes = () => {
    const st = String(props.rental?.status || '').toLowerCase();
    const lines = [];
    if (props.container?.current_status) {
        lines.push(`Equipment status: ${statusLabel(props.container.current_status)}`);
    }
    if (st === 'rejected') {
        lines.push(props.rental?.rejection_reason?.trim() || 'No rejection reason was recorded.');
    } else if (st === 'cancelled') {
        lines.push(props.rental?.cancellation_reason?.trim() || 'No cancellation notes were recorded.');
    } else if (st === 'pending_approval') {
        lines.push('Awaiting operations review.');
    } else if (st === 'draft') {
        lines.push('Draft rental — submit from the request form when ready.');
    } else if (props.rental?.rejection_reason?.trim()) {
        lines.push(`Note: ${props.rental.rejection_reason.trim()}`);
    } else if (props.rental?.cancellation_reason?.trim()) {
        lines.push(`Note: ${props.rental.cancellation_reason.trim()}`);
    }
    statusNotesModal.title = `Rental #${props.rental.id} · status notes`;
    statusNotesModal.body = lines.join('\n\n');
    statusNotesModal.show = true;
};

const closeStatusNotes = () => {
    statusNotesModal.show = false;
};
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">IoT Rental Monitor</p>
                <h1 class="mt-1 text-xl font-bold text-slate-900">
                    Rental #{{ rental.id }}
                    <span v-if="container?.serial_number" class="ml-2 text-sm font-semibold text-slate-500">
                        · {{ container.serial_number }}
                    </span>
                </h1>
                <p class="mt-2 text-sm text-slate-600">
                    {{ rental.origin_port?.name || 'Origin' }}
                    <span class="text-slate-400"> → </span>
                    {{ rental.destination_port?.name || 'Destination' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ formatDate(rental.start_date) }} – {{ formatDate(rental.end_date) }}
                    <span v-if="rental.rental_days" class="ml-1">
                        · {{ rental.rental_days }} days
                    </span>
                    <span v-if="rental.estimated_distance" class="ml-1">
                        · ~{{ Number(rental.estimated_distance).toLocaleString('en-US') }} km
                    </span>
                </p>
            </div>

            <div class="grid gap-3 text-xs text-slate-700 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Rental status</p>
                    <p class="mt-1 inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="['bg-slate-400', rental.status === 'in_progress' || rental.status === 'active' ? 'bg-emerald-500' : '', rental.status === 'completed' ? 'bg-slate-500' : '', rental.status === 'cancelled' ? 'bg-rose-500' : '']"
                        />
                        {{ statusLabel(rental.status) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Payment</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ statusLabel(rental.payment_status) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Container status</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ statusLabel(container?.current_status || 'unknown') }}
                    </p>
                    <p v-if="container?.current_port" class="mt-0.5 text-[11px] text-slate-500">
                        Now at {{ container.current_port.name }}, {{ container.current_port.country || container.current_port.city }}
                    </p>
                </div>
            </div>
            <div v-if="statusNotesEligible()" class="md:self-start">
                <button
                    type="button"
                    class="text-xs font-semibold text-blue-700 underline decoration-blue-200 underline-offset-2 hover:text-blue-900"
                    @click="openStatusNotes"
                >
                    Status notes
                </button>
            </div>
        </div>

        <div
            v-if="iotLatest?.sensors && Object.keys(iotLatest.sensors).length"
            :key="`iot-latest-${pollRevision}`"
            class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4"
        >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Latest snapshot (DB + Redis buffer)</p>
            <p v-if="iotLatest.recorded_at" class="mt-1 text-[11px] text-emerald-700/90">
                {{ formatDate(iotLatest.recorded_at) }}
            </p>
            <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="(val, key) in iotLatest.sensors" :key="key" class="flex justify-between gap-2 rounded-lg bg-white/80 px-2 py-1.5">
                    <dt class="font-mono text-[10px] text-slate-500">{{ key }}</dt>
                    <dd class="font-semibold text-slate-900">{{ Number(val).toFixed(2) }}</dd>
                </div>
            </dl>
        </div>

        <Modal :show="statusNotesModal.show" max-width="md" @close="closeStatusNotes">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">{{ statusNotesModal.title }}</h3>
                <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ statusNotesModal.body }}</p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="closeStatusNotes"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>

