<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTheme } from '@/composables/useTheme';

const { t, locale } = useI18n();
const { setTheme } = useTheme();

const props = defineProps({
    profile: Object,
    settings: Object,
    availableForms: Array,
    auth: Object,
});

// API Tokens management
const apiTokens = ref([]);
const loadingTokens = ref(false);
const newTokenName = ref('');
const creatingToken = ref(false);
const newToken = ref(null);
const showNewToken = ref(false);

const loadApiTokens = async () => {
    loadingTokens.value = true;
    try {
        const response = await fetch('/api/v1/tokens', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        });
        if (response.ok) {
            const data = await response.json();
            apiTokens.value = data.data;
        }
    } catch (error) {
        console.error('Failed to load API tokens:', error);
    } finally {
        loadingTokens.value = false;
    }
};

const createApiToken = async () => {
    if (!newTokenName.value.trim()) return;

    creatingToken.value = true;
    try {
        const response = await fetch('/api/v1/tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: newTokenName.value.trim() }),
        });
        if (response.ok) {
            const data = await response.json();
            newToken.value = data.token;
            showNewToken.value = true;
            newTokenName.value = '';
            loadApiTokens();
        }
    } catch (error) {
        console.error('Failed to create API token:', error);
    } finally {
        creatingToken.value = false;
    }
};

const deleteApiToken = async (tokenId) => {
    if (!confirm('Naozaj chcete zmazať tento API token?')) return;

    try {
        const response = await fetch(`/api/v1/tokens/${tokenId}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-HTTP-Method-Override': 'DELETE',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
        if (response.ok) {
            loadApiTokens();
        }
    } catch (error) {
        console.error('Failed to delete API token:', error);
    }
};

const copyToken = () => {
    if (newToken.value) {
        navigator.clipboard.writeText(newToken.value);
    }
};

const formatTokenDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    loadApiTokens();
});

const form = useForm({
    theme: props.settings?.theme || 'system',
    email_notifications: props.settings?.email_notifications ?? true,
    language: props.settings?.language || 'system',
    notify_new_submissions: props.settings?.notify_new_submissions ?? false,
});

// Local state for available forms notifications
const availableFormsState = ref(
    (props.availableForms || []).map(f => ({
        ...f,
        notify_enabled: f.notify_enabled,
        loading: false,
    }))
);

// Toggle notification for a specific form
const toggleFormNotification = async (formItem) => {
    formItem.loading = true;
    try {
        const response = await fetch(`/profile/settings/form-notification/${formItem.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ notify_enabled: !formItem.notify_enabled }),
        });
        if (response.ok) {
            formItem.notify_enabled = !formItem.notify_enabled;
        }
    } catch (error) {
        console.error('Failed to toggle form notification:', error);
    } finally {
        formItem.loading = false;
    }
};

// Role hierarchy for checking minimum role
const ROLE_HIERARCHY = {
    user: 0,
    viewer: 1,
    approver: 2,
    admin: 3,
    super_admin: 4,
};

const hasMinRole = (minRole) => {
    return (ROLE_HIERARCHY[props.profile.role] || 0) >= (ROLE_HIERARCHY[minRole] || 0);
};

const themeOptions = computed(() => [
    { value: 'system', label: t('profile.themeSystem'), icon: '💻', description: t('profile.themeSystemDesc') },
    { value: 'light', label: t('profile.themeLight'), icon: '☀️', description: t('profile.themeLightDesc') },
    { value: 'dark', label: t('profile.themeDark'), icon: '🌙', description: t('profile.themeDarkDesc') },
]);

const languageOptions = computed(() => [
    { value: 'system', label: t('profile.languageSystem'), icon: '💻', description: t('profile.languageSystemDesc') },
    { value: 'sk', label: t('profile.languageSk'), icon: '🇸🇰', description: 'Slovenčina' },
    { value: 'en', label: t('profile.languageEn'), icon: '🇬🇧', description: 'English' },
]);

// Detect system language
const getSystemLanguage = () => {
    const browserLang = navigator.language || navigator.userLanguage;
    // Check if browser language starts with 'sk' (Slovak)
    if (browserLang.toLowerCase().startsWith('sk')) {
        return 'sk';
    }
    // Default to English for all other languages
    return 'en';
};

// Get effective language (resolves 'system' to actual language)
const getEffectiveLanguage = (lang) => {
    return lang === 'system' ? getSystemLanguage() : lang;
};

// Watch for language changes and update locale immediately
watch(() => form.language, (newLang) => {
    locale.value = getEffectiveLanguage(newLang);
}, { immediate: true });

// Watch for theme changes and apply immediately
watch(() => form.theme, (newTheme) => {
    setTheme(newTheme);
});

const submit = () => {
    form.transform(data => ({ ...data, _method: 'PUT' })).post('/profile/settings', {
        preserveScroll: true,
    });
};

