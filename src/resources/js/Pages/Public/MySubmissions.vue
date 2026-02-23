<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useLocalized } from '@/composables/useLocalized';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const props = defineProps({
    submissions: Object,
    auth: Object,
    stats: Object,
});

// Helper to get localized form name
const getFormName = (form) => {
    if (!form) return '';
    return getLocalized(form.name) || form.slug || '';
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateShort = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const getStatusLabel = (status) => {
    return t(`submissions.status.${status}`) || t('submissions.status.pending');
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        submitted: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    };
    return classes[status] || 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
};
</script>

<template>
    <Head :title="t('public.mySubmissions')" />
    <PublicLayout :auth="auth">
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-sm mb-6">
            <a
                href="/"
                class="inline-flex items-center text-gray-500 dark:text-gray-400 hover:text-brand-gold dark:hover:text-brand-gold transition-colors"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                {{ t('public.home') }}
            </a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-gray-900 dark:text-white font-medium">
                {{ t('public.mySubmissions') }}
            </span>
        </nav>

        <div class="mb-6">
            <h1 class="text-xl md:text-2xl font-bold text-brand-navy dark:text-white flex items-center">
                <span class="w-1 h-8 bg-brand-gold rounded mr-3"></span>
                {{ t('public.mySubmissions') }}
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">{{ t('public.mySubmissionsDesc') }}</p>
        </div>

        <!-- Stats Cards -->
        <div v-if="stats && stats.total > 0" class="mb-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('mySubmissions.stats.total') }}</p>
                        </div>
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('mySubmissions.stats.pending') }}</p>
                        </div>
                        <div class="w-10 h-10 bg-amber-50 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.approved }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('mySubmissions.stats.approved') }}</p>
                        </div>
                        <div class="w-10 h-10 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.rejected }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('mySubmissions.stats.rejected') }}</p>
                        </div>
                        <div class="w-10 h-10 bg-red-50 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="submissions.data.length" class="space-y-3 md:space-y-0">
            <!-- Mobile: Card layout -->
            <div class="md:hidden space-y-3">
                <Link
                    v-for="submission in submissions.data"
                    :key="submission.id"
                    :href="`/my/submissions/${submission.id}`"
                    class="card-brand block"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-medium text-gray-900 dark:text-white truncate">{{ getFormName(submission.form) }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ formatDateShort(submission.created_at) }}</p>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-full font-medium flex-shrink-0"
                            :class="getStatusClass(submission.status)"
                        >
                            {{ getStatusLabel(submission.status) }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center text-sm text-brand-gold font-medium">
                        {{ t('mySubmissions.viewDetail') }}
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>
            </div>

            <!-- Desktop: Table layout -->
            <div class="hidden md:block card overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('mySubmissions.table.form') }}</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('mySubmissions.table.status') }}</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('mySubmissions.table.submitted') }}</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900 dark:text-white"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="submission in submissions.data" :key="submission.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="py-3 px-4">
                                <span class="font-medium text-gray-900 dark:text-white">{{ getFormName(submission.form) }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="getStatusClass(submission.status)"
                                >
                                    {{ getStatusLabel(submission.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-500 dark:text-gray-400">
                                {{ formatDate(submission.created_at) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <Link
                                    :href="`/my/submissions/${submission.id}`"
                                    class="text-brand-gold hover:text-brand-gold-dark font-medium"
                                >
                                    {{ t('mySubmissions.detail') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="submissions.last_page > 1" class="mt-6 flex justify-center gap-1 md:gap-2 flex-wrap">
                <Link
                    v-for="page in submissions.last_page"
                    :key="page"
                    :href="`/my/submissions?page=${page}`"
                    class="px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="page === submissions.current_page ? 'bg-brand-gold text-brand-navy' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >
                    {{ page }}
                </Link>
            </div>
        </div>

        <div v-else class="card text-center py-16">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ t('mySubmissions.noSubmissions') }}</h2>
            <p class="text-gray-500 dark:text-gray-400 mb-6">{{ t('mySubmissions.noSubmissionsDesc') }}</p>
            <Link href="/" class="btn btn-primary inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ t('mySubmissions.viewForms') }}
            </Link>
        </div>
    </PublicLayout>
</template>
