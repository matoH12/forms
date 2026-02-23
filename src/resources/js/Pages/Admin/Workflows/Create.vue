<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import WorkflowEditor from '@/Components/WorkflowEditor/WorkflowEditor.vue';

const page = usePage();

const props = defineProps({
    forms: Array,
    emailTemplates: Array,
    auth: Object,
});

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    if (typeof form.name === 'object' && form.name !== null) {
        return form.name.sk || form.name.en || form.slug || '';
    }
    return form.name || form.slug || '';
};

const initialNodes = [
    { id: 'start', type: 'start', position: { x: 250, y: 50 }, data: { label: 'Start' } },
    { id: 'end', type: 'end', position: { x: 250, y: 400 }, data: { label: 'Koniec' } },
];

const initialEdges = [];

const formData = useForm({
    name: '',
    description: '',
    form_id: new URLSearchParams(window.location.search).get('form_id') || null,
    trigger_on: 'submission',
    is_active: true,
    nodes: initialNodes,
    edges: initialEdges,
});

const selectedFormFields = computed(() => {
    const form = props.forms.find(f => f.id === parseInt(formData.form_id));
    return form?.schema?.fields || [];
});

const submit = () => {
    formData.post('/admin/workflows');
};

const updateFlow = ({ nodes, edges }) => {
    formData.nodes = nodes;
    formData.edges = edges;
};
</script>

<template>
    <Head title="Novy workflow" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Novy workflow</h1>
        </div>

        <!-- Error display -->
        <div v-if="Object.keys(formData.errors).length > 0" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <h3 class="text-red-800 dark:text-red-300 font-medium mb-2">Chyby pri validácii:</h3>
            <ul class="list-disc list-inside text-red-700 dark:text-red-400 text-sm">
                <li v-for="(error, field) in formData.errors" :key="field">{{ error }}</li>
            </ul>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Zakladne informacie</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Nazov workflow *</label>
                        <input v-model="formData.name" type="text" class="form-input" required />
                    </div>

                    <div>
                        <label class="form-label">Formular (volitelne)</label>
                        <select v-model="formData.form_id" class="form-input">
                            <option :value="null">-- Globalny workflow --</option>
                            <option v-for="form in forms" :key="form.id" :value="form.id">
                                {{ getFormName(form) }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Vyberte formular ak chcete pouzit premenne z jeho poli. Workflow priradite k formularu v nastaveniach formulara.
                        </p>
                    </div>

                    <div>
                        <label class="form-label">Spustac</label>
                        <select v-model="formData.trigger_on" class="form-input">
                            <option value="submission">Pri novej poziadavke</option>
                            <option value="approval">Pri schvaleni poziadavky</option>
                            <option value="manual">Manualne</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <span v-if="formData.trigger_on === 'submission'">Workflow sa spusti automaticky po odoslani formulara</span>
                            <span v-else-if="formData.trigger_on === 'approval'">Workflow sa spusti ked admin schvali poziadavku</span>
                            <span v-else>Workflow sa spusti manualne z administracie</span>
                        </p>
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center gap-2">
                            <input v-model="formData.is_active" type="checkbox" class="w-4 h-4 text-blue-600" />
                            Aktivny workflow
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label">Popis</label>
                    <textarea v-model="formData.description" class="form-input" rows="2" />
                </div>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Workflow editor</h2>
                <p v-if="!formData.form_id" class="text-yellow-600 text-sm mb-4">
                    Vyberte formular pre zobrazenie dostupnych poli v premennych.
                </p>
                <div class="h-[500px] border rounded-lg overflow-hidden">
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
                <a href="/admin/workflows" class="btn btn-secondary">Zrusit</a>
                <button type="submit" :disabled="formData.processing" class="btn btn-primary">
                    {{ formData.processing ? 'Ukladam...' : 'Vytvorit workflow' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
