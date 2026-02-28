<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-700">Security check</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900">Confirm your password</h1>
            <p class="mt-2 text-sm text-gray-500">For safety, re-enter your password to continue.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-gray-700">Password</label>
                <input
                    id="password"
                    type="password"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="Enter your password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-xl bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/20 transition hover:from-blue-800 hover:to-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Confirm
            </button>
        </form>
    </GuestLayout>
</template>
