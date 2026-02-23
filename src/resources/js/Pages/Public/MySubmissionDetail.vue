<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ImageLightbox from '@/Components/ImageLightbox.vue';
import { useLocalized } from '@/composables/useLocalized';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    submission: Object,
    auth: Object,
});

// Localization
const { getLocalized, getFormName } = useLocalized();

// Lightbox state
const lightboxOpen = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const openLightbox = (images, index = 0) => {
    lightboxImages.value = Array.isArray(images) ? images : [images];
    lightboxIndex.value = index;
    lightboxOpen.value = true;
};

const closeLightbox = () => {
    lightboxOpen.value = false;
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusLabel = (status) => {
    return t(`submissions.status.${status}`) || t('submissions.status.pending');
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700',
        submitted: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700',
        approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-300 dark:border-green-700',
        rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-300 dark:border-red-700',
        processing: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700',
    };
    return classes[status] || 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 border-yellow-300 dark:border-yellow-700';
};

const getFieldLabel = (fieldName) => {
    const fields = props.submission.form?.schema?.fields || [];
    const field = fields.find(f => f.name === fieldName);
    return field?.label ? getLocalized(field.label) : fieldName;
};

const formatFieldValue = (value) => {
    if (value === null || value === undefined) return '-';
    if (isFileObject(value) || isFileArray(value)) return null; // Handled separately in template
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'boolean') return value ? t('common.yes') : t('common.no');
    return String(value);
};

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// Check if value is a file object
const isFileObject = (value) => {
    return value && typeof value === 'object' && value.path && value.original_name;
};

// Check if value is array of file objects
const isFileArray = (value) => {
    return Array.isArray(value) && value.length > 0 && isFileObject(value[0]);
};

// Check if file is an image
const isImageFile = (file) => {
    if (!file?.mime_type) return false;
    return file.mime_type.startsWith('image/');
};
</script>

<template>
    <Head :title="`${t('mySubmissions.title')} - ${getFormName(submission.form)}`" />
    <PublicLayout :auth="auth">
        <div class="max-w-3xl mx-auto">
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
                <Link
                    href="/my/submissions"
                    class="text-gray-500 dark:text-gray-400 hover:text-brand-gold dark:hover:text-brand-gold transition-colors"
                >
                    {{ t('public.mySubmissions') }}
                </Link>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="text-gray-900 dark:text-white font-medium truncate max-w-[200px]">
                    {{ getFormName(submission.form) }}
                </span>
            </nav>

            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <Link
                        href="/my/submissions"
                        class="p-2 -ml-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                        :title="t('mySubmissions.back')"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ getFormName(submission.form) }}</h1>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-sm ml-9">{{ t('mySubmissions.submittedAt') }}: {{ formatDate(submission.created_at) }}</p>
            </div>

            <!-- Status Card -->
            <div class="card mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ t('mySubmissions.submissionStatus') }}</h2>
                <div
                    class="p-4 rounded-lg border-2"
                    :class="getStatusClass(submission.status)"
                >
                    <div class="flex items-center gap-3">
                        <!-- Status Icon -->
                        <div class="flex-shrink-0">
                            <svg v-if="submission.status === 'approved'" class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else-if="submission.status === 'rejected'" class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-lg">{{ getStatusLabel(submission.status) }}</p>
                            <p v-if="submission.reviewed_at" class="text-sm opacity-75">
                                {{ formatDate(submission.reviewed_at) }}
                                <span v-if="submission.reviewer">- {{ submission.reviewer.name }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Admin Response -->
                <div v-if="submission.admin_response" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-2">{{ t('mySubmissions.adminResponse') }}:</h3>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ submission.admin_response }}</p>
                </div>
            </div>

            <!-- Submitted Data Card -->
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ t('mySubmissions.yourData') }}</h2>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div
                        v-for="(value, key) in submission.data"
                        :key="key"
                        class="py-3 first:pt-0 last:pb-0"
                    >
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ getFieldLabel(key) }}</div>
                        <!-- Single file -->
                        <div v-if="isFileObject(value)" class="text-gray-900 dark:text-white">
                            <div class="flex items-center gap-3">
                                <!-- Image preview -->
                                <button v-if="isImageFile(value)" @click="openLightbox(value)" class="flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                                    <img
                                        :src="value.url"
                                        :alt="value.original_name"
                                        class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 transition-colors cursor-pointer"
                                    />
                                </button>
                                <!-- File icon for non-images -->
                                <div v-else class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-600 rounded-lg flex-shrink-0">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <a
                                        :href="value.url"
                                        target="_blank"
                                        class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                    >
                                        {{ value.original_name }}
                                    </a>
                                    <p class="text-gray-400 dark:text-gray-500 text-xs">{{ formatFileSize(value.size) }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Multiple files -->
                        <div v-else-if="isFileArray(value)" class="text-gray-900 dark:text-white space-y-3">
                            <div
                                v-for="(file, fileIndex) in value"
                                :key="fileIndex"
                                class="flex items-center gap-3"
                            >
                                <!-- Image preview -->
                                <button v-if="isImageFile(file)" @click="openLightbox(value.filter(f => isImageFile(f)), value.filter(f => isImageFile(f)).indexOf(file))" class="flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                                    <img
                                        :src="file.url"
                                        :alt="file.original_name"
                                        class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 transition-colors cursor-pointer"
                                    />
                                </button>
                                <!-- File icon for non-images -->
                                <div v-else class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-600 rounded-lg flex-shrink-0">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <a
                                        :href="file.url"
                                        target="_blank"
                                        class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                    >
                                        {{ file.original_name }}
                                    </a>
                                    <p class="text-gray-400 dark:text-gray-500 text-xs">{{ formatFileSize(file.size) }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Regular value -->
                        <div v-else class="text-gray-900 dark:text-white">{{ formatFieldValue(value) }}</div>
                    </div>
                </div>

                <div v-if="!submission.data || Object.keys(submission.data).length === 0" class="text-gray-500 dark:text-gray-400 text-center py-4">
                    {{ t('mySubmissions.noData') }}
                </div>
            </div>
        </div>

        <!-- Image Lightbox -->
        <ImageLightbox
            :images="lightboxImages"
            :initial-index="lightboxIndex"
            :show="lightboxOpen"
            @close="closeLightbox"
        />
    </PublicLayout>
</template>
