<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Navbar from '@/Components/Navbar.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const isRegisterRoute = computed(() => page.url.startsWith('/register'));
const isLoginRoute = computed(() => page.url.startsWith('/login'));

const heroTitle = computed(() => {
    if (isRegisterRoute.value) {
        return 'Create your logistics account in minutes';
    }

    if (isLoginRoute.value) {
        return 'Welcome back to your shipping command center';
    }

    return 'Secure access for your operations team';
});

const heroText = computed(() => {
    if (isRegisterRoute.value) {
        return 'Get full access to shipment management, account tools, and real-time tracking with a secure business-ready account.';
    }

    if (isLoginRoute.value) {
        return 'Manage container bookings, monitor shipments, and collaborate with your logistics team from one protected account.';
    }

    return 'Use protected flows to verify, recover, and secure your account without friction.';
});

const mode = computed(() => {
    if (isLoginRoute.value) {
        return 'login';
    }

    if (isRegisterRoute.value) {
        return 'register';
    }

    return 'neutral';
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-950">
        <!-- Site-wide navigation — keeps auth pages connected to the rest of the site -->
        <Navbar />

        <!-- Auth content fills the remaining viewport height -->
        <div class="relative flex-1 overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-24 -right-14 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
                <div class="absolute -bottom-20 -left-10 h-72 w-72 rounded-full bg-sky-400/15 blur-3xl"></div>
                <div
                    class="absolute inset-0 opacity-[0.04]"
                    style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 30px 30px;"
                ></div>
            </div>

            <div class="relative z-10 mx-auto flex h-full min-h-[calc(100vh-5rem)] w-full max-w-7xl items-stretch px-4 py-6 sm:px-6 lg:px-8">
                <div class="relative w-full overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/70 shadow-2xl shadow-slate-950/60 backdrop-blur-sm">
                    <div
                        class="pointer-events-none absolute inset-y-4 left-4 hidden w-[calc(50%-1rem)] rounded-[1.5rem] border border-white/10 bg-gradient-to-br from-blue-900/40 via-slate-900/30 to-sky-900/30 transition-transform duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)] lg:block"
                        :class="mode === 'register' ? 'translate-x-[calc(100%+0.5rem)]' : 'translate-x-0'"
                    ></div>

                    <div class="grid lg:grid-cols-2">
                        <aside class="relative hidden border-r border-white/10 p-10 lg:flex lg:flex-col lg:justify-between">
                            <div>
                                <Link href="/" class="inline-flex items-center gap-3">
                                    <ApplicationLogo class="h-10 w-10 fill-current text-sky-200" />
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-100/80">Logistics SWay</p>
                                        <p class="text-sm font-medium text-slate-100">Secure account access</p>
                                    </div>
                                </Link>

                                <div class="mt-10 flex items-center gap-2 rounded-full border border-blue-200/30 bg-slate-700/40 p-1.5 backdrop-blur">
                                    <Link
                                        href="/login"
                                        class="flex-1 rounded-full px-4 py-2 text-center text-sm font-semibold transition-all duration-300 ease-out"
                                        :class="mode === 'login' ? 'bg-slate-100 text-slate-900 shadow-sm shadow-slate-950/20' : 'text-slate-200 hover:bg-white/10'"
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        href="/register"
                                        class="flex-1 rounded-full px-4 py-2 text-center text-sm font-semibold transition-all duration-300 ease-out"
                                        :class="mode === 'register' ? 'bg-slate-100 text-slate-900 shadow-sm shadow-slate-950/20' : 'text-slate-200 hover:bg-white/10'"
                                    >
                                        Sign up
                                    </Link>
                                </div>

                                <div class="mt-10 space-y-4 transition-all duration-700 ease-out">
                                    <h1 class="text-4xl font-extrabold leading-tight text-slate-50">
                                        {{ heroTitle }}
                                    </h1>
                                    <p class="max-w-md text-sm leading-relaxed text-slate-300">
                                        {{ heroText }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-white/15 bg-slate-800/60 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-sky-200">Platform benefits</p>
                                    <ul class="mt-3 space-y-2 text-sm text-slate-300">
                                        <li class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span>
                                            Unified profile and shipment access
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span>
                                            Secure authentication and recovery
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-300"></span>
                                            Fast onboarding for logistics clients
                                        </li>
                                    </ul>
                                </div>

                                <p class="text-xs text-slate-400">
                                    Need a public overview?
                                    <Link href="/" class="font-semibold text-sky-200 hover:text-sky-100">
                                        Return to homepage
                                    </Link>
                                </p>
                            </div>
                        </aside>

                        <main class="relative flex min-h-[640px] items-center justify-center p-4 sm:p-8">
                            <div class="w-full max-w-lg rounded-2xl border border-slate-200/80 bg-white px-6 py-7 shadow-2xl shadow-slate-950/25 transition-all duration-700 ease-out sm:px-8"
                                :class="mode === 'register' ? 'lg:translate-x-1 lg:scale-[1.005]' : 'lg:-translate-x-1 lg:scale-[1.005]'"
                            >
                                <div class="mb-6 flex items-center justify-between lg:hidden">
                                    <Link href="/" class="inline-flex items-center gap-2">
                                        <ApplicationLogo class="h-8 w-8 fill-current text-blue-900" />
                                        <span class="text-sm font-semibold text-blue-900">Logistics SWay</span>
                                    </Link>
                                    <div class="flex items-center gap-1 rounded-full border border-slate-300 bg-slate-100 p-1">
                                        <Link
                                            href="/login"
                                            class="rounded-full px-3 py-1 text-xs font-semibold transition"
                                            :class="mode === 'login' ? 'bg-slate-50 text-slate-900 shadow-sm' : 'text-slate-500'"
                                        >
                                            Sign in
                                        </Link>
                                        <Link
                                            href="/register"
                                            class="rounded-full px-3 py-1 text-xs font-semibold transition"
                                            :class="mode === 'register' ? 'bg-slate-50 text-slate-900 shadow-sm' : 'text-slate-500'"
                                        >
                                            Sign up
                                        </Link>
                                    </div>
                                </div>

                                <Transition name="auth-smooth" mode="out-in">
                                    <div :key="mode" class="will-change-transform">
                                        <slot />
                                    </div>
                                </Transition>
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-smooth-enter-active,
.auth-smooth-leave-active {
    transition: opacity 0.38s ease, transform 0.38s ease;
}

.auth-smooth-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.99);
}

.auth-smooth-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.995);
}
</style>
