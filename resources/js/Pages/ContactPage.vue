<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = ref({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const subjects = ref([
    'General Inquiry',
    'Request a Quote',
    'Container Rental',
    'Shipping & Logistics',
    'IoT Monitoring',
    'Partnership Opportunity',
    'Technical Support',
    'Other',
]);

const aboutCards = ref([
    {
        image: '/image/contact1.webp',
        title: 'Our Mission',
        description: 'Efficient, innovative, and reliable shipping solutions for clients worldwide. We invest in technology and people to make global logistics seamless.',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
    },
    {
        image: '/image/contact2.webp',
        title: 'Our Vision',
        description: 'To be the most trusted logistics partner for global trade, setting industry standards for transparency, service quality, and innovation.',
        icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    },
    {
        image: '/image/contact3.webp',
        title: 'Our Values',
        description: 'Commitment to quality, integrity, and respect. Every shipment matters — we treat your cargo with the same care we would our own.',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
]);

const officeInfo = ref([
    {
        label: 'Address',
        value: 'Gavrilishina 24, Kyiv, Ukraine',
        icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    },
    {
        label: 'Phone',
        value: '+38 (099) 547-23-54',
        icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
    },
    {
        label: 'Email',
        value: 'dmitrybeznosiukcode@gmail.com',
        icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    },
]);

const workingHours = ref([
    { day: 'Mon — Fri', hours: '9:00 — 18:00' },
    { day: 'Saturday', hours: '10:00 — 15:00' },
    { day: 'Sunday', hours: 'Closed' },
]);

const socialLinks = ref([
    { name: 'Facebook', url: 'https://www.facebook.com', icon: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z' },
    { name: 'Twitter', url: 'https://www.twitter.com', icon: 'M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z' },
    { name: 'Instagram', url: 'https://www.instagram.com', icon: 'M16 4H8a4 4 0 00-4 4v8a4 4 0 004 4h8a4 4 0 004-4V8a4 4 0 00-4-4z M12 9a3 3 0 100 6 3 3 0 000-6z M17.5 6.5h.01' },
    { name: 'LinkedIn', url: 'https://www.linkedin.com', icon: 'M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z M2 9h4v12H2z M4 2a2 2 0 100 4 2 2 0 000-4z' },
]);

const highlights = ref([
    {
        label: 'Dedicated Account Manager',
        description: 'Every client gets a personal logistics expert — one point of contact from quote to delivery.',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
    {
        label: 'Average Response Time',
        value: '< 4 hours',
        description: 'Our team replies within 4 hours on business days, including custom quote requests.',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        label: 'Languages We Speak',
        description: 'Ukrainian, English, German, Polish, Turkish — communicate in the language you prefer.',
        icon: 'M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129',
    },
]);

const handleSubmit = () => {
    console.log('Form submitted:', form.value);
};
</script>

<template>
    <AppLayout active-route="contact">
        <Head title="Contact Us — Logistics Co." />

        <!-- Hero Section — Asymmetric like HomePage -->
        <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-blue-900">
            <div class="absolute inset-0">
                <img src="/image/cont1.jpg" alt="Contact Background" class="w-full h-full object-cover opacity-10" />
                <div class="absolute inset-0 bg-gradient-to-br from-blue-900/95 via-blue-900/85 to-blue-800/90"></div>
            </div>
            <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-yellow-400/8 to-transparent"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-700 rounded-full blur-3xl opacity-20"></div>
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0z&quot; fill=&quot;none&quot; stroke=&quot;white&quot; stroke-width=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 40px 40px;"></div>

            <div class="relative container mx-auto px-6 py-16 z-10">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <!-- Left: Title & CTA -->
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-400/15 backdrop-blur-sm border border-yellow-400/25 rounded-full mb-8">
                            <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                            <span class="text-yellow-400 text-sm font-medium">Get in Touch</span>
                        </div>
                        <h1 class="text-5xl md:text-7xl font-extrabold text-white mb-6 leading-tight">
                            Let's Build
                            <span class="block text-yellow-400 mt-2">Together</span>
                        </h1>
                        <p class="text-xl text-blue-100 mb-6 max-w-xl leading-relaxed">
                            Questions about logistics, container rental, or a custom shipping solution? Our expert team is ready to help you every step of the way.
                        </p>

                        <div class="flex flex-wrap gap-2 mb-8">
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">24h Response</span>
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">Free Consultation</span>
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">Custom Solutions</span>
                        </div>

                        <div class="h-px bg-gradient-to-r from-transparent via-yellow-400/40 to-transparent mb-8 max-w-md"></div>

                        <div class="flex flex-wrap gap-4">
                            <a href="#form" class="px-8 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-300 transition-colors shadow-lg">
                                Send a Message
                            </a>
                            <a href="tel:+380995472354" class="px-8 py-4 bg-white/10 backdrop-blur-sm border-2 border-white/25 text-white font-bold rounded-lg hover:bg-white/20 transition-colors">
                                Call Us Now
                            </a>
                        </div>
                    </div>

                    <!-- Right: Contact Info Card -->
                    <div class="hidden lg:block">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/15 to-blue-600/15 rounded-3xl transform rotate-3"></div>
                            <div class="relative bg-white/5 backdrop-blur-sm rounded-3xl p-8 border border-white/10">
                                <!-- Office info -->
                                <div class="space-y-4 mb-6">
                                    <div
                                        v-for="(info, index) in officeInfo"
                                        :key="index"
                                        class="flex items-center gap-4 p-3.5 bg-blue-800/40 rounded-xl border border-blue-700/20"
                                    >
                                        <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center border border-white/10">
                                            <svg class="w-4.5 h-4.5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="info.icon" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-blue-300/60 text-xs font-semibold uppercase tracking-wider">{{ info.label }}</div>
                                            <div class="text-white text-sm font-medium">{{ info.value }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Working hours -->
                                <div class="pt-5 border-t border-white/10">
                                    <div class="text-blue-300/50 text-xs font-semibold uppercase tracking-wider mb-3">Working Hours</div>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(schedule, index) in workingHours"
                                            :key="index"
                                            class="flex items-center justify-between text-sm"
                                        >
                                            <span class="text-blue-200/70">{{ schedule.day }}</span>
                                            <span
                                                class="font-semibold"
                                                :class="schedule.hours === 'Closed' ? 'text-red-400/70' : 'text-yellow-400'"
                                            >{{ schedule.hours }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Social -->
                                <div class="pt-5 mt-5 border-t border-white/10 flex items-center justify-between">
                                    <span class="text-blue-300/50 text-xs font-semibold uppercase tracking-wider">Follow Us</span>
                                    <div class="flex gap-2">
                                        <a
                                            v-for="(social, index) in socialLinks"
                                            :key="index"
                                            :href="social.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center text-blue-200/70 hover:bg-yellow-400 hover:text-blue-900 transition-all duration-200"
                                            :title="social.name"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path :d="social.icon" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Us — Light Section -->
        <section class="py-12 bg-gradient-to-b from-white via-gray-50/50 to-gray-50">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-blue-50 border border-blue-200 rounded-full mb-4">
                        <span class="text-blue-600 text-sm font-semibold">Who We Are</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">About Our Company</h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        A leading logistics provider since 2004, delivering container solutions across the globe.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <div
                        v-for="(card, index) in aboutCards"
                        :key="index"
                        class="group relative bg-white rounded-2xl p-6 border-2 border-gray-100 hover:border-blue-600 hover:shadow-lg transition-all duration-300 overflow-hidden"
                    >
                        <!-- Top accent line on hover -->
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-yellow-400 to-blue-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                        <!-- Watermark number -->
                        <span class="absolute top-3 right-4 text-6xl font-black text-blue-900/[0.04] select-none pointer-events-none">0{{ index + 1 }}</span>

                        <div class="relative z-10">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-14 h-14 rounded-xl overflow-hidden border-2 border-gray-100 group-hover:border-blue-200 transition-colors flex-shrink-0">
                                    <img :src="card.image" :alt="card.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-blue-900 transition-colors">{{ card.title }}</h3>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed">{{ card.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Form + Office Info -->
        <section id="form" class="py-12 bg-gradient-to-b from-gray-50 to-white">
            <div class="container mx-auto px-6">
                <div class="max-w-5xl mx-auto">
                    <div class="grid lg:grid-cols-2 gap-10">

                        <!-- Left: Form -->
                        <div class="bg-white rounded-2xl p-7 border border-gray-200 shadow-sm">
                            <h2 class="text-2xl font-bold text-gray-900 mb-1">Send a Message</h2>
                            <p class="text-sm text-gray-500 mb-6">We'll get back to you within one business day.</p>

                            <form @submit.prevent="handleSubmit" class="space-y-4">
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Name</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </div>
                                            <input
                                                type="text" id="name" v-model="form.name"
                                                placeholder="John Doe" required
                                                class="w-full bg-gray-50/80 border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 outline-none"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label for="email" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Email</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </div>
                                            <input
                                                type="email" id="email" v-model="form.email"
                                                placeholder="john@company.com" required
                                                class="w-full bg-gray-50/80 border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="subject" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Subject</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                        </div>
                                        <select
                                            id="subject" v-model="form.subject" required
                                            class="w-full bg-gray-50/80 border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 outline-none appearance-none"
                                        >
                                            <option value="" disabled>Select a subject</option>
                                            <option v-for="subject in subjects" :key="subject" :value="subject">{{ subject }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label for="message" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Message</label>
                                    <textarea
                                        id="message" v-model="form.message" rows="5"
                                        placeholder="Tell us about your logistics needs..."
                                        required
                                        class="w-full bg-gray-50/80 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all duration-200 outline-none resize-none"
                                    ></textarea>
                                </div>
                                <button
                                    type="submit"
                                    class="w-full px-8 py-3.5 bg-gradient-to-r from-blue-900 to-blue-800 text-white font-bold text-sm rounded-xl hover:from-blue-800 hover:to-blue-700 transition-all shadow-lg shadow-blue-900/20"
                                >
                                    Send Message
                                </button>
                            </form>
                        </div>

                        <!-- Right: Why contact us -->
                        <div class="space-y-4">
                            <!-- Office photo -->
                            <div class="relative rounded-2xl overflow-hidden">
                                <img src="/image/cont1.jpg" alt="Our Office" class="w-full h-44 object-cover" />
                                <div class="absolute inset-0 bg-gradient-to-t from-blue-900/80 via-blue-900/20 to-transparent"></div>
                                <div class="absolute bottom-4 left-5">
                                    <div class="text-white font-bold">Logistics Co. HQ</div>
                                    <div class="text-blue-200/80 text-xs">Kyiv, Ukraine · Since 2004</div>
                                </div>
                            </div>

                            <!-- Highlights -->
                            <div
                                v-for="(item, index) in highlights"
                                :key="index"
                                class="group flex gap-3.5 p-4 bg-white rounded-xl border border-gray-200 hover:border-blue-200 transition-all duration-300"
                            >
                                <div class="flex-shrink-0 w-9 h-9 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center mt-0.5">
                                    <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-gray-900 mb-0.5 group-hover:text-blue-900 transition-colors">{{ item.label }}</div>
                                    <p class="text-xs text-gray-500 leading-relaxed">{{ item.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
