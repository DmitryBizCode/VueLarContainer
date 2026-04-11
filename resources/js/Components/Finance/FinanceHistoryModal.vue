<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    type: { type: String, default: 'transaction' }, // 'transaction' | 'rental'
    entityId: { type: [Number, String], default: null },
    title: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const logs = ref([]);
const loading = ref(false);
const error = ref(null);

const fetchHistory = async () => {
    if (!props.entityId) return;
    loading.value = true;
    error.value = null;
    try {
        const url = props.type === 'transaction'
            ? route('admin.finance.transactions.history', props.entityId)
            : route('admin.finance.rentals.payment-history', props.entityId);
        const { data } = await axios.get(url);
        logs.value = data.logs || [];
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to load history';
        logs.value = [];
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.show, props.entityId],
    ([show, id]) => {
        if (show && id) fetchHistory();
        if (!show) {
            logs.value = [];
            error.value = null;
        }
    },
    { immediate: true }
);

const formatDate = (v) =>
    v
        ? new Intl.DateTimeFormat('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(v))
        : '—';

const actionLabel = (action) => {
    const a = String(action || '').toLowerCase();
    if (a === 'transaction_status_changed') return 'Transaction status changed';
    if (a === 'rental_payment_status_changed') return 'Payment status changed';
    if (a === 'rental_auto_rejected_non_payment') return 'Rental auto-rejected (non-payment)';
    if (a === 'payment_approved') return 'Payment approved';
    if (a.startsWith('status_changed_to_')) return 'Rental status changed';
    return action ? action.replace(/_/g, ' ') : '—';
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-5">
            <h3 class="text-lg font-semibold text-slate-900">
                {{ title || (type === 'transaction' ? `Transaction #${entityId} history` : `Rental #${entityId} payment history`) }}
            </h3>
            <p v-if="loading" class="mt-3 text-sm text-slate-500">Loading…</p>
            <p v-else-if="error" class="mt-3 text-sm text-rose-600">{{ error }}</p>
            <div v-else-if="!logs.length" class="mt-3 text-sm text-slate-500">No history entries.</div>
            <ul v-else class="mt-4 space-y-3">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 text-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-slate-700">{{ actionLabel(log.action) }}</span>
                        <span class="text-slate-500">{{ formatDate(log.created_at) }}</span>
                    </div>
                    <p v-if="log.user" class="mt-1 text-xs text-slate-500">By {{ log.user.name }}</p>
                    <div v-if="log.old_values && Object.keys(log.old_values).length" class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span
                            v-for="(val, key) in log.old_values"
                            :key="key"
                            class="rounded bg-slate-200 px-1.5 py-0.5"
                        >
                            {{ key }}: {{ val }}
                        </span>
                        <span class="text-slate-400">→</span>
                        <span
                            v-for="(val, key) in (log.new_values || {})"
                            :key="key"
                            class="rounded bg-slate-300 px-1.5 py-0.5"
                        >
                            {{ key }}: {{ val }}
                        </span>
                    </div>
                </li>
            </ul>
        </div>
    </Modal>
</template>
