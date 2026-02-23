<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: Object,
    allFields: {
        type: Array,
        default: () => [],
    },
    currentFieldId: String,
});

// Fields available for conditions (exclude current field)
const availableFieldsForCondition = computed(() => {
    return props.allFields.filter(f => f.id !== props.currentFieldId);
});

// Get field label for display
const getFieldLabelForCondition = (fieldName) => {
    const field = props.allFields.find(f => f.name === fieldName);
    if (!field) return fieldName;
    const label = field.label;
    if (typeof label === 'object' && label !== null) {
        return label.sk || label.en || fieldName;
    }
    return label || fieldName;
};

// Get options for a field (for select/radio conditions)
const getFieldOptions = (fieldName) => {
    const field = props.allFields.find(f => f.name === fieldName);
    if (!field || !field.options) return [];
    return field.options;
};

// Get option label for display
const getConditionOptionLabel = (option) => {
    if (typeof option.label === 'object' && option.label !== null) {
        return option.label.sk || option.label.en || option.value;
    }
    return option.label || option.value;
};

// Check if field has options (select/radio)
const fieldHasOptions = (fieldName) => {
    const field = props.allFields.find(f => f.name === fieldName);
    return field && ['select', 'radio'].includes(field.type);
};

// Condition operators
const conditionOperators = [
    { value: 'equals', label: 'rovná sa' },
    { value: 'not_equals', label: 'nerovná sa' },
    { value: 'contains', label: 'obsahuje' },
    { value: 'not_empty', label: 'nie je prázdne' },
    { value: 'is_empty', label: 'je prázdne' },
];

// Add new condition
const addCondition = () => {
    const conditions = [...(field.value?.conditions || [])];
    conditions.push({
        field: '',
        operator: 'equals',
        value: '',
    });
    update('conditions', conditions);
};

// Update condition
const updateCondition = (index, key, value) => {
    const conditions = [...(field.value?.conditions || [])];
    conditions[index] = { ...conditions[index], [key]: value };
    // Reset value when field changes
    if (key === 'field') {
        conditions[index].value = '';
    }
    update('conditions', conditions);
};

// Remove condition
const removeCondition = (index) => {
    const conditions = field.value?.conditions?.filter((_, i) => i !== index) || [];
    update('conditions', conditions.length > 0 ? conditions : null);
};

const emit = defineEmits(['update:modelValue']);

// Current editing language
const currentLang = ref('sk');
const languages = [
    { code: 'sk', name: 'Slovensky', flag: '🇸🇰' },
    { code: 'en', name: 'English', flag: '🇬🇧' },
];

const field = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const update = (key, value) => {
    emit('update:modelValue', { ...field.value, [key]: value });
};

// Helper to get/set multilingual values
const getLocalizedValue = (key) => {
    const value = field.value?.[key];
    if (typeof value === 'object' && value !== null) {
        return value[currentLang.value] || '';
    }
    // Legacy: if value is string, return it for SK, empty for EN
    if (typeof value === 'string') {
        return currentLang.value === 'sk' ? value : '';
    }
    return '';
};

const setLocalizedValue = (key, value) => {
    const currentValue = field.value?.[key];
    let newValue;

    if (typeof currentValue === 'object' && currentValue !== null) {
        newValue = { ...currentValue, [currentLang.value]: value };
    } else {
        // Convert from legacy string format
        newValue = {
            sk: currentLang.value === 'sk' ? value : (currentValue || ''),
            en: currentLang.value === 'en' ? value : '',
        };
    }

    update(key, newValue);
};

const hasOptions = computed(() => {
    return field.value && ['select', 'radio'].includes(field.value.type);
});

const addOption = () => {
    const options = [...(field.value.options || [])];
    const num = options.length + 1;
    options.push({
        label: { sk: `Možnosť ${num}`, en: `Option ${num}` },
        value: `option${num}`
    });
    update('options', options);
};

const getOptionLabel = (option) => {
    if (typeof option.label === 'object' && option.label !== null) {
        return option.label[currentLang.value] || '';
    }
    return currentLang.value === 'sk' ? (option.label || '') : '';
};

