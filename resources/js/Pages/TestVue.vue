<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

// Реактивні дані
const counter = ref(0);
const message = ref('Вітаю! Vue 3 працює коректно! 🎉');
const inputText = ref('');
const items = ref(['Перший елемент', 'Другий елемент', 'Третій елемент']);
const isVueWorking = ref(true);

// Computed властивості
const doubleCounter = computed(() => counter.value * 2);
const reversedMessage = computed(() => message.value.split('').reverse().join(''));
const itemCount = computed(() => items.value.length);

// Методи
function increment() {
    counter.value++;
}

function decrement() {
    counter.value--;
}

function addItem() {
    if (inputText.value.trim()) {
        items.value.push(inputText.value.trim());
        inputText.value = '';
    }
}

function removeItem(index) {
    items.value.splice(index, 1);
}

function toggleVueStatus() {
    isVueWorking.value = !isVueWorking.value;
}

// Lifecycle hook
onMounted(() => {
    console.log('Vue компонент змонтовано успішно!');
    console.log('Vue версія:', '3.4.0');
});
</script>

<template>
    <Head title="Тест Vue" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Заголовок -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    🧪 Тест Vue 3 + Inertia.js
                </h1>
                <p class="text-lg text-gray-600">
                    Перевірка роботи Vue фреймворку
                </p>
            </div>

            <!-- Статус Vue -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800 mb-2">
                            Статус Vue:
                        </h2>
                        <p :class="[
                            'text-xl font-medium',
                            isVueWorking ? 'text-green-600' : 'text-red-600'
                        ]">
                            {{ isVueWorking ? '✅ Працює' : '❌ Не працює' }}
                        </p>
                    </div>
                    <button
                        @click="toggleVueStatus"
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
                    >
                        Перемкнути
                    </button>
                </div>
            </div>

            <!-- Реактивність - Счетчик -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                    📊 Реактивність (Counter)
                </h2>
                <div class="flex items-center justify-center space-x-4 mb-4">
                    <button
                        @click="decrement"
                        class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-bold text-xl"
                    >
                        -
                    </button>
                    <div class="text-center">
                        <div class="text-5xl font-bold text-indigo-600 mb-2">
                            {{ counter }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Подвоєне значення: {{ doubleCounter }}
                        </div>
                    </div>
                    <button
                        @click="increment"
                        class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-bold text-xl"
                    >
                        +
                    </button>
                </div>
                <p class="text-center text-gray-600 mt-4">
                    Computed property: doubleCounter = {{ doubleCounter }}
                </p>
            </div>

            <!-- Двостороннє зв'язування (v-model) -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                    ✏️ Двостороннє зв'язування (v-model)
                </h2>
                <div class="space-y-4">
                    <input
                        v-model="message"
                        type="text"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="Введіть текст..."
                    />
                    <div class="p-4 bg-indigo-50 rounded-lg">
                        <p class="text-gray-700 mb-2">
                            <strong>Оригінал:</strong> {{ message }}
                        </p>
                        <p class="text-gray-700">
                            <strong>Навпаки:</strong> {{ reversedMessage }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Список елементів (v-for) -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                    📝 Список елементів (v-for) - {{ itemCount }} шт.
                </h2>
                <div class="flex gap-2 mb-4">
                    <input
                        v-model="inputText"
                        @keyup.enter="addItem"
                        type="text"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="Додати новий елемент..."
                    />
                    <button
                        @click="addItem"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"
                    >
                        Додати
                    </button>
                </div>
                <ul class="space-y-2">
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <span class="text-gray-700">{{ index + 1 }}. {{ item }}</span>
                        <button
                            @click="removeItem(index)"
                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors text-sm"
                        >
                            Видалити
                        </button>
                    </li>
                </ul>
                <p v-if="items.length === 0" class="text-center text-gray-500 py-4">
                    Список порожній
                </p>
            </div>

            <!-- Інформація про Vue -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                    ℹ️ Інформація
                </h2>
                <div class="space-y-2 text-gray-700">
                    <p><strong>Фреймворк:</strong> Vue 3 (Composition API)</p>
                    <p><strong>Реактивність:</strong> ✅ ref(), computed()</p>
                    <p><strong>Lifecycle:</strong> ✅ onMounted()</p>
                    <p><strong>Директиви:</strong> ✅ v-model, v-for, v-if, @click, @keyup</p>
                    <p><strong>Інтеграція:</strong> ✅ Inertia.js + Laravel</p>
                </div>
            </div>

            <!-- Кнопка повернення -->
            <div class="text-center mt-6">
                <a
                    href="/"
                    class="inline-block px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium"
                >
                    ← Повернутися на головну
                </a>
            </div>
        </div>
    </div>
</template>

