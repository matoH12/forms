<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormBuilder from '@/Components/FormBuilder/FormBuilder.vue';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocalized } from '@/composables/useLocalized';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const props = defineProps({
    form: Object,
    workflows: Array,
    categories: Array,
    emailTemplates: Array,
    auth: Object,
});

// Version history
const showVersionHistory = ref(false);
const versions = ref([]);
const loadingVersions = ref(false);
const selectedVersion = ref(null);
const restoringVersion = ref(false);

const loadVersions = async () => {
    loadingVersions.value = true;
    try {
        const response = await fetch(`/admin/forms/${props.form.id}/versions`, {
            headers: {
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            const data = await response.json();
            versions.value = data.versions;
        }
    } catch (error) {
        console.error('Failed to load versions:', error);
    } finally {
        loadingVersions.value = false;
    }
};

const toggleVersionHistory = () => {
    showVersionHistory.value = !showVersionHistory.value;
    if (showVersionHistory.value) {
        // Always reload versions when opening panel to get fresh data
        loadVersions();
    }
};

const viewVersion = async (version) => {
    try {
        const response = await fetch(`/admin/forms/${props.form.id}/versions/${version.id}`, {
            headers: {
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            const data = await response.json();
            selectedVersion.value = data.version;
        }
    } catch (error) {
        console.error('Failed to load version:', error);
    }
};

const restoreVersion = async (version) => {
    if (!confirm(`Naozaj chcete obnoviť formulár na verziu ${version.version_number}?`)) {
        return;
    }

    restoringVersion.value = true;
    try {
        const response = await fetch(`/admin/forms/${props.form.id}/versions/${version.id}/restore`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            router.reload();
        } else {
            alert('Nepodarilo sa obnoviť verziu');
        }
    } catch (error) {
        console.error('Failed to restore version:', error);
        alert('Nastala chyba pri obnove verzie');
    } finally {
        restoringVersion.value = false;
    }
};

const formatVersionDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Current editing language
const currentLang = ref('sk');
const languages = [
    { code: 'sk', name: 'Slovensky', flag: '🇸🇰' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
];

// Copy link functionality
const linkCopied = ref(false);
const baseUrl = ref('');

// Set base URL after mount (SSR compatibility)
onMounted(() => {
    baseUrl.value = window.location.origin;
});

// Computed property for the full form link
const formLink = computed(() => {
    return baseUrl.value ? `${baseUrl.value}/forms/${props.form.slug}` : `/forms/${props.form.slug}`;
});

const copyFormLink = async () => {
    const url = formLink.value;
    try {
        await navigator.clipboard.writeText(url);
        linkCopied.value = true;
        setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        linkCopied.value = true;
        setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    }
};

// Helper to initialize multilingual value
const initMultilingualValue = (value) => {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value;
    }
    // Convert string to multilingual object
    return { sk: value || '', en: '' };
};

// Helper to extract keywords from either string or multilingual object
const initKeywords = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'object') {
        // Combine sk and en keywords if both exist
        const sk = value.sk || '';
        const en = value.en || '';
        if (sk && en && sk !== en) {
            return `${sk}, ${en}`;
        }
        return sk || en || '';
    }
    return '';
};

const formData = useForm({
    name: initMultilingualValue(props.form.name),
    description: initMultilingualValue(props.form.description),
    schema: props.form.schema || { fields: [] },
    settings: props.form.settings || {},
    workflow_id: props.form.workflow_id || null,
    is_public: props.form.is_public,
    is_active: props.form.is_active,
    is_featured: props.form.is_featured || false,
    featured_order: props.form.featured_order || 0,
    prevent_duplicates: props.form.prevent_duplicates || false,
    duplicate_message: initMultilingualValue(props.form.duplicate_message),
    category_id: props.form.category_id || null,
    tags: props.form.tags || [],
    keywords: initKeywords(props.form.keywords),
    send_confirmation_email: props.form.send_confirmation_email || false,
    email_template_id: props.form.email_template_id || null,
    approval_email_template_id: props.form.approval_email_template_id || null,
    rejection_email_template_id: props.form.rejection_email_template_id || null,
    allowed_email_domains: props.form.allowed_email_domains || [],
    domain_restriction_mode: props.form.domain_restriction_mode || 'none',
});

// Email domain management
const newEmailDomain = ref('');

const addEmailDomain = () => {
    let domain = newEmailDomain.value.trim().toLowerCase();
    // Remove @ prefix if user typed it
    if (domain.startsWith('@')) {
        domain = domain.substring(1);
    }
    if (domain && !formData.allowed_email_domains.includes(domain)) {
        formData.allowed_email_domains.push(domain);
    }
    newEmailDomain.value = '';
};

const removeEmailDomain = (index) => {
    formData.allowed_email_domains.splice(index, 1);
};

const handleEmailDomainKeydown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addEmailDomain();
    }
};

