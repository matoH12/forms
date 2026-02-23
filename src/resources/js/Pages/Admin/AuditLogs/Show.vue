<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    log: Object,
    auth: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const actionLabels = {
    form_created: 'Vytvorenie formulára',
    form_updated: 'Úprava formulára',
    form_deleted: 'Zmazanie formulára',
    form_submitted: 'Odoslanie formulára',
    submission_approved: 'Schválenie žiadosti',
    submission_rejected: 'Zamietnutie žiadosti',
    submission_deleted: 'Zmazanie žiadosti',
    workflow_created: 'Vytvorenie workflow',
    workflow_updated: 'Úprava workflow',
    workflow_deleted: 'Zmazanie workflow',
    workflow_executed: 'Spustenie workflow',
    user_login: 'Prihlásenie',
    user_logout: 'Odhlásenie',
    user_updated: 'Úprava používateľa',
    user_admin_toggled: 'Zmena admin práv',
    user_deleted: 'Zmazanie používateľa',
    settings_updated: 'Úprava nastavení',
    approval_approved: 'Schválenie (workflow)',
    approval_rejected: 'Zamietnutie (workflow)',
};

const actionIcons = {
    form_created: '📝',
    form_updated: '✏️',
    form_deleted: '🗑️',
    form_submitted: '📤',
    submission_approved: '✅',
    submission_rejected: '❌',
    submission_deleted: '🗑️',
    workflow_created: '⚡',
    workflow_updated: '⚙️',
    workflow_deleted: '🗑️',
    workflow_executed: '▶️',
    user_login: '🔓',
    user_logout: '🔒',
    user_updated: '👤',
    user_admin_toggled: '🛡️',
    user_deleted: '🗑️',
    settings_updated: '⚙️',
    approval_approved: '✅',
    approval_rejected: '❌',
};

const actionColors = {
    form_created: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800',
    form_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    form_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
    form_submitted: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    submission_approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800',
    submission_rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
    submission_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
    workflow_created: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800',
    workflow_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    workflow_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
    workflow_executed: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 border-purple-200 dark:border-purple-800',
    user_login: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-600',
    user_logout: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-600',
    user_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    user_admin_toggled: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
    user_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
    settings_updated: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
    approval_approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800',
    approval_rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800',
};

const formatModelType = (type) => {
    if (!type) return '-';
    const parts = type.split('\\');
    const modelName = parts[parts.length - 1];
    const labels = {
        Form: 'Formulár',
        FormSubmission: 'Odpoveď formulára',
        Workflow: 'Workflow',
        WorkflowExecution: 'Vykonanie workflow',
        User: 'Používateľ',
        ApprovalRequest: 'Žiadosť o schválenie',
        EmailTemplate: 'E-mailová šablóna',
    };
    return labels[modelName] || modelName;
};

// Check if value is a localized name object (has sk/en keys)
const isLocalizedName = (value) => {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
    const keys = Object.keys(value);
    return keys.length > 0 && keys.every(k => ['sk', 'en', 'cs', 'de'].includes(k));
};

// Extract localized value from object
const getLocalizedValue = (value) => {
    if (!value || typeof value !== 'object') return value;
    // Try Slovak first, then English, then first available
    return value.sk || value.en || Object.values(value).find(v => v) || '';
};

const formatValue = (value) => {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'Áno' : 'Nie';
    if (isLocalizedName(value)) return getLocalizedValue(value);
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
};

const fieldLabels = {
    name: 'Názov',
    title: 'Titulok',
    description: 'Popis',
    is_active: 'Aktívny',
    is_public: 'Verejný',
    is_admin: 'Administrátor',
    email: 'E-mail',
    status: 'Stav',
    trigger_on: 'Spúšťač',
    form_id: 'ID formulára',
    form_name: 'Názov formulára',
    workflow_name: 'Názov workflow',
    submission_id: 'ID odpovede',
    section: 'Sekcia',
    subject: 'Predmet',
    body: 'Obsah',
    slug: 'URL slug',
    schema: 'Schéma',
    settings: 'Nastavenia',
    nodes: 'Uzly',
    edges: 'Hrany',
    admin_response: 'Odpoveď admina',
    reviewed_by: 'Schválil',
    reviewed_at: 'Dátum schválenia',
};

const getFieldLabel = (field) => fieldLabels[field] || field;

// Get all unique keys from both old and new values
const changedFields = computed(() => {
    const oldVals = props.log.old_values || {};
    const newVals = props.log.new_values || {};
    const allKeys = new Set([...Object.keys(oldVals), ...Object.keys(newVals)]);

    return Array.from(allKeys).map(key => ({
        key,
        label: getFieldLabel(key),
        oldValue: oldVals[key],
        newValue: newVals[key],
        changed: JSON.stringify(oldVals[key]) !== JSON.stringify(newVals[key]),
        isComplex: isComplexValue(oldVals[key]) || isComplexValue(newVals[key]),
    }));
});

const hasChanges = computed(() => {
    return props.log.old_values || props.log.new_values;
});

const isComplexValue = (value) => {
    if (value === null || typeof value !== 'object') return false;
    // Localized name objects should not be treated as complex
    if (isLocalizedName(value)) return false;
    return true;
};
</script>

