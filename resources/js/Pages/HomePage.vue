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
        description: 'Container rentals for worldwide destinations with real-time tracking across multiple global markets.',
    },
    {
        title: 'IoT Monitoring Solutions',
        description: 'Advanced IoT-enabled containers for condition monitoring and security tracking throughout transit.',
    },
    {
        title: 'Online Management Platform',
        description: 'Streamlined digital platform for managing rentals, monitoring conditions, and extending contracts.',
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
        description: 'Ensuring your cargo reaches destination despite all challenges.',
    },
    {
        image: '/image/Warehouse Services.webp',
        title: 'Warehouse Services',
        description: 'Secure storage solutions tailored to your business needs.',
    },
    {
        image: '/image/EU and Global Reach.webp',
        title: 'Global Reach',
        description: 'Extensive network covering Poland, EU, and worldwide destinations.',
    },
    {
        image: '/image/Product Sourcing.webp',
        title: 'Product Sourcing',
        description: 'Expert assistance in finding and purchasing goods upon request.',
    },
]);

const logisticsInfo = ref([
    {
        title: 'Container Types & Specifications',
        content: 'We provide various container types including standard 20ft and 40ft containers, refrigerated units for temperature-sensitive cargo, and specialized containers for hazardous materials. All containers meet international ISO standards and undergo regular maintenance inspections.',
    },
    {
        title: 'Shipping Routes & Transit Times',
        content: 'Our extensive network covers major shipping routes across all continents. Typical transit times from Europe to Asia range from 25-35 days, while transatlantic routes to North America take 10-14 days. We optimize routes for efficiency and cost-effectiveness.',
    },
    {
        title: 'Customs & Documentation',
        content: 'Professional handling of all customs documentation including commercial invoices, packing lists, certificates of origin, and import/export licenses. Our customs experts ensure compliance with international trade regulations and minimize delays at ports.',
    },
    {
        title: 'Logistics Process Overview',
        content: 'Complete logistics chain management from cargo pickup at origin warehouse, container loading, sea freight transportation, port handling, customs clearance, final delivery to destination. Real-time tracking available at every stage of the journey.',
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
        <section class="relative min-h-screen flex items-center overflow-hidden bg-blue-900">
            <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-yellow-400/10 to-transparent"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-800 rounded-full blur-3xl opacity-50"></div>
            
            <div class="relative container mx-auto px-6 py-20 z-10">
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
                                class="px-8 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-500 transition-all shadow-lg hover:shadow-yellow-400/50 transform hover:-translate-y-1"
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
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="bg-blue-800/50 rounded-xl p-6 backdrop-blur-sm">
                                        <div class="text-3xl font-bold text-yellow-400 mb-2">200+</div>
                                        <div class="text-blue-200 text-sm">Employees</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-6 backdrop-blur-sm">
                                        <div class="text-3xl font-bold text-yellow-400 mb-2">100K+</div>
                                        <div class="text-blue-200 text-sm">Clients</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-6 backdrop-blur-sm">
                                        <div class="text-3xl font-bold text-yellow-400 mb-2">5</div>
                                        <div class="text-blue-200 text-sm">Continents</div>
                                    </div>
                                    <div class="bg-blue-800/50 rounded-xl p-6 backdrop-blur-sm">
                                        <div class="text-3xl font-bold text-yellow-400 mb-2">25M+</div>
                                        <div class="text-blue-200 text-sm">KG Shipped</div>
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
                <div class="bg-white rounded-2xl shadow-2xl p-8 grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div
                        v-for="(stat, index) in stats"
                        :key="index"
                        class="text-center p-4 hover:bg-blue-50 rounded-xl transition-all duration-500 transform hover:scale-105 hover:shadow-lg"
                        :style="{ animationDelay: index * 0.1 + 's' }"
                    >
                        <div class="text-4xl font-bold text-blue-900 mb-2">{{ stat.value }}</div>
                        <div class="text-sm text-gray-600 uppercase tracking-wide">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section - Modern Cards -->
        <section class="py-16 bg-gradient-to-b from-white via-gray-50/50 to-gray-50 transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Complete Logistics Solutions</h2>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                        Everything you need for successful global shipping operations
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 mb-12">
                    <div
                        v-for="(feature, index) in features"
                        :key="index"
                        class="group relative bg-white rounded-2xl p-8 border-2 border-gray-100 hover:border-blue-600 hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2 hover:scale-[1.02] overflow-hidden"
                        :style="{ animationDelay: index * 0.15 + 's' }"
                    >
                        <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-blue-600/10 to-yellow-400/10 rounded-bl-full transition-all duration-500 group-hover:scale-[1.15]" style="transform-origin: top right;"></div>
                        <div class="relative z-10">
                            <div class="text-4xl font-bold text-blue-900/20 mb-4 group-hover:text-blue-900/30 transition-colors">0{{ index + 1 }}</div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-blue-900 transition-colors">{{ feature.title }}</h3>
                            <p class="text-gray-600 leading-relaxed group-hover:text-gray-700 transition-colors">{{ feature.description }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 rounded-2xl p-10 text-white relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-yellow-400/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-600/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
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
                                class="group/item flex items-center gap-3 p-4 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 hover:bg-white/10 hover:border-yellow-400/50 transition-all duration-300 transform hover:-translate-y-1 hover:scale-105"
                                :style="{ animationDelay: index * 0.05 + 's' }"
                            >
                                <div class="flex-shrink-0 w-3 h-3 bg-yellow-400 rounded-full group-hover/item:scale-125 transition-transform duration-300 shadow-lg shadow-yellow-400/50"></div>
                                <span class="text-sm font-medium text-blue-100 group-hover/item:text-yellow-400 transition-colors">{{ service }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Image & Content - Split Design -->
        <section id="services" class="py-16 bg-gradient-to-b from-gray-50 via-gray-50/50 to-white transition-all duration-700 ease-in-out">
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
                        </div>
                    </div>

                    <div class="lg:order-1">
                        <div class="inline-block px-4 py-2 bg-yellow-400/20 border border-yellow-400/30 rounded-full mb-6">
                            <span class="text-yellow-600 text-sm font-semibold">Comprehensive Solutions</span>
                        </div>
                        <h2 class="text-5xl font-bold text-gray-900 mb-6 leading-tight">
                            End-to-End Logistics Management
                        </h2>
                        <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                            Professional shipping and container rental services designed for businesses of all sizes. 
                            From initial documentation to final delivery, we handle every step with precision.
                        </p>

                        <div class="space-y-5 mb-10">
                            <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition-colors">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-900 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-1">Short & Long-Term Leasing</h4>
                                    <p class="text-gray-600 text-sm">Flexible rental periods tailored to your schedule</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition-colors">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-900 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-1">Competitive Pricing</h4>
                                    <p class="text-gray-600 text-sm">Transparent rates with flexible payment options</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition-colors">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-900 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-1">Global Availability</h4>
                                    <p class="text-gray-600 text-sm">Quick access to containers worldwide</p>
                                </div>
                            </div>
                        </div>

                        <Link
                            href="#contact"
                            class="inline-block px-10 py-4 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-1"
                        >
                            Request Information
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Logistics Info - Staggered Layout -->
        <section class="py-16 bg-gradient-to-b from-white via-gray-50/50 to-gray-50 transition-all duration-700 ease-in-out">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Logistics Expertise</h2>
                    <p class="text-xl text-gray-600">Comprehensive knowledge and best practices</p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div
                        v-for="(info, index) in logisticsInfo"
                        :key="index"
                        class="group relative bg-white rounded-2xl p-8 border-2 border-gray-100 hover:border-blue-600 hover:shadow-xl transition-all duration-500 transform hover:-translate-y-2 overflow-hidden"
                    >
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-yellow-400 to-blue-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>
                        <div class="absolute top-6 right-6 w-16 h-16 bg-gradient-to-br from-yellow-400/20 to-blue-600/20 rounded-full opacity-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500"></div>
                        <div class="relative z-10">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 rounded-full mb-3 group-hover:bg-blue-100 transition-colors">
                                <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                                <span class="text-sm font-semibold text-blue-600">0{{ index + 1 }}</span>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-blue-900 transition-colors">{{ info.title }}</h3>
                            <p class="text-gray-600 leading-relaxed group-hover:text-gray-700 transition-colors">{{ info.content }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Process - Timeline Design -->
        <section class="py-16 bg-gradient-to-b from-gray-50 via-gray-50/50 to-white transition-all duration-700 ease-in-out relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50/30 via-transparent to-yellow-50/20"></div>
            <div class="container mx-auto px-6 relative z-10">
                <div class="text-center mb-16">
                    <div class="inline-block px-4 py-2 bg-yellow-400/20 border border-yellow-400/30 rounded-full mb-4">
                        <span class="text-yellow-600 text-sm font-semibold">Our Workflow</span>
                    </div>
                    <h2 class="text-5xl font-bold text-gray-900 mb-4">Shipping Process</h2>
                    <p class="text-xl text-gray-600">Simple workflow from booking to delivery</p>
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
        <section class="py-16 bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 text-white relative overflow-hidden transition-all duration-700 ease-in-out">
            <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-400/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-600/30 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-yellow-400/10 rounded-full blur-3xl"></div>
            
            <div class="relative container mx-auto px-6 z-10">
                <div class="text-center mb-16">
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
                        class="group relative bg-white/15 backdrop-blur-md rounded-2xl p-8 border-2 border-white/30 hover:bg-white/25 hover:border-yellow-400 transition-all duration-500 transform hover:-translate-y-3 hover:scale-105 hover:shadow-2xl hover:shadow-yellow-400/30 overflow-hidden"
                        :style="{ animationDelay: index * 0.1 + 's' }"
                    >
                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-yellow-400/10 to-transparent rounded-bl-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-gradient-to-tr from-blue-600/20 to-transparent rounded-tr-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="mb-6 flex justify-center relative z-10">
                            <div class="relative">
                                <div class="absolute inset-0 bg-yellow-400/40 rounded-full blur-xl group-hover:blur-2xl group-hover:bg-yellow-400/60 transition-all duration-300"></div>
                                <div class="relative w-28 h-28 bg-gradient-to-br from-white/30 to-white/10 rounded-full flex items-center justify-center group-hover:bg-gradient-to-br group-hover:from-yellow-400/40 group-hover:to-yellow-400/20 transition-all duration-300 border-2 border-white/30 group-hover:border-yellow-400/70 shadow-lg group-hover:shadow-yellow-400/50 overflow-hidden">
                                    <img
                                        :src="benefit.image"
                                        :alt="benefit.title"
                                        class="w-full h-full object-cover rounded-full group-hover:scale-110 transition-transform duration-300 filter brightness-110 group-hover:brightness-125"
                                    />
                                </div>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-3 text-center text-white group-hover:text-yellow-400 transition-colors relative z-10">{{ benefit.title }}</h3>
                        <p class="text-blue-100 text-sm text-center leading-relaxed group-hover:text-white transition-colors relative z-10">{{ benefit.description }}</p>
                    </div>
                </div>

                <div class="text-center mt-16">
                    <Link
                        href="#contact"
                        class="inline-flex items-center gap-3 px-10 py-4 bg-gradient-to-r from-yellow-400 to-yellow-500 text-blue-900 font-bold rounded-lg hover:from-yellow-500 hover:to-yellow-400 transition-all shadow-xl hover:shadow-2xl hover:shadow-yellow-400/50 transform hover:-translate-y-1"
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
