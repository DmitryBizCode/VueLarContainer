<script setup>
defineProps({
    stats: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active rentals</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.activeRentals }}</p>
            <p class="mt-1 text-sm text-slate-500">Current running contracts.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed rentals</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.completedRentals }}</p>
            <p class="mt-1 text-sm text-slate-500">Finished rental cycles.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unread notifications</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.unreadNotifications }}</p>
            <p class="mt-1 text-sm text-slate-500">Need your attention now.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activity logs</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.recentActivityCount }}</p>
            <p class="mt-1 text-sm text-slate-500">Recorded user operations.</p>
        </div>
    </div>
</template>
