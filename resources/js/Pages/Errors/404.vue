<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ErrorLayout from '@/Layouts/ErrorLayout.vue';
</script>

<template>
    <ErrorLayout title="404 - Page Not Found">
        <Head title="404 - Page Not Found" />

        <div class="min-h-screen flex items-center justify-center relative overflow-hidden p-8 bg-gradient-to-br from-blue-50 via-white to-gray-50">
            <!-- Декоративні елементи фону -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-blue-600/10 to-transparent rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-tr from-yellow-400/10 to-transparent rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-blue-600/5 via-transparent to-yellow-400/5 rounded-full blur-3xl"></div>
            
            <!-- Геометричні декоративні елементи -->
            <div class="absolute top-10 left-10 w-32 h-32 border-2 border-blue-600/10 rounded-lg rotate-45"></div>
            <div class="absolute bottom-10 right-10 w-24 h-24 border-2 border-yellow-400/10 rounded-full"></div>
            <div class="absolute top-1/4 right-1/4 w-16 h-16 bg-blue-600/5 rounded-lg rotate-12"></div>
            <div class="absolute bottom-1/4 left-1/4 w-20 h-20 bg-yellow-400/5 rounded-full"></div>

            <!-- Декоративні лінії -->
            <svg class="absolute inset-0 w-full h-full opacity-5" style="pointer-events: none;">
                <defs>
                    <linearGradient id="line-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#2563eb;stop-opacity:0.3" />
                        <stop offset="100%" style="stop-color:#fbbf24;stop-opacity:0.3" />
                    </linearGradient>
                </defs>
                <line x1="0" y1="20%" x2="100%" y2="20%" stroke="url(#line-gradient)" stroke-width="1" />
                <line x1="0" y1="80%" x2="100%" y2="80%" stroke="url(#line-gradient)" stroke-width="1" />
                <line x1="10%" y1="0" x2="10%" y2="100%" stroke="url(#line-gradient)" stroke-width="1" />
                <line x1="90%" y1="0" x2="90%" y2="100%" stroke="url(#line-gradient)" stroke-width="1" />
            </svg>

            <div class="relative z-10 text-center max-w-2xl mx-auto px-4">
                <!-- Великий 404 з декоративними елементами -->
                <div class="mb-8 relative">
                    <div class="absolute -top-4 -left-4 w-8 h-8 bg-blue-600/20 rounded-full blur-sm"></div>
                    <div class="absolute -bottom-4 -right-4 w-6 h-6 bg-yellow-400/20 rounded-full blur-sm"></div>
                    <h1 class="text-8xl md:text-9xl font-extrabold bg-gradient-to-r from-blue-600 via-blue-800 to-blue-600 bg-clip-text text-transparent relative">
                        404
                        <span class="absolute -top-2 -right-2 text-2xl text-blue-600/30">•</span>
                    </h1>
                </div>

                <!-- Текст помилки -->
                <div class="mb-12 space-y-3">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Сторінку не знайдено</h2>
                    <div class="w-24 h-1 bg-gradient-to-r from-blue-600 to-yellow-400 mx-auto rounded-full"></div>
                    <p class="text-lg text-gray-600 max-w-md mx-auto">Схоже, ця сторінка не існує або була переміщена.</p>
                </div>

                <!-- Кнопки дій -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                    <Link href="/" class="group flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl hover:shadow-blue-600/50 transition-all hover:-translate-y-1 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-700 to-blue-900 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <svg class="w-5 h-5 relative z-10 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="relative z-10">На головну</span>
                    </Link>
                    <Link href="/contact" class="px-8 py-4 bg-white text-blue-600 font-semibold rounded-lg border-2 border-blue-600 transition-all hover:bg-blue-50 hover:-translate-y-1 shadow-lg hover:shadow-xl hover:border-blue-700">
                        Зв'язатися з нами
                    </Link>
                </div>
            </div>
        </div>
    </ErrorLayout>
</template>