// Helper to get localized value for display (header title)
const getLocalizedName = computed(() => {
    if (typeof formData.name === 'object' && formData.name !== null) {
        return formData.name.sk || formData.name.en || '';
    }
    return formData.name || '';
});

// Tags management
const newTag = ref('');

const addTag = () => {
    const tag = newTag.value.trim();
    if (tag && !formData.tags.includes(tag)) {
        formData.tags.push(tag);
    }
    newTag.value = '';
};

const removeTag = (index) => {
    formData.tags.splice(index, 1);
};

const handleTagKeydown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addTag();
    }
};

const submit = () => {
    formData.transform(data => ({ ...data, _method: 'PUT' })).post(`/admin/forms/${props.form.id}`);
};

// Export single form
const exportingForm = ref(false);
const exportForm = async () => {
    exportingForm.value = true;
    try {
        const response = await fetch(`/api/v1/admin/export/forms/${props.form.id}?include_workflow=true`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Export zlyhal');

        const data = await response.json();

        // Download as JSON file
        const formName = typeof props.form.name === 'object' ? (props.form.name.sk || props.form.name.en || 'form') : props.form.name;
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `form_${props.form.slug}_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (error) {
        alert(error.message || 'Nastala chyba pri exporte');
    } finally {
        exportingForm.value = false;
    }
};
</script>

<template>
    <Head :title="`${t('forms.edit')}: ${getLocalizedName}`" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 truncate">{{ t('forms.edit') }}</h1>
                <p class="text-gray-500 dark:text-gray-400 truncate">/forms/{{ form.slug }}</p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button
                    @click="exportForm"
                    :disabled="exportingForm"
                    class="btn btn-secondary flex items-center gap-2"
                    title="Exportovať formulár"
                >
                    <svg v-if="exportingForm" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span class="hidden md:inline">Export</span>
                </button>
                <button
                    @click="toggleVersionHistory"
                    class="btn btn-secondary flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="hidden md:inline">História</span>
                    <span v-if="form.current_version" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 text-xs rounded">
                        v{{ form.current_version }}
                    </span>
                </button>
                <Link :href="`/admin/forms/${form.id}/submissions`" class="btn btn-primary">
                    {{ t('forms.submissions') }}
                </Link>
                <Link :href="`/forms/${form.slug}`" target="_blank" class="btn btn-secondary">
                    {{ t('common.view') || 'Zobraziť' }}
                </Link>
            </div>
        </div>

        <!-- Sharing Section -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                {{ t('forms.sharing') }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="form-label">{{ t('forms.publicLink') }}</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input
                            type="text"
                            :value="formLink"
                            readonly
                            class="form-input flex-1 min-w-0 bg-gray-50 dark:bg-gray-700 font-mono text-sm"
                            @click="$event.target.select()"
                        />
                        <button
                            type="button"
                            @click="copyFormLink"
                            class="btn btn-secondary flex items-center gap-2"
                        >
                            <svg v-if="linkCopied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            {{ linkCopied ? t('forms.linkCopied') : t('forms.copyLink') }}
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        {{ t('forms.sharingDescription') }}
                    </p>
                </div>

                <!-- Status indicators -->
                <div class="flex flex-wrap gap-3">
                    <div v-if="!formData.is_active" class="flex items-center gap-2 text-amber-600 dark:text-amber-400 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ t('forms.sharingInactiveWarning') }}
                    </div>
                    <div v-else-if="formData.is_public" class="flex items-center gap-2 text-green-600 dark:text-green-400 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ t('forms.sharingPublicNote') }}
                    </div>
                    <div v-else class="flex items-center gap-2 text-blue-600 dark:text-blue-400 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        {{ t('forms.sharingPrivateNote') }}
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">{{ t('forms.basicInfo') || 'Základné informácie' }}</h2>

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

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">
                            {{ t('forms.name') }} *
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

                    <div class="flex flex-wrap items-center gap-6 pt-6">
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_public" type="checkbox" class="w-4 h-4 text-blue-600" />
                            {{ t('forms.isPublic') }}
                        </label>
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_active" type="checkbox" class="w-4 h-4 text-blue-600" />
                            {{ t('forms.isActive') }}
                        </label>
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_featured" type="checkbox" class="w-4 h-4 text-brand-gold" />
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Rýchla akcia
                            </span>
                        </label>
                    </div>
                    <!-- Featured order (only shown when is_featured is checked) -->
                    <div v-if="formData.is_featured" class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-amber-800 dark:text-amber-200">Poradie zobrazenia:</label>
                            <input
                                v-model.number="formData.featured_order"
                                type="number"
                                min="0"
                                max="100"
                                class="w-20 px-2 py-1 text-sm border border-amber-300 dark:border-amber-600 rounded bg-white dark:bg-gray-800"
                            />
                            <span class="text-xs text-amber-600 dark:text-amber-400">(nižšie = zobrazí sa skôr)</span>
                        </div>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                            Formulár sa zobrazí v sekcii "Rýchle akcie" na úvodnej stránke.
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">
                        {{ t('forms.description') }}
                        <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
                    </label>
                    <textarea v-model="formData.description[currentLang]" class="form-input" rows="2" />
                </div>
            </div>

            <!-- Category and Tags -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Kategória a tagy</h2>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Category -->
                    <div>
                        <label class="form-label">Kategória</label>
                        <select v-model="formData.category_id" class="form-input">
                            <option :value="null">-- Bez kategórie --</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ getLocalized(category.name) }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Kategória pomáha používateľom filtrovať formuláre
                        </p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="form-label">Tagy</label>
                        <div class="flex gap-2">
                            <input
                                v-model="newTag"
                                type="text"
                                class="form-input flex-1"
                                placeholder="Napíšte tag a stlačte Enter"
                                @keydown="handleTagKeydown"
                            />
                            <button
                                type="button"
                                @click="addTag"
                                class="btn btn-secondary px-4"
                            >
                                Pridať
                            </button>
                        </div>
                        <div v-if="formData.tags.length" class="flex flex-wrap gap-2 mt-3">
                            <span
                                v-for="(tag, index) in formData.tags"
                                :key="index"
                                class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                            >
                                {{ tag }}
                                <button
                                    type="button"
                                    @click="removeTag(index)"
                                    class="ml-2 text-blue-600 hover:text-blue-800"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Tagy pomáhajú pri vyhľadávaní formulára
                        </p>
                    </div>
                </div>

                <!-- Keywords -->
                <div class="mt-4">
                    <label class="form-label">Kľúčové slová pre vyhľadávanie</label>
                    <textarea
                        v-model="formData.keywords"
                        class="form-input"
                        rows="2"
                        placeholder="Napíšte kľúčové slová oddelené čiarkou (napr.: prihláška, zápis, škola, štúdium)"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Tieto slová pomôžu používateľom nájsť formulár pri vyhľadávaní
                    </p>
                </div>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Polia formulára</h2>
                <FormBuilder v-model="formData.schema.fields" />
            </div>

            <!-- Duplicate settings -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Nastavenia duplicity</h2>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            v-model="formData.prevent_duplicates"
                            type="checkbox"
                            class="w-5 h-5 text-blue-600 rounded mt-0.5"
                        />
                        <div>
                            <span class="font-medium">Zakázať duplicitné odpovede</span>
                            <p class="text-sm text-gray-500">
                                Ak je povolené, prihlásený používateľ môže tento formulár vyplniť len raz.
                                Pri pokuse o opätovné vyplnenie sa zobrazí správa, že formulár už bol vyplnený.
                            </p>
                        </div>
                    </label>

                    <div v-if="formData.prevent_duplicates" class="ml-8">
                        <label class="form-label">
                            {{ t('forms.duplicateMessage') }}
                            <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
                        </label>
                        <textarea
                            v-model="formData.duplicate_message[currentLang]"
                            class="form-input"
                            rows="2"
                            :placeholder="currentLang === 'sk' ? 'Napríklad: Pre ďalšie informácie kontaktujte podporu na email@example.com' : 'E.g.: For more information, please contact support at email@example.com'"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            {{ t('forms.duplicateMessageHelp') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Email domain restriction -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Viditeľnosť podľa emailovej domény
                </h2>

                <div class="space-y-4">
                    <!-- Mode selection -->
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="formData.domain_restriction_mode"
                                type="radio"
                                value="none"
                                class="w-4 h-4 text-green-600 mt-0.5"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Povolené pre všetkých</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Formulár bude dostupný pre všetkých používateľov bez obmedzenia.
                                </p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="formData.domain_restriction_mode"
                                type="radio"
                                value="allow"
                                class="w-4 h-4 text-green-600 mt-0.5"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Povoliť len pre domény</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Formulár uvidia <strong>len</strong> používatelia s emailom z uvedených domén.
                                </p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="formData.domain_restriction_mode"
                                type="radio"
                                value="block"
                                class="w-4 h-4 text-red-600 mt-0.5"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Zakázať pre domény</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Formulár uvidia všetci <strong>okrem</strong> používateľov s emailom z uvedených domén.
                                </p>
                            </div>
                        </label>
                    </div>

                    <!-- Domain list (shown for allow/block modes) -->
                    <div v-if="formData.domain_restriction_mode !== 'none'" class="ml-7 space-y-4 pt-2 border-t dark:border-gray-700">
                        <div>
                            <label class="form-label">
                                {{ formData.domain_restriction_mode === 'allow' ? 'Povolené domény' : 'Zakázané domény' }}
                            </label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">@</span>
                                    <input
                                        v-model="newEmailDomain"
                                        type="text"
                                        class="form-input pl-8"
                                        placeholder="napr. example.com"
                                        @keydown="handleEmailDomainKeydown"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="addEmailDomain"
                                    class="btn btn-secondary px-4"
                                >
                                    Pridať
                                </button>
                            </div>
                        </div>

                        <div v-if="formData.allowed_email_domains.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="(domain, index) in formData.allowed_email_domains"
                                :key="index"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm"
                                :class="formData.domain_restriction_mode === 'allow'
                                    ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300'
                                    : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'"
                            >
                                @{{ domain }}
                                <button
                                    type="button"
                                    @click="removeEmailDomain(index)"
                                    class="ml-2"
                                    :class="formData.domain_restriction_mode === 'allow'
                                        ? 'text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200'
                                        : 'text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>

                        <div v-if="formData.allowed_email_domains.length === 0" class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                Pridajte aspoň jednu doménu, inak sa režim automaticky zmení na "Povolené pre všetkých".
                            </p>
                        </div>

                        <div v-else class="p-3 rounded-lg" :class="formData.domain_restriction_mode === 'allow'
                            ? 'bg-green-50 dark:bg-green-900/20'
                            : 'bg-red-50 dark:bg-red-900/20'"
                        >
                            <p class="text-sm" :class="formData.domain_restriction_mode === 'allow'
                                ? 'text-green-700 dark:text-green-300'
                                : 'text-red-700 dark:text-red-300'"
                            >
                                <template v-if="formData.domain_restriction_mode === 'allow'">
                                    Formulár uvidia <strong>len</strong> používatelia s emailom:
                                </template>
                                <template v-else>
                                    Formulár <strong>neuvidia</strong> používatelia s emailom:
                                </template>
                                <strong>{{ formData.allowed_email_domains.map(d => '*@' + d).join(', ') }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workflow assignment -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Workflow</h2>

                <div class="space-y-4">
                    <div>
                        <label class="form-label">Priradiť workflow</label>
                        <select v-model="formData.workflow_id" class="form-input">
                            <option :value="null">-- Bez workflow --</option>
                            <option v-for="workflow in workflows" :key="workflow.id" :value="workflow.id">
                                {{ workflow.name }}
                                <template v-if="!workflow.is_active"> (neaktívny)</template>
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Vybraný workflow sa spustí pri odoslaní formulára.
                        </p>
                    </div>

                    <!-- Show selected workflow info -->
                    <div v-if="formData.workflow_id" class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-blue-900 dark:text-blue-100">
                                    {{ workflows.find(w => w.id === formData.workflow_id)?.name }}
                                </p>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    Spúšťa sa pri: {{ workflows.find(w => w.id === formData.workflow_id)?.trigger_on }}
                                </p>
                            </div>
                            <Link
                                :href="`/admin/workflows/${formData.workflow_id}/edit`"
                                class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
                            >
                                Upraviť workflow
                            </Link>
                        </div>
                    </div>

                    <div class="pt-2 border-t">
                        <Link href="/admin/workflows/create" class="text-blue-600 hover:underline text-sm">
                            + Vytvoriť nový workflow
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Email confirmation -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Potvrdzujuci email</h2>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            v-model="formData.send_confirmation_email"
                            type="checkbox"
                            class="w-5 h-5 text-blue-600 rounded mt-0.5"
                        />
                        <div>
                            <span class="font-medium">Odoslat potvrdzujuci email po vyplneni</span>
                            <p class="text-sm text-gray-500">
                                Po odoslani formulara bude pouzivatelovi odoslany automaticky email s potvrdenim a rekapitulaciou odpovedi.
                            </p>
                        </div>
                    </label>

                    <div v-if="formData.send_confirmation_email" class="ml-8 space-y-4">
                        <div>
                            <label class="form-label">Emailova sablona</label>
                            <select v-model="formData.email_template_id" class="form-input">
                                <option :value="null">-- Pouzit predvolenu sablonu --</option>
                                <option
                                    v-for="template in emailTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                    <template v-if="template.is_default"> (predvolena)</template>
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                Vyberte sablonu pre potvrdzujuci email alebo nechajte predvolenu
                            </p>
                        </div>

                        <div v-if="!emailTemplates || emailTemplates.length === 0" class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                Zatial nemate ziadne emailove sablony.
                                <Link href="/admin/email-templates/create" class="underline hover:no-underline">
                                    Vytvorte prvu sablonu
                                </Link>
                            </p>
                        </div>

                        <div class="pt-2 border-t">
                            <Link href="/admin/email-templates" class="text-blue-600 hover:underline text-sm">
                                Spravovat emailove sablony
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status notification emails -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Email notifikacie pri zmene stavu</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Vyberte emailove sablony, ktore sa pouziju pri schvaleni alebo zamietnutí odpovede.
                    Ak nie je sablona vybrana, pouzije sa predvoleny systemovy email.
                </p>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Approval email template -->
                    <div>
                        <label class="form-label flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Email pri schvaleni
                        </label>
                        <select v-model="formData.approval_email_template_id" class="form-input">
                            <option :value="null">-- Predvoleny systemovy email --</option>
                            <option
                                v-for="template in emailTemplates"
                                :key="template.id"
                                :value="template.id"
                            >
                                {{ template.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Tento email sa odosle pouzivatelovi ked schvalite jeho odpoved
                        </p>
                    </div>

                    <!-- Rejection email template -->
                    <div>
                        <label class="form-label flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Email pri zamietnutí
                        </label>
                        <select v-model="formData.rejection_email_template_id" class="form-input">
                            <option :value="null">-- Predvoleny systemovy email --</option>
                            <option
                                v-for="template in emailTemplates"
                                :key="template.id"
                                :value="template.id"
                            >
                                {{ template.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Tento email sa odosle pouzivatelovi ked zamietnete jeho odpoved
                        </p>
                    </div>
                </div>

                <div v-if="!emailTemplates || emailTemplates.length === 0" class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                        Zatial nemate ziadne emailove sablony.
                        <Link href="/admin/email-templates/create" class="underline hover:no-underline">
                            Vytvorte prvu sablonu
                        </Link>
                    </p>
                </div>

                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dostupne premenne v sablonach:</h4>
                    <div class="flex flex-wrap gap-2" v-pre>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{form_name}}</code>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{submission_id}}</code>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{submission_date}}</code>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{user_name}}</code>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{user_email}}</code>
                        <code class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-xs rounded">{{admin_response}}</code>
                    </div>
                </div>

                <div class="pt-4 border-t mt-4">
                    <Link href="/admin/email-templates" class="text-blue-600 hover:underline text-sm">
                        Spravovat emailove sablony
                    </Link>
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link href="/admin/forms" class="btn btn-secondary">Späť</Link>
                <button type="submit" :disabled="formData.processing" class="btn btn-primary">
                    {{ formData.processing ? 'Ukladám...' : 'Uložiť zmeny' }}
                </button>
            </div>
        </form>

        <!-- Version History Slide-over -->
        <div
            v-if="showVersionHistory"
            class="fixed inset-0 z-50 overflow-hidden"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black bg-opacity-50" @click="showVersionHistory = false"></div>

            <!-- Panel -->
            <div class="absolute inset-y-0 right-0 max-w-md w-full bg-white dark:bg-gray-800 shadow-xl flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">História verzií</h2>
                    <button
                        @click="showVersionHistory = false"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="loadingVersions" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Načítavam...
                    </div>

                    <div v-else-if="versions.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Žiadne verzie
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="version in versions"
                            :key="version.id"
                            class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                            :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': version.version_number === form.current_version }"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                                            Verzia {{ version.version_number }}
                                        </span>
                                        <span
                                            v-if="version.version_number === form.current_version"
                                            class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-xs rounded"
                                        >
                                            Aktuálna
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        {{ formatVersionDate(version.created_at) }}
                                    </p>
                                    <p v-if="version.creator" class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ version.creator.name || version.creator.email }}
                                    </p>
                                    <p v-if="version.change_note" class="text-sm text-gray-700 dark:text-gray-300 mt-2 italic">
                                        "{{ version.change_note }}"
                                    </p>
                                </div>

                                <div class="flex gap-1">
                                    <button
                                        v-if="version.version_number !== form.current_version"
                                        @click="restoreVersion(version)"
                                        :disabled="restoringVersion"
                                        class="px-3 py-1.5 text-sm bg-blue-500 text-white hover:bg-blue-600 rounded font-medium disabled:opacity-50"
                                    >
                                        Obnoviť
                                    </button>
                                </div>
                            </div>

                            <!-- Schema preview -->
                            <div v-if="version.schema?.fields" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    Polia: {{ version.schema.fields.length }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="field in version.schema.fields.slice(0, 5)"
                                        :key="field.id || field.name"
                                        class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs rounded"
                                    >
                                        {{ field.label || field.name }}
                                    </span>
                                    <span
                                        v-if="version.schema.fields.length > 5"
                                        class="px-2 py-0.5 text-gray-500 dark:text-gray-400 text-xs"
                                    >
                                        +{{ version.schema.fields.length - 5 }} ďalších
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
