<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Sign in to continue</h2>
            <p class="mt-1 text-sm text-slate-500">Use your company account to access dashboard and logistics operations.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50/60 text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center text-sm text-slate-600">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2">Remember me</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-semibold text-blue-700 transition hover:text-blue-900"
                >
                    Forgot your password?
                </Link>
            </div>

            <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/30 transition-all duration-300 hover:from-blue-700 hover:via-blue-800 hover:to-blue-700 hover:shadow-blue-600/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Log in
            </button>

            <p class="text-center text-sm text-slate-500">
                No account yet?
                <Link :href="route('register')" class="font-semibold text-blue-700 hover:text-blue-900">
                    Create one
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
