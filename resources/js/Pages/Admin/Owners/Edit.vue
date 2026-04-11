<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ owner: { type: Object, required: true } });
const form = useForm({ name: props.owner.name, email: props.owner.email, phone_number: props.owner.phone_number });
const submit = () => form.put(route('admin.owners.update', props.owner.id));
</script>

<template>
    <Head title="Admin – Edit owner" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Edit owner {{ owner.name }}</h1>
                <Link :href="route('admin.owners.index')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
                <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div><label class="block text-xs font-semibold text-slate-500">Name *</label><input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.name" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Email *</label><input v-model="form.email" type="email" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.email" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Phone *</label><input v-model="form.phone_number" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.phone_number" /></div>
                    <div class="flex gap-2"><button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" :disabled="form.processing">Update</button><Link :href="route('admin.owners.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</Link></div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
