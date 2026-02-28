<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-700">Security check</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900">Verify your email</h1>
            <p class="mt-2 text-sm text-gray-500">
                We sent a verification link to your inbox. Confirm it to activate your account.
            </p>
        </div>

        <div
            class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
            v-if="verificationLinkSent"
        >
            A fresh verification link has been sent.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 space-y-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/20 transition hover:from-blue-800 hover:to-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Resend Verification Email
                </button>

                <div class="text-center">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
