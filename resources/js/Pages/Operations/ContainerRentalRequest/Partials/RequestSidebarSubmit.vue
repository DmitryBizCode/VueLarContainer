<script setup>
import InputError from '@/Components/InputError.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    previewLoading: {
        type: Boolean,
        default: false,
    },
    previewError: {
        type: String,
        default: '',
    },
    hasPriceBreakdown: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">Submit request</h3>
        <p class="mt-1 text-sm text-slate-600">
            Price is calculated on the server and revalidated before saving.
        </p>

        <p v-if="previewError" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
            {{ previewError }}
        </p>

        <InputError class="mt-2" :message="form.errors.route_id || form.errors.origin_port_id || form.errors.destination_port_id" />
        <InputError class="mt-2" :message="form.errors.container_id" />
        <InputError class="mt-2" :message="form.errors.start_date" />
        <InputError class="mt-2" :message="form.errors.end_date" />
        <InputError class="mt-2" :message="form.errors.requested_weight" />
        <InputError class="mt-2" :message="form.errors.cargo_value" />
        <InputError class="mt-2" :message="form.errors.priority" />
        <InputError class="mt-2" :message="form.errors.incoterm" />
        <InputError class="mt-2" :message="form.errors.delivery_mode" />
        <InputError class="mt-2" :message="form.errors.loading_type" />
        <InputError class="mt-2" :message="form.errors.sustainability_pref" />
        <InputError class="mt-2" :message="form.errors.cargo_volume_cbm" />
        <InputError class="mt-2" :message="form.errors.package_count" />
        <InputError class="mt-2" :message="form.errors.contact_name" />
        <InputError class="mt-2" :message="form.errors.contact_phone" />
        <InputError class="mt-2" :message="form.errors.pickup_window_start" />
        <InputError class="mt-2" :message="form.errors.pickup_window_end" />
        <InputError class="mt-2" :message="form.errors.special_requirements" />
        <InputError class="mt-2" :message="form.errors.un_number" />
        <InputError class="mt-2" :message="form.errors.dangerous_goods_class" />
        <InputError class="mt-2" :message="form.errors.origin_customs_code" />
        <InputError class="mt-2" :message="form.errors.destination_customs_code" />

        <div class="mt-4 flex gap-2">
            <button
                type="button"
                class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                :disabled="form.processing || previewLoading || !form.container_id || !hasPriceBreakdown || !form.terms_accepted"
                @click="emit('submit')"
            >
                Submit request
            </button>
        </div>

        <label class="mt-4 inline-flex items-start gap-2 text-xs text-slate-600">
            <input
                v-model="form.terms_accepted"
                type="checkbox"
                class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-700"
            >
            <span>I confirm cargo and compliance details are accurate, and accept commercial terms.</span>
        </label>
        <InputError class="mt-2" :message="form.errors.terms_accepted" />
    </section>
</template>
