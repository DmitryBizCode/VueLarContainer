<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    countries: {
        type: Array,
        default: () => [],
    },
    profileCompletion: {
        type: Number,
        default: 0,
    },
    profileReadiness: {
        type: Object,
        default: () => ({
            items: [],
            missingFields: [],
        }),
    },
    accountSummary: {
        type: Object,
        default: () => ({
            role: 'client',
            accountStatus: 'pending_verification',
            emailVerified: false,
            memberSince: null,
            lastUpdatedAt: null,
            countryName: null,
        }),
    },
});

const user = usePage().props.auth.user;
const activeTab = ref('overview');

const fullName = computed(() => [user.first_name, user.last_name].filter(Boolean).join(' ').trim() || user.email);
const initials = computed(() => [user.first_name?.[0], user.last_name?.[0]].filter(Boolean).join('').toUpperCase() || 'U');
const missingReadinessFields = computed(() => props.profileReadiness?.missingFields ?? []);

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'account', label: 'Account' },
    { key: 'security', label: 'Security' },
    { key: 'danger', label: 'Danger Zone' },
];

const markerIconPath = (state) => {
    const normalized = String(state || '').toLowerCase();

    if (['warning', 'pending'].includes(normalized)) {
        return 'M8 2.75l6 10.5H2l6-10.5zm0 3.25a.75.75 0 0 0-.75.75v2.75a.75.75 0 1 0 1.5 0V6.75A.75.75 0 0 0 8 6zm0 6a.9.9 0 1 0 0-1.8.9.9 0 0 0 0 1.8z';
    }

    if (['failed', 'error'].includes(normalized)) {
        return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm-2.1 3.4a.75.75 0 0 0 0 1.06L6.94 8 5.9 9.04a.75.75 0 1 0 1.06 1.06L8 9.06l1.04 1.04a.75.75 0 0 0 1.06-1.06L9.06 8l1.04-1.04A.75.75 0 1 0 9.04 5.9L8 6.94 6.96 5.9a.75.75 0 0 0-1.06 0z';
    }

    return 'M8 2.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zm2.35 3.95a.75.75 0 1 0-1.2-.9L7.55 7.7l-.72-.68a.75.75 0 1 0-1.04 1.08l1.35 1.3c.33.31.85.29 1.15-.06l2.06-2.89z';
};

const markerColor = (state) => {
    const normalized = String(state || '').toLowerCase();
    if (['warning', 'pending'].includes(normalized)) return 'text-amber-500';
    if (['failed', 'error'].includes(normalized)) return 'text-rose-600';
    return 'text-emerald-600';
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
};
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Personal cabinet</p>
                    <h2 class="mt-1 text-2xl font-bold leading-tight text-slate-900">Profile control center</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-600" />
                    Readiness {{ profileCompletion }}%
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 p-6 text-white shadow-sm">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <p class="text-xs uppercase tracking-[0.16em] text-blue-100/80">Account overview</p>
                            <h3 class="mt-2 text-2xl font-bold">{{ fullName }}</h3>
                            <p class="mt-2 text-sm text-blue-100/85">
                                Manage identity, security and account lifecycle from one workspace aligned with your logistics operations.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                                    Role: {{ accountSummary.role }}
                                </span>
                                <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                                    Status: {{ accountSummary.accountStatus }}
                                </span>
                                <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-blue-100">
                                    Country: {{ accountSummary.countryName || 'Not set' }}
                                </span>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 text-lg font-bold text-white">
                                    {{ initials }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ fullName }}</p>
                                    <p class="truncate text-xs text-blue-100/80">{{ user.email }}</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-blue-100/80">Member since {{ formatDate(accountSummary.memberSince) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center rounded-xl border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                            :class="activeTab === tab.key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </section>

                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <section v-if="activeTab === 'overview'" key="overview" class="grid gap-6 lg:grid-cols-12">
                        <div class="space-y-6 lg:col-span-5">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-bold text-slate-900">Profile readiness</h3>
                                    <span class="text-sm font-extrabold text-slate-900">{{ profileCompletion }}%</span>
                                </div>
                                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-blue-700 to-slate-900 transition-all duration-700" :style="{ width: `${profileCompletion}%` }" />
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="item in profileReadiness.items"
                                        :key="item.key"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700"
                                    >
                                        <svg class="h-3 w-3" :class="markerColor(item.done ? 'success' : 'pending')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                            <path :d="markerIconPath(item.done ? 'success' : 'pending')" />
                                        </svg>
                                        {{ item.label }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-slate-500">Readiness gaps</h4>
                                <div v-if="missingReadinessFields.length" class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="field in missingReadinessFields"
                                        :key="field"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        <svg class="h-3 w-3 text-amber-500" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                            <path :d="markerIconPath('pending')" />
                                        </svg>
                                        {{ field }}
                                    </span>
                                </div>
                                <p v-else class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700">
                                    <svg class="h-3 w-3 text-emerald-600" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                        <path :d="markerIconPath('success')" />
                                    </svg>
                                    Profile is complete.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-6 lg:col-span-7">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-lg font-bold text-slate-900">Account summary</h3>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Email verification</p>
                                        <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-900">
                                            <svg class="h-3 w-3" :class="markerColor(accountSummary.emailVerified ? 'success' : 'pending')" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path :d="markerIconPath(accountSummary.emailVerified ? 'success' : 'pending')" />
                                            </svg>
                                            {{ accountSummary.emailVerified ? 'Verified' : 'Pending verification' }}
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Last updated</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatDate(accountSummary.lastUpdatedAt) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Phone</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ user.phone_number || 'Not set' }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Company</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ user.company_name || 'Not set' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-slate-500">Next recommended steps</h4>
                                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                    <li class="inline-flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-blue-600" />
                                        Complete missing profile fields to reduce manual checks during rentals.
                                    </li>
                                    <li class="inline-flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-violet-600" />
                                        Keep company and address current for billing and contract documents.
                                    </li>
                                    <li class="inline-flex items-start gap-2">
                                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-600" />
                                        Review password policy in Security tab every 60-90 days.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </Transition>

                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <section v-if="activeTab === 'account'" key="account" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                            :countries="countries"
                        />
                    </section>
                </Transition>

                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <section v-if="activeTab === 'security'" key="security" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        <UpdatePasswordForm />
                    </section>
                </Transition>

                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <section v-if="activeTab === 'danger'" key="danger" class="rounded-3xl border border-rose-200 bg-white p-5 shadow-sm sm:p-8">
                        <DeleteUserForm />
                    </section>
                </Transition>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
