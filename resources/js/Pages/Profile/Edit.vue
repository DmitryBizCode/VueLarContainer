<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

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
    telegram: {
        type: Object,
        default: () => ({
            linked: false,
            chat_id: null,
            bot_username: '',
        }),
    },
    notificationChannels: {
        type: Object,
        default: () => ({
            email_enabled: true,
            telegram_enabled: true,
            update_route: 'profile.notification-channels.update',
        }),
    },
});

const user = usePage().props.auth.user;
const activeTab = ref('overview');
const { success: toastSuccess, error: toastError } = useToast();

const telegramLinkCode = ref(null);
const telegramLinkExpiresAt = ref(null);

const telegramBotUsernameClean = computed(() => String(props.telegram?.bot_username || '').replace(/^@/, '').trim());

const telegramDeepLink = computed(() => {
    if (!telegramBotUsernameClean.value || !telegramLinkCode.value) {
        return null;
    }
    return `https://t.me/${telegramBotUsernameClean.value}?start=${encodeURIComponent(telegramLinkCode.value)}`;
});

const channelEmailEnabled = ref(!!props.notificationChannels?.email_enabled);
const channelTelegramEnabled = ref(!!props.notificationChannels?.telegram_enabled);
const channelsSaving = ref(false);

watch(
    () => props.notificationChannels,
    (ch) => {
        if (!ch) return;
        channelEmailEnabled.value = !!ch.email_enabled;
        channelTelegramEnabled.value = !!ch.telegram_enabled;
    },
    { deep: true },
);

const fullName = computed(() => [user.first_name, user.last_name].filter(Boolean).join(' ').trim() || user.email);
const initials = computed(() => [user.first_name?.[0], user.last_name?.[0]].filter(Boolean).join('').toUpperCase() || 'U');
const missingReadinessFields = computed(() => props.profileReadiness?.missingFields ?? []);

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'account', label: 'Account' },
    { key: 'security', label: 'Security' },
    { key: 'notifications', label: 'Notifications' },
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

const generateTelegramCode = async () => {
    telegramLinkCode.value = null;
    telegramLinkExpiresAt.value = null;
    try {
        const { data } = await axios.post(route('telegram.link-code'));
        telegramLinkCode.value = data?.code ?? null;
        telegramLinkExpiresAt.value = data?.expires_at ?? null;
        toastSuccess('Connection code ready');
    } catch (e) {
        toastError('Failed to generate code');
    }
};

const unlinkTelegram = async () => {
    try {
        await axios.post(route('telegram.unlink'));
        toastSuccess('Telegram unlinked');
        window.location.reload();
    } catch {
        toastError('Failed to unlink');
    }
};

