<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    category: Object,
    auth: Object,
});

// Current editing language
const currentLang = ref('sk');
const languages = [
    { code: 'sk', name: 'Slovensky', flag: '🇸🇰' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
];

// Helper to initialize multilingual value
const initMultilingualValue = (value) => {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value;
    }
    // Convert string to multilingual object
    return { sk: value || '', en: '' };
};

const formData = useForm({
    name: initMultilingualValue(props.category.name),
    description: initMultilingualValue(props.category.description),
    color: props.category.color,
    icon: props.category.icon || '',
    order: props.category.order,
});

// For preview and header display
const previewName = computed(() => formData.name[currentLang.value] || formData.name.sk || '');
const headerName = computed(() => formData.name.sk || formData.name.en || '');

// Predefined colors
const predefinedColors = [
    { name: 'Gold', value: '#A59466' },
    { name: 'Blue', value: '#3B82F6' },
    { name: 'Green', value: '#10B981' },
    { name: 'Amber', value: '#F59E0B' },
    { name: 'Violet', value: '#8B5CF6' },
    { name: 'Cyan', value: '#06B6D4' },
    { name: 'Pink', value: '#EC4899' },
    { name: 'Red', value: '#EF4444' },
    { name: 'Gray', value: '#6B7280' },
];

// Predefined icons (SVG paths) - IT Services focused
const predefinedIcons = [
    // IT & Networking
    { name: 'PC/Monitor', path: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { name: 'Server', path: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
    { name: 'Cloud', path: 'M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z' },
    { name: 'WiFi', path: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
    { name: 'Siet/Globe', path: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
    { name: 'Database', path: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' },
    { name: 'Kod', path: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
    { name: 'Terminal', path: 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { name: 'Chip/CPU', path: 'M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },

    // Security
    { name: 'Zamok', path: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { name: 'Kluc', path: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z' },
    { name: 'Stit', path: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { name: 'VPN/Tunel', path: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1' },
    { name: 'Fingerprint', path: 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4' },

    // Support & Help
    { name: 'Podpora', path: 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Headset', path: 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z' },
    { name: 'Otaznik', path: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Varovanie', path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
    { name: 'Info', path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },

    // Communication
    { name: 'Email', path: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { name: 'Chat', path: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { name: 'Telefon', path: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
    { name: 'Video', path: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z' },

    // Documents & Files
    { name: 'Dokument', path: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'Priecinok', path: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z' },
    { name: 'Download', path: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4' },
    { name: 'Upload', path: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
    { name: 'Printer', path: 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z' },

    // Users & Access
    { name: 'Uzivatel', path: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { name: 'Uzivatelia', path: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'ID karta', path: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2' },

    // General
    { name: 'Nastavenia', path: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { name: 'Nastroje', path: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { name: 'Kalendar', path: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { name: 'Hodiny', path: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Hviezda', path: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' },
    { name: 'Blesk', path: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: 'Dom', path: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Studium', path: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
    { name: 'Praca', path: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { name: 'Peniaze', path: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const selectIcon = (path) => {
    formData.icon = path;
};

const submit = () => {
    formData.transform(data => ({ ...data, _method: 'PUT' })).post(`/admin/categories/${props.category.id}`);
};
</script>

<template>
    <Head :title="`${t('categories.edit')}: ${headerName}`" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ t('categories.edit') }}</h1>
                <p class="text-gray-500 dark:text-gray-400 truncate">{{ category.slug }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">{{ t('categories.basicInfo') || 'Základné informácie' }}</h2>

                <!-- Language tabs -->
                <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg mb-4">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        @click="currentLang = lang.code"
                        type="button"
                        class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors"
                        :class="currentLang === lang.code
                            ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                    >
                        <span>{{ lang.flag }}</span>
                        <span>{{ lang.name }}</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="form-label">
                            {{ t('categories.name') }} *
                            <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
                        </label>
                        <input
                            v-model="formData.name[currentLang]"
                            type="text"
                            class="form-input"
                            :required="currentLang === 'sk'"
                        />
                        <p v-if="formData.errors.name" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ formData.errors.name }}</p>
                    </div>

                    <div>
                        <label class="form-label">
                            {{ t('categories.description') || 'Popis' }}
                            <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
                        </label>
                        <textarea
                            v-model="formData.description[currentLang]"
                            class="form-input"
                            rows="2"
                            :placeholder="t('categories.descriptionPlaceholder') || 'Krátky popis kategórie...'"
                        />
                    </div>

                    <div>
                        <label class="form-label">{{ t('categories.order') || 'Poradie' }}</label>
                        <input v-model.number="formData.order" type="number" class="form-input w-32" min="0" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('categories.orderHelp') || 'Nižšie číslo = vyššia priorita' }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Vzhlad</h2>

                <div class="space-y-6">
                    <!-- Color picker -->
                    <div>
                        <label class="form-label">Farba</label>
                        <div class="flex items-center gap-4">
                            <input
                                v-model="formData.color"
                                type="color"
                                class="w-16 h-10 rounded cursor-pointer border-2 border-gray-200 dark:border-gray-600"
                            />
                            <input
                                v-model="formData.color"
                                type="text"
                                class="form-input w-32"
                                placeholder="#000000"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button
                                v-for="color in predefinedColors"
                                :key="color.value"
                                type="button"
                                @click="formData.color = color.value"
                                class="w-8 h-8 rounded-full border-2 transition-all"
                                :style="{ backgroundColor: color.value }"
                                :class="formData.color === color.value ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent hover:scale-105'"
                                :title="color.name"
                            />
                        </div>
                    </div>

                    <!-- Icon picker -->
                    <div>
                        <label class="form-label">Ikona (volitelne)</label>
                        <div class="grid grid-cols-5 sm:grid-cols-10 gap-2 mt-2">
                            <button
                                v-for="icon in predefinedIcons"
                                :key="icon.name"
                                type="button"
                                @click="selectIcon(icon.path)"
                                class="w-12 h-12 rounded-lg border-2 flex items-center justify-center transition-all"
                                :class="formData.icon === icon.path ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                :title="icon.name"
                            >
                                <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icon.path" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="formData.icon = ''"
                                class="w-12 h-12 rounded-lg border-2 flex items-center justify-center transition-all"
                                :class="!formData.icon ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                title="Bez ikony"
                            >
                                <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div>
                        <label class="form-label">{{ t('categories.preview') || 'Náhľad' }}</label>
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div
                                    class="w-12 h-12 rounded-lg flex items-center justify-center"
                                    :style="{ backgroundColor: formData.color }"
                                >
                                    <svg v-if="formData.icon" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="formData.icon" />
                                    </svg>
                                    <span v-else class="text-white font-bold">{{ previewName?.charAt(0) || '?' }}</span>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ previewName || t('categories.namePlaceholder') || 'Názov kategórie' }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ formData.description[currentLang] || t('categories.descriptionPlaceholder') || 'Popis kategórie' }}</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span
                                    class="px-3 py-1 text-sm rounded-full text-white"
                                    :style="{ backgroundColor: formData.color }"
                                >
                                    {{ previewName || t('categories.category') || 'Kategória' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link href="/admin/categories" class="btn btn-secondary">{{ t('common.back') }}</Link>
                <button type="submit" :disabled="formData.processing" class="btn btn-primary">
                    {{ formData.processing ? t('common.saving') : t('common.saveChanges') }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
