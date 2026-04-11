<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    countries: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = page.props.auth.user;
const profileUpdateRoute = computed(() => ((page.url || '').startsWith('/admin') ? route('admin.profile.update') : route('profile.update')));

const form = useForm({
    first_name: user.first_name || '',
    last_name: user.last_name || '',
    email: user.email,
    phone_number: user.phone_number || '',
    company_name: user.company_name || '',
    address: user.address || '',
    country_id: user.country_id ? String(user.country_id) : '',
});

const profileCompletion = computed(() => {
    const fields = [
        form.first_name,
        form.last_name,
        form.email,
        form.phone_number,
        form.company_name,
        form.address,
        form.country_id,
    ];

    return Math.round((fields.filter((value) => Boolean(String(value || '').trim())).length / fields.length) * 100);
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            country_id: data.country_id ? Number(data.country_id) : null,
        }))
        .patch(profileUpdateRoute.value);
};
</script>

<template>
    <section class="space-y-5">
        <header class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Account identity</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900">Profile information</h3>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-600" />
                    Completion {{ profileCompletion }}%
                </span>
            </div>

            <p class="text-sm text-slate-500">
                Keep your contact data and company details synchronized for smoother rental and shipment operations.
            </p>
        </header>

        <form
            @submit.prevent="submit"
            class="space-y-5"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="first_name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">First name</label>
                    <input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        autocomplete="given-name"
                        required
                        autofocus
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.first_name" class="text-xs font-medium text-rose-600">{{ form.errors.first_name }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="last_name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last name</label>
                    <input
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        autocomplete="family-name"
                        required
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.last_name" class="text-xs font-medium text-rose-600">{{ form.errors.last_name }}</p>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="email" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
                <p v-if="form.errors.email" class="text-xs font-medium text-rose-600">{{ form.errors.email }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="phone_number" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone number</label>
                    <input
                        id="phone_number"
                        v-model="form.phone_number"
                        type="text"
                        autocomplete="tel"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.phone_number" class="text-xs font-medium text-rose-600">{{ form.errors.phone_number }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="company_name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Company name</label>
                    <input
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        autocomplete="organization"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    />
                    <p v-if="form.errors.company_name" class="text-xs font-medium text-rose-600">{{ form.errors.company_name }}</p>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="address" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                <input
                    id="address"
                    v-model="form.address"
                    type="text"
                    autocomplete="street-address"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
                <p v-if="form.errors.address" class="text-xs font-medium text-rose-600">{{ form.errors.address }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="country_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Country</label>
                <select
                    id="country_id"
                    v-model="form.country_id"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Select country</option>
                    <option v-for="country in countries" :key="country.id" :value="String(country.id)">
                        {{ country.name }}
                    </option>
                </select>
                <p v-if="form.errors.country_id" class="text-xs font-medium text-rose-600">{{ form.errors.country_id }}</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-900">
                <p>
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-semibold underline underline-offset-4 transition hover:text-amber-700"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-xs font-semibold text-emerald-700"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Save changes
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
