<script setup>
const props = defineProps({
    containers: {
        type: Array,
        default: () => [],
    },
    selectedContainerId: {
        type: [String, Number, null],
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:selectedContainerId']);

const statusLabel = (value) =>
    String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Step 2</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Matching containers</h3>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ containers.length }} available
            </span>
        </div>

        <div v-if="loading" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
            Calculating matches...
        </div>

        <div v-else-if="!containers.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
            No containers currently match your criteria.
        </div>

        <div v-else class="space-y-3">
            <label
                v-for="container in containers"
                :key="container.id"
                class="block cursor-pointer rounded-2xl border p-4 transition"
                :class="String(selectedContainerId) === String(container.id) ? 'border-blue-700 bg-blue-50/40' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50'"
            >
                <input
                    type="radio"
                    class="sr-only"
                    name="container_id"
                    :value="container.id"
                    :checked="String(selectedContainerId) === String(container.id)"
                    @change="emit('update:selectedContainerId', String(container.id))"
                >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">{{ container.serial_number }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ container.type }} · {{ container.dimensions }} · max {{ container.max_weight }} kg
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Owner: {{ container.owner_name || 'N/A' }} · Port: {{ container.current_port_name || 'N/A' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="container.current_status"
                            class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700"
                        >
                            {{ statusLabel(container.current_status) }}
                        </span>
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">
                            IoT {{ container.iot_active ? 'on' : 'off' }}
                        </span>
                    </div>
                </div>
            </label>
        </div>
    </section>
</template>
