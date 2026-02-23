<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExportImportModal from '@/Components/ExportImportModal.vue';
import { ref } from 'vue';
import { useLocalized } from '@/composables/useLocalized';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const decodePaginationLabel = (label) => {
    const txt = document.createElement('textarea');
    txt.innerHTML = label;
    return txt.value;
};

// Copy link notification
const copiedFormId = ref(null);

const copyFormLink = async (form) => {
    const url = `${window.location.origin}/forms/${form.slug}`;
    try {
        await navigator.clipboard.writeText(url);
        copiedFormId.value = form.id;
        setTimeout(() => {
            copiedFormId.value = null;
        }, 2000);
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        copiedFormId.value = form.id;
        setTimeout(() => {
            copiedFormId.value = null;
        }, 2000);
    }
};

const props = defineProps({
    forms: Object,
    categories: Array,
    filters: Object,
    auth: Object,
});

const selectedCategory = ref(props.filters?.category || '');
const showExportImport = ref(false);

const handleImported = () => {
    router.reload({ only: ['forms'] });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK');
};

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (typeof form.name === 'object' && form.name !== null) {
        return form.name.sk || form.name.en || form.slug;
    }
    return form.name || form.slug;
};

const deleteForm = (form) => {
    if (confirm(`Naozaj chcete zmazať formulár "${getFormName(form)}"?`)) {
        router.post(`/admin/forms/${form.id}`, { _method: 'delete' });
    }
};

const filterByCategory = () => {
    const params = {};
    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }
    router.get('/admin/forms', params, { preserveState: true });
};

const clearFilters = () => {
    selectedCategory.value = '';
    router.get('/admin/forms', {}, { preserveState: true });
};
</script>

<template>
    <Head title="Formuláre" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Formuláre</h1>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="showExportImport = true" class="btn btn-secondary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span class="hidden sm:inline">Export / Import</span>
                    <span class="sm:hidden">Export</span>
                </button>
                <Link href="/admin/forms/create" class="btn btn-primary">
                    + Nový formulár
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <label class="form-label text-sm">Filtrovať podľa kategórie</label>
                    <select v-model="selectedCategory" @change="filterByCategory" class="form-input">
                        <option value="">Všetky kategórie</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ getLocalized(category.name) }}
                        </option>
                    </select>
                </div>
                <div class="pt-5">
                    <button
                        v-if="selectedCategory"
                        @click="clearFilters"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                    >
                        Zrušiť filter
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-600 text-left">
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Názov</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Kategória</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Stav</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Typ</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Odpovedí</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Vytvorené</th>
                        <th class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="form in forms.data" :key="form.id" class="border-b border-gray-200 dark:border-gray-600 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="py-3 px-4">
                            <Link :href="`/admin/forms/${form.id}/edit`" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                {{ getFormName(form) }}
                            </Link>
                            <p class="text-sm text-gray-500 dark:text-gray-400">/forms/{{ form.slug }}</p>
                        </td>
                        <td class="py-3 px-4">
                            <span
                                v-if="form.category"
                                class="px-2 py-1 text-xs rounded-full text-white"
                                :style="{ backgroundColor: form.category.color }"
                            >
                                {{ getLocalized(form.category.name) }}
                            </span>
                            <span v-else class="text-gray-400 dark:text-gray-500 text-sm">-</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="form.is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'">
                                {{ form.is_active ? 'Aktívny' : 'Neaktívny' }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="form.is_public ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300'">
                                {{ form.is_public ? 'Verejný' : 'Privátny' }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <Link
                                :href="`/admin/forms/${form.id}/submissions`"
                                class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                            >
                                {{ form.submissions_count }}
                            </Link>
                        </td>
                        <td class="py-3 px-4 text-gray-500 dark:text-gray-400">{{ formatDate(form.created_at) }}</td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    @click="copyFormLink(form)"
                                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-brand-gold dark:hover:text-brand-gold transition-colors"
                                    :title="t('forms.copyLink')"
                                >
                                    <svg v-if="copiedFormId === form.id" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                    <span class="text-xs">{{ copiedFormId === form.id ? t('forms.linkCopied') : t('forms.copyLink') }}</span>
                                </button>
                                <Link :href="`/forms/${form.slug}`" target="_blank" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                                    {{ t('common.view') }}
                                </Link>
                                <Link :href="`/admin/forms/${form.id}/edit`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ t('common.edit') }}
                                </Link>
                                <button @click="deleteForm(form)" class="text-red-600 dark:text-red-400 hover:underline">
                                    {{ t('common.delete') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!forms.data.length" class="text-center py-12">
                <p class="text-gray-500 dark:text-gray-400 mb-4">
                    {{ selectedCategory ? 'V tejto kategórii nie sú žiadne formuláre.' : 'Zatiaľ nemáte žiadne formuláre.' }}
                </p>
                <Link href="/admin/forms/create" class="btn btn-primary">
                    Vytvoriť prvý formulár
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="forms.links && forms.links.length > 3" class="mt-4 flex justify-center gap-1">
                <Link
                    v-for="link in forms.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="px-3 py-1 rounded text-sm"
                    :class="link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >{{ decodePaginationLabel(link.label) }}</Link>
            </div>
        </div>

        <!-- Export/Import Modal -->
        <ExportImportModal
            :show="showExportImport"
            type="forms"
            export-url="/api/v1/admin/export/forms"
            import-url="/api/v1/admin/import/forms"
            @close="showExportImport = false"
            @imported="handleImported"
        />
    </AdminLayout>
</template>