<template>
    <Head title="Detail záznamu" />
    <AdminLayout :auth="auth">
        <div class="flex items-center gap-4 mb-6">
            <Link href="/admin/audit-logs" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </Link>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Detail záznamu #{{ log.id }}</h1>
        </div>

        <!-- Action summary banner -->
        <div
            class="card mb-6 border-2"
            :class="actionColors[log.action] || 'bg-gray-100 dark:bg-gray-700 border-gray-200 dark:border-gray-600'"
        >
            <div class="flex items-center gap-4">
                <div class="text-3xl">{{ actionIcons[log.action] || '📋' }}</div>
                <div>
                    <h2 class="text-xl font-bold">{{ actionLabels[log.action] || log.action }}</h2>
                    <p class="text-sm opacity-75">
                        {{ formatModelType(log.model_type) }}
                        <span v-if="log.model_id">#{{ log.model_id }}</span>
                        • {{ formatDate(log.created_at) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Basic info -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Základné informácie
                </h2>
                <dl class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400">Čas:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(log.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400">Akcia:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ actionLabels[log.action] || log.action }}</dd>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400">Typ objektu:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatModelType(log.model_type) }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-500 dark:text-gray-400">ID objektu:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ log.model_id || '-' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- User info -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Používateľ
                </h2>
                <div v-if="log.user" class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-medium text-lg shadow-md">
                        {{ log.user.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            {{ log.user.first_name && log.user.last_name
                                ? `${log.user.first_name} ${log.user.last_name}`
                                : log.user.name }}
                        </p>
                        <p v-if="log.user.login" class="text-xs font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded inline-block mb-1">{{ log.user.login }}</p>
                        <p class="text-gray-500 dark:text-gray-400">{{ log.user.email }}</p>
                    </div>
                </div>
                <div v-else class="text-gray-400 dark:text-gray-500 italic mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Anonymný používateľ
                </div>

                <dl class="space-y-3 border-t border-gray-200 dark:border-gray-600 pt-4">
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400">IP Adresa:</dt>
                        <dd class="font-medium font-mono text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            {{ log.ip_address || '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400 mb-2">User Agent:</dt>
                        <dd class="text-sm text-gray-600 dark:text-gray-400 break-all bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                            {{ log.user_agent || '-' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Changes comparison -->
        <div v-if="hasChanges" class="card mt-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Porovnanie zmien
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-600 text-left bg-gray-50 dark:bg-gray-700/50">
                            <th class="py-3 px-4 font-medium text-gray-700 dark:text-gray-300 w-1/4">Pole</th>
                            <th class="py-3 px-4 font-medium text-gray-700 dark:text-gray-300 w-[37.5%]">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-red-400"></span>
                                    Pôvodná hodnota
                                </span>
                            </th>
                            <th class="py-3 px-4 font-medium text-gray-700 dark:text-gray-300 w-[37.5%]">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-green-400"></span>
                                    Nová hodnota
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="field in changedFields"
                            :key="field.key"
                            class="border-b border-gray-100 dark:border-gray-700 last:border-0"
                            :class="{ 'bg-yellow-50/50 dark:bg-yellow-900/10': field.changed }"
                        >
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="field.changed"
                                        class="w-2 h-2 rounded-full bg-yellow-400"
                                        title="Zmenené"
                                    ></span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ field.label }}</span>
                                </div>
                                <span class="text-xs text-gray-400 font-mono">{{ field.key }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <template v-if="field.isComplex && field.oldValue">
                                    <details class="cursor-pointer">
                                        <summary class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                            Zobraziť JSON
                                        </summary>
                                        <pre class="mt-2 text-xs bg-red-50 dark:bg-red-900/20 p-2 rounded overflow-auto max-h-40 text-red-800 dark:text-red-300">{{ formatValue(field.oldValue) }}</pre>
                                    </details>
                                </template>
                                <template v-else>
                                    <span
                                        class="inline-block px-2 py-1 rounded text-sm"
                                        :class="field.oldValue !== undefined && field.oldValue !== null
                                            ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
                                            : 'text-gray-400 dark:text-gray-500 italic'"
                                    >
                                        {{ formatValue(field.oldValue) }}
                                    </span>
                                </template>
                            </td>
                            <td class="py-3 px-4">
                                <template v-if="field.isComplex && field.newValue">
                                    <details class="cursor-pointer">
                                        <summary class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                            Zobraziť JSON
                                        </summary>
                                        <pre class="mt-2 text-xs bg-green-50 dark:bg-green-900/20 p-2 rounded overflow-auto max-h-40 text-green-800 dark:text-green-300">{{ formatValue(field.newValue) }}</pre>
                                    </details>
                                </template>
                                <template v-else>
                                    <span
                                        class="inline-block px-2 py-1 rounded text-sm"
                                        :class="field.newValue !== undefined && field.newValue !== null
                                            ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300'
                                            : 'text-gray-400 dark:text-gray-500 italic'"
                                    >
                                        {{ formatValue(field.newValue) }}
                                    </span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600 flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-red-400"></span>
                    Odstránené/Predtým
                </span>
                <span class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-green-400"></span>
                    Pridané/Potom
                </span>
                <span class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                    Zmenená hodnota
                </span>
            </div>
        </div>

        <!-- Metadata -->
        <div v-if="log.metadata && Object.keys(log.metadata).length > 0" class="card mt-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Doplnkové informácie
            </h2>

            <div class="grid gap-3">
                <div
                    v-for="(value, key) in log.metadata"
                    :key="key"
                    class="flex items-start gap-4 py-2 border-b border-gray-100 dark:border-gray-700 last:border-0"
                >
                    <dt class="text-gray-500 dark:text-gray-400 font-medium min-w-[120px]">{{ getFieldLabel(key) }}:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        <template v-if="isComplexValue(value)">
                            <details class="cursor-pointer">
                                <summary class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Zobraziť údaje
                                </summary>
                                <pre class="mt-2 text-xs bg-gray-50 dark:bg-gray-700 p-2 rounded overflow-auto max-h-40">{{ JSON.stringify(value, null, 2) }}</pre>
                            </details>
                        </template>
                        <template v-else>
                            {{ formatValue(value) }}
                        </template>
                    </dd>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
