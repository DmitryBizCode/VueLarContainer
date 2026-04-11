<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    countries: {
        type: Array,
        default: () => [],
    },
    detected_country_id: {
        type: Number,
        default: null,
    },
    detected_country_iso: {
        type: String,
        default: null,
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    country_id: props.detected_country_id ? String(props.detected_country_id) : '',
    password: '',
    password_confirmation: '',
});

const selectedCountry = computed(() =>
    props.countries.find((country) => String(country.id) === String(form.country_id)),
);

const selectedCountryFlag = computed(() => {
    const iso = (selectedCountry.value?.iso_code || props.detected_country_iso || '').toUpperCase();

    if (!/^[A-Z]{2}$/.test(iso)) {
        return '';
    }

    return String.fromCodePoint(...iso.split('').map((char) => 127397 + char.charCodeAt(0)));
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Create your account</h2>
            <p class="mt-1 text-sm text-slate-500">Set up secure access to booking, tracking and account operations.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="block text-sm font-semibold text-slate-700">First name</label>

                    <TextInput
                        id="first_name"
                        type="text"
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                        v-model="form.first_name"
                        required
                        autofocus
                        autocomplete="given-name"
                    />

                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <label for="last_name" class="block text-sm font-semibold text-slate-700">Last name</label>

                    <TextInput
                        id="last_name"
                        type="text"
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                        v-model="form.last_name"
                        required
                        autocomplete="family-name"
                    />

                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <label for="country_id" class="block text-sm font-semibold text-slate-700">Country</label>
                    <span
                        v-if="selectedCountryFlag"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700"
                    >
                        <span class="text-base leading-none">{{ selectedCountryFlag }}</span>
                        <span>{{ selectedCountry?.name ?? 'Detected' }}</span>
                    </span>
                </div>

                <select
                    id="country_id"
                    v-model="form.country_id"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    required
                >
                    <option value="" disabled>Select country</option>
                    <option v-for="country in props.countries" :key="country.id" :value="country.id">
                        {{ country.iso_code }} - {{ country.name }}
                    </option>
                </select>

                <InputError class="mt-2" :message="form.errors.country_id" />
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Confirm password</label>

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm font-semibold text-blue-700 transition hover:text-blue-900"
                >
                    Already registered?
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2"
                    :class="{ 'opacity-70': form.processing }"
                    :disabled="form.processing"
                >
                    Register
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
