<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="space-y-5">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Security</p>
            <h3 class="mt-1 text-xl font-bold text-slate-900">Update password</h3>
            <p class="mt-2 text-sm text-slate-500">
                Protect your workspace by using a strong password and rotating it regularly.
            </p>
        </header>

        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 text-xs text-slate-600">
            <p class="font-semibold text-slate-700">Password checklist</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-600" />
                    8+ characters
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-violet-600" />
                    Mixed symbols
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600" />
                    Do not reuse old password
                </span>
            </div>
        </div>

        <form @submit.prevent="updatePassword" class="space-y-5">
            <div class="space-y-1.5">
                <label for="current_password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current password</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    autocomplete="current-password"
                />
                <p v-if="form.errors.current_password" class="text-xs font-medium text-rose-600">{{ form.errors.current_password }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">New password</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password" class="text-xs font-medium text-rose-600">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password_confirmation" class="text-xs font-medium text-rose-600">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Save password
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
