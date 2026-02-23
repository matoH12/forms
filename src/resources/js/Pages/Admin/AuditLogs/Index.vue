<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    logs: Object,
    actions: Array,
    filters: Object,
    auth: Object,
});

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get('/admin/audit-logs', localFilters.value, { preserveState: true });
};

const clearFilters = () => {
    localFilters.value = {};
    applyFilters();
};

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

const actionColors = {
    form_created: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    form_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    form_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    form_submitted: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    submission_approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    submission_rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    submission_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    workflow_created: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    workflow_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    workflow_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    workflow_executed: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
    user_login: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
    user_logout: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
    user_updated: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    user_admin_toggled: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    user_deleted: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    settings_updated: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    approval_approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    approval_rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
};

const getActionLabel = (action) => actionLabels[action] || action;
const getActionColor = (action) => actionColors[action] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300';

const formatModelType = (type) => {
    if (!type) return '-';
    const parts = type.split('\\');
    return parts[parts.length - 1];
};

const hasFilters = computed(() => {
    return Object.values(localFilters.value).some(v => v);
});
</script>

<template>
    <Head title="Audit Log" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Audit Log</h1>
            <span class="text-gray-500 dark:text-gray-400">Celkom záznamov: {{ logs.total }}</span>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="form-label">Typ akcie</label>
                    <select v-model="localFilters.action" class="form-input">
                        <option value="">Všetky akcie</option>
                        <option v-for="action in actions" :key="action" :value="action">
                            {{ getActionLabel(action) }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Od dátumu</label>
                    <input
                        v-model="localFilters.date_from"
                        type="date"
                        class="form-input"
                    />
                </div>
                <div>
                    <label class="form-label">Do dátumu</label>
                    <input
                        v-model="localFilters.date_to"
                        type="date"
                        class="form-input"
                    />
                </div>
                <div class="flex gap-2">
                    <button @click="applyFilters" class="btn btn-primary flex-1">
                        Filtrovať
                    </button>
                    <button
                        v-if="hasFilters"
                        @click="clearFilters"
                        class="btn btn-secondary"
                    >
                        Zrušiť
                    </button>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-600 text-left bg-gray-50 dark:bg-gray-700">
                            <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Čas</th>
                            <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Používateľ</th>
                            <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Akcia</th>
                            <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Objekt</th>
                            <th class="py-3 px-4 text-gray-900 dark:text-gray-100">IP Adresa</th>
                            <th class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="border-b border-gray-200 dark:border-gray-600 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <td class="py-3 px-4 whitespace-nowrap text-gray-500 dark:text-gray-400 text-sm">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="py-3 px-4">
                                <div v-if="log.user" class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium text-sm">
                                        {{ log.user.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-sm text-gray-900 dark:text-gray-100">
                                            {{ log.user.first_name && log.user.last_name
                                                ? `${log.user.first_name} ${log.user.last_name}`
                                                : log.user.name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <span v-if="log.user.login" class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1">{{ log.user.login }}</span>
                                            {{ log.user.email }}
                                        </p>
                                    </div>
                                </div>
                                <span v-else class="text-gray-400 dark:text-gray-500 italic">Anonymný</span>
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full font-medium"
                                    :class="getActionColor(log.action)"
                                >
                                    {{ getActionLabel(log.action) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm">
                                <span v-if="log.model_type" class="text-gray-600 dark:text-gray-400">
                                    {{ formatModelType(log.model_type) }}
                                    <span v-if="log.model_id" class="text-gray-400 dark:text-gray-500">#{{ log.model_id }}</span>
                                </span>
                                <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ log.ip_address || '-' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <Link
                                    :href="`/admin/audit-logs/${log.id}`"
                                    class="px-3 py-1 text-sm bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 rounded"
                                >
                                    Detail
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!logs.data.length" class="text-center py-12 text-gray-500 dark:text-gray-400">
                Žiadne záznamy neboli nájdené.
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="mt-4 p-4 border-t border-gray-200 dark:border-gray-600 flex justify-center gap-2">
                <Link
                    v-if="logs.current_page > 1"
                    :href="`/admin/audit-logs?page=${logs.current_page - 1}`"
                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                    preserve-state
                >
                    Predchádzajúca
                </Link>
                <span class="px-3 py-1 text-gray-600 dark:text-gray-400">
                    Strana {{ logs.current_page }} z {{ logs.last_page }}
                </span>
                <Link
                    v-if="logs.current_page < logs.last_page"
                    :href="`/admin/audit-logs?page=${logs.current_page + 1}`"
                    class="px-3 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                    preserve-state
                >
                    Nasledujúca
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
