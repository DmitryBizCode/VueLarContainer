<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    rentalId: { type: [Number, String], required: true },
    currentStatus: { type: String, default: '' },
    currentPaymentStatus: { type: String, default: '' },
    statusOptions: { type: Array, default: () => [] },
    paymentStatusOptions: { type: Array, default: () => [] },
    hidePaymentStatus: { type: Boolean, default: false },
});

const showConfirmModal = ref(false);

const form = useForm({
    status: props.currentStatus,
    payment_status: props.currentPaymentStatus || '',
    rejection_reason: '',
});

watch(
    () => [props.currentStatus, props.currentPaymentStatus],
    ([status, payment]) => {
        form.status = status ?? '';
        form.payment_status = payment ?? '';
        form.rejection_reason = '';
    }
);

const openConfirm = () => {
    form.clearErrors();
    if (form.status === 'rejected' && !form.rejection_reason?.trim()) {
        form.setError('rejection_reason', 'Rejection reason is required when rejecting.');
        return;
    }
    showConfirmModal.value = true;
};

const submit = () => {
    const payload = {
        status: form.status,
        rejection_reason: form.status === 'rejected' ? form.rejection_reason : undefined,
    };
    if (!props.hidePaymentStatus && form.payment_status) {
        payload.payment_status = form.payment_status;
    }
    router.patch(route('admin.rentals.status', props.rentalId), payload, {
        preserveScroll: true,
        onSuccess: () => { showConfirmModal.value = false; },
    });
};

const statusLabel = (s) => String(s).replace(/_/g, ' ');
</script>

<template>
    <div class="space-y-3">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Status</label>
            <select
                v-model="form.status"
                class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                name="status"
            >
                <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
            </select>
            <InputError :message="form.errors.status" />
        </div>
        <div v-if="!hidePaymentStatus">
            <label class="block text-xs font-semibold text-slate-500">Payment status</label>
            <select
                v-model="form.payment_status"
                class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                name="payment_status"
            >
                <option value="">—</option>
                <option v-for="s in paymentStatusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
            </select>
        </div>
        <div v-if="form.status === 'rejected'">
            <label class="block text-xs font-semibold text-slate-500">Rejection reason</label>
            <textarea
                v-model="form.rejection_reason"
                class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                rows="2"
                name="rejection_reason"
                placeholder="Required when rejecting"
            />
            <InputError :message="form.errors.rejection_reason" />
        </div>
        <button
            type="button"
            class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50"
            :disabled="form.processing"
            @click="openConfirm"
        >
            Confirm change
        </button>
    </div>

    <Modal :show="showConfirmModal" max-width="sm" @close="showConfirmModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-slate-900">Confirm status change</h3>
            <p class="mt-2 text-sm text-slate-600">
                Change status to <strong>{{ statusLabel(form.status) }}</strong>
                <span v-if="!hidePaymentStatus && form.payment_status"> and payment to <strong>{{ statusLabel(form.payment_status) }}</strong></span>
                <span v-else-if="form.status === 'approved'"> (payment will be set to pending automatically)</span>?
            </p>
            <p v-if="form.status === 'rejected' && form.rejection_reason" class="mt-2 text-sm text-slate-600">
                Rejection reason: {{ form.rejection_reason }}
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="showConfirmModal = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    Confirm
                </button>
            </div>
        </div>
    </Modal>
</template>
