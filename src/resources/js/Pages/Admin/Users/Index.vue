<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    users: Object,
    filters: Object,
    auth: Object,
    roles: Array,
});

const localFilters = ref({ ...props.filters });

const applyFilters = () => {
    router.get('/admin/users', localFilters.value, { preserveState: true });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'super_admin': return t('users.roles.super_admin');
        case 'admin': return t('users.roles.admin');
        case 'approver': return t('users.roles.approver');
        case 'viewer': return t('users.roles.viewer');
        default: return t('users.roles.user');
    }
};

const getRoleColor = (role) => {
    switch (role) {
        case 'super_admin': return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
        case 'admin': return 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300';
        case 'approver': return 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
        case 'viewer': return 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
        default: return 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300';
    }
};

const deleteUser = (user) => {
    if (confirm(`Naozaj chcete zmazať používateľa "${user.name}"?`)) {
        router.post(`/admin/users/${user.id}`, { _method: 'delete' });
    }
};

// Stats by role
const statsByRole = computed(() => {
    const stats = {};
    props.users.data.forEach(user => {
        stats[user.role] = (stats[user.role] || 0) + 1;
    });
    return stats;
});
</script>

<template>
    <Head title="Používatelia" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Používatelia</h1>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="form-label">{{ t('common.search') }}</label>
                    <input
                        v-model="localFilters.search"
                        type="text"
                        class="form-input"
                        :placeholder="t('users.searchPlaceholder')"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div class="w-48">
                    <label class="form-label">{{ t('users.role') }}</label>
                    <select v-model="localFilters.role" class="form-input">
                        <option value="">{{ t('common.all') }}</option>
                        <option value="super_admin">{{ t('users.roles.super_admin') }}</option>
                        <option value="admin">{{ t('users.roles.admin') }}</option>
                        <option value="approver">{{ t('users.roles.approver') }}</option>
                        <option value="viewer">{{ t('users.roles.viewer') }}</option>
                        <option value="user">{{ t('users.roles.user') }}</option>
                    </select>
                </div>
                <button @click="applyFilters" class="btn btn-primary">
                    {{ t('common.filter') }}
                </button>
            </div>
        </div>

        <div class="card">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-600 text-left">
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Používateľ</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Email</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Rola</th>
                        <th class="py-3 px-4 text-gray-900 dark:text-gray-100">Registrovaný</th>
                        <th class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b border-gray-200 dark:border-gray-600 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium">
                                    {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ user.first_name && user.last_name
                                            ? `${user.first_name} ${user.last_name}`
                                            : user.name }}
                                    </p>
                                    <p v-if="user.id === auth.user.id" class="text-xs text-blue-600 dark:text-blue-400">(vy)</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div v-if="user.login" class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded inline-block mb-1">{{ user.login }}</div>
                            <div class="text-gray-600 dark:text-gray-400">{{ user.email }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <span
                                class="px-2 py-1 text-xs rounded-full font-medium"
                                :class="getRoleColor(user.role)"
                            >
                                {{ getRoleLabel(user.role) }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-500 dark:text-gray-400">{{ formatDate(user.created_at) }}</td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="`/admin/users/${user.id}/edit`"
                                    class="px-3 py-1 text-sm bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 rounded"
                                >
                                    {{ t('common.edit') }}
                                </Link>
                                <button
                                    v-if="user.id !== auth.user.id"
                                    @click="deleteUser(user)"
                                    class="px-3 py-1 text-sm bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 rounded"
                                >
                                    {{ t('common.delete') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!users.data.length" class="text-center py-12 text-gray-500 dark:text-gray-400">
                Žiadni používatelia neboli nájdení.
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="mt-4 flex justify-center gap-2">
                <Link
                    v-for="page in users.last_page"
                    :key="page"
                    :href="`/admin/users?page=${page}`"
                    class="px-3 py-1 rounded"
                    :class="page === users.current_page ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >
                    {{ page }}
                </Link>
            </div>
        </div>

        <!-- Stats -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ users.total }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.totalUsers') }}</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ statsByRole.super_admin || 0 }}
                </p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.roles.super_admin') }}</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {{ statsByRole.admin || 0 }}
                </p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.roles.admin') }}</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ statsByRole.approver || 0 }}
                </p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.roles.approver') }}</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ statsByRole.viewer || 0 }}
                </p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.roles.viewer') }}</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">
                    {{ statsByRole.user || 0 }}
                </p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('users.roles.user') }}</p>
            </div>
        </div>
    </AdminLayout>
</template>
