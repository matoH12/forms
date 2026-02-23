<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExportImportModal from '@/Components/ExportImportModal.vue';
import { ref } from 'vue';

defineProps({
    workflows: Object,
    auth: Object,
});

const showExportImport = ref(false);

const handleImported = () => {
    router.reload({ only: ['workflows'] });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK');
};

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    if (typeof form.name === 'object' && form.name !== null) {
        return form.name.sk || form.name.en || form.slug || '';
    }
    return form.name || form.slug || '';
};

const deleteWorkflow = (workflow) => {
    if (confirm(`Naozaj chcete zmazať workflow "${workflow.name}"?`)) {
        router.post(`/admin/workflows/${workflow.id}`, { _method: 'delete' });
    }
};
</script>

<template>
    <Head title="Workflow" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Workflow</h1>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="showExportImport = true" class="btn btn-secondary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span class="hidden sm:inline">Export / Import</span>
                    <span class="sm:hidden">Export</span>
                </button>
                <Link href="/admin/workflows/create" class="btn btn-primary">
                    + Nový workflow
                </Link>
            </div>
        </div>

        <div class="card">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-600 text-left">
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Názov</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Formulár</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Spúšťač</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Stav</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Vykonaní</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Vytvorené</th>
                        <th class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="workflow in workflows.data" :key="workflow.id" class="border-b border-gray-200 dark:border-gray-600 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="py-3 px-4">
                            <Link :href="`/admin/workflows/${workflow.id}/edit`" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                {{ workflow.name }}
                            </Link>
                        </td>
                        <td class="py-3 px-4 text-gray-700 dark:text-gray-300">
                            <span v-if="workflow.form">{{ getFormName(workflow.form) }}</span>
                            <span v-else class="text-gray-400 dark:text-gray-500 italic">Globálny</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                {{ workflow.trigger_on }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="workflow.is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'">
                                {{ workflow.is_active ? 'Aktívny' : 'Neaktívny' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-700 dark:text-gray-300">{{ workflow.executions_count }}</td>
                        <td class="py-3 px-4 text-gray-500 dark:text-gray-400">{{ formatDate(workflow.created_at) }}</td>
                        <td class="py-3 px-4 text-right space-x-2">
                            <Link :href="`/admin/workflows/${workflow.id}/executions`" class="text-gray-600 dark:text-gray-400 hover:underline hover:text-gray-900 dark:hover:text-gray-200">
                                História
                            </Link>
                            <Link :href="`/admin/workflows/${workflow.id}/edit`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                Upraviť
                            </Link>
                            <button @click="deleteWorkflow(workflow)" class="text-red-600 dark:text-red-400 hover:underline">
                                Zmazať
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!workflows.data.length" class="text-center py-12">
                <p class="text-gray-500 dark:text-gray-400 mb-4">Zatiaľ nemáte žiadne workflow.</p>
                <Link href="/admin/workflows/create" class="btn btn-primary">
                    Vytvoriť prvý workflow
                </Link>
            </div>
        </div>

        <!-- Export/Import Modal -->
        <ExportImportModal
            :show="showExportImport"
            type="workflows"
            export-url="/api/v1/admin/export/workflows"
            import-url="/api/v1/admin/import/workflows"
            @close="showExportImport = false"
            @imported="handleImported"
        />
    </AdminLayout>
</template>
