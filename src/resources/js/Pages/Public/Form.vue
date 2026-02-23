<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocalized } from '@/composables/useLocalized';

const { t } = useI18n();
const { locale, getLocalized, getFieldLabel, getFieldPlaceholder, getCheckboxLabel, getOptionLabel, getFormName, getFormDescription } = useLocalized();

const props = defineProps({
    form: Object,
    auth: Object,
    alreadySubmitted: {
        type: Boolean,
        default: false,
    },
    existingSubmission: {
        type: Object,
        default: null,
    },
});

// Anti-spam: timestamp when form was loaded
const formLoadedAt = ref(Date.now());

// Track file uploads separately
const fileUploads = ref({});
// Track image previews
const imagePreviews = ref({});

const formData = useForm({
    // Form fields (exclude static_text as it's not an input)
    ...Object.fromEntries(
        (props.form.schema?.fields || [])
            .filter(f => f.type !== 'static_text')
            .map(f => [f.name, f.type === 'checkbox' ? false : ''])
    ),
    // Anti-spam fields
    _honeypot: '', // Honeypot - should be empty
    _timestamp: '', // Will be set on mount
});

onMounted(() => {
    formData._timestamp = formLoadedAt.value.toString();
});

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});

const submitted = ref(false);
const rateLimited = ref(false);
const rateLimitCountdown = ref(0);
let countdownInterval = null;

