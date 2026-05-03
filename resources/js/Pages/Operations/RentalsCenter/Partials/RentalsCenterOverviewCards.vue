<script setup>
defineProps({
    overview: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active rentals</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ overview.activeCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Scheduled and in progress</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed rentals</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ overview.completedCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Closed operations</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overdue payments</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ overview.overduePaymentsCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Needs immediate follow-up</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Upcoming starts</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ overview.upcomingStartsCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Next 7 days</p>
            </div>
        </div>
    </section>
</template>
