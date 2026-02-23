<script setup>
import { ref, computed, nextTick } from 'vue';
import draggable from 'vuedraggable';
import FieldEditor from './FieldEditor.vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const fields = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const availableTypes = [
    { type: 'text', label: 'Text', icon: 'M4 6h16M4 12h16M4 18h7' },
    { type: 'textarea', label: 'Textarea', icon: 'M4 6h16M4 10h16M4 14h16M4 18h12' },
    { type: 'email', label: 'Email', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { type: 'number', label: 'Číslo', icon: 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14' },
    { type: 'date', label: 'Dátum', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { type: 'select', label: 'Výber', icon: 'M19 9l-7 7-7-7' },
    { type: 'radio', label: 'Radio', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { type: 'checkbox', label: 'Checkbox', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { type: 'file', label: 'Súbor', icon: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12' },
    { type: 'static_text', label: 'Info text', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
];

// Helper to get localized label for display
const getDisplayLabel = (label) => {
    if (typeof label === 'object' && label !== null) {
        return label.sk || label.en || '';
    }
    return label || '';
};

const editingField = ref(null);
const editingIndex = ref(-1);

// Deep copy function for field objects
const deepCopyField = (field) => {
    return {
        ...field,
        options: field.options ? field.options.map(opt => ({ ...opt })) : undefined,
    };
};

const addField = async (type) => {
    const newField = {
        id: `field_${Date.now()}`,
        type: type,
        name: `field_${fields.value.length + 1}`,
        label: type === 'static_text'
            ? { sk: 'Informačný text', en: 'Information text' }
            : `Nové pole ${fields.value.length + 1}`,
        placeholder: '',
        required: false,
        options: type === 'select' || type === 'radio' ? [
            { label: 'Možnosť 1', value: 'option1' },
            { label: 'Možnosť 2', value: 'option2' },
        ] : undefined,
        // File field defaults
        ...(type === 'file' ? {
            accept: '',
            maxSize: 10,
            multiple: false,
        } : {}),
        // Static text defaults
        ...(type === 'static_text' ? {
            content: { sk: 'Tu napíšte váš informačný text...', en: 'Write your information text here...' },
            style: 'info', // info, warning, success
        } : {}),
    };

    // Add field to the list
    fields.value = [...fields.value, newField];

    // Wait for Vue to update, then open editor
    await nextTick();

    // Open editor with a deep copy
    editingIndex.value = fields.value.length - 1;
    editingField.value = deepCopyField(newField);
};

const editField = (index) => {
    editingIndex.value = index;
    // Deep copy to avoid modifying original until save
    editingField.value = deepCopyField(fields.value[index]);
};

const saveField = () => {
    if (editingIndex.value >= 0 && editingField.value) {
        const newFields = [...fields.value];
        newFields[editingIndex.value] = editingField.value;
        fields.value = newFields;
    }
    // Always close the editor
    closeEditor();
};

const closeEditor = () => {
    editingField.value = null;
    editingIndex.value = -1;
};

const removeField = (index) => {
    if (confirm('Naozaj chcete odstrániť toto pole?')) {
        fields.value = fields.value.filter((_, i) => i !== index);
    }
};

const duplicateField = (index) => {
    const field = deepCopyField(fields.value[index]);
    field.id = `field_${Date.now()}`;
    field.name = `${field.name}_copy`;
    const newFields = [...fields.value];
    newFields.splice(index + 1, 0, field);
    fields.value = newFields;
};
</script>

<template>
    <div class="grid md:grid-cols-3 gap-6">
        <!-- Available field types -->
        <div class="md:col-span-1">
            <h3 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Dostupné polia</h3>
            <div class="space-y-2">
                <button
                    v-for="fieldType in availableTypes"
                    :key="fieldType.type"
                    @click="addField(fieldType.type)"
                    class="w-full flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors text-left text-gray-900 dark:text-gray-100"
                >
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="fieldType.icon" />
                    </svg>
                    <span>{{ fieldType.label }}</span>
                </button>
            </div>
        </div>

        <!-- Form preview -->
        <div class="md:col-span-2">
            <h3 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Polia formulára</h3>

            <div v-if="!fields.length" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center text-gray-500 dark:text-gray-400">
                Kliknutím na pole vľavo ho pridáte do formulára
            </div>

            <draggable
                v-else
                v-model="fields"
                item-key="id"
                handle=".drag-handle"
                class="space-y-3"
            >
                <template #item="{ element, index }">
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-700 hover:border-blue-300 dark:hover:border-blue-500 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 pt-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                </svg>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs rounded">
                                        {{ element.type }}
                                    </span>
                                    <span v-if="element.required" class="text-red-500 dark:text-red-400 text-xs">Povinné</span>
                                    <span v-if="element.conditions?.length" class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 text-xs rounded flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Podmienka
                                    </span>
                                </div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ getDisplayLabel(element.label) }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">name: {{ element.name }}</p>
                            </div>

                            <div class="flex gap-1">
                                <button @click="editField(index)" class="p-1 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button @click="duplicateField(index)" class="p-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <button @click="removeField(index)" class="p-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </draggable>
        </div>
    </div>

    <!-- Field Editor Modal -->
    <div v-if="editingField" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
            <div class="p-4 border-b dark:border-gray-700 flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Upraviť pole</h3>
                <button @click="closeEditor" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                <FieldEditor v-model="editingField" :all-fields="fields" :current-field-id="editingField?.id" />
            </div>

            <div class="p-4 border-t dark:border-gray-700 flex justify-end gap-2 flex-shrink-0">
                <button @click="closeEditor" class="btn btn-secondary">Zrušiť</button>
                <button @click="saveField" class="btn btn-primary">Uložiť</button>
            </div>
        </div>
    </div>
</template>
