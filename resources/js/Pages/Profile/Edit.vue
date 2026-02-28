<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-blue-700/80">Personal Cabinet</p>
                <h2 class="mt-1 text-2xl font-bold leading-tight text-gray-900">Profile Settings</h2>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                <aside class="lg:col-span-4">
                    <div class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600/80">Profile control</p>
                        <h3 class="mt-3 text-2xl font-bold text-gray-900">Manage your account details</h3>
                        <p class="mt-3 text-sm leading-relaxed text-gray-500">
                            Update identity, email, password, and account security settings from one place.
                        </p>
                        <div class="mt-5 space-y-2 text-xs text-gray-500">
                            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">Keep contact info up to date</div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">Use strong password hygiene</div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">Review account deletion carefully</div>
                        </div>
                    </div>
                </aside>

                <div class="space-y-6 lg:col-span-8">
                    <div class="rounded-3xl border border-blue-100 bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :countries="countries"
                        class="max-w-xl"
                    />
                </div>

                    <div class="rounded-3xl border border-blue-100 bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                    <div class="rounded-3xl border border-red-100 bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
