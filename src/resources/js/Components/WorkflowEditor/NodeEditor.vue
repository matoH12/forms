<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: Object,
    type: String,
    formFields: {
        type: Array,
        default: () => [],
    },
    emailTemplates: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const data = computed({
    get: () => props.modelValue || {},
    set: (value) => emit('update:modelValue', value),
});

const update = (key, value) => {
    emit('update:modelValue', { ...data.value, [key]: value });
};

// Headers as array for easier editing - stored separately to allow empty keys during editing
const headersArray = ref([]);

// Initialize headers from data
const initHeaders = () => {
    const h = data.value?.headers || {};
    headersArray.value = Object.entries(h).map(([key, value]) => ({ key, value }));
};

// Watch for data changes and reinitialize headers
watch(() => data.value?.headers, (newHeaders) => {
    if (newHeaders) {
        const currentKeys = headersArray.value.map(h => h.key).filter(k => k).join(',');
        const newKeys = Object.keys(newHeaders).join(',');
        // Only reinitialize if headers changed externally
        if (currentKeys !== newKeys && headersArray.value.every(h => h.key)) {
            initHeaders();
        }
    }
}, { immediate: true });

// Computed headers that syncs back to data (filtering empty keys)
const headers = computed({
    get: () => headersArray.value,
    set: (arr) => {
        headersArray.value = arr;
        // Only save headers with non-empty keys to the actual data
        const obj = {};
        arr.forEach(item => {
            if (item.key) obj[item.key] = item.value;
        });
        update('headers', obj);
    }
});

const addHeader = () => {
    headers.value = [...headers.value, { key: '', value: '' }];
};

const removeHeader = (index) => {
    const newHeaders = [...headers.value];
    newHeaders.splice(index, 1);
    headers.value = newHeaders;
};

const updateHeader = (index, field, value) => {
    const newHeaders = [...headers.value];
    // Sanitize header key - remove trailing colons (common mistake from curl syntax)
    const sanitizedValue = field === 'key' ? value.replace(/:+$/, '').trim() : value;
    newHeaders[index] = { ...newHeaders[index], [field]: sanitizedValue };
    headers.value = newHeaders;
};

// Variable picker
const showVariablePicker = ref(false);
const variableTarget = ref(null);
const variableTargetField = ref(null);

const getFieldTypeIcon = (type) => {
    const icons = {
        text: '📝',
        textarea: '📄',
        email: '📧',
        number: '🔢',
        date: '📅',
        select: '📋',
        checkbox: '☑️',
        radio: '🔘',
        file: '📎',
    };
    return icons[type] || '📝';
};

const getFieldTypeLabel = (type) => {
    const labels = {
        text: 'Text',
        textarea: 'Dlhy text',
        email: 'Email',
        number: 'Cislo',
        date: 'Datum',
        select: 'Vyber',
        checkbox: 'Zaskrtavacie pole',
        radio: 'Vyber jednej moznosti',
        file: 'Subor',
    };
    return labels[type] || type;
};

// Helper to get localized label (handles both string and object format)
const getLocalizedLabel = (label, fallback = '') => {
    if (!label) return fallback;
    if (typeof label === 'string') return label;
    if (typeof label === 'object') {
        // Try to get current language or fallback to sk, then en
        return label.sk || label.en || fallback;
    }
    return fallback;
};

