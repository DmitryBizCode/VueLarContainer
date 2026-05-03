<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const stats = ref([
    { value: '200+', label: 'Employees' },
    { value: '100,000+', label: 'Clients' },
    { value: '5', label: 'Continents' },
    { value: '25M+ kg', label: 'Goods Transported' },
]);

const features = ref([
    {
        title: 'Global Shipping Coverage',
        description: 'Direct routes from Odesa and Chornomorsk to 50+ ports across 5 continents. FCL and LCL options with transit times from 8 to 35 days depending on destination.',
    },
    {
        title: 'IoT Monitoring Solutions',
        description: '8 sensor types per container: temperature, humidity, vibration, GPS, and more. Data transmitted every 15 minutes via LTE-M with satellite fallback. EN 12830 certified.',
    },
    {
        title: 'Online Management Platform',
        description: 'Track up to 500 containers simultaneously. REST API for ERP integration, custom alert thresholds, CSV/PDF export, and 5-year data retention.',
    },
]);

const services = ref([
    'Full Container Load (FCL)',
    'Less than Container Load (LCL)',
    'End-to-End Container Management',
    'Real-Time Cargo Tracking',
    'Customs Documentation Handling',
    'Secure Container Storage',
    'Container Maintenance & Repair',
    'Insurance Coverage',
]);

const benefits = ref([
    {
        image: '/image/Reliable Delivery.webp',
        title: 'Reliable Delivery',
        description: '98.7% on-time delivery rate across 15,000+ annual shipments. Dedicated account managers for priority and time-sensitive cargo.',
        stat: '98.7%',
        statLabel: 'on-time',
    },
    {
        image: '/image/Warehouse Services.webp',
        title: 'Warehouse Services',
        description: 'Bonded and non-bonded warehousing in Odesa, Hamburg, and Rotterdam. Short-term cross-docking and long-term storage with inventory management.',
        stat: '3',
        statLabel: 'locations',
    },
    {
        image: '/image/EU and Global Reach.webp',
        title: 'Global Reach',
        description: '120+ active trade routes from Ukraine to all continents. AEO-certified EU clearance with mutual recognition in Japan, China, and the US.',
        stat: '120+',
        statLabel: 'routes',
    },
    {
        image: '/image/Product Sourcing.webp',
        title: 'Product Sourcing',
        description: 'Procurement support in China, Turkey, and Southeast Asia. Factory audits, quality inspection, and consolidated shipping to reduce per-unit cost.',
        stat: '3',
        statLabel: 'regions',
    },
]);

const logisticsInfo = ref([
    {
        title: 'Container Types & Specifications',
        content: '20ft standard (33.2 m³, 28.2t payload), 40ft standard (67.7 m³), 40ft high cube (76.3 m³), and 20ft reefer (-30°C to +30°C). All units ISO 668/6346 certified with valid CSC plates. Flat rack, open top, and tank containers available on request.',
        highlight: '4 container types',
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    },
    {
        title: 'Shipping Routes & Transit Times',
        content: 'Ukraine to UK: 8–12 days. Ukraine to Germany: 10–14 days (incl. rail to Duisburg). Ukraine to USA East Coast: 14–18 days. Ukraine to China: 25–32 days via Suez. All times from Odesa/Chornomorsk. COSCO, OOCL, and Maersk alliance bookings.',
        highlight: '8–35 day transit',
        icon: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        title: 'Customs & Documentation',
        content: 'Full document preparation: Bill of Lading (B/L), CMR waybills, EUR.1 certificates of origin, HS code classification, T1 transit declarations, and phytosanitary certificates. Post-Brexit GVMS expertise for UK-bound cargo. AEO-certified clearance.',
        highlight: 'AEO certified',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        title: 'Logistics Process Overview',
        content: 'Door-to-door chain: factory pickup, VGM-compliant weighing, container loading, sea freight with 15-min IoT updates, port discharge, customs clearance (avg. 4h for AEO), last-mile delivery. Average booking-to-dispatch: 48 hours for standard shipments.',
        highlight: '48h dispatch',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
    },
]);

const processes = ref([
    {
        step: '01',
        title: 'Booking & Planning',
        description: 'Schedule your shipment and select container type. Our logistics team coordinates pickup and delivery dates.',
    },
    {
        step: '02',
        title: 'Cargo Collection',
        description: 'Professional handling at origin warehouse with proper packaging and loading into containers.',
    },
    {
        step: '03',
        title: 'Sea Freight Transport',
        description: 'Secure ocean transportation with real-time GPS tracking and condition monitoring throughout the journey.',
    },
    {
        step: '04',
        title: 'Delivery & Unloading',
        description: 'Final customs clearance and delivery to your designated destination warehouse or facility.',
    },
]);
</script>

