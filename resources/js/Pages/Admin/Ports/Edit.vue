<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ port: { type: Object, required: true }, countries: { type: Array, default: () => [] } });
const form = useForm({ country_id: props.port.country_id, name: props.port.name, city: props.port.city });
const submit = () => form.put(route('admin.ports.update', props.port.id));
</script>

<template>
    <Head title="Admin – Edit port" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Edit port {{ port.name }}</h1>
                <Link :href="route('admin.ports.index')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
                <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div><label class="block text-xs font-semibold text-slate-500">Country *</label><select v-model="form.country_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required><option value="">Select</option><option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option></select><InputError :message="form.errors.country_id" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Name *</label><input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.name" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">City *</label><input v-model="form.city" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.city" /></div>
                    <div class="flex gap-2"><button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" :disabled="form.processing">Update</button><Link :href="route('admin.ports.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</Link></div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