const formatDate = (date) => {
    if (!date) return '-';
    const effectiveLang = getEffectiveLanguage(form.language);
    const dateLocale = effectiveLang === 'sk' ? 'sk-SK' : 'en-US';
    return new Date(date).toLocaleDateString(dateLocale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="t('profile.title')" />
    <PublicLayout :auth="auth">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('profile.title') }}</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ t('profile.personalInfo') }}</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Profile info card -->
                <div class="lg:col-span-1">
                    <div class="card">
                        <div class="flex flex-col items-center text-center">
                            <!-- Avatar -->
                            <div class="w-20 h-20 bg-gradient-to-br from-brand-gold to-yellow-600 rounded-full flex items-center justify-center text-brand-navy text-3xl font-bold shadow-lg mb-4">
                                {{ profile.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>

                            <!-- Name -->
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ profile.first_name && profile.last_name
                                    ? `${profile.first_name} ${profile.last_name}`
                                    : profile.name }}
                            </h2>

                            <!-- Email -->
                            <p class="text-gray-500 dark:text-gray-400 mt-1">{{ profile.email }}</p>

                            <!-- Badge -->
                            <div class="mt-3">
                                <span
                                    v-if="profile.is_admin"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ t('users.isAdmin') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                    {{ t('submissions.user') }}
                                </span>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-gray-200 dark:border-gray-700 my-4"></div>

                        <!-- Info -->
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Keycloak ID:</dt>
                                <dd class="text-gray-900 dark:text-white font-mono text-xs">
                                    {{ profile.keycloak_id ? profile.keycloak_id.substring(0, 8) + '...' : '-' }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">{{ t('users.createdAt') }}:</dt>
                                <dd class="text-gray-900 dark:text-white">{{ formatDate(profile.created_at) }}</dd>
                            </div>
                        </dl>

                        <!-- Links -->
                        <div class="mt-6 space-y-2">
                            <Link
                                href="/my/submissions"
                                class="flex items-center gap-2 w-full px-4 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ t('nav.mySubmissions') }}
                            </Link>
                            <Link
                                v-if="profile.is_admin"
                                href="/admin"
                                class="flex items-center gap-2 w-full px-4 py-2 text-left text-brand-gold hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ t('nav.adminPanel') }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Settings form -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Theme settings -->
                        <div class="card">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                </svg>
                                {{ t('profile.theme') }}
                            </h3>

                            <div class="space-y-3">
                                <label class="form-label">{{ t('profile.theme') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label
                                        v-for="option in themeOptions"
                                        :key="option.value"
                                        class="relative flex flex-col items-center p-4 border-2 rounded-xl cursor-pointer transition-all"
                                        :class="form.theme === option.value
                                            ? 'border-brand-gold bg-brand-gold/10 dark:bg-brand-gold/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.theme"
                                            :value="option.value"
                                            class="sr-only"
                                        />
                                        <span class="text-2xl mb-2">{{ option.icon }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ option.label }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">{{ option.description }}</span>

                                        <!-- Checkmark -->
                                        <div
                                            v-if="form.theme === option.value"
                                            class="absolute top-2 right-2 w-5 h-5 bg-brand-gold rounded-full flex items-center justify-center"
                                        >
                                            <svg class="w-3 h-3 text-brand-navy" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Notification settings -->
                        <div class="card">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                {{ t('profile.notifications') }}
                            </h3>

                            <div class="space-y-4">
                                <label class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg cursor-pointer">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ t('profile.notifications') }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ t('profile.notificationsDesc') }}
                                        </p>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="checkbox"
                                            v-model="form.email_notifications"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 rounded-full peer peer-checked:bg-brand-gold transition-colors"></div>
                                        <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                                    </div>
                                </label>

                                <!-- New submission notifications section - only for approver+ -->
                                <div v-if="hasMinRole('approver')" class="border-t border-gray-200 dark:border-gray-600 pt-4 mt-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        {{ t('profile.newSubmissionNotificationsTitle') }}
                                    </p>

                                    <!-- Global notifications - all forms -->
                                    <label class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg cursor-pointer mb-3">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ t('profile.newSubmissionNotifications') }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ t('profile.newSubmissionNotificationsDesc') }}
                                            </p>
                                        </div>
                                        <div class="relative">
                                            <input
                                                type="checkbox"
                                                v-model="form.notify_new_submissions"
                                                class="sr-only peer"
                                            />
                                            <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 rounded-full peer peer-checked:bg-brand-gold transition-colors"></div>
                                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                                        </div>
                                    </label>

                                    <!-- Per-form notifications list -->
                                    <div v-if="availableFormsState.length > 0" class="mt-4">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ t('profile.perFormNotificationsTitle') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            {{ t('profile.perFormNotificationsDesc') }}
                                        </p>
                                        <div class="space-y-2 max-h-64 overflow-y-auto">
                                            <div
                                                v-for="formItem in availableFormsState"
                                                :key="formItem.id"
                                                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                                            >
                                                <div class="flex-1 min-w-0 mr-3">
                                                    <p class="font-medium text-gray-900 dark:text-white truncate">
                                                        {{ formItem.name }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                        /forms/{{ formItem.slug }}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="toggleFormNotification(formItem)"
                                                    :disabled="formItem.loading"
                                                    class="relative flex-shrink-0"
                                                >
                                                    <div
                                                        class="w-11 h-6 rounded-full transition-colors"
                                                        :class="formItem.notify_enabled ? 'bg-brand-gold' : 'bg-gray-300 dark:bg-gray-600'"
                                                    ></div>
                                                    <div
                                                        class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                                                        :class="formItem.notify_enabled ? 'translate-x-5' : ''"
                                                    ></div>
                                                    <svg
                                                        v-if="formItem.loading"
                                                        class="absolute inset-0 m-auto w-4 h-4 animate-spin text-gray-600"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Language settings -->
                        <div class="card">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                </svg>
                                {{ t('profile.language') }}
                            </h3>

                            <div class="space-y-3">
                                <label class="form-label">{{ t('profile.language') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label
                                        v-for="option in languageOptions"
                                        :key="option.value"
                                        class="relative flex flex-col items-center p-4 border-2 rounded-xl cursor-pointer transition-all"
                                        :class="form.language === option.value
                                            ? 'border-brand-gold bg-brand-gold/10 dark:bg-brand-gold/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.language"
                                            :value="option.value"
                                            class="sr-only"
                                        />
                                        <span class="text-2xl mb-2">{{ option.icon }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ option.label }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">{{ option.description }}</span>

                                        <!-- Checkmark -->
                                        <div
                                            v-if="form.language === option.value"
                                            class="absolute top-2 right-2 w-5 h-5 bg-brand-gold rounded-full flex items-center justify-center"
                                        >
                                            <svg class="w-3 h-3 text-brand-navy" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('profile.languageDesc') }}
                                </p>
                            </div>
                        </div>

                        <!-- Submit button -->
                        <div class="flex justify-end gap-4">
                            <Link href="/" class="btn btn-secondary">
                                {{ t('common.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="btn btn-primary"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ form.processing ? t('common.loading') : t('common.save') }}
                            </button>
                        </div>
                    </form>

                    <!-- API Tokens Section - Admin only -->
                    <div v-if="profile.is_admin" class="mt-6 space-y-6">
                        <!-- API Documentation Link -->
                        <div class="card">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                API Dokumentácia
                            </h3>

                            <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">Swagger / OpenAPI</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Interaktívna dokumentácia REST API pre integráciu
                                    </p>
                                </div>
                                <a
                                    href="/api/documentation"
                                    target="_blank"
                                    class="btn btn-primary flex items-center gap-2"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    Otvoriť dokumentáciu
                                </a>
                            </div>
                        </div>

                        <!-- API Tokens -->
                        <div class="card">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                API Tokeny
                            </h3>

                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                Vytvorte si API tokeny pre prístup k REST API. Tokeny slúžia na autentifikáciu pri volaní API endpointov.
                            </p>

                            <!-- Create new token -->
                            <div class="flex gap-2 mb-4">
                                <input
                                    v-model="newTokenName"
                                    type="text"
                                    placeholder="Názov tokenu (napr. Integrácia CMDB)"
                                    class="form-input flex-1"
                                    @keyup.enter="createApiToken"
                                />
                                <button
                                    @click="createApiToken"
                                    :disabled="creatingToken || !newTokenName.trim()"
                                    class="btn btn-primary whitespace-nowrap"
                                >
                                    <svg v-if="creatingToken" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Vytvoriť token
                                </button>
                            </div>

                            <!-- New token display -->
                            <div v-if="showNewToken && newToken" class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-green-800 dark:text-green-200 mb-1">Token bol vytvorený!</p>
                                        <p class="text-sm text-green-600 dark:text-green-400 mb-2">
                                            Skopírujte si token teraz. Nebude ho možné znova zobraziť.
                                        </p>
                                        <code class="block p-2 bg-white dark:bg-gray-800 rounded border border-green-200 dark:border-green-700 text-xs font-mono break-all text-gray-900 dark:text-white">
                                            {{ newToken }}
                                        </code>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="copyToken" class="btn btn-secondary btn-sm" title="Kopírovať">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                        <button @click="showNewToken = false; newToken = null" class="btn btn-secondary btn-sm" title="Zavrieť">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Token list -->
                            <div v-if="loadingTokens" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                Načítavam tokeny...
                            </div>

                            <div v-else-if="apiTokens.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                Nemáte žiadne API tokeny.
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="token in apiTokens"
                                    :key="token.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ token.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Vytvorené: {{ formatTokenDate(token.created_at) }}
                                            <span v-if="token.last_used_at">
                                                • Naposledy použité: {{ formatTokenDate(token.last_used_at) }}
                                            </span>
                                        </p>
                                    </div>
                                    <button
                                        @click="deleteApiToken(token.id)"
                                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 p-1"
                                        title="Zmazať token"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Usage hint -->
                            <div class="mt-4 p-3 bg-gray-100 dark:bg-gray-700/30 rounded-lg">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Použitie:</p>
                                <code class="text-xs text-gray-600 dark:text-gray-400 font-mono break-all">
                                    curl -H "Authorization: Bearer YOUR_TOKEN" /api/v1/submissions/approved
                                </code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