<template>
    <AppLayout>
        <Head title="Global Container Shipping & Logistics" />

        <!-- Hero Section - Asymmetric Design -->
        <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-blue-900">
            <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-yellow-400/10 to-transparent"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-800 rounded-full blur-3xl opacity-50"></div>
            <!-- Subtle grid pattern overlay -->
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0z&quot; fill=&quot;none&quot; stroke=&quot;white&quot; stroke-width=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 40px 40px;"></div>
            
            <div class="relative container mx-auto px-6 pt-16 pb-16 z-10">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-400/20 backdrop-blur-sm border border-yellow-400/30 rounded-full mb-8">
                            <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                            <span class="text-yellow-400 text-sm font-medium">Trusted Worldwide</span>
                        </div>
                        <h1 class="text-5xl md:text-7xl font-extrabold text-white mb-6 leading-tight">
                            Container Shipping
                            <span class="block text-yellow-400 mt-2">Made Simple</span>
                        </h1>
                        <p class="text-xl text-blue-100 mb-10 max-w-xl leading-relaxed">
                            Reliable container rental services with IoT-enabled tracking. 
                            Your trusted partner for seamless global shipping operations.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <Link
                                href="#services"
                                class="px-8 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-300 transition-colors shadow-lg"
                            >
                                Explore Services
                            </Link>
                            <Link
                                href="#contact"
                                class="px-8 py-4 bg-white/10 backdrop-blur-sm border-2 border-white/30 text-white font-bold rounded-lg hover:bg-white hover:text-blue-900 transition-all"
                            >
                                Get Quote
                            </Link>
                        </div>
                    </div>
                    <div class="hidden lg:block">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/20 to-blue-600/20 rounded-3xl transform rotate-6"></div>
                            <div class="relative bg-white/5 backdrop-blur-sm rounded-3xl p-8 border border-white/10">
                                <div class="grid grid-cols-2 gap-5">
                                    <div class="bg-blue-800/50 rounded-xl p-5 backdrop-blur-sm border border-white/5">
                                        <div class="text-3xl font-bold text-yellow-400 mb-1">200+</div>
                                        <div class="text-blue-200 text-sm mb-1.5">Employees</div>
                                        <div class="w-full h-px bg-gradient-to-r from-yellow-400/20 to-transparent"></div>
                                        <div class="text-blue-300/40 text-[10px] mt-1.5">5 countries</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-5 backdrop-blur-sm border border-white/5">
                                        <div class="text-3xl font-bold text-yellow-400 mb-1">100K+</div>
                                        <div class="text-blue-200 text-sm mb-1.5">Clients</div>
                                        <div class="w-full h-px bg-gradient-to-r from-yellow-400/20 to-transparent"></div>
                                        <div class="text-blue-300/40 text-[10px] mt-1.5">Since 2004</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-5 backdrop-blur-sm border border-white/5">
                                        <div class="text-3xl font-bold text-yellow-400 mb-1">5</div>
                                        <div class="text-blue-200 text-sm mb-1.5">Continents</div>
                                        <div class="w-full h-px bg-gradient-to-r from-yellow-400/20 to-transparent"></div>
                                        <div class="text-blue-300/40 text-[10px] mt-1.5">50+ ports</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-5 backdrop-blur-sm border border-white/5">
                                        <div class="text-3xl font-bold text-yellow-400 mb-1">25M+</div>
                                        <div class="text-blue-200 text-sm mb-1.5">KG Shipped</div>
                                        <div class="w-full h-px bg-gradient-to-r from-yellow-400/20 to-transparent"></div>
                                        <div class="text-blue-300/40 text-[10px] mt-1.5">15K+ shipments/yr</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Bar - Floating Cards -->
        <section class="relative -mt-20 z-20 transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6 pb-8">
                <div class="bg-white rounded-2xl shadow-2xl p-8">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div
                            v-for="(stat, index) in stats"
                            :key="index"
                            class="text-center p-4 hover:bg-blue-50 rounded-xl transition-colors duration-300 relative"
                            :style="{ animationDelay: index * 0.1 + 's' }"
                        >
                            <div v-if="index < stats.length - 1" class="hidden md:block absolute right-0 top-1/2 -translate-y-1/2 w-px h-8 bg-gray-200"></div>
                            <div class="text-4xl font-bold text-blue-900 mb-1">{{ stat.value }}</div>
                            <div class="text-sm text-gray-600 uppercase tracking-wide mb-1">{{ stat.label }}</div>
                            <div class="w-6 h-0.5 bg-yellow-400 mx-auto rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section - Modern Cards -->
        <section class="py-12 bg-gradient-to-b from-white via-gray-50/50 to-gray-50 transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-full mb-4">
                        <div class="w-1.5 h-1.5 bg-blue-600 rounded-full"></div>
                        <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Core Services</span>
                    </div>
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Complete Logistics Solutions</h2>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                        Everything you need for successful global shipping operations
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 mb-12">
                    <div
                        v-for="(feature, index) in features"
                        :key="index"
                        class="group relative bg-white rounded-2xl p-8 border-2 border-gray-100 hover:border-blue-600 hover:shadow-lg transition-all duration-300 overflow-hidden"
                        :style="{ animationDelay: index * 0.15 + 's' }"
                    >
                        <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-blue-600/10 to-yellow-400/10 rounded-bl-full"></div>
                        <div class="relative z-10">
                            <div class="text-4xl font-bold text-blue-900/20 mb-4 group-hover:text-blue-900/30 transition-colors">0{{ index + 1 }}</div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-blue-900 transition-colors">{{ feature.title }}</h3>
                            <p class="text-gray-600 leading-relaxed group-hover:text-gray-700 transition-colors">{{ feature.description }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 rounded-2xl p-10 text-white relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-yellow-400/15 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-600/15 rounded-full blur-3xl"></div>
                    <div class="relative">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-1 h-8 bg-gradient-to-b from-yellow-400 to-yellow-500 rounded-full"></div>
                            <h3 class="text-3xl font-bold">Available Services</h3>
                            <div class="flex-1 h-px bg-gradient-to-r from-yellow-400/50 to-transparent"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div
                                v-for="(service, index) in services"
                                :key="index"
                                class="group/item flex items-center gap-3 p-4 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 hover:bg-white/10 hover:border-yellow-400/40 transition-all duration-300"
                                :style="{ animationDelay: index * 0.05 + 's' }"
                            >
                                <div class="flex-shrink-0 w-3 h-3 bg-yellow-400 rounded-full shadow-sm shadow-yellow-400/30"></div>
                                <span class="text-sm font-medium text-blue-100 group-hover/item:text-yellow-400 transition-colors">{{ service }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Image & Content - Split Design -->
        <section id="services" class="py-12 bg-gradient-to-b from-gray-50 via-gray-50/50 to-white transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="lg:order-2">
                        <div class="relative group">
                            <img
                                src="/image/500main.webp"
                                alt="Container Services"
                                class="w-full h-[550px] object-cover rounded-3xl shadow-xl group-hover:shadow-2xl transition-shadow"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-blue-900/50 to-transparent rounded-3xl"></div>
                            <!-- Floating stat card on image -->
                            <div class="absolute bottom-6 left-6 right-6 bg-white/90 backdrop-blur-md rounded-xl p-4 shadow-lg">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <div class="text-lg font-bold text-blue-900">20+ yrs</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Experience</div>
                                    </div>
                                    <div class="border-x border-gray-200">
                                        <div class="text-lg font-bold text-blue-900">4 types</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Containers</div>
                                    </div>
                                    <div>
                                        <div class="text-lg font-bold text-blue-900">48h</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Avg. Dispatch</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:order-1">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="inline-block px-4 py-2 bg-yellow-400/20 border border-yellow-400/30 rounded-full">
                                <span class="text-yellow-600 text-sm font-semibold">Comprehensive Solutions</span>
                            </div>
                            <div class="h-px flex-1 bg-gradient-to-r from-yellow-400/30 to-transparent"></div>
                        </div>
                        <h2 class="text-5xl font-bold text-gray-900 mb-4 leading-tight">
                            End-to-End Logistics Management
                        </h2>
                        <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                            Professional shipping and container rental services designed for businesses of all sizes. 
                            From initial documentation to final delivery, we handle every step with precision.
                        </p>

                        <!-- Compact highlights -->
                        <div class="flex flex-wrap gap-2 mb-8">
                            <span class="text-xs font-medium text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100">FCL & LCL</span>
                            <span class="text-xs font-medium text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100">Door-to-Door</span>
                            <span class="text-xs font-medium text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100">IoT Tracking</span>
                            <span class="text-xs font-medium text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100">Insurance Included</span>
                        </div>

                        <div class="space-y-4 mb-10">
                            <div class="group/item flex items-start gap-4 p-4 bg-blue-50/70 rounded-xl border border-transparent hover:border-blue-200 hover:bg-blue-50 transition-all duration-300">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-gray-900 mb-1">Short & Long-Term Leasing</h4>
                                        <span class="text-[10px] text-gray-400 font-medium">from 7 days</span>
                                    </div>
                                    <p class="text-gray-600 text-sm">Flexible rental periods from one week to multi-year contracts, tailored to your operational needs</p>
                                </div>
                            </div>
                            <div class="group/item flex items-start gap-4 p-4 bg-blue-50/70 rounded-xl border border-transparent hover:border-blue-200 hover:bg-blue-50 transition-all duration-300">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-gray-900 mb-1">Competitive Pricing</h4>
                                        <span class="text-[10px] text-gray-400 font-medium">volume discounts</span>
                                    </div>
                                    <p class="text-gray-600 text-sm">Transparent rates with no hidden fees. Tiered pricing for high-volume clients and flexible payment terms</p>
                                </div>
                            </div>
                            <div class="group/item flex items-start gap-4 p-4 bg-blue-50/70 rounded-xl border border-transparent hover:border-blue-200 hover:bg-blue-50 transition-all duration-300">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-gray-900 mb-1">Global Availability</h4>
                                        <span class="text-[10px] text-gray-400 font-medium">50+ ports</span>
                                    </div>
                                    <p class="text-gray-600 text-sm">Quick access to containers at major ports worldwide. Average positioning time of 24–72 hours</p>
                                </div>
                            </div>
                        </div>

                        <Link
                            href="#contact"
                            class="inline-block px-10 py-4 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition-colors shadow-lg"
                        >
                            Request Information
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Logistics Info - Staggered Layout -->
        <section class="py-12 bg-gradient-to-b from-white via-gray-50/50 to-gray-50 transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-full mb-4">
                        <div class="w-1.5 h-1.5 bg-blue-600 rounded-full"></div>
                        <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Knowledge Base</span>
                    </div>
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Logistics Expertise</h2>
                    <p class="text-xl text-gray-600">Comprehensive knowledge and best practices</p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div
                        v-for="(info, index) in logisticsInfo"
                        :key="index"
                        class="group relative bg-white rounded-2xl p-8 border-2 border-gray-100 hover:border-blue-600 hover:shadow-lg transition-all duration-300 overflow-hidden"
                    >
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-yellow-400 to-blue-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                        <!-- Faded watermark number -->
                        <span class="absolute top-4 right-5 text-6xl font-black text-blue-900/[0.04] select-none pointer-events-none">0{{ index + 1 }}</span>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="info.icon" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-900 group-hover:text-blue-900 transition-colors">{{ info.title }}</h3>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-yellow-600 bg-yellow-50 px-2.5 py-1 rounded-full border border-yellow-100">{{ info.highlight }}</span>
                            </div>
                            <p class="text-gray-600 leading-relaxed group-hover:text-gray-700 transition-colors">{{ info.content }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Process - Timeline Design -->
        <section class="py-12 bg-gradient-to-b from-gray-50 via-gray-50/50 to-white transition-all duration-700 ease-in-out relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50/30 via-transparent to-yellow-50/20"></div>
            <div class="container mx-auto px-6 relative z-10">
                <div class="text-center mb-10">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-400/20 border border-yellow-400/30 rounded-full mb-4">
                        <div class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></div>
                        <span class="text-yellow-600 text-sm font-semibold">Our Workflow</span>
                    </div>
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Shipping Process</h2>
                    <p class="text-xl text-gray-600">Simple workflow from booking to delivery</p>
                    <div class="flex items-center justify-center gap-3 mt-3">
                        <span class="text-xs text-gray-400">Booking</span>
                        <div class="w-16 h-px bg-gradient-to-r from-gray-300 to-yellow-400"></div>
                        <span class="text-xs text-gray-400">Collection</span>
                        <div class="w-16 h-px bg-gradient-to-r from-yellow-400 to-blue-400"></div>
                        <span class="text-xs text-gray-400">Transport</span>
                        <div class="w-16 h-px bg-gradient-to-r from-blue-400 to-green-400"></div>
                        <span class="text-xs text-green-500 font-medium">Delivery</span>
                    </div>
                </div>

                <div class="relative max-w-6xl mx-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8 relative">
                        <div
                            v-for="(process, index) in processes"
                            :key="index"
                            class="group relative flex flex-col items-center"
                        >
                            <!-- Кружочок з цифрою вище картки -->
                            <div class="mb-4 relative z-30">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 text-yellow-400 rounded-full flex items-center justify-center font-bold text-lg shadow-lg border-4 border-white group-hover:scale-110 group-hover:shadow-xl group-hover:shadow-blue-600/50 transition-all duration-300">
                                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/20 to-transparent rounded-full"></div>
                                    <span class="relative z-10">{{ process.step }}</span>
                                </div>
                            </div>
                            
                            <!-- Плавна з'єднувальна лінія між блоками (тільки на десктопі) -->
                            <div v-if="index < processes.length - 1" class="hidden lg:block absolute top-6 left-1/2 w-full z-0 pointer-events-none" style="width: calc(100% + 2rem); left: calc(50% + 1rem);">
                                <svg class="w-full h-8" viewBox="0 0 200 32" preserveAspectRatio="none" style="height: 32px;">
                                    <defs>
                                        <linearGradient :id="`connector-${index}`" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" style="stop-color:#2563eb;stop-opacity:0.6" />
                                            <stop offset="30%" style="stop-color:#2563eb;stop-opacity:0.7" />
                                            <stop offset="50%" style="stop-color:#fbbf24;stop-opacity:0.9" />
                                            <stop offset="70%" style="stop-color:#2563eb;stop-opacity:0.7" />
                                            <stop offset="100%" style="stop-color:#2563eb;stop-opacity:0.6" />
                                        </linearGradient>
                                    </defs>
                                    <path 
                                        d="M 0 16 C 30 6, 70 6, 100 16 C 130 26, 170 26, 200 16" 
                                        :stroke="`url(#connector-${index})`" 
                                        stroke-width="2.5" 
                                        fill="none"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                            
                            <!-- Картка -->
                            <div class="bg-white rounded-xl p-6 border-2 border-gray-100 hover:border-blue-500 hover:shadow-lg transition-all duration-300 hover:-translate-y-1 group-hover:bg-gradient-to-br group-hover:from-blue-50 group-hover:to-white w-full relative z-20 cursor-pointer">
                                <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-blue-900 transition-colors text-center">{{ process.title }}</h3>
                                <p class="text-gray-600 text-sm leading-relaxed text-center">{{ process.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits - Grid with Images -->
        <section class="py-12 bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 text-white relative overflow-hidden transition-all duration-700 ease-in-out">
            <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-400/15 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
            
            <div class="relative container mx-auto px-6 z-10">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-yellow-400/20 backdrop-blur-sm border border-yellow-400/30 rounded-full mb-4">
                        <span class="text-yellow-400 text-sm font-medium">Trusted Partnership</span>
                    </div>
                    <h2 class="text-5xl font-bold mb-4">Why Partner With Us</h2>
                    <p class="text-xl text-blue-100">Industry-leading solutions for your logistics needs</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="(benefit, index) in benefits"
                        :key="index"
                        class="group relative bg-white/10 backdrop-blur-md rounded-2xl p-8 border border-white/20 hover:bg-white/15 hover:border-white/30 transition-all duration-300 overflow-hidden"
                        :style="{ animationDelay: index * 0.1 + 's' }"
                    >
                        <!-- Bottom accent line on hover -->
                        <div class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-transparent via-yellow-400 to-transparent scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                        <div class="mb-6 flex justify-center relative z-10">
                            <div class="relative w-24 h-24 rounded-full overflow-hidden border-2 border-white/20 shadow-lg group-hover:border-yellow-400/40 group-hover:shadow-yellow-400/10 transition-all duration-300">
                                <img
                                    :src="benefit.image"
                                    :alt="benefit.title"
                                    class="w-full h-full object-cover"
                                />
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-3 text-center text-white group-hover:text-yellow-400 transition-colors relative z-10">{{ benefit.title }}</h3>
                        <p class="text-blue-100 text-sm text-center leading-relaxed group-hover:text-blue-50 transition-colors relative z-10 mb-4">{{ benefit.description }}</p>
                        <div class="flex items-center justify-center gap-1.5 relative z-10">
                            <span class="text-sm font-bold text-yellow-400">{{ benefit.stat }}</span>
                            <span class="text-xs text-blue-200/60">{{ benefit.statLabel }}</span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-16">
                    <Link
                        href="#contact"
                        class="inline-flex items-center gap-3 px-10 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-300 transition-colors shadow-lg"
                    >
                        <span>Start Your Project</span>
                        <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
