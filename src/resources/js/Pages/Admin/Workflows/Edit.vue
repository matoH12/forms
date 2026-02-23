<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import WorkflowEditor from '@/Components/WorkflowEditor/WorkflowEditor.vue';

const props = defineProps({
    workflow: Object,
    forms: Array,
    emailTemplates: Array,
    auth: Object,
});

// Version history
const showVersionHistory = ref(false);
const versions = ref([]);
const loadingVersions = ref(false);
const restoringVersion = ref(false);

const loadVersions = async () => {
    loadingVersions.value = true;
    try {
        const response = await fetch(`/admin/workflows/${props.workflow.id}/versions`, {
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

const restoreVersion = async (version) => {
    if (!confirm(`Naozaj chcete obnoviť workflow na verziu ${version.version_number}?`)) {
        return;
    }

    restoringVersion.value = true;
    try {
        const response = await fetch(`/admin/workflows/${props.workflow.id}/versions/${version.id}/restore`, {
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

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    if (typeof form.name === 'object' && form.name !== null) {
        return form.name.sk || form.name.en || form.slug || '';
    }
    return form.name || form.slug || '';
};

const formData = useForm({
    name: props.workflow.name,
    description: props.workflow.description || '',
    form_id: props.workflow.form_id,
    trigger_on: props.workflow.trigger_on,
    is_active: props.workflow.is_active,
    nodes: props.workflow.nodes || [],
    edges: props.workflow.edges || [],
});

const selectedFormFields = computed(() => {
    const form = props.forms.find(f => f.id === formData.form_id);
    return form?.schema?.fields || [];
});

const submit = () => {
    formData.transform(data => ({ ...data, _method: 'PUT' })).post(`/admin/workflows/${props.workflow.id}`);
};

const updateFlow = ({ nodes, edges }) => {
    formData.nodes = nodes;
    formData.edges = edges;
};

// Export single workflow
const exportingWorkflow = ref(false);
const exportWorkflow = async () => {
    exportingWorkflow.value = true;
    try {
        const response = await fetch(`/api/v1/admin/export/workflows/${props.workflow.id}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Export zlyhal');

        const data = await response.json();

        // Download as JSON file
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `workflow_${props.workflow.name.replace(/[^a-z0-9]/gi, '_')}_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (error) {
        alert(error.message || 'Nastala chyba pri exporte');
    } finally {
        exportingWorkflow.value = false;
    }
};
</script>

<template>
    <Head :title="`Upraviť: ${workflow.name}`" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 truncate">Upraviť workflow</h1>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button
                    @click="exportWorkflow"
                    :disabled="exportingWorkflow"
                    class="btn btn-secondary flex items-center gap-2"
                    title="Exportovať workflow"
                >
                    <svg v-if="exportingWorkflow" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
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
                    <span v-if="workflow.current_version" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 text-xs rounded">
                        v{{ workflow.current_version }}
                    </span>
                </button>
                <Link :href="`/admin/workflows/${workflow.id}/executions`" class="btn btn-secondary">
                    <span class="hidden md:inline">História vykonaní</span>
                    <span class="md:hidden">Vykonania</span>
                </Link>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Základné informácie</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Názov workflow *</label>
                        <input v-model="formData.name" type="text" class="form-input" required />
                    </div>

                    <div>
                        <label class="form-label">Formulár (voliteľné)</label>
                        <select v-model="formData.form_id" class="form-input">
                            <option :value="null">-- Globálny workflow --</option>
                            <option v-for="form in forms" :key="form.id" :value="form.id">
                                {{ getFormName(form) }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Vyberte formulár ak chcete použiť premenné z jeho polí.
                        </p>
                    </div>

                    <div>
                        <label class="form-label">Spúšťač</label>
                        <select v-model="formData.trigger_on" class="form-input">
                            <option value="submission">Pri novej požiadavke</option>
                            <option value="approval">Pri schválení požiadavky</option>
                            <option value="manual">Manuálne</option>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <span v-if="formData.trigger_on === 'submission'">Workflow sa spustí automaticky po odoslaní formulára</span>
                            <span v-else-if="formData.trigger_on === 'approval'">Workflow sa spustí keď admin schváli požiadavku</span>
                            <span v-else>Workflow sa spustí manuálne z administrácie</span>
                        </p>
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <input v-model="formData.is_active" type="checkbox" class="w-4 h-4 text-blue-600" />
                            Aktívny workflow
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Popis</label>
                    <textarea v-model="formData.description" class="form-input" rows="2" />
                </div>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Workflow editor</h2>
                <div class="h-[500px] border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <WorkflowEditor
                        :initial-nodes="formData.nodes"
                        :initial-edges="formData.edges"
                        :form-fields="selectedFormFields"
                        :email-templates="emailTemplates"
                        @update="updateFlow"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <Link href="/admin/workflows" class="btn btn-secondary">Späť</Link>
                <button type="button" @click="submit" :disabled="formData.processing" class="btn btn-primary">
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
                            :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': version.version_number === workflow.current_version }"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                                            Verzia {{ version.version_number }}
                                        </span>
                                        <span
                                            v-if="version.version_number === workflow.current_version"
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
                                        v-if="version.version_number !== workflow.current_version"
                                        @click="restoreVersion(version)"
                                        :disabled="restoringVersion"
                                        class="px-3 py-1.5 text-sm bg-blue-500 text-white hover:bg-blue-600 rounded font-medium disabled:opacity-50"
                                    >
                                        Obnoviť
                                    </button>
                                </div>
                            </div>

                            <!-- Nodes preview -->
                            <div v-if="version.nodes?.length" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    Uzly: {{ version.nodes.length }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="node in version.nodes.filter(n => !['start', 'end'].includes(n.type)).slice(0, 5)"
                                        :key="node.id"
                                        class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs rounded"
                                    >
                                        {{ node.data?.label || node.type }}
                                    </span>
                                    <span
                                        v-if="version.nodes.filter(n => !['start', 'end'].includes(n.type)).length > 5"
                                        class="px-2 py-0.5 text-gray-500 dark:text-gray-400 text-xs"
                                    >
                                        +{{ version.nodes.filter(n => !['start', 'end'].includes(n.type)).length - 5 }} ďalších
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