// Convert URLs and emails in text to clickable links
const linkifyText = (text) => {
    if (!text) return '';
    // Escape HTML first to prevent XSS
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    // URL regex - matches http, https, and www links
    const urlRegex = /(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi;
    // Email regex
    const emailRegex = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/gi;

    let result = escaped;

    // Replace URLs with links
    result = result.replace(urlRegex, (url) => {
        const href = url.startsWith('www.') ? 'https://' + url : url;
        return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-300">${url}</a>`;
    });

    // Replace emails with mailto links
    result = result.replace(emailRegex, (email) => {
        return `<a href="mailto:${email}" class="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-300">${email}</a>`;
    });

    // Convert newlines to <br>
    result = result.replace(/\n/g, '<br>');

    return result;
};

// Conditional logic - check if field should be visible
const isFieldVisible = (field) => {
    // No conditions = always visible
    if (!field.conditions || field.conditions.length === 0) {
        return true;
    }

    // All conditions must be met (AND logic)
    return field.conditions.every(condition => {
        const fieldValue = formData[condition.field];

        switch (condition.operator) {
            case 'equals':
                return fieldValue == condition.value;
            case 'not_equals':
                return fieldValue != condition.value;
            case 'contains':
                return String(fieldValue || '').toLowerCase().includes(String(condition.value).toLowerCase());
            case 'is_empty':
                return !fieldValue || fieldValue === '' || fieldValue === false;
            case 'not_empty':
                return fieldValue && fieldValue !== '' && fieldValue !== false;
            default:
                return true;
        }
    });
};

const startCountdown = (seconds) => {
    rateLimited.value = true;
    rateLimitCountdown.value = seconds;

    if (countdownInterval) {
        clearInterval(countdownInterval);
    }

    countdownInterval = setInterval(() => {
        rateLimitCountdown.value--;
        if (rateLimitCountdown.value <= 0) {
            clearInterval(countdownInterval);
            rateLimited.value = false;
        }
    }, 1000);
};

const retrySubmit = () => {
    rateLimited.value = false;
    rateLimitCountdown.value = 0;
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    submit();
};

const isSubmitting = ref(false);

const submit = async () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    formData.errors = {};

    // Create FormData for file uploads
    const data = new FormData();

    // Add regular form fields
    Object.keys(formData.data()).forEach(key => {
        if (key !== 'errors' && key !== 'processing' && key !== 'progress') {
            const value = formData[key];
            if (value !== null && value !== undefined) {
                // Convert boolean to "1" or "0" for Laravel validation
                if (typeof value === 'boolean') {
                    data.append(key, value ? '1' : '0');
                } else {
                    data.append(key, value);
                }
            }
        }
    });

    // Add file uploads
    Object.keys(fileUploads.value).forEach(fieldName => {
        const files = fileUploads.value[fieldName];
        if (files) {
            if (Array.isArray(files)) {
                files.forEach((file, index) => {
                    data.append(`${fieldName}[${index}]`, file);
                });
            } else {
                data.append(fieldName, files);
            }
        }
    });

    try {
        // SECURITY: Validate CSRF token presence before submitting
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            formData.errors = { message: t('publicForm.submitError') };
            isSubmitting.value = false;
            return;
        }

        const response = await fetch(`/forms/${props.form.slug}/submit`, {
            method: 'POST',
            body: data,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        if (response.status === 429) {
            // Rate limited
            const retryAfter = response.headers.get('Retry-After') || 60;
            startCountdown(parseInt(retryAfter));
        } else if (response.ok) {
            submitted.value = true;
        } else {
            // Validation or other errors
            const result = await response.json();
            if (result.errors) {
                formData.errors = result.errors;
            } else if (result.message) {
                formData.errors = { message: result.message };
            }
        }
    } catch (error) {
        formData.errors = { message: t('publicForm.submitError') };
    } finally {
        isSubmitting.value = false;
    }
};

const isImageFile = (file) => {
    return file && file.type && file.type.startsWith('image/');
};

const createImagePreview = (file) => {
    return new Promise((resolve) => {
        if (!isImageFile(file)) {
            resolve(null);
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
};

const handleFileChange = async (field, event) => {
    const files = event.target.files;
    if (field.multiple) {
        const fileArray = Array.from(files);
        fileUploads.value[field.name] = fileArray;

        // Generate previews for images
        const previews = await Promise.all(
            fileArray.map(file => createImagePreview(file))
        );
        imagePreviews.value[field.name] = previews;
    } else {
        const file = files[0] || null;
        fileUploads.value[field.name] = file;

        // Generate preview for single image
        if (file) {
            imagePreviews.value[field.name] = await createImagePreview(file);
        } else {
            imagePreviews.value[field.name] = null;
        }
    }
};

const removeFile = (fieldName, index = null) => {
    if (index !== null && Array.isArray(fileUploads.value[fieldName])) {
        fileUploads.value[fieldName].splice(index, 1);
        if (Array.isArray(imagePreviews.value[fieldName])) {
            imagePreviews.value[fieldName].splice(index, 1);
        }
    } else {
        fileUploads.value[fieldName] = null;
        imagePreviews.value[fieldName] = null;
    }
};

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const getInputType = (type) => {
    const types = {
        text: 'text',
        email: 'email',
        number: 'number',
        date: 'date',
    };
    return types[type] || 'text';
};
</script>

<template>
    <Head :title="getFormName(form)" />
    <PublicLayout :auth="auth">
        <div class="max-w-2xl mx-auto">
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-sm mb-6">
                <a
                    href="/"
                    class="inline-flex items-center text-gray-500 dark:text-gray-400 hover:text-brand-gold dark:hover:text-brand-gold transition-colors"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ t('nav.forms') }}
                </a>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span
                    v-if="form.category"
                    class="text-gray-500 dark:text-gray-400"
                >
                    {{ getLocalized(form.category?.name) }}
                </span>
                <svg v-if="form.category" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="text-gray-900 dark:text-white font-medium truncate max-w-[200px]">
                    {{ getFormName(form) }}
                </span>
            </nav>

            <div class="card">
                <!-- Header -->
                <div class="flex items-start space-x-4 mb-6">
                    <div class="w-12 h-12 bg-brand-navy dark:bg-brand-gold rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-brand-gold dark:text-brand-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-brand-navy dark:text-white">{{ getFormName(form) }}</h1>
                        <p v-if="getFormDescription(form)" class="text-gray-600 dark:text-gray-400 mt-1">{{ getFormDescription(form) }}</p>
                    </div>
                </div>

                <div class="brand-accent-line mb-6 -mx-4 md:-mx-6"></div>

                <!-- Already submitted message -->
                <div v-if="alreadySubmitted" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
                    <div class="w-16 h-16 mx-auto bg-yellow-100 dark:bg-yellow-900/30 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-yellow-800 dark:text-yellow-300">{{ t('publicForm.alreadySubmitted') }}</h2>
                    <p class="text-yellow-600 dark:text-yellow-400 mt-2">
                        {{ t('publicForm.alreadySubmittedMessage') }}
                    </p>
                    <p v-if="getLocalized(form.duplicate_message)" class="text-yellow-700 dark:text-yellow-300 mt-4 text-sm whitespace-pre-line" v-html="linkifyText(getLocalized(form.duplicate_message))">
                    </p>
                    <p v-if="existingSubmission" class="text-yellow-600 dark:text-yellow-400 mt-4 text-sm">
                        {{ t('publicForm.submittedAt') }}: {{ new Date(existingSubmission.created_at).toLocaleDateString(locale === 'en' ? 'en-US' : 'sk-SK', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                    </p>

                    <!-- Action buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-6">
                        <a
                            href="/"
                            class="inline-flex items-center px-5 py-2.5 bg-white dark:bg-gray-800 border border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-300 hover:bg-yellow-50 dark:hover:bg-yellow-900/30 font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            {{ t('public.backToForms') }}
                        </a>
                        <a
                            v-if="auth?.user"
                            href="/my/submissions"
                            class="inline-flex items-center px-5 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            {{ t('public.viewMySubmission') }}
                        </a>
                    </div>
                </div>

                <!-- Rate limit message -->
                <div v-else-if="rateLimited" class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-6 text-center">
                    <div class="w-16 h-16 mx-auto bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-orange-800 dark:text-orange-300">{{ t('publicForm.tooManyRequests') }}</h2>
                    <p class="text-orange-600 dark:text-orange-400 mt-2">
                        {{ t('publicForm.tooManyRequestsMessage') }}
                    </p>

                    <!-- Countdown -->
                    <div v-if="rateLimitCountdown > 0" class="mt-4">
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-orange-100 dark:bg-orange-900/30 rounded-full">
                            <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-orange-700 dark:text-orange-300 font-medium">
                                {{ t('publicForm.tryAgainIn', { seconds: rateLimitCountdown }) }}
                            </span>
                        </div>
                    </div>

                    <!-- Retry button -->
                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <button
                            @click="retrySubmit"
                            :disabled="rateLimitCountdown > 0"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors"
                            :class="rateLimitCountdown > 0
                                ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                                : 'bg-orange-600 hover:bg-orange-700 text-white'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ t('publicForm.tryAgain') }}
                        </button>
                        <button
                            @click="rateLimited = false"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ t('publicForm.editForm') }}
                        </button>
                    </div>
                </div>

                <!-- Success message -->
                <div v-else-if="submitted" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 text-center">
                    <div class="w-16 h-16 mx-auto bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-green-800 dark:text-green-300">{{ t('publicForm.successTitle') }}</h2>
                    <p class="text-green-600 dark:text-green-400 mt-2">{{ t('publicForm.successMessage') }}</p>

                    <!-- Action buttons after success -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-6">
                        <a
                            href="/"
                            class="inline-flex items-center px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            {{ t('public.backToForms') }}
                        </a>
                        <a
                            v-if="auth?.user"
                            href="/my/submissions"
                            class="inline-flex items-center px-5 py-2.5 bg-brand-gold hover:bg-brand-gold-dark text-white font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            {{ t('public.mySubmissions') }}
                        </a>
                    </div>
                </div>

                <!-- Form -->
                <form v-else @submit.prevent="submit" class="space-y-6">
                    <!-- Spam error message -->
                    <div v-if="formData.errors.spam" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <p class="text-red-700 dark:text-red-400">{{ formData.errors.spam }}</p>
                    </div>

                    <!-- Anti-spam: Honeypot field (hidden from humans, bots will fill it) -->
                    <div class="absolute -left-[9999px]" aria-hidden="true" tabindex="-1">
                        <label for="_honeypot">Nechajte prázdne</label>
                        <input
                            type="text"
                            id="_honeypot"
                            name="_honeypot"
                            v-model="formData._honeypot"
                            autocomplete="off"
                            tabindex="-1"
                        />
                    </div>

                    <template v-for="field in form.schema?.fields || []" :key="field.name">
                    <div v-if="isFieldVisible(field)" class="space-y-2">
                        <!-- Static text (no label, just content) -->
                        <template v-if="field.type === 'static_text'">
                            <div
                                class="p-4 rounded-lg"
                                :class="{
                                    'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300': field.style === 'info' || !field.style,
                                    'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-300': field.style === 'warning',
                                    'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300': field.style === 'success',
                                    'bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300': field.style === 'neutral',
                                }"
                            >
                                <div class="flex items-start gap-3">
                                    <svg v-if="field.style === 'info' || !field.style" class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else-if="field.style === 'warning'" class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <svg v-else-if="field.style === 'success'" class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="whitespace-pre-wrap">{{ getLocalized(field.content) }}</p>
                                </div>
                            </div>
                        </template>

                        <!-- Regular fields with label (not checkbox - it has inline label) -->
                        <template v-else-if="field.type !== 'checkbox'">
                            <label :for="field.name" class="form-label">
                                {{ getFieldLabel(field) }}
                                <span v-if="field.required" class="text-red-500 dark:text-red-400">*</span>
                            </label>
                        </template>

                        <!-- Text, Email, Number, Date -->
                        <template v-if="['text', 'email', 'number', 'date'].includes(field.type)">
                            <input
                                :id="field.name"
                                v-model="formData[field.name]"
                                :type="getInputType(field.type)"
                                :placeholder="getFieldPlaceholder(field)"
                                class="form-input"
                                :required="field.required"
                            />
                        </template>

                        <!-- Textarea -->
                        <template v-else-if="field.type === 'textarea'">
                            <textarea
                                :id="field.name"
                                v-model="formData[field.name]"
                                :placeholder="getFieldPlaceholder(field)"
                                class="form-input"
                                rows="4"
                                :required="field.required"
                            />
                        </template>

                        <!-- Select -->
                        <template v-else-if="field.type === 'select'">
                            <select
                                :id="field.name"
                                v-model="formData[field.name]"
                                class="form-input"
                                :required="field.required"
                            >
                                <option value="">{{ t('publicForm.selectOption') }}</option>
                                <option
                                    v-for="option in field.options"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ getOptionLabel(option) }}
                                </option>
                            </select>
                        </template>

                        <!-- Checkbox -->
                        <template v-else-if="field.type === 'checkbox'">
                            <div class="flex items-start">
                                <input
                                    :id="field.name"
                                    v-model="formData[field.name]"
                                    type="checkbox"
                                    class="w-4 h-4 mt-1 text-brand-gold border-gray-300 dark:border-gray-600 rounded focus:ring-brand-gold dark:bg-gray-700"
                                    :required="field.required"
                                />
                                <label :for="field.name" class="ml-2 text-gray-700 dark:text-gray-300">
                                    {{ getCheckboxLabel(field) || getFieldLabel(field) }}
                                    <span v-if="field.required" class="text-red-500 dark:text-red-400">*</span>
                                </label>
                            </div>
                        </template>

                        <!-- Radio -->
                        <template v-else-if="field.type === 'radio'">
                            <div class="space-y-2">
                                <div
                                    v-for="option in field.options"
                                    :key="option.value"
                                    class="flex items-center"
                                >
                                    <input
                                        :id="`${field.name}_${option.value}`"
                                        v-model="formData[field.name]"
                                        type="radio"
                                        :name="field.name"
                                        :value="option.value"
                                        class="w-4 h-4 text-brand-gold border-gray-300 dark:border-gray-600 focus:ring-brand-gold dark:bg-gray-700"
                                    />
                                    <label :for="`${field.name}_${option.value}`" class="ml-2 text-gray-700 dark:text-gray-300">
                                        {{ getOptionLabel(option) }}
                                    </label>
                                </div>
                            </div>
                        </template>

                        <!-- File upload -->
                        <template v-else-if="field.type === 'file'">
                            <div class="space-y-3">
                                <!-- Drop zone / Upload area -->
                                <label
                                    :for="field.name"
                                    class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer transition-colors"
                                    :class="fileUploads[field.name] ? 'border-green-400 bg-green-50 dark:bg-green-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-brand-gold dark:hover:border-brand-gold bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                >
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p class="mb-1 text-sm text-gray-600 dark:text-gray-400">
                                            <span class="font-semibold">{{ t('publicForm.clickToUpload') }}</span> {{ t('publicForm.orDragDrop') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ field.accept || t('publicForm.allFileTypes') }}
                                            <span v-if="field.maxSize"> ({{ t('publicForm.maxFileSize', { size: field.maxSize }) }})</span>
                                        </p>
                                    </div>
                                    <input
                                        :id="field.name"
                                        type="file"
                                        class="hidden"
                                        :accept="field.accept"
                                        :multiple="field.multiple"
                                        :required="field.required && !fileUploads[field.name]"
                                        @change="handleFileChange(field, $event)"
                                    />
                                </label>

                                <!-- Selected files list -->
                                <div v-if="fileUploads[field.name]" class="space-y-2">
                                    <!-- Multiple files -->
                                    <template v-if="Array.isArray(fileUploads[field.name])">
                                        <div
                                            v-for="(file, index) in fileUploads[field.name]"
                                            :key="index"
                                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                        >
                                            <div class="flex items-center gap-3 min-w-0">
                                                <!-- Image preview -->
                                                <img
                                                    v-if="imagePreviews[field.name]?.[index]"
                                                    :src="imagePreviews[field.name][index]"
                                                    class="w-12 h-12 object-cover rounded-lg flex-shrink-0"
                                                    alt="Náhľad"
                                                />
                                                <!-- File icon for non-images -->
                                                <svg v-else class="w-12 h-12 p-3 text-gray-400 bg-gray-200 dark:bg-gray-600 rounded-lg flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ file.name }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(file.size) }}</p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="removeFile(field.name, index)"
                                                class="p-1 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 rounded"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <!-- Single file -->
                                    <template v-else>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <!-- Image preview -->
                                                <img
                                                    v-if="imagePreviews[field.name]"
                                                    :src="imagePreviews[field.name]"
                                                    class="w-12 h-12 object-cover rounded-lg flex-shrink-0"
                                                    alt="Náhľad"
                                                />
                                                <!-- File icon for non-images -->
                                                <svg v-else class="w-12 h-12 p-3 text-gray-400 bg-gray-200 dark:bg-gray-600 rounded-lg flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ fileUploads[field.name].name }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(fileUploads[field.name].size) }}</p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="removeFile(field.name)"
                                                class="p-1 text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 rounded"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <p v-if="formData.errors[field.name]" class="text-sm text-red-600 dark:text-red-400">
                            {{ formData.errors[field.name] }}
                        </p>
                    </div>
                    </template>

                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="btn btn-primary w-full flex items-center justify-center"
                    >
                        <template v-if="isSubmitting">
                            <svg class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ t('publicForm.submitting') }}
                        </template>
                        <template v-else>
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            {{ t('publicForm.submit') }}
                        </template>
                    </button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
