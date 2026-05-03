<script setup>
defineProps({
    steps: {
        type: Array,
        required: true,
    },
    currentStep: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['go-step']);
</script>

<template>
    <nav class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-6">
        <ol class="flex items-center gap-2 sm:gap-4">
            <li
                v-for="(step, index) in steps"
                :key="step.id"
                class="flex items-center gap-2"
            >
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold transition sm:px-3"
                    :class="currentStep === step.id ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                    @click="emit('go-step', step.id)"
                >
                    <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-current" :class="currentStep === step.id ? 'border-white' : ''">{{ step.id }}</span>
                    <span class="hidden sm:inline">{{ step.short }}</span>
                </button>
                <span v-if="index < steps.length - 1" class="hidden h-px w-4 bg-slate-200 sm:block" aria-hidden="true" />
            </li>
        </ol>
        <div class="flex gap-2">
            <button
                v-show="currentStep > 1"
                type="button"
                class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                @click="emit('go-step', currentStep - 1)"
            >
                Back
            </button>
            <button
                v-show="currentStep < steps.length"
                type="button"
                class="rounded-xl bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-800"
                @click="emit('go-step', currentStep + 1)"
            >
                Next
            </button>
        </div>
    </nav>
</template>
