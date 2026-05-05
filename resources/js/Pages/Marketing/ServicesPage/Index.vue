<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const countries = ref([
    {
        image: '/image/services/USA.png',
        name: 'USA',
        description: 'Direct routes to East and West Coast ports. Full customs brokerage with CBP compliance, bonded warehousing available at destination.',
        ports: 'New York, Los Angeles, Houston',
        transit: '14–18 days',
        services: 'FCL / LCL',
        note: 'CTPAT-certified supply chain',
    },
    {
        image: '/image/services/Germany.png',
        name: 'Germany',
        description: 'Primary EU gateway with daily departures. Integrated rail connections to inland terminals. EUR.1 and T1 transit documentation handled in-house.',
        ports: 'Hamburg, Bremerhaven, Duisburg',
        transit: '10–14 days',
        services: 'FCL / LCL / Rail',
        note: 'AEO-certified EU clearance',
    },
    {
        image: '/image/services/China.png',
        name: 'China',
        description: 'Established partnerships with COSCO and OOCL. End-to-end visibility from factory gate to European warehouse. CIQ and CCIC inspection coordination.',
        ports: 'Shanghai, Shenzhen, Qingdao',
        transit: '25–32 days',
        services: 'FCL / LCL',
        note: 'China Customs AEO mutual recognition',
    },
    {
        image: '/image/services/Japan.png',
        name: 'Japan',
        description: 'Premium service tier with guaranteed equipment availability. JIT delivery scheduling aligned with Japanese manufacturing standards.',
        ports: 'Tokyo, Yokohama, Kobe',
        transit: '28–35 days',
        services: 'FCL',
        note: 'AEO Japan bilateral agreement',
    },
    {
        image: '/image/services/UK.png',
        name: 'United Kingdom',
        description: 'Post-Brexit customs expertise including GVMS declarations, HMRC compliance, and Rules of Origin certification for preferential tariffs.',
        ports: 'Rotterdam, Antwerp, Southampton',
        transit: '8–12 days',
        services: 'FCL / LCL',
        note: 'UK DMSCI pre-clearance',
    },
]);

const containerFleet = ref([
    {
        type: '20ft Standard Dry',
        code: '22G1',
        dimensions: '5.90 × 2.35 × 2.39 m',
        payload: '28,200 kg',
        tare: '2,300 kg',
        volume: '33.2 m³',
    },
    {
        type: '40ft Standard Dry',
        code: '42G1',
        dimensions: '12.03 × 2.35 × 2.39 m',
        payload: '26,680 kg',
        tare: '3,800 kg',
        volume: '67.7 m³',
    },
    {
        type: '40ft High Cube',
        code: '45G1',
        dimensions: '12.03 × 2.35 × 2.70 m',
        payload: '26,460 kg',
        tare: '4,020 kg',
        volume: '76.3 m³',
    },
    {
        type: '20ft Reefer',
        code: '22R1',
        dimensions: '5.44 × 2.29 × 2.27 m',
        payload: '27,400 kg',
        tare: '3,080 kg',
        volume: '28.3 m³',
    },
]);

const orderSteps = ref([
    {
        step: '01',
        title: 'Container Selection',
        description: 'Choose from our fleet of ISO 668-certified containers. Specify cargo type, weight class, and required IoT sensor package. Our team verifies equipment availability within 2 hours.',
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        timeline: '< 2 hours',
        documents: 'Equipment request form, cargo specification sheet',
    },
    {
        step: '02',
        title: 'Rental Agreement',
        description: 'Digital contract with transparent per-diem rates. Includes standard liability coverage up to $50,000 per container. Damage deposit via bank guarantee or escrow.',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        timeline: '24 hours',
        documents: 'Lease contract, insurance certificate, bank guarantee',
    },
    {
        step: '03',
        title: 'Customs Documentation',
        description: 'We prepare Bill of Lading (B/L), CMR waybills, commercial invoices, packing lists, and certificates of origin. HS code classification and duty estimation included.',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        timeline: '24–48 hours',
        documents: 'B/L, CMR, EUR.1, packing list, commercial invoice',
    },
    {
        step: '04',
        title: 'Owner Verification',
        description: 'Container owner confirms allocation and pre-trip inspection (PTI) results. CSC plate validity and structural integrity verified per Convention for Safe Containers.',
        icon: 'M5 13l4 4L19 7',
        timeline: '12–24 hours',
        documents: 'PTI report, CSC certificate, condition survey',
    },
    {
        step: '05',
        title: 'Payment & Booking',
        description: 'Secure payment via wire transfer, LC, or approved credit terms. Booking confirmation with vessel/voyage details, container number, and seal assignment issued immediately.',
        icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        timeline: 'Same day',
        documents: 'Booking confirmation, seal number, vessel manifest',
    },
    {
        step: '06',
        title: 'Live IoT Tracking',
        description: 'From gate-out to final delivery: GPS position, temperature, humidity, shock events, and door status updated every 15 minutes via our dashboard or API webhook.',
        icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
        timeline: 'Continuous',
        documents: 'Dashboard access, API credentials, alert config',
    },
]);

