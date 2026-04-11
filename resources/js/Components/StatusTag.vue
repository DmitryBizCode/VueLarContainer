<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md', // md | sm
    },
});

const normalized = computed(() => {
    const raw = String(props.status ?? '').trim().toLowerCase();
    if (!raw) return '';
    return raw.replace(/\s+/g, '_');
});

const variantKey = computed(() => {
    const s = normalized.value;
    if (!s) return 'default';

    // Map app-specific statuses into generic variants
    if (['pending', 'pending_approval', 'scheduled'].includes(s)) return 'pending';
    if (['in_progress', 'draft'].includes(s)) return 'in_progress';
    if (['submitted', 'requested'].includes(s)) return 'submitted';
    if (['in_review', 'approved'].includes(s)) return 'in_review';
    if (['success', 'completed', 'paid'].includes(s)) return 'success';
    if (['failed', 'rejected', 'cancelled', 'canceled'].includes(s)) return 'failed';
    if (['expired'].includes(s)) return 'expired';

    return 'default';
});

const variants = {
    pending: {
        wrapper: 'bg-amber-50 text-amber-800 border border-amber-200',
        icon: 'warning',
    },
    in_progress: {
        wrapper: 'bg-sky-50 text-sky-800 border border-sky-200',
        icon: 'progress',
    },
    submitted: {
        wrapper: 'bg-violet-50 text-violet-800 border border-violet-200',
        icon: 'paper_plane',
    },
    in_review: {
        wrapper: 'bg-amber-50 text-amber-800 border border-amber-200',
        icon: 'review',
    },
    success: {
        wrapper: 'bg-emerald-50 text-emerald-800 border border-emerald-200',
        icon: 'success',
    },
    failed: {
        wrapper: 'bg-rose-50 text-rose-800 border border-rose-200',
        icon: 'failed',
    },
    expired: {
        wrapper: 'bg-slate-100 text-slate-700 border border-slate-200',
        icon: 'expired',
    },
    default: {
        wrapper: 'bg-slate-50 text-slate-700 border border-slate-200',
        icon: 'dot',
    },
};

const currentVariant = computed(() => variants[variantKey.value] ?? variants.default);

const sizeClasses = computed(() => {
    return props.size === 'sm'
        ? 'px-2.5 py-0.5 text-[11px]'
        : 'px-3 py-1 text-xs';
});

const displayLabel = computed(() => {
    if (props.label) return props.label;
    const raw = String(props.status ?? '');
    if (!raw) return '';
    return raw.replace(/_/g, ' ');
});
</script>

<template>
    <span
        v-if="displayLabel"
        class="inline-flex items-center gap-1.5 rounded-full font-medium shadow-sm"
        :class="[currentVariant.wrapper, sizeClasses]"
    >
        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-white/60">
            <!-- Warning / pending -->
            <svg
                v-if="currentVariant.icon === 'warning'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M9.048 2.927a1.5 1.5 0 0 1 1.904 0l6.518 5.66A1.5 1.5 0 0 1 16.518 12H3.482a1.5 1.5 0 0 1-1.952-1.413c0-.43.187-.839.514-1.12l6.518-5.54Z"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linejoin="round"
                />
                <path
                    d="M10 7v3.2"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                />
                <circle cx="10" cy="13.2" r="0.9" fill="currentColor" />
            </svg>

            <!-- In progress / spinner -->
            <svg
                v-else-if="currentVariant.icon === 'progress'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    cx="10"
                    cy="10"
                    r="6"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-dasharray="4 3"
                />
            </svg>

            <!-- Submitted / paper plane -->
            <svg
                v-else-if="currentVariant.icon === 'paper_plane'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M15.5 4.5 9 11"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M5 8.5 15.5 4.5 11.5 15l-2-4-4-2.5Z"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linejoin="round"
                />
            </svg>

            <!-- In review / magnifier -->
            <svg
                v-else-if="currentVariant.icon === 'review'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    cx="8.5"
                    cy="8.5"
                    r="3.5"
                    stroke="currentColor"
                    stroke-width="1.4"
                />
                <path
                    d="M11.2 11.2 14 14"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                />
            </svg>

            <!-- Success / check -->
            <svg
                v-else-if="currentVariant.icon === 'success'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    cx="10"
                    cy="10"
                    r="6"
                    stroke="currentColor"
                    stroke-width="1.4"
                />
                <path
                    d="M7.8 10.2 9.3 11.7 12.2 8.8"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            <!-- Failed / x -->
            <svg
                v-else-if="currentVariant.icon === 'failed'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    cx="10"
                    cy="10"
                    r="6"
                    stroke="currentColor"
                    stroke-width="1.4"
                />
                <path
                    d="M8 8l4 4M12 8l-4 4"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                />
            </svg>

            <!-- Expired / clock -->
            <svg
                v-else-if="currentVariant.icon === 'expired'"
                class="h-3 w-3"
                viewBox="0 0 20 20"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    cx="10"
                    cy="10"
                    r="6"
                    stroke="currentColor"
                    stroke-width="1.4"
                />
                <path
                    d="M10 7.5V10l2 1.5"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            <!-- Default / dot -->
            <svg
                v-else
                class="h-2.5 w-2.5"
                viewBox="0 0 10 10"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle cx="5" cy="5" r="3" fill="currentColor" />
            </svg>
        </span>
        <span class="capitalize">
            {{ displayLabel }}
        </span>
    </span>
</template>