const updateOptionLabel = (index, value) => {
    const options = [...field.value.options];
    const currentLabel = options[index].label;

    let newLabel;
    if (typeof currentLabel === 'object' && currentLabel !== null) {
        newLabel = { ...currentLabel, [currentLang.value]: value };
    } else {
        newLabel = {
            sk: currentLang.value === 'sk' ? value : (currentLabel || ''),
            en: currentLang.value === 'en' ? value : '',
        };
    }

    options[index] = { ...options[index], label: newLabel };
    update('options', options);
};

const updateOptionValue = (index, value) => {
    const options = [...field.value.options];
    options[index] = { ...options[index], value: value };
    update('options', options);
};

const removeOption = (index) => {
    const options = field.value.options.filter((_, i) => i !== index);
    update('options', options);
};
</script>

<template>
    <div class="space-y-4">
        <!-- Language tabs -->
        <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg">
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

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ t('formBuilder.fieldSettings.name') }}</label>
                <input
                    :value="field.name"
                    @input="update('name', $event.target.value)"
                    type="text"
                    class="form-input"
                    pattern="[a-zA-Z_][a-zA-Z0-9_]*"
                    required
                />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t('formBuilder.fieldSettings.nameHelp') }}</p>
            </div>

            <div>
                <label class="form-label">{{ t('formBuilder.fieldTypes.' + field.type) || field.type }}</label>
                <input :value="field.type" type="text" class="form-input bg-gray-100 dark:bg-gray-600" disabled />
            </div>
        </div>

        <div>
            <label class="form-label">
                {{ t('formBuilder.fieldSettings.label') }}
                <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
            </label>
            <input
                :value="getLocalizedValue('label')"
                @input="setLocalizedValue('label', $event.target.value)"
                type="text"
                class="form-input"
                required
            />
        </div>

        <div>
            <label class="form-label">
                {{ t('formBuilder.fieldSettings.placeholder') }}
                <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
            </label>
            <input
                :value="getLocalizedValue('placeholder')"
                @input="setLocalizedValue('placeholder', $event.target.value)"
                type="text"
                class="form-input"
            />
        </div>

        <!-- Options for select/radio -->
        <div v-if="hasOptions" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <label class="form-label text-blue-800 dark:text-blue-300 font-semibold mb-2 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                {{ t('formBuilder.fieldSettings.options') }}
                <span class="text-xs font-normal">({{ currentLang.toUpperCase() }})</span>
            </label>

            <!-- Column headers -->
            <div class="flex gap-2 mb-2 text-xs text-blue-700 dark:text-blue-400">
                <div class="flex-1">
                    <span class="font-medium">{{ t('formBuilder.fieldSettings.optionLabel') }}</span>
                    <span class="text-blue-600 dark:text-blue-500"> — {{ t('formBuilder.fieldSettings.optionLabelHelp') }}</span>
                </div>
                <div class="w-32">
                    <span class="font-medium">{{ t('formBuilder.fieldSettings.optionValue') }}</span>
                    <span class="text-blue-600 dark:text-blue-500"> — {{ t('formBuilder.fieldSettings.optionValueHelp') }}</span>
                </div>
                <div class="w-9"></div>
            </div>

            <div class="space-y-2">
                <div v-for="(option, index) in (field.options || [])" :key="index" class="flex gap-2">
                    <input
                        :value="getOptionLabel(option)"
                        @input="updateOptionLabel(index, $event.target.value)"
                        type="text"
                        class="form-input flex-1"
                        :placeholder="t('formBuilder.fieldSettings.optionLabel')"
                    />
                    <input
                        :value="option.value"
                        @input="updateOptionValue(index, $event.target.value)"
                        type="text"
                        class="form-input w-32"
                        :placeholder="t('formBuilder.fieldSettings.optionValue')"
                    />
                    <button @click="removeOption(index)" class="text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 px-2" :title="t('common.delete')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div v-if="!field.options || field.options.length === 0" class="text-sm text-blue-600 dark:text-blue-400 italic mb-2">
                {{ t('formBuilder.fieldSettings.noOptions') }}
            </div>
            <button @click="addOption" type="button" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 dark:bg-blue-700 text-white text-sm rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('formBuilder.fieldSettings.addOption') }}
            </button>
        </div>

        <!-- Static text content -->
        <div v-if="field.type === 'static_text'" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 space-y-4">
            <label class="form-label text-blue-800 dark:text-blue-300 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Nastavenia textu
            </label>

            <div>
                <label class="form-label">
                    Obsah textu
                    <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
                </label>
                <textarea
                    :value="getLocalizedValue('content')"
                    @input="setLocalizedValue('content', $event.target.value)"
                    class="form-input"
                    rows="4"
                    placeholder="Napíšte text, ktorý sa zobrazí používateľovi..."
                />
            </div>

            <div>
                <label class="form-label">Štýl zobrazenia</label>
                <select
                    :value="field.style || 'info'"
                    @change="update('style', $event.target.value)"
                    class="form-input"
                >
                    <option value="info">Informácia (modrá)</option>
                    <option value="warning">Upozornenie (žltá)</option>
                    <option value="success">Úspech (zelená)</option>
                    <option value="neutral">Neutrálny (sivá)</option>
                </select>
            </div>
        </div>

        <!-- Checkbox label -->
        <div v-if="field.type === 'checkbox'">
            <label class="form-label">
                {{ t('formBuilder.fieldSettings.checkboxLabel') }}
                <span class="text-xs text-gray-500 ml-1">({{ currentLang.toUpperCase() }})</span>
            </label>
            <input
                :value="getLocalizedValue('checkboxLabel')"
                @input="setLocalizedValue('checkboxLabel', $event.target.value)"
                type="text"
                class="form-input"
            />
        </div>

        <!-- File field options -->
        <div v-if="field.type === 'file'" class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 space-y-4">
            <label class="form-label text-purple-800 dark:text-purple-300 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                {{ t('formBuilder.fileSettings.title') }}
            </label>

            <div>
                <label class="form-label">{{ t('formBuilder.fileSettings.accept') }}</label>
                <select
                    :value="field.accept || ''"
                    @change="update('accept', $event.target.value)"
                    class="form-input"
                >
                    <option value="">{{ t('formBuilder.fileSettings.acceptAll') }}</option>
                    <option value="image/*">{{ t('formBuilder.fileSettings.acceptImages') }}</option>
                    <option value=".pdf">{{ t('formBuilder.fileSettings.acceptPdf') }}</option>
                    <option value=".pdf,.doc,.docx">{{ t('formBuilder.fileSettings.acceptDocs') }}</option>
                    <option value=".pdf,.doc,.docx,.xls,.xlsx">{{ t('formBuilder.fileSettings.acceptDocsExcel') }}</option>
                    <option value="image/*,.pdf">{{ t('formBuilder.fileSettings.acceptImagesAndPdf') }}</option>
                    <option value=".zip,.rar,.7z">{{ t('formBuilder.fileSettings.acceptArchives') }}</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">{{ t('formBuilder.fileSettings.maxSize') }}</label>
                    <input
                        :value="field.maxSize || 10"
                        @input="update('maxSize', parseInt($event.target.value) || 10)"
                        type="number"
                        class="form-input"
                        min="1"
                        max="100"
                    />
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-gray-900 dark:text-gray-100">
                        <input
                            :checked="field.multiple"
                            @change="update('multiple', $event.target.checked)"
                            type="checkbox"
                            class="w-4 h-4 text-purple-600"
                        />
                        {{ t('formBuilder.fileSettings.multiple') }}
                    </label>
                </div>
            </div>
        </div>

        <!-- Validation -->
        <div class="border-t dark:border-gray-700 pt-4">
            <label class="flex items-center gap-2 text-gray-900 dark:text-gray-100">
                <input
                    :checked="field.required"
                    @change="update('required', $event.target.checked)"
                    type="checkbox"
                    class="w-4 h-4 text-blue-600"
                />
                {{ t('formBuilder.fieldSettings.required') }}
            </label>
        </div>

        <div v-if="['text', 'textarea'].includes(field.type)" class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ t('formBuilder.fieldSettings.minLength') }}</label>
                <input
                    :value="field.minLength"
                    @input="update('minLength', parseInt($event.target.value) || null)"
                    type="number"
                    class="form-input"
                    min="0"
                />
            </div>
            <div>
                <label class="form-label">{{ t('formBuilder.fieldSettings.maxLength') }}</label>
                <input
                    :value="field.maxLength"
                    @input="update('maxLength', parseInt($event.target.value) || null)"
                    type="number"
                    class="form-input"
                    min="0"
                />
            </div>
        </div>

        <div v-if="field.type === 'number'" class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ t('formBuilder.fieldSettings.minValue') }}</label>
                <input
                    :value="field.min"
                    @input="update('min', parseInt($event.target.value) || null)"
                    type="number"
                    class="form-input"
                />
            </div>
            <div>
                <label class="form-label">{{ t('formBuilder.fieldSettings.maxValue') }}</label>
                <input
                    :value="field.max"
                    @input="update('max', parseInt($event.target.value) || null)"
                    type="number"
                    class="form-input"
                />
            </div>
        </div>

        <!-- Conditional Logic -->
        <div v-if="availableFieldsForCondition.length > 0" class="border-t dark:border-gray-700 pt-4 mt-4">
            <div class="flex items-center justify-between mb-3">
                <label class="form-label text-purple-700 dark:text-purple-400 font-semibold flex items-center gap-2 mb-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Podmienené zobrazenie
                </label>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                Pole sa zobrazí len ak sú splnené všetky podmienky
            </p>

            <!-- Conditions list -->
            <div class="space-y-2 mb-3">
                <div
                    v-for="(condition, index) in (field.conditions || [])"
                    :key="index"
                    class="flex gap-2 items-start bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg"
                >
                    <div class="flex-1 grid grid-cols-3 gap-2">
                        <!-- Field select -->
                        <select
                            :value="condition.field"
                            @change="updateCondition(index, 'field', $event.target.value)"
                            class="form-input text-sm"
                        >
                            <option value="">-- Vyber pole --</option>
                            <option
                                v-for="f in availableFieldsForCondition"
                                :key="f.id"
                                :value="f.name"
                            >
                                {{ getFieldLabelForCondition(f.name) }}
                            </option>
                        </select>

                        <!-- Operator select -->
                        <select
                            :value="condition.operator"
                            @change="updateCondition(index, 'operator', $event.target.value)"
                            class="form-input text-sm"
                        >
                            <option
                                v-for="op in conditionOperators"
                                :key="op.value"
                                :value="op.value"
                            >
                                {{ op.label }}
                            </option>
                        </select>

                        <!-- Value input (hidden for is_empty/not_empty) -->
                        <template v-if="!['is_empty', 'not_empty'].includes(condition.operator)">
                            <!-- Select for fields with options -->
                            <select
                                v-if="fieldHasOptions(condition.field)"
                                :value="condition.value"
                                @change="updateCondition(index, 'value', $event.target.value)"
                                class="form-input text-sm"
                            >
                                <option value="">-- Vyber hodnotu --</option>
                                <option
                                    v-for="opt in getFieldOptions(condition.field)"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ getConditionOptionLabel(opt) }}
                                </option>
                            </select>
                            <!-- Text input for other fields -->
                            <input
                                v-else
                                :value="condition.value"
                                @input="updateCondition(index, 'value', $event.target.value)"
                                type="text"
                                class="form-input text-sm"
                                placeholder="Hodnota"
                            />
                        </template>
                        <div v-else></div>
                    </div>

                    <!-- Remove button -->
                    <button
                        @click="removeCondition(index)"
                        type="button"
                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 p-1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Add condition button -->
            <button
                @click="addCondition"
                type="button"
                class="inline-flex items-center gap-1 px-3 py-1.5 bg-purple-600 dark:bg-purple-700 text-white text-sm rounded hover:bg-purple-700 dark:hover:bg-purple-600 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Pridať podmienku
            </button>
        </div>
    </div>
</template>
