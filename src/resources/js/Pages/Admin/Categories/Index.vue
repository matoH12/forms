<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExportImportModal from '@/Components/ExportImportModal.vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocalized } from '@/composables/useLocalized';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const props = defineProps({
    categories: Array,
    auth: Object,
});

const draggingIndex = ref(null);
const dragOverIndex = ref(null);
const showExportImport = ref(false);

const handleImported = () => {
    router.reload({ only: ['categories'] });
};

// Helper to get localized category name
const getCategoryName = (category) => {
    return getLocalized(category.name);
};

// Helper to get localized category description
const getCategoryDescription = (category) => {
    return getLocalized(category.description);
};

const deleteCategory = (category) => {
    const name = getCategoryName(category);
    if (category.forms_count > 0) {
        alert(t('categories.cannotDelete', { name, count: category.forms_count }));
        return;
    }

    if (confirm(t('categories.confirmDelete', { name }))) {
        router.post(`/admin/categories/${category.id}`, { _method: 'delete' });
    }
};

// Drag and drop reordering
const handleDragStart = (index) => {
    draggingIndex.value = index;
};

const handleDragOver = (e, index) => {
    e.preventDefault();
    dragOverIndex.value = index;
};

const handleDragEnd = () => {
    if (draggingIndex.value !== null && dragOverIndex.value !== null && draggingIndex.value !== dragOverIndex.value) {
        const newCategories = [...props.categories];
        const [movedItem] = newCategories.splice(draggingIndex.value, 1);
        newCategories.splice(dragOverIndex.value, 0, movedItem);

        // Update order values
        const updates = newCategories.map((cat, index) => ({
            id: cat.id,
            order: index,
        }));

        // Send update to server
        fetch('/admin/categories/update-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ categories: updates }),
        }).then(() => {
            router.reload({ only: ['categories'] });
        });
    }

    draggingIndex.value = null;
    dragOverIndex.value = null;
};
</script>

<template>
    <Head :title="t('categories.title')" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ t('categories.title') }}</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ t('categories.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                <button @click="showExportImport = true" class="btn btn-secondary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span class="hidden sm:inline">Export / Import</span>
                    <span class="sm:hidden">Export</span>
                </button>
                <Link href="/admin/categories/create" class="btn btn-primary">
                    + {{ t('categories.createNew') }}
                </Link>
            </div>
        </div>

        <div class="card">
            <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Poradie kategorii mozete zmenit presuvanim (drag & drop)
            </div>

            <div class="space-y-2">
                <div
                    v-for="(category, index) in categories"
                    :key="category.id"
                    :draggable="true"
                    @dragstart="handleDragStart(index)"
                    @dragover="handleDragOver($event, index)"
                    @dragend="handleDragEnd"
                    class="flex items-center justify-between p-4 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:shadow-md dark:hover:shadow-gray-900/50 transition-all cursor-move"
                    :class="{
                        'border-blue-500 bg-blue-50 dark:bg-blue-900/30': dragOverIndex === index,
                        'opacity-50': draggingIndex === index,
                    }"
                >
                    <div class="flex items-center space-x-4">
                        <!-- Drag handle -->
                        <div class="text-gray-400 dark:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>

                        <!-- Color indicator -->
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center"
                            :style="{ backgroundColor: category.color }"
                        >
                            <svg v-if="category.icon" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="category.icon" />
                            </svg>
                            <span v-else class="text-white font-bold text-sm">{{ getCategoryName(category).charAt(0) }}</span>
                        </div>

                        <!-- Category info -->
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ getCategoryName(category) }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ getCategoryDescription(category) || t('categories.noDescription') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-6">
                        <!-- Stats -->
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ category.forms_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">formularov</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ category.active_forms_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">aktivnych</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2">
                            <Link
                                :href="`/admin/categories/${category.id}/edit`"
                                class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                title="Upravit"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </Link>
                            <button
                                @click="deleteCategory(category)"
                                class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                :class="{ 'opacity-50 cursor-not-allowed': category.forms_count > 0 }"
                                :disabled="category.forms_count > 0"
                                title="Zmazat"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!categories.length" class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mb-4">{{ t('categories.noCategories') }}</p>
                <Link href="/admin/categories/create" class="btn btn-primary">
                    {{ t('categories.createFirst') }}
                </Link>
            </div>
        </div>

        <!-- Export/Import Modal -->
        <ExportImportModal
            :show="showExportImport"
            type="categories"
            export-url="/api/v1/admin/export/categories"
            import-url="/api/v1/admin/import/categories"
            @close="showExportImport = false"
            @imported="handleImported"
        />
    </AdminLayout>
</template>