const availableVariables = computed(() => {
    const vars = [];

    // Add form fields first (most important)
    if (props.formFields.length > 0) {
        vars.push({
            group: 'Polia z formulara',
            icon: '📋',
            description: 'Hodnoty vyplnene pouzivatelom',
            items: props.formFields.map(f => ({
                key: `submission.${f.name}`,
                label: getLocalizedLabel(f.label, f.name),
                description: `${getFieldTypeIcon(f.type)} ${getFieldTypeLabel(f.type)}`,
                example: f.type === 'email' ? 'jan@example.com' : f.type === 'number' ? '123' : 'hodnota',
            })),
        });
    } else {
        vars.push({
            group: 'Polia z formulara',
            icon: '📋',
            description: 'Vyberte formular pre zobrazenie poli',
            items: [
                { key: 'submission.nazov_pola', label: 'Nie je vybrany formular', description: 'Vyberte formular v nastaveniach workflow', disabled: true },
            ],
        });
    }

    // User info
    vars.push({
        group: 'Informacie o pouzivatelovi',
        icon: '👤',
        description: 'Udaje o tom, kto vyplnil formular',
        items: [
            { key: 'user.name', label: 'Meno a priezvisko', description: 'Cele meno pouzivatela', example: 'Jan Novak' },
            { key: 'user.email', label: 'Email adresa', description: 'Email pouzivatela', example: 'jan@example.com' },
            { key: 'user.login', label: 'Login (UPN)', description: 'Unikatny login z Keycloak', example: 'jn123ab' },
            { key: 'user.id', label: 'ID pouzivatela', description: 'Jedinecne ID v systeme', example: '123' },
        ],
    });

    // Form info
    vars.push({
        group: 'Informacie o formulari',
        icon: '📝',
        description: 'Metadata formulara',
        items: [
            { key: 'form.id', label: 'ID formulara', description: 'Jedinecne ID formulara', example: '1' },
            { key: 'form.name', label: 'Nazov formulara', description: 'Nazov formulara', example: 'Ziadost o dovolenku' },
        ],
    });

    // API response (only for API call nodes or after API calls)
    vars.push({
        group: 'Odpoved z API',
        icon: '🌐',
        description: 'Data z predchadzajuceho API volania',
        items: [
            { key: 'last_api_response.status', label: 'HTTP status kod', description: 'Navratovy kod (200, 404, ...)', example: '200' },
            { key: 'last_api_response.body', label: 'Telo odpovede', description: 'Cele telo odpovede', example: '{"id": 1}' },
            { key: 'last_api_response.body.id', label: 'Pole z odpovede', description: 'Konkretne pole z JSON odpovede', example: '1' },
        ],
    });

    return vars;
});

const openVariablePicker = (target, field = null) => {
    variableTarget.value = target;
    variableTargetField.value = field;
    showVariablePicker.value = true;
};

const insertVariable = (varKey) => {
    const variable = `{{${varKey}}}`;

    if (variableTargetField.value === 'header') {
        const index = variableTarget.value;
        updateHeader(index, 'value', (headers.value[index]?.value || '') + variable);
    } else if (variableTarget.value) {
        const currentValue = data.value?.[variableTarget.value] || '';
        update(variableTarget.value, currentValue + variable);
    }

    showVariablePicker.value = false;
};

// Body formatting
const formatBody = () => {
    try {
        const parsed = JSON.parse(data.value?.body || '{}');
        update('body', JSON.stringify(parsed, null, 2));
    } catch (e) {
        // Not valid JSON, leave as is
    }
};

const operators = [
    { value: 'equals', label: 'rovná sa', icon: '=' },
    { value: 'not_equals', label: 'nerovná sa', icon: '≠' },
    { value: 'contains', label: 'obsahuje', icon: '⊃' },
    { value: 'greater_than', label: 'väčšie ako', icon: '>' },
    { value: 'less_than', label: 'menšie ako', icon: '<' },
    { value: 'is_empty', label: 'je prázdne', icon: '∅' },
    { value: 'is_not_empty', label: 'nie je prázdne', icon: '≠∅' },
];

// Available fields for condition
const conditionFields = computed(() => {
    const fields = [];

    // Form fields
    if (props.formFields.length > 0) {
        fields.push({
            group: 'Polia z formulára',
            icon: '📋',
            items: props.formFields.map(f => ({
                key: `submission.${f.name}`,
                label: getLocalizedLabel(f.label, f.name),
                type: f.type,
            })),
        });
    }

    // User fields
    fields.push({
        group: 'Používateľ',
        icon: '👤',
        items: [
            { key: 'user.name', label: 'Meno používateľa', type: 'text' },
            { key: 'user.email', label: 'Email používateľa', type: 'email' },
            { key: 'user.id', label: 'ID používateľa', type: 'number' },
        ],
    });

    // Form metadata
    fields.push({
        group: 'Formulár',
        icon: '📝',
        items: [
            { key: 'form.id', label: 'ID formulára', type: 'number' },
            { key: 'form.name', label: 'Názov formulára', type: 'text' },
        ],
    });

    // API response (from previous steps)
    fields.push({
        group: 'Odpoveď z API (predchádzajúci krok)',
        icon: '🌐',
        items: [
            { key: 'last_api_response.status', label: 'HTTP status kód', type: 'number' },
            { key: 'last_api_response.body', label: 'Celé telo odpovede', type: 'object' },
        ],
    });

    return fields;
});

// Flat list of all condition fields for easy lookup
const allConditionFields = computed(() => {
    const all = [];
    conditionFields.value.forEach(group => {
        group.items.forEach(item => {
            all.push({ ...item, group: group.group, icon: group.icon });
        });
    });
    return all;
});