const saveNotificationChannels = () => {
    channelsSaving.value = true;
    router.patch(
        route(props.notificationChannels.update_route),
        {
            notification_email_enabled: channelEmailEnabled.value,
            notification_telegram_enabled: channelTelegramEnabled.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                const first =
                    errors?.notification_email_enabled?.[0] ||
                    errors?.notification_telegram_enabled?.[0] ||
                    Object.values(errors || {})[0]?.[0];
                toastError(first || 'Could not save preferences');
            },
            onFinish: () => {
                channelsSaving.value = false;
            },
        },
    );
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
                    <section v-if="activeTab === 'notifications'" key="notifications">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                            <div class="max-w-2xl space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Delivery channels</p>
                                <h3 class="text-xl font-bold text-slate-900">Where to send alerts</h3>
                                <p class="text-sm text-slate-600">
                                    New items always appear under <span class="font-semibold text-slate-800">Notifications</span> in your cabinet. Choose if we should also email you or push to Telegram.
                                </p>
                            </div>

                            <div class="mt-6 max-w-2xl space-y-3">
                                <label
                                    class="flex cursor-pointer flex-col gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm transition hover:border-slate-300 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-900">Email</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                            Send copies to <span class="font-medium text-slate-700">{{ user.email }}</span> (rental updates, messages, and other cabinet alerts).
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <input v-model="channelEmailEnabled" type="checkbox" class="peer sr-only" />
                                        <span
                                            class="relative inline-block h-8 w-14 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-400 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-6 after:w-6 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-6"
                                        />
                                    </div>
                                </label>

                                <label
                                    class="flex cursor-pointer flex-col gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm transition hover:border-slate-300 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-900">Telegram</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                            Instant pushes to the bot when this is on, your account is linked below, and the server allows Telegram.
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <input v-model="channelTelegramEnabled" type="checkbox" class="peer sr-only" />
                                        <span
                                            class="relative inline-block h-8 w-14 rounded-full bg-slate-300 transition peer-checked:bg-sky-500 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-400 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-6 after:w-6 after:rounded-full after:bg-white after:shadow after:transition after:content-[''] peer-checked:after:translate-x-6"
                                        />
                                    </div>
                                </label>
                            </div>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800 disabled:opacity-60"
                                    :disabled="channelsSaving"
                                    @click="saveNotificationChannels"
                                >
                                    {{ channelsSaving ? 'Saving…' : 'Save preferences' }}
                                </button>
                                <p class="text-xs text-slate-500">You can turn off both — you will only see alerts inside the app.</p>
                            </div>

                            <div class="mt-10 max-w-2xl border-t border-slate-100 pt-10">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Telegram account</p>
                                        <h3 class="text-xl font-bold text-slate-900">Connect Telegram bot</h3>
                                        <p class="text-sm text-slate-600">
                                            Generate a code here, then paste it in the bot or use
                                            <span class="font-semibold text-slate-800">Open in Telegram</span> when configured. Turn on
                                            <span class="font-semibold text-slate-800">Telegram</span> above for pushes. The bot shows button rows and a
                                            <span class="font-semibold text-slate-800">Menu</span> (↔) with commands while the server worker is running.
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                        <span
                                            v-if="telegram.linked"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-800"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                            Linked
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400" />
                                            Not linked
                                        </span>
                                        <span v-if="telegramBotUsernameClean" class="text-xs font-medium text-slate-600"> @{{ telegramBotUsernameClean }} </span>
                                    </div>
                                </div>

                                <div
                                    v-if="!telegramBotUsernameClean"
                                    class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-950"
                                >
                                    <p class="font-semibold text-amber-900">Open in Telegram</p>
                                    <p class="mt-1 text-xs leading-relaxed text-amber-900/90">
                                        Set <code class="rounded bg-amber-100/90 px-1 py-0.5 font-mono text-[11px]">TELEGRAM_BOT_USERNAME</code> in the server env
                                        (no <code class="font-mono">@</code>), then reload. You can still open the bot manually and paste the code.
                                    </p>
                                </div>

                                <p class="mt-6 text-xs text-slate-500">
                                    Linking alone does not send messages until <span class="font-semibold text-slate-700">Telegram</span> is enabled above.
                                </p>

                                <ol class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <li
                                        class="flex gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-3 shadow-sm transition hover:border-slate-300"
                                    >
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200/80 text-sm font-bold text-slate-800"
                                            >1</span
                                        >
                                        <span class="text-xs font-medium leading-snug text-slate-700">Get a code below</span>
                                    </li>
                                    <li
                                        class="flex gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-3 shadow-sm transition hover:border-slate-300"
                                    >
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200/80 text-sm font-bold text-slate-800"
                                            >2</span
                                        >
                                        <span class="text-xs font-medium leading-snug text-slate-700">Open the bot, tap Start, or paste the code</span>
                                    </li>
                                    <li
                                        class="flex gap-3 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-3 shadow-sm transition hover:border-slate-300"
                                    >
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200/80 text-sm font-bold text-slate-800"
                                            >3</span
                                        >
                                        <span class="text-xs font-medium leading-snug text-slate-700">Use bot buttons or Menu (↔) for commands</span>
                                    </li>
                                </ol>

                                <div
                                    v-if="telegramLinkCode"
                                    class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Active connection code</p>
                                    <p class="mt-2 font-mono text-xl font-bold tracking-wide text-slate-900 sm:text-2xl">{{ telegramLinkCode }}</p>
                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <a
                                            v-if="telegramDeepLink"
                                            :href="telegramDeepLink"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2 transition hover:text-sky-900"
                                        >
                                            Open in Telegram
                                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                        <span class="text-xs text-slate-500">Expires {{ telegramLinkExpiresAt || '—' }}</span>
                                    </div>
                                    <p class="mt-3 text-xs text-slate-600">
                                        Or send <span class="font-mono font-semibold text-slate-800">{{ telegramLinkCode }}</span> as one message.
                                        <span class="font-mono text-[11px] text-slate-500">/link {{ telegramLinkCode }}</span>
                                    </p>
                                </div>

                                <div id="telegram-link-actions" class="mt-6 flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-slate-800"
                                        @click="generateTelegramCode"
                                    >
                                        Get connection code
                                    </button>
                                    <button
                                        v-if="telegram.linked"
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50"
                                        @click="unlinkTelegram"
                                    >
                                        Unlink
                                    </button>
                                </div>

                                <p class="mt-4 text-xs text-slate-500">
                                    The command menu in Telegram is registered when the
                                    <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[11px]">telegram:poll</code> worker starts (see
                                    <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[11px]">.env.example</code>).
                                </p>
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
                    <section v-if="activeTab === 'danger'" key="danger" class="rounded-3xl border border-rose-200 bg-white p-5 shadow-sm sm:p-8">
                        <DeleteUserForm />
                    </section>
                </Transition>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