const sensors = ref([
    {
        image: '/image/services/temperature.webp',
        name: 'Temperature',
        description: 'Continuous monitoring for perishables, pharmaceuticals, and chemicals. Configurable alert thresholds with instant notification.',
        range: '-30°C to +70°C',
        accuracy: '±0.5°C · EN 12830',
        color: 'from-red-500 to-orange-500',
    },
    {
        image: '/image/services/humidity.webp',
        name: 'Humidity',
        description: 'Prevents condensation damage (container rain) for electronics, textiles, and food products. Dew point calculation included.',
        range: '0–100% RH',
        accuracy: '±2% RH · ISO 16000-23',
        color: 'from-cyan-500 to-blue-500',
    },
    {
        image: '/image/services/vibrometer.webp',
        name: 'Vibration',
        description: 'Tri-axis accelerometer detects impacts, rough handling, and excessive vibration. Event logging with timestamp and g-force magnitude.',
        range: '0–50 g',
        accuracy: '±0.1 g · IEC 60068-2',
        color: 'from-purple-500 to-indigo-500',
    },
    {
        image: '/image/services/incline.webp',
        name: 'Incline',
        description: 'Detects tilt events for machinery, vehicles, and position-sensitive cargo. Alerts on deviation beyond configured angle.',
        range: '0°–90°',
        accuracy: '±1° · ASTM D4169',
        color: 'from-emerald-500 to-teal-500',
    },
    {
        image: '/image/services/openings.webp',
        name: 'Door Monitoring',
        description: 'Magnetic reed sensor detects every door open/close event. Tamper-evident logging for cargo security and chain of custody.',
        range: 'Open / Closed',
        accuracy: 'Event-based · C-TPAT',
        color: 'from-yellow-500 to-amber-500',
    },
    {
        image: '/image/services/gps.webp',
        name: 'GPS Positioning',
        description: 'Global satellite positioning with cellular fallback. Geofence alerts for route deviation and unauthorized stops.',
        range: 'Global',
        accuracy: '±3 m · GPS/GLONASS',
        color: 'from-blue-600 to-blue-800',
    },
    {
        image: '/image/services/illuminated.webp',
        name: 'Light Exposure',
        description: 'Detects unauthorized openings in dark-container shipments. Protects light-sensitive goods: film, chemicals, fine art.',
        range: '0–100k lux',
        accuracy: '±5% · ISO 8995',
        color: 'from-yellow-400 to-yellow-600',
    },
    {
        image: '/image/services/gases.webp',
        name: 'Gas Detection',
        description: 'Multi-gas monitoring: CO₂, O₂, ethylene, fumigant residue. Essential for produce, grain, and post-fumigation shipments.',
        range: 'Multi-gas',
        accuracy: 'ppm-level · ATEX Zone 2',
        color: 'from-green-500 to-emerald-600',
    },
]);

const certifications = ref([
    { name: 'ISO 9001:2015', label: 'Quality Management' },
    { name: 'ISPS Code', label: 'Port Security' },
    { name: 'SOLAS / VGM', label: 'Container Weighing' },
    { name: 'AEO Status', label: 'Authorized Economic Operator' },
    { name: 'GDP', label: 'Pharmaceutical Logistics' },
    { name: 'IMDG Code', label: 'Dangerous Goods' },
]);
</script>

