<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    reason: {
        type: String,
        default: 'not_found'
    },
    message: {
        type: String,
        default: ''
    }
});

const messages = {
    not_found: {
        sk: {
            title: 'Formulár nebol nájdený',
            description: 'Požadovaný formulár neexistuje alebo bol odstránený.',
            suggestion: 'Skontrolujte prosím URL adresu alebo sa vráťte na hlavnú stránku.'
        },
        en: {
            title: 'Form not found',
            description: 'The requested form does not exist or has been removed.',
            suggestion: 'Please check the URL or return to the home page.'
        }
    },
    inactive: {
        sk: {
            title: 'Formulár nie je aktívny',
            description: 'Tento formulár momentálne nie je k dispozícii.',
            suggestion: 'Formulár môže byť dočasne vypnutý alebo ešte nebol zverejnený. Skúste to neskôr alebo kontaktujte administrátora.'
        },
        en: {
            title: 'Form is not active',
            description: 'This form is currently not available.',
            suggestion: 'The form may be temporarily disabled or not yet published. Please try again later or contact the administrator.'
        }
    }
};

const currentMessage = messages[props.reason] || messages.not_found;
const lang = locale.value === 'en' ? 'en' : 'sk';
</script>

<template>
    <PublicLayout>
        <Head :title="currentMessage[lang].title" />

        <div class="min-h-[60vh] flex items-center justify-center px-4">
            <div class="max-w-md w-full text-center">
                <!-- Icon -->
                <div class="mb-6">
                    <div v-if="reason === 'inactive'" class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                        <svg class="w-10 h-10 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div v-else class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-800">
                        <svg class="w-10 h-10 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                    {{ currentMessage[lang].title }}
                </h1>

                <!-- Description -->
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    {{ currentMessage[lang].description }}
                </p>

                <!-- Suggestion -->
                <p class="text-sm text-gray-500 dark:text-gray-500 mb-8">
                    {{ currentMessage[lang].suggestion }}
                </p>

                <!-- Action buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <Link
                        href="/"
                        class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-medium transition-colors"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {{ lang === 'sk' ? 'Späť na hlavnú stránku' : 'Back to home page' }}
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
