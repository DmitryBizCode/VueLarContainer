<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const page = usePage();
const profileDestroyRoute = computed(() => ((page.url || '').startsWith('/admin') ? route('admin.profile.destroy') : route('profile.destroy')));

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(profileDestroyRoute.value, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-600">Danger zone</p>
            <h3 class="mt-1 text-xl font-bold text-slate-900">Delete account</h3>
            <p class="mt-2 text-sm text-slate-500">
                This action permanently removes your account and related resources. Please make sure all required exports are completed.
            </p>
        </header>

        <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-medium text-rose-900">Permanent action. This cannot be undone.</p>
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-rose-500"
                    @click="confirmUserDeletion"
                >
                    Delete account
                </button>
            </div>
        </div>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-slate-900">
                    Are you sure you want to delete your account?
                </h2>

                <p class="mt-2 text-sm text-slate-600">
                    Once your account is deleted, all of its resources and data will be permanently removed. Enter your password to confirm.
                </p>

                <div class="mt-6 space-y-1.5">
                    <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Password</label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <p v-if="form.errors.password" class="text-xs font-medium text-rose-600">{{ form.errors.password }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-rose-500 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Delete Account
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
