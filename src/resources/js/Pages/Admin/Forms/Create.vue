<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormBuilder from '@/Components/FormBuilder/FormBuilder.vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocalized } from '@/composables/useLocalized';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const props = defineProps({
    auth: Object,
    categories: Array,
});

// Current editing language
const currentLang = ref('sk');
const languages = [
    { code: 'sk', name: 'Slovensky', flag: '🇸🇰' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
];

const formData = useForm({
    name: { sk: '', en: '' },
    description: { sk: '', en: '' },
    schema: { fields: [] },
    settings: {},
    is_public: true,
    is_active: true,
    prevent_duplicates: false,
    duplicate_message: { sk: '', en: '' },
    category_id: null,
    tags: [],
    keywords: '',
    allowed_email_domains: [],
    domain_restriction_mode: 'none',
});

// Email domain management
const newEmailDomain = ref('');

const addEmailDomain = () => {
    let domain = newEmailDomain.value.trim().toLowerCase();
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
    formData.post('/admin/forms');
};
</script>

<template>
    <Head :title="t('forms.createNew')" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ t('forms.createNew') }}</h1>
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

                    <div class="flex items-center gap-6 pt-6">
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_public" type="checkbox" class="w-4 h-4 text-blue-600" />
                            {{ t('forms.isPublic') }}
                        </label>
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_active" type="checkbox" class="w-4 h-4 text-blue-600" />
                            {{ t('forms.isActive') }}
                        </label>
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
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Kategória a tagy</h2>

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
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                                class="inline-flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full text-sm"
                            >
                                {{ tag }}
                                <button
                                    type="button"
                                    @click="removeTag(index)"
                                    class="ml-2 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Tieto slová pomôžu používateľom nájsť formulár pri vyhľadávaní
                    </p>
                </div>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Polia formulára</h2>
                <FormBuilder v-model="formData.schema.fields" />
                <p v-if="formData.errors['schema.fields']" class="text-red-500 dark:text-red-400 text-sm mt-2">
                    {{ formData.errors['schema.fields'] }}
                </p>
            </div>

            <!-- Duplicate settings -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Nastavenia duplicity</h2>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            v-model="formData.prevent_duplicates"
                            type="checkbox"
                            class="w-5 h-5 text-blue-600 rounded mt-0.5"
                        />
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">Zakázať duplicitné odpovede</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Ak je povolené, prihlásený používateľ môže tento formulár vyplniť len raz.
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
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
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

            <div class="flex justify-end gap-4">
                <Link href="/admin/forms" class="btn btn-secondary">Zrušiť</Link>
                <button type="submit" :disabled="formData.processing" class="btn btn-primary">
                    {{ formData.processing ? 'Ukladám...' : 'Vytvoriť formulár' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