// Get selected field info
const selectedConditionField = computed(() => {
    if (!data.value?.field) return null;
    return allConditionFields.value.find(f => f.key === data.value.field);
});

const httpMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

// Helper for setting submitter as email recipient
const setSubmitterRecipient = () => {
    update('recipient_type', 'submitter');
    update('to', '{{user.email}}');
};
</script>

<template>
    <div class="space-y-4">
        <!-- Label -->
        <div>
            <label class="form-label">Nazov kroku</label>
            <input
                :value="data.label || ''"
                @input="update('label', $event.target.value)"
                type="text"
                class="form-input"
            />
        </div>

        <!-- API Call -->
        <template v-if="type === 'api_call'">
            <!-- Method and URL -->
            <div class="grid grid-cols-4 gap-2">
                <div>
                    <label class="form-label">Metoda</label>
                    <select :value="data.method || 'GET'" @change="update('method', $event.target.value)" class="form-input">
                        <option v-for="method in httpMethods" :key="method" :value="method">
                            {{ method }}
                        </option>
                    </select>
                </div>
                <div class="col-span-3">
                    <label class="form-label">
                        URL
                        <button type="button" @click="openVariablePicker('url')" class="ml-2 text-blue-600 dark:text-blue-400 text-xs hover:underline">
                            + Pridat premennu
                        </button>
                    </label>
                    <input
                        :value="data.url"
                        @input="update('url', $event.target.value)"
                        type="text"
                        class="form-input"
                        placeholder="https://api.example.com/endpoint"
                    />
                </div>
            </div>

            <!-- Headers -->
            <div>
                <label class="form-label flex justify-between items-center">
                    <span>Headers</span>
                    <button type="button" @click="addHeader" class="text-blue-600 dark:text-blue-400 text-xs hover:underline">
                        + Pridat header
                    </button>
                </label>
                <div class="space-y-2">
                    <div v-for="(header, index) in headers" :key="index" class="flex gap-2 items-start">
                        <input
                            :value="header.key"
                            @input="updateHeader(index, 'key', $event.target.value)"
                            type="text"
                            class="form-input flex-1"
                            placeholder="Content-Type"
                        />
                        <div class="flex-1 flex gap-1">
                            <input
                                :value="header.value"
                                @input="updateHeader(index, 'value', $event.target.value)"
                                type="text"
                                class="form-input flex-1"
                                placeholder="application/json"
                            />
                            <button
                                type="button"
                                @click="openVariablePicker(index, 'header')"
                                class="px-2 py-1 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded text-sm"
                                title="Vlozit premennu"
                            >
                                {x}
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="removeHeader(index)"
                            class="p-2 text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div v-if="headers.length === 0" class="text-gray-500 dark:text-gray-400 text-sm py-2">
                        Ziadne headers. Kliknite na "Pridat header" pre pridanie.
                    </div>
                </div>
            </div>

            <!-- Common headers presets -->
            <div class="flex gap-2 flex-wrap">
                <span class="text-xs text-gray-500 dark:text-gray-400">Rychle pridanie:</span>
                <button
                    type="button"
                    @click="headers = [...headers, { key: 'Content-Type', value: 'application/json' }]"
                    class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded"
                >
                    Content-Type: JSON
                </button>
                <button
                    type="button"
                    @click="headers = [...headers, { key: 'Authorization', value: 'Bearer ' }]"
                    class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded"
                >
                    Authorization: Bearer
                </button>
                <button
                    type="button"
                    @click="headers = [...headers, { key: 'Accept', value: 'application/json' }]"
                    class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded"
                >
                    Accept: JSON
                </button>
            </div>

            <!-- Body -->
            <div v-if="['POST', 'PUT', 'PATCH'].includes(data.method)">
                <label class="form-label flex justify-between items-center">
                    <span>Body (JSON)</span>
                    <div class="flex gap-2">
                        <button type="button" @click="openVariablePicker('body')" class="text-blue-600 dark:text-blue-400 text-xs hover:underline">
                            + Pridat premennu
                        </button>
                        <button type="button" @click="formatBody" class="text-gray-600 dark:text-gray-400 text-xs hover:underline">
                            Formatovat JSON
                        </button>
                    </div>
                </label>
                <textarea
                    :value="data.body"
                    @input="update('body', $event.target.value)"
                    class="form-input font-mono text-sm"
                    rows="6"
                    placeholder='{
  "name": "{{submission.meno}}",
  "email": "{{user.email}}"
}'
                />
            </div>

            <!-- Advanced settings -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Pokrocile nastavenia</h4>

                <!-- Insecure mode (skip SSL verification) -->
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="data.insecure"
                            @change="update('insecure', $event.target.checked)"
                            class="w-4 h-4 text-orange-600 rounded"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Insecure mód (preskočiť overenie SSL certifikátu)</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
                        Povoľuje HTTP volania a HTTPS bez platného certifikátu. Používajte len pre interné/testovacie API.
                    </p>
                </div>

                <!-- Async mode -->
                <div class="mb-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="data.async"
                            @change="update('async', $event.target.checked)"
                            class="w-4 h-4 text-blue-600 rounded"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Asynchronne volanie (nepockat na odpoved)</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-6">
                        Pouzite ak API trva dlho a nepotrebujete odpoved. Workflow pokracuje okamzite.
                    </p>
                </div>

                <!-- Timeout (only if not async) -->
                <div v-if="!data.async" class="mb-4">
                    <label class="form-label">Timeout</label>
                    <select
                        :value="data.timeout || 30"
                        @change="update('timeout', parseInt($event.target.value))"
                        class="form-input"
                    >
                        <option :value="30">30 sekund (standard)</option>
                        <option :value="60">1 minuta</option>
                        <option :value="120">2 minuty</option>
                        <option :value="300">5 minut</option>
                        <option :value="600">10 minut (maximum)</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Kolko dlho cakat na odpoved od API
                    </p>
                </div>

                <!-- Retry settings (only if not async) -->
                <div v-if="!data.async" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Opakovanie pri chybe</label>
                        <select
                            :value="data.retry_count || 0"
                            @change="update('retry_count', parseInt($event.target.value))"
                            class="form-input"
                        >
                            <option :value="0">Bez opakovania</option>
                            <option :value="1">1 opakovanie</option>
                            <option :value="2">2 opakovania</option>
                            <option :value="3">3 opakovania</option>
                            <option :value="5">5 opakovani</option>
                        </select>
                    </div>
                    <div v-if="data.retry_count > 0">
                        <label class="form-label">Pauza medzi pokusmi</label>
                        <select
                            :value="data.retry_delay || 5"
                            @change="update('retry_delay', parseInt($event.target.value))"
                            class="form-input"
                        >
                            <option :value="1">1 sekunda</option>
                            <option :value="5">5 sekund</option>
                            <option :value="10">10 sekund</option>
                            <option :value="30">30 sekund</option>
                            <option :value="60">1 minuta</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Variable help -->
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded text-sm" v-pre>
                <p class="font-medium text-blue-800 dark:text-blue-200 mb-2">Pouzitie premennych:</p>
                <p class="text-blue-700 dark:text-blue-300 mb-2">
                    Premenne vkladajte v tvare <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{nazov}}</code>
                </p>
                <div class="text-blue-700 dark:text-blue-300 space-y-1">
                    <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">{{submission.pole}}</code> - hodnota z formulara</div>
                    <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">{{user.email}}</code> - email pouzivatela</div>
                    <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs">{{last_api_response.body.id}}</code> - data z predchadzajuceho API</div>
                </div>
            </div>
        </template>

        <!-- Approval -->
        <template v-if="type === 'approval'">
            <div>
                <label class="form-label">
                    Email schvalovatela
                    <button type="button" @click="openVariablePicker('approver_email')" class="ml-2 text-blue-600 dark:text-blue-400 text-xs hover:underline">
                        + Pridat premennu
                    </button>
                </label>
                <input
                    :value="data.approver_email"
                    @input="update('approver_email', $event.target.value)"
                    type="text"
                    class="form-input"
                    placeholder="schvalovatel@example.com alebo {{submission.manager_email}}"
                />
            </div>

            <div>
                <label class="form-label">Sprava pre schvalovatela</label>
                <textarea
                    :value="data.message"
                    @input="update('message', $event.target.value)"
                    class="form-input"
                    rows="3"
                    placeholder="Prosim schvalte ziadost od {{user.name}}"
                />
            </div>
        </template>

        <!-- Condition -->
        <template v-if="type === 'condition'">
            <!-- Field selection -->
            <div>
                <label class="form-label">Pole na kontrolu</label>
                <select
                    :value="data.field"
                    @change="update('field', $event.target.value)"
                    class="form-input"
                >
                    <option value="">-- Vyberte pole --</option>
                    <optgroup v-for="group in conditionFields" :key="group.group" :label="`${group.icon} ${group.group}`">
                        <option v-for="item in group.items" :key="item.key" :value="item.key">
                            {{ item.label }}
                        </option>
                    </optgroup>
                </select>
                <!-- Selected field info -->
                <div v-if="selectedConditionField" class="mt-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs">
                    <span class="text-gray-500 dark:text-gray-400">Vybrané:</span>
                    <code class="ml-1 bg-gray-200 dark:bg-gray-600 px-1 rounded text-gray-700 dark:text-gray-300">{{ selectedConditionField.key }}</code>
                </div>
            </div>

            <!-- Custom field input (for advanced users or API response paths) -->
            <div class="mt-2">
                <button
                    type="button"
                    @click="update('showCustomField', !data.showCustomField)"
                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                >
                    {{ data.showCustomField ? '− Skryť vlastné pole' : '+ Zadať vlastnú cestu (pre API odpovede)' }}
                </button>
                <div v-if="data.showCustomField" class="mt-2">
                    <input
                        :value="data.field"
                        @input="update('field', $event.target.value)"
                        type="text"
                        class="form-input text-sm font-mono"
                        placeholder="last_api_response.body.data.status"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Pre vnorené dáta z API použite bodkovú notáciu, napr. <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">last_api_response.body.user.role</code>
                    </p>
                </div>
            </div>

            <!-- Operator -->
            <div class="mt-4">
                <label class="form-label">Operátor</label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="op in operators"
                        :key="op.value"
                        type="button"
                        @click="update('operator', op.value)"
                        class="px-3 py-2 text-sm rounded-lg border-2 transition-colors text-left flex items-center gap-2"
                        :class="data.operator === op.value
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-700 dark:text-gray-300'"
                    >
                        <span class="text-lg font-mono">{{ op.icon }}</span>
                        <span>{{ op.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Value input (not shown for is_empty/is_not_empty) -->
            <div v-if="!['is_empty', 'is_not_empty'].includes(data.operator)" class="mt-4">
                <label class="form-label">
                    Porovnávaná hodnota
                    <button type="button" @click="openVariablePicker('value')" class="ml-2 text-blue-600 dark:text-blue-400 text-xs hover:underline">
                        + Použiť premennú
                    </button>
                </label>
                <input
                    :value="data.value"
                    @input="update('value', $event.target.value)"
                    type="text"
                    class="form-input"
                    placeholder="hodnota alebo {{premenna}}"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Zadajte konkrétnu hodnotu alebo použite premennú z iného poľa
                </p>
            </div>

            <!-- Preview of condition -->
            <div v-if="data.field && data.operator" class="mt-4 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Náhľad podmienky:</p>
                <p class="text-sm font-mono text-gray-800 dark:text-gray-200">
                    <span class="text-blue-600 dark:text-blue-400">{{ selectedConditionField?.label || data.field }}</span>
                    <span class="text-purple-600 dark:text-purple-400 mx-2">{{ operators.find(o => o.value === data.operator)?.label }}</span>
                    <span v-if="!['is_empty', 'is_not_empty'].includes(data.operator)" class="text-green-600 dark:text-green-400">"{{ data.value || '...' }}"</span>
                </p>
            </div>

            <!-- Info box -->
            <div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded text-sm">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <strong>Tip:</strong> Podmienka vytvorí dve vetvy vo workflow:
                </p>
                <ul class="mt-2 space-y-1 text-yellow-700 dark:text-yellow-300">
                    <li class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full bg-green-500 flex-shrink-0"></span>
                        <span><strong>Áno</strong> - ak je podmienka splnená</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full bg-red-500 flex-shrink-0"></span>
                        <span><strong>Nie</strong> - ak podmienka nie je splnená</span>
                    </li>
                </ul>
            </div>
        </template>

        <!-- Delay -->
        <template v-if="type === 'delay'">
            <div>
                <label class="form-label">Cas cakania</label>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="preset in [
                            { value: 5, label: '5 sekund' },
                            { value: 10, label: '10 sekund' },
                            { value: 30, label: '30 sekund' },
                            { value: 60, label: '1 minuta' },
                            { value: 120, label: '2 minuty' },
                            { value: 300, label: '5 minut' },
                        ]"
                        :key="preset.value"
                        type="button"
                        @click="update('delay_seconds', preset.value)"
                        class="px-3 py-2 text-sm rounded-lg border-2 transition-colors"
                        :class="data.delay_seconds === preset.value
                            ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 text-gray-700 dark:text-gray-300'"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label">Vlastna hodnota (sekundy)</label>
                <input
                    :value="data.delay_seconds"
                    @input="update('delay_seconds', parseInt($event.target.value) || 0)"
                    type="number"
                    min="1"
                    max="3600"
                    class="form-input"
                    placeholder="Pocet sekund"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Zadajte pocet sekund (1 - 3600, max 1 hodina)
                </p>
            </div>

            <!-- Info box -->
            <div class="mt-4 bg-orange-50 dark:bg-orange-900/20 p-3 rounded text-sm">
                <p class="text-orange-800 dark:text-orange-200">
                    <strong>Tip:</strong> Pouzite cakanie ak potrebujete dat cas externemu systemu na spracovanie
                    pred dalsim krokom (napr. po API volani, ktore spusta dlhsiu operaciu).
                </p>
            </div>
        </template>

        <!-- Email -->
        <template v-if="type === 'email'">
            <!-- Email template selection -->
            <div>
                <label class="form-label">Emailova sablona *</label>
                <select
                    :value="data.template_id"
                    @change="update('template_id', $event.target.value ? parseInt($event.target.value) : null)"
                    class="form-input"
                >
                    <option :value="null">-- Vyberte sablonu --</option>
                    <option v-for="template in emailTemplates" :key="template.id" :value="template.id">
                        {{ template.name }} ({{ template.subject }})
                    </option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Vyberte emailovu sablonu, ktoru ste vytvorili v sekcii "Email sablony"
                </p>
            </div>

            <!-- Recipient type -->
            <div>
                <label class="form-label">Prijemca</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            :checked="data.recipient_type === 'submitter' || !data.recipient_type"
                            @change="setSubmitterRecipient"
                            class="w-4 h-4 text-blue-600"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Odosielatel formulara <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">{<!-- -->{user.email}<!-- -->}</code></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            :checked="data.recipient_type === 'custom'"
                            @change="update('recipient_type', 'custom'); update('to', '')"
                            class="w-4 h-4 text-blue-600"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Vlastny email</span>
                    </label>
                </div>
            </div>

            <!-- Custom recipient input -->
            <div v-if="data.recipient_type === 'custom'">
                <label class="form-label">
                    Email prijemcu
                    <button type="button" @click="openVariablePicker('to')" class="ml-2 text-blue-600 dark:text-blue-400 text-xs hover:underline">
                        + Pridat premennu
                    </button>
                </label>
                <input
                    :value="data.to"
                    @input="update('to', $event.target.value)"
                    type="text"
                    class="form-input"
                    placeholder="prijemca@example.com"
                />
            </div>

            <!-- Info box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded text-sm" v-pre>
                <p class="text-blue-800 dark:text-blue-200">
                    Email bude odoslany s obsahom vybranej sablony. Premenne v sablone budu automaticky nahradene.
                </p>
            </div>
        </template>

        <!-- Variable Picker Modal -->
        <div v-if="showVariablePicker" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full max-h-[85vh] overflow-hidden mx-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Vyberte premennu</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Kliknite na premennu pre vlozenie</p>
                    </div>
                    <button @click="showVariablePicker = false" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[65vh]">
                    <div v-for="group in availableVariables" :key="group.group" class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        <!-- Group header -->
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 sticky top-0">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">{{ group.icon }}</span>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ group.group }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ group.description }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Group items -->
                        <div class="p-2">
                            <button
                                v-for="item in group.items"
                                :key="item.key"
                                @click="!item.disabled && insertVariable(item.key)"
                                :disabled="item.disabled"
                                class="w-full text-left px-3 py-2.5 rounded-lg mb-1 last:mb-0 transition-colors"
                                :class="item.disabled
                                    ? 'opacity-50 cursor-not-allowed bg-gray-50 dark:bg-gray-800'
                                    : 'hover:bg-blue-50 dark:hover:bg-blue-900/30 cursor-pointer'"
                            >
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm text-gray-900 dark:text-white">{{ item.label }}</div>
                                        <div v-if="item.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ item.description }}</div>
                                    </div>
                                    <code class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-1 rounded font-mono flex-shrink-0">{{ item.key }}</code>
                                </div>
                                <div v-if="item.example && !item.disabled" class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                                    Priklad: <span class="text-gray-600 dark:text-gray-400">{{ item.example }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Footer with help -->
                <div class="p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <p class="text-xs text-gray-500 dark:text-gray-400 text-center" v-pre>
                        Premenne sa pouzivaju v tvare {{nazov_premennej}}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
