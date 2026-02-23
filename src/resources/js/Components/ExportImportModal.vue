<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    type: {
        type: String,
        required: true,
        validator: (v) => ['categories', 'forms', 'workflows'].includes(v)
    },
    exportUrl: String,
    importUrl: String,
    title: String,
});

const emit = defineEmits(['close', 'imported']);

const activeTab = ref('export');
const importing = ref(false);
const exporting = ref(false);
const importFile = ref(null);
const importMode = ref('merge');
const message = ref(null);
const messageType = ref('success');
const importErrors = ref([]);
const copiedErrors = ref(false);

const copyErrors = async () => {
    const errorText = importErrors.value.join('\n');
    try {
        await navigator.clipboard.writeText(errorText);
        copiedErrors.value = true;
        setTimeout(() => {
            copiedErrors.value = false;
        }, 2000);
    } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = errorText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copiedErrors.value = true;
        setTimeout(() => {
            copiedErrors.value = false;
        }, 2000);
    }
};

const typeLabels = {
    categories: 'kategórie',
    forms: 'formuláre',
    workflows: 'workflow',
};

const typeLabel = computed(() => typeLabels[props.type] || props.type);

const exportData = async () => {
    exporting.value = true;
    message.value = null;

    try {
        const response = await fetch(props.exportUrl, {
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
        a.download = `${props.type}_export_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        message.value = 'Export bol uspesne stiahnuty';
        messageType.value = 'success';
    } catch (error) {
        message.value = error.message || 'Nastala chyba pri exporte';
        messageType.value = 'error';
    } finally {
        exporting.value = false;
    }
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        importFile.value = file;
    }
};

const importData = async () => {
    if (!importFile.value) {
        message.value = 'Vyberte subor na import';
        messageType.value = 'error';
        return;
    }

    importing.value = true;
    message.value = null;
    importErrors.value = [];

    try {
        const fileContent = await importFile.value.text();
        let jsonData;

        try {
            jsonData = JSON.parse(fileContent);
        } catch {
            throw new Error('Neplatny JSON format');
        }

        // Prepare request body
        const requestBody = {
            mode: importMode.value,
        };

        // Handle different export formats
        if (jsonData.data) {
            requestBody.data = jsonData.data;
            if (jsonData.export_type) {
                requestBody.export_type = jsonData.export_type;
            }
        } else if (Array.isArray(jsonData)) {
            requestBody.data = jsonData;
        } else {
            requestBody.data = [jsonData];
        }

        const response = await fetch(props.importUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestBody),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || result.message || 'Import zlyhal');
        }

        let successMessage = 'Import dokončený. ';
        if (result.imported !== undefined) {
            successMessage += `Importovaných: ${result.imported}. `;
        }
        if (result.updated !== undefined) {
            successMessage += `Aktualizovaných: ${result.updated}. `;
        }
        if (result.workflows_imported !== undefined) {
            successMessage += `Workflow: ${result.workflows_imported}. `;
        }
        if (result.errors && result.errors.length > 0) {
            successMessage += `Chyby: ${result.errors.length}`;
            importErrors.value = result.errors;
        }

        message.value = successMessage;
        messageType.value = result.errors && result.errors.length > 0 ? 'warning' : 'success';

        // Reset file input
        importFile.value = null;
        const fileInput = document.getElementById('import-file');
        if (fileInput) fileInput.value = '';

        emit('imported', result);
    } catch (error) {
        message.value = error.message || 'Nastala chyba pri importe';
        messageType.value = 'error';
    } finally {
        importing.value = false;
    }
};

const close = () => {
    message.value = null;
    importFile.value = null;
    importErrors.value = [];
    copiedErrors.value = false;
    activeTab.value = 'export';
    emit('close');
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4">
            <!-- Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ title || `Export / Import ${typeLabel}` }}
                </h3>
                <button @click="close" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex -mb-px">
                    <button
                        @click="activeTab = 'export'"
                        class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'export'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                    >
                        Export
                    </button>
                    <button
                        @click="activeTab = 'import'"
                        class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'import'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                    >
                        Import
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Message -->
                <div
                    v-if="message"
                    class="mb-4 p-3 rounded-lg text-sm"
                    :class="{
                        'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': messageType === 'success',
                        'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': messageType === 'error',
                        'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': messageType === 'warning',
                    }"
                >
                    {{ message }}
                </div>

                <!-- Import Errors Detail -->
                <div
                    v-if="importErrors.length > 0"
                    class="mb-4 p-3 rounded-lg text-sm bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-red-800 dark:text-red-300">
                            Detaily chýb:
                        </span>
                        <button
                            @click="copyErrors"
                            class="flex items-center gap-1 px-2 py-1 text-xs rounded bg-red-100 dark:bg-red-800/50 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition-colors"
                        >
                            <svg v-if="!copiedErrors" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ copiedErrors ? 'Skopírované!' : 'Kopírovať' }}
                        </button>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-red-700 dark:text-red-400 max-h-40 overflow-y-auto">
                        <li v-for="(error, index) in importErrors" :key="index">
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <!-- Export Tab -->
                <div v-if="activeTab === 'export'">
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Exportujte vsetky {{ typeLabel }} do JSON suboru. Tento subor mozete pouzit na zalohovanie alebo prenos do inej instancie.
                    </p>

                    <button
                        @click="exportData"
                        :disabled="exporting"
                        class="w-full btn btn-primary flex items-center justify-center"
                    >
                        <svg v-if="exporting" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ exporting ? 'Exportujem...' : 'Stiahnut export' }}
                    </button>
                </div>

                <!-- Import Tab -->
                <div v-if="activeTab === 'import'">
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Importujte {{ typeLabel }} z JSON suboru. Existujuce zaznamy s rovnakym slug/identifikatorom budu aktualizovane.
                    </p>

                    <!-- Import mode selection (only for categories) -->
                    <div v-if="type === 'categories'" class="mb-4">
                        <label class="form-label">Rezim importu</label>
                        <select v-model="importMode" class="form-input">
                            <option value="merge">Zlucit (aktualizovat existujuce)</option>
                            <option value="replace">Nahradit (zmazat vsetko a importovat)</option>
                        </select>
                    </div>

                    <!-- File input -->
                    <div class="mb-4">
                        <label class="form-label">Vyberte JSON subor</label>
                        <input
                            id="import-file"
                            type="file"
                            accept=".json,application/json"
                            @change="handleFileSelect"
                            class="block w-full text-sm text-gray-500 dark:text-gray-400
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                dark:file:bg-blue-900/30 dark:file:text-blue-400
                                hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50
                                cursor-pointer"
                        />
                        <p v-if="importFile" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Vybrany subor: {{ importFile.name }}
                        </p>
                    </div>

                    <button
                        @click="importData"
                        :disabled="importing || !importFile"
                        class="w-full btn btn-primary flex items-center justify-center"
                        :class="{ 'opacity-50 cursor-not-allowed': !importFile }"
                    >
                        <svg v-if="importing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {{ importing ? 'Importujem...' : 'Importovat' }}
                    </button>

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        <strong>Poznamka:</strong> Importovane {{ typeLabel }} budu vytvorene ako neaktivne a s pridanou znackou "(import)" v nazve.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                <button @click="close" class="btn btn-secondary">
                    Zavriet
                </button>
            </div>
        </div>
    </div>
</template>
