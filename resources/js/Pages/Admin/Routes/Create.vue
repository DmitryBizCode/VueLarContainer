<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ ports: { type: Array, default: () => [] } });
const form = useForm({ origin_port_id: '', destination_port_id: '', estimated_days: '', distance: '', route_status: 'open' });
const submit = () => form.post(route('admin.routes.store'));
</script>

<template>
    <Head title="Admin – Add route" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Add route</h1>
                <Link :href="route('admin.routes.index')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</Link>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
                <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div><label class="block text-xs font-semibold text-slate-500">Origin port *</label><select v-model="form.origin_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required><option value="">Select</option><option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option></select><InputError :message="form.errors.origin_port_id" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Destination port *</label><select v-model="form.destination_port_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required><option value="">Select</option><option v-for="p in ports" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country }})</option></select><InputError :message="form.errors.destination_port_id" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Estimated days *</label><input v-model="form.estimated_days" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /><InputError :message="form.errors.estimated_days" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Distance</label><input v-model="form.distance" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
                    <div><label class="block text-xs font-semibold text-slate-500">Status *</label><select v-model="form.route_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="open">Open</option><option value="closed">Closed</option></select><InputError :message="form.errors.route_status" /></div>
                    <div class="flex gap-2"><button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" :disabled="form.processing">Create</button><Link :href="route('admin.routes.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</Link></div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
