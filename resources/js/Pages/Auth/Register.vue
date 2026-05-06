<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
    phone_number: '',
    password: '',
    password_confirmation: '',
});

const selectedCountry = computed(() =>
    props.countries.find((country) => String(country.id) === String(form.country_id)),
);

const detectedCountry = computed(() =>
    props.detected_country_id
        ? props.countries.find((country) => String(country.id) === String(props.detected_country_id))
        : null
);

const selectedCountryFlag = computed(() => {
    const iso = (selectedCountry.value?.iso_code || props.detected_country_iso || '').toUpperCase();

    if (!/^[A-Z]{2}$/.test(iso)) {
        return '';
    }

    return String.fromCodePoint(...iso.split('').map((char) => 127397 + char.charCodeAt(0)));
});

const countryPanelOpen = ref(false);
const countrySearch = ref('');
const countrySearchRef = ref(null);

const filteredCountries = computed(() => {
    const q = countrySearch.value.trim().toLowerCase();
    if (!q) {
        return props.countries;
    }
    return props.countries.filter((c) => {
        const name = String(c.name || '').toLowerCase();
        const iso = String(c.iso_code || '').toLowerCase();
        return name.includes(q) || iso.includes(q);
    });
});

const closeCountryPanel = () => {
    countryPanelOpen.value = false;
    countrySearch.value = '';
};

const selectCountry = (id) => {
    form.country_id = id ? String(id) : '';
    closeCountryPanel();
};

const onDocumentClick = (e) => {
    if (!countryPanelOpen.value) return;
    const wrap = document.getElementById('country-field');
    const t = e.target;
    if (wrap && wrap.contains?.(t)) return;
    closeCountryPanel();
};

const onEscape = (e) => {
    if (e.key !== 'Escape') return;
    if (!countryPanelOpen.value) return;
    e.preventDefault();
    closeCountryPanel();
    countrySearchRef.value?.focus?.();
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
});

watch(
    () => countryPanelOpen.value,
    (open) => {
        if (!open) return;
        countrySearch.value = '';
    }
);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Create your account</h2>
                <p class="mt-1 text-sm text-slate-500">Set up secure access to booking, tracking and account operations.</p>
            </div>

            <!-- Country icon (compact, top-right) -->
            <div id="country-field" class="relative shrink-0">
                <!-- Hidden: real value for submit + required -->
                <input id="country_id" type="hidden" v-model="form.country_id" required>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    :aria-label="`Country: ${selectedCountry?.name || detectedCountry?.name || 'Select'}`"
                    @click="
                        countryPanelOpen = !countryPanelOpen;
                        if (countryPanelOpen) {
                            countrySearch = '';
                            setTimeout(() => countrySearchRef?.focus?.(), 0);
                        }
                    "
                >
                    <span v-if="selectedCountryFlag" class="text-lg leading-none" aria-hidden="true">{{ selectedCountryFlag }}</span>
                    <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm-6.5-8a6.5 6.5 0 0011.684 3.78c-.525-.212-1.244-.405-2.184-.405-1.7 0-2.35.63-3.46 1.22-.95.505-1.865.785-3.074.395A6.48 6.48 0 013.5 10zm.29-1.5h3.113c.76 0 1.29-.37 1.29-1.05 0-.37-.17-.64-.42-.85-.21-.18-.48-.35-.48-.78 0-.54.58-.87 1.17-.87.6 0 .96.34 1.19.6.24.27.38.44.74.44.6 0 1.16-.42 1.16-1.03 0-.6-.46-.97-.96-1.27.92-.11 1.74.03 2.47.32A6.5 6.5 0 003.79 8.5z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <div
                    v-if="countryPanelOpen"
                    class="absolute right-0 z-20 mt-2 w-[min(420px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                    <div class="border-b border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Country
                            <span v-if="selectedCountry?.name" class="normal-case font-medium text-slate-400">· {{ selectedCountry.name }}</span>
                        </p>
                        <input
                            ref="countrySearchRef"
                            v-model="countrySearch"
                            type="search"
                            autocomplete="off"
                            class="mt-2 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Search (name or ISO)…"
                        >
                    </div>

                    <div class="max-h-64 overflow-auto p-1">
                        <button
                            v-for="country in filteredCountries"
                            :key="country.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-50"
                            :class="String(country.id) === String(form.country_id) ? 'bg-slate-100' : ''"
                            @click="selectCountry(country.id)"
                        >
                            <span class="min-w-0 truncate font-semibold text-slate-900">{{ country.name }}</span>
                            <span class="ml-3 shrink-0 text-xs font-semibold text-slate-500">{{ country.iso_code }}</span>
                        </button>

                        <div v-if="filteredCountries.length === 0" class="px-3 py-4 text-sm text-slate-500">
                            No match.
                        </div>
                    </div>
                </div>
            </div>
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
                <label for="phone_number" class="block text-sm font-semibold text-slate-700">Phone (optional)</label>
                <TextInput
                    id="phone_number"
                    type="tel"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.phone_number"
                    autocomplete="tel"
                    placeholder="+1 555 123 4567"
                />
                <InputError class="mt-2" :message="form.errors.phone_number" />
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
