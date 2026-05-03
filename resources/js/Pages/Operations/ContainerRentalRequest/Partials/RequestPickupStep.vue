<script setup>
import PhoneInputWithCountry from '@/Components/Rentals/PhoneInputWithCountry.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    countries: {
        type: Array,
        default: () => [],
    },
    userCountryId: {
        type: [Number, String, null],
        default: null,
    },
    showPickupWindow: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:showPickupWindow']);

const togglePickup = () => {
    emit('update:showPickupWindow', !props.showPickupWindow);
};
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Operations contacts</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900">Pickup and consignee details</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="contact_name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact name</label>
                <input
                    id="contact_name"
                    v-model="form.contact_name"
                    type="text"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Responsible person"
                >
            </div>
            <div>
                <label for="contact_phone" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact phone</label>
                <PhoneInputWithCountry
                    id="contact_phone"
                    :model-value="form.contact_phone"
                    :countries="countries"
                    :user-country-id="userCountryId"
                    class="mt-1.5"
                    @update:model-value="form.contact_phone = $event"
                />
            </div>
            <div class="md:col-span-2">
                <label for="special_requirements" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Special requirements</label>
                <textarea
                    id="special_requirements"
                    v-model="form.special_requirements"
                    rows="2"
                    class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    placeholder="Permits, handling equipment, security requests"
                />
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4">
            <button
                type="button"
                class="text-xs font-semibold text-blue-700 hover:underline"
                @click="togglePickup"
            >
                {{ showPickupWindow ? 'Hide pickup window' : 'Add pickup window (optional)' }}
            </button>

            <div v-if="showPickupWindow" class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="pickup_window_start" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup window start</label>
                    <input
                        id="pickup_window_start"
                        v-model="form.pickup_window_start"
                        type="datetime-local"
                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    >
                    <p class="mt-1 text-xs text-slate-500">Within rental period</p>
                </div>
                <div>
                    <label for="pickup_window_end" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pickup window end</label>
                    <input
                        id="pickup_window_end"
                        v-model="form.pickup_window_end"
                        type="datetime-local"
                        class="mt-1.5 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"
                    >
                </div>
            </div>
        </div>
    </section>
</template>