<template>
    <AppLayout active-route="services">
        <Head title="Our Services — Global Reach & IoT Management" />

        <!-- Hero Section -->
        <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-blue-900">
            <div class="absolute inset-0">
                <img src="/image/main.webp" alt="Services Background" class="w-full h-full object-cover opacity-15" />
                <div class="absolute inset-0 bg-gradient-to-br from-blue-900/95 via-blue-900/85 to-blue-800/90"></div>
            </div>
            <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-yellow-400/8 to-transparent"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-700 rounded-full blur-3xl opacity-20"></div>

            <div class="relative container mx-auto px-6 py-20 z-10">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-400/15 backdrop-blur-sm border border-yellow-400/25 rounded-full mb-8">
                            <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                            <span class="text-yellow-400 text-sm font-medium">Our Services & Global Reach</span>
                        </div>
                        <h1 class="text-5xl md:text-7xl font-extrabold text-white mb-6 leading-tight">
                            Services &
                            <span class="block text-yellow-400 mt-2">IoT Solutions</span>
                        </h1>
                        <p class="text-xl text-blue-100 mb-6 max-w-xl leading-relaxed">
                            End-to-end container logistics with real-time IoT monitoring.
                            From booking to last-mile delivery across 5 continents, managed by a single team.
                        </p>

                        <!-- Credential chips -->
                        <div class="flex flex-wrap gap-2 mb-8">
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">ISO 9001:2015</span>
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">AEO Certified</span>
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">SOLAS / VGM</span>
                            <span class="text-xs text-blue-200/80 bg-white/8 border border-white/10 px-3 py-1.5 rounded-full">ISPS Code</span>
                        </div>

                        <div class="h-px bg-gradient-to-r from-transparent via-yellow-400/40 to-transparent mb-8 max-w-md"></div>
                        <div class="flex flex-wrap gap-4">
                            <a href="#countries" class="px-8 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-300 transition-colors shadow-lg">
                                View Destinations
                            </a>
                            <a href="#iot" class="px-8 py-4 bg-white/10 backdrop-blur-sm border-2 border-white/25 text-white font-bold rounded-lg hover:bg-white/20 transition-colors">
                                IoT Capabilities
                            </a>
                        </div>
                    </div>
                    <div class="hidden lg:block">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/15 to-blue-600/15 rounded-3xl transform rotate-3"></div>
                            <div class="relative bg-white/5 backdrop-blur-sm rounded-3xl p-8 border border-white/10">
                                <!-- Featured stat -->
                                <div class="bg-blue-800/40 rounded-xl p-6 border border-blue-700/20 mb-5 text-center">
                                    <div class="text-5xl font-extrabold text-yellow-400 mb-1">100K+</div>
                                    <div class="text-blue-200 text-sm">Clients served since 2004</div>
                                </div>
                                <!-- Secondary stats -->
                                <div class="grid grid-cols-3 gap-4 mb-5">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-400">5</div>
                                        <div class="text-blue-300/60 text-xs mt-0.5">Continents</div>
                                    </div>
                                    <div class="text-center border-x border-white/10">
                                        <div class="text-2xl font-bold text-yellow-400">50+</div>
                                        <div class="text-blue-300/60 text-xs mt-0.5">Ports</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-400">24/7</div>
                                        <div class="text-blue-300/60 text-xs mt-0.5">Monitoring</div>
                                    </div>
                                </div>
                                <!-- Detail rows -->
                                <div class="space-y-3 pt-4 border-t border-white/10">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                                        </svg>
                                        <span class="text-sm text-blue-100/70">Multi-sensor IoT fleet — temperature, GPS, humidity & more</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                                        </svg>
                                        <span class="text-sm text-blue-100/70">Average 48h from booking to dispatch</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                                        </svg>
                                        <span class="text-sm text-blue-100/70">Full customs & documentation in-house</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Countries Section -->
        <section id="countries" class="py-12 bg-gradient-to-b from-white via-gray-50/50 to-gray-50">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-blue-50 border border-blue-200 rounded-full mb-4">
                        <span class="text-blue-600 text-sm font-semibold">Global Network</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Countries We Work With</h2>
                    <p class="text-lg text-gray-600 max-w-3xl mx-auto">
                        Direct shipping routes from Ukraine to key global markets. All transit times calculated from Odesa/Chornomorsk ports.
                    </p>
                </div>

                <!-- Top Row: 3 countries -->
                <div class="grid md:grid-cols-3 gap-6 mb-6">
                    <div
                        v-for="(country, index) in countries.slice(0, 3)"
                        :key="index"
                        class="group bg-white rounded-2xl overflow-hidden border border-gray-200 hover:border-blue-200 hover:shadow-xl transition-all duration-300"
                    >
                        <div class="p-7">
                            <div class="flex items-center gap-5 mb-4">
                                <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-100 shadow-sm flex-shrink-0">
                                    <img :src="country.image" :alt="country.name" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 group-hover:text-blue-900 transition-colors">{{ country.name }}</h3>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded">{{ country.transit }}</span>
                                        <span class="text-xs font-medium text-gray-500">{{ country.services }}</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed mb-4">{{ country.description }}</p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    <span class="text-xs text-gray-500">{{ country.ports }}</span>
                                </div>
                                <span class="text-xs text-green-600 font-medium">{{ country.note }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: 2 countries centered -->
                <div class="grid md:grid-cols-2 gap-6 max-w-3xl mx-auto mb-10">
                    <div
                        v-for="(country, index) in countries.slice(3)"
                        :key="index + 3"
                        class="group bg-white rounded-2xl overflow-hidden border border-gray-200 hover:border-blue-200 hover:shadow-xl transition-all duration-300"
                    >
                        <div class="p-7">
                            <div class="flex items-center gap-5 mb-4">
                                <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-100 shadow-sm flex-shrink-0">
                                    <img :src="country.image" :alt="country.name" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 group-hover:text-blue-900 transition-colors">{{ country.name }}</h3>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded">{{ country.transit }}</span>
                                        <span class="text-xs font-medium text-gray-500">{{ country.services }}</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed mb-4">{{ country.description }}</p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    <span class="text-xs text-gray-500">{{ country.ports }}</span>
                                </div>
                                <span class="text-xs text-green-600 font-medium">{{ country.note }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Routes summary -->
                <div class="text-center text-sm text-gray-500 border-t border-gray-200 pt-6">
                    <p>120+ active trade routes · 50+ partner ports · All routes comply with EU trade regulations, ISPS Code, and SOLAS requirements.</p>
                </div>
            </div>
        </section>

        <!-- Container Fleet Section -->
        <section class="py-12 bg-gradient-to-b from-gray-50 via-white to-white">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-blue-50 border border-blue-200 rounded-full mb-4">
                        <span class="text-blue-600 text-sm font-semibold">Equipment</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Container Fleet</h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        All units ISO 668 / ISO 6346 certified. Regular maintenance per CSC Convention. Pre-trip inspected before every allocation.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div
                        v-for="(container, index) in containerFleet"
                        :key="index"
                        class="bg-white rounded-xl border border-gray-200 p-6"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-gray-900">{{ container.type }}</h3>
                            <span class="text-xs font-mono text-gray-400 bg-gray-50 px-2 py-1 rounded">{{ container.code }}</span>
                        </div>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Internal dim.</span>
                                <span class="font-medium text-gray-800">{{ container.dimensions }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Max payload</span>
                                <span class="font-medium text-gray-800">{{ container.payload }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tare weight</span>
                                <span class="font-medium text-gray-800">{{ container.tare }}</span>
                            </div>
                            <div class="flex justify-between pt-2.5 border-t border-gray-100">
                                <span class="text-gray-500">Capacity</span>
                                <span class="font-bold text-blue-700">{{ container.volume }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400 mt-6">
                    Specialized equipment (flat rack, open top, tank) available on request. Reefer units maintain -30°C to +30°C range.
                </p>
            </div>
        </section>

        <!-- Order Process Section -->
        <section class="py-12 bg-gradient-to-b from-white via-gray-50/30 to-gray-50 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50/20 via-transparent to-yellow-50/10"></div>
            <div class="container mx-auto px-6 relative z-10">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-yellow-400/15 border border-yellow-400/25 rounded-full mb-4">
                        <span class="text-yellow-700 text-sm font-semibold">Step by Step</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Order Process</h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">From initial inquiry to live tracking — typically completed within 72 hours for standard shipments.</p>
                </div>

                <div class="grid lg:grid-cols-12 gap-10 items-start">
                    <div class="lg:col-span-8">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div
                                v-for="(step, index) in orderSteps"
                                :key="index"
                                class="group relative bg-white rounded-xl p-6 border border-gray-200 border-l-2 border-l-blue-600 hover:border-l-yellow-400 hover:shadow-md transition-all duration-300"
                            >
                                <!-- Watermark step number -->
                                <span class="absolute top-3 right-4 text-5xl font-black text-blue-900/[0.06] select-none pointer-events-none">{{ step.step }}</span>
                                <div class="relative z-10">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-11 h-11 bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="step.icon" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-bold text-gray-900 group-hover:text-blue-900 transition-colors">{{ step.title }}</h3>
                                            <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded font-medium">{{ step.timeline }}</span>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm leading-relaxed mb-3">{{ step.description }}</p>
                                    <p class="text-xs text-gray-400 italic">{{ step.documents }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- ISO note -->
                        <div class="mt-6 flex items-start gap-3 p-4 bg-blue-50/50 rounded-lg border border-blue-100">
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <p class="text-sm text-gray-600">All processes are <strong class="text-gray-800">ISO 9001:2015</strong> certified and follow <strong class="text-gray-800">SOLAS/VGM</strong> international container weighing requirements.</p>
                        </div>
                    </div>

                    <!-- Process Image -->
                    <div class="lg:col-span-4 hidden lg:block">
                        <div class="sticky top-28">
                            <div class="relative overflow-hidden rounded-2xl shadow-lg">
                                <img src="/image/services/process.webp" alt="Order Process" class="w-full h-[520px] object-cover" />
                                <div class="absolute inset-0 bg-gradient-to-t from-blue-900/70 via-blue-900/20 to-transparent"></div>
                                <div class="absolute bottom-5 left-5 right-5">
                                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <svg class="w-5 h-5 text-blue-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-white font-bold text-sm">Fast Processing</div>
                                                <div class="text-blue-200 text-xs">Average 48h from booking to dispatch</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- IoT Management Section -->
        <section id="iot" class="py-12 bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 text-white relative overflow-hidden">
            <!-- Subtle decorative elements (no animate-pulse) -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-400/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl"></div>

            <!-- Subtle grid pattern -->
            <div class="absolute inset-0 opacity-[0.03]">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <div class="relative container mx-auto px-6 z-10">
                <div class="text-center mb-10">
                    <div class="inline-block px-4 py-2 bg-yellow-400/15 backdrop-blur-sm border border-yellow-400/25 rounded-full mb-4">
                        <span class="text-yellow-400 text-sm font-medium">Advanced Technology</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-bold mb-4">IoT Container Management</h2>
                    <p class="text-lg text-blue-100 max-w-3xl mx-auto leading-relaxed">
                        Every sensor individually configurable per container. Data transmitted every 15 minutes via LTE-M/NB-IoT with satellite fallback.
                        No hardware installation required — sensors are pre-mounted during pre-trip inspection.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div
                        v-for="(sensor, index) in sensors"
                        :key="index"
                        class="group bg-white/8 backdrop-blur-sm rounded-xl p-5 border border-white/15 hover:bg-white/12 hover:border-white/25 transition-all duration-300"
                    >
                        <div class="mb-4 flex justify-center">
                            <div class="w-14 h-14 rounded-lg overflow-hidden border border-white/20">
                                <img :src="sensor.image" :alt="sensor.name" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-white mb-1.5 text-center pb-1 border-b-2 border-transparent group-hover:text-yellow-400 group-hover:border-yellow-400 transition-all duration-300">{{ sensor.name }}</h3>
                        <p class="text-blue-100/70 text-sm text-center leading-relaxed mb-3 mt-2">{{ sensor.description }}</p>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full"></div>
                                <span class="text-xs font-medium text-blue-200">{{ sensor.range }}</span>
                            </div>
                            <div class="text-center">
                                <span class="text-[11px] text-blue-300/60 font-mono">{{ sensor.accuracy }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Preview -->
                <div class="mt-14 bg-white/5 backdrop-blur-sm rounded-2xl p-8 border border-white/10">
                    <div class="grid md:grid-cols-2 gap-10 items-center">
                        <div>
                            <h3 class="text-2xl font-bold mb-4">Real-Time Dashboard</h3>
                            <p class="text-blue-100/80 leading-relaxed mb-6">
                                Access all sensor data through our web platform. Set custom alert thresholds,
                                review historical trends, and manage your entire fleet from a single interface.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex items-center gap-2 px-3 py-2 bg-white/8 rounded-lg border border-white/10">
                                    <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    <span class="text-xs text-blue-100">Custom Alerts</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-white/8 rounded-lg border border-white/10">
                                    <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span class="text-xs text-blue-100">Analytics & Trends</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-white/8 rounded-lg border border-white/10">
                                    <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    <span class="text-xs text-blue-100">REST API Access</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 bg-white/8 rounded-lg border border-white/10">
                                    <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span class="text-xs text-blue-100">CSV / PDF Export</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/8 rounded-xl p-5 border border-white/10 text-center">
                                    <div class="text-3xl font-bold text-yellow-400 mb-1">500+</div>
                                    <div class="text-xs text-blue-200">Containers simultaneously</div>
                                </div>
                                <div class="bg-white/8 rounded-xl p-5 border border-white/10 text-center">
                                    <div class="text-3xl font-bold text-yellow-400 mb-1">15 min</div>
                                    <div class="text-xs text-blue-200">Data refresh interval</div>
                                </div>
                                <div class="bg-white/8 rounded-xl p-5 border border-white/10 text-center">
                                    <div class="text-3xl font-bold text-yellow-400 mb-1">5 yrs</div>
                                    <div class="text-xs text-blue-200">Data retention</div>
                                </div>
                                <div class="bg-white/8 rounded-xl p-5 border border-white/10 text-center">
                                    <div class="text-3xl font-bold text-yellow-400 mb-1">99.8%</div>
                                    <div class="text-xs text-blue-200">Platform uptime</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Certifications & Compliance -->
        <section class="py-12 bg-white border-b border-gray-100">
            <div class="container mx-auto px-6">
                <div class="text-center mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Certifications & Compliance</h2>
                    <p class="text-sm text-gray-500">Industry standards we adhere to across all operations</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div
                        v-for="(cert, index) in certifications"
                        :key="index"
                        class="text-center p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-blue-200 transition-colors duration-200"
                    >
                        <div class="text-lg font-bold text-blue-900 mb-1">{{ cert.name }}</div>
                        <div class="text-xs text-gray-500">{{ cert.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-12 bg-gradient-to-b from-white to-gray-50">
            <div class="container mx-auto px-6">
                <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 rounded-2xl overflow-hidden">
                    <div class="py-14 px-8 md:px-16 text-center">
                        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Request a Custom Logistics Quote</h2>
                        <p class="text-lg text-blue-100 mb-3 max-w-2xl mx-auto">
                            Tell us about your cargo, destination, and timeline. We'll prepare a detailed proposal with pricing, transit options, and IoT configuration.
                        </p>
                        <p class="text-sm text-blue-200/60 mb-8">Our team responds within 4 business hours.</p>
                        <div class="flex flex-wrap justify-center gap-4">
                            <Link
                                href="/contact"
                                class="inline-flex items-center gap-2 px-10 py-4 bg-yellow-400 text-blue-900 font-bold rounded-lg hover:bg-yellow-300 transition-colors shadow-lg"
                            >
                                <span>Get a Quote</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </Link>
                            <Link href="/" class="px-10 py-4 bg-white/10 border-2 border-white/25 text-white font-bold rounded-lg hover:bg-white/20 transition-colors">
                                Back to Home
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
