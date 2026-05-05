<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="flex min-w-0 items-start justify-between gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="break-words text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ eyebrow }}</p>
            <h1 class="mt-1 break-words text-xl font-bold text-slate-900">{{ title }}</h1>
        </div>
        <div v-if="$slots.aside" class="hidden shrink-0 sm:block">
            <slot name="aside" />
        </div>
    </div>
</template>
