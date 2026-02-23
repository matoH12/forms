<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    workflow: Object,
    executions: Object,
    auth: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Čaká',
        running: 'Beží',
        waiting_approval: 'Čaká na schválenie',
        completed: 'Dokončené',
        failed: 'Zlyhalo',
        rejected: 'Zamietnuté',
    };
    return labels[status] || status;
};
</script>

<template>
    <Head :title="`História: ${workflow.name}`" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <Link href="/admin/workflows" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 text-sm">
                    ← Späť na zoznam
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 truncate">História: {{ workflow.name }}</h1>
            </div>
            <Link :href="`/admin/workflows/${workflow.id}/edit`" class="btn btn-secondary flex-shrink-0">
                Upraviť workflow
            </Link>
        </div>

        <div class="card">
            <table class="w-full">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Odpoveď</th>
                        <th class="py-3 px-4">Stav</th>
                        <th class="py-3 px-4">Spustené</th>
                        <th class="py-3 px-4">Dokončené</th>
                        <th class="py-3 px-4 text-right">Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="execution in executions.data" :key="execution.id" class="border-b last:border-0 hover:bg-gray-50">
                        <td class="py-3 px-4 text-gray-500">#{{ execution.id }}</td>
                        <td class="py-3 px-4">
                            <Link :href="`/admin/submissions/${execution.submission_id}`" class="text-blue-600 hover:underline">
                                Odpoveď #{{ execution.submission_id }}
                            </Link>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': execution.status === 'completed',
                                    'bg-yellow-100 text-yellow-800': execution.status === 'waiting_approval',
                                    'bg-blue-100 text-blue-800': ['running', 'pending'].includes(execution.status),
                                    'bg-red-100 text-red-800': ['failed', 'rejected'].includes(execution.status),
                                }"
                            >
                                {{ getStatusLabel(execution.status) }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-500">
                            {{ execution.started_at ? formatDate(execution.started_at) : '-' }}
                        </td>
                        <td class="py-3 px-4 text-gray-500">
                            {{ execution.completed_at ? formatDate(execution.completed_at) : '-' }}
                        </td>
                        <td class="py-3 px-4 text-right">
                            <Link :href="`/admin/submissions/${execution.submission_id}`" class="text-blue-600 hover:underline">
                                Detail
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!executions.data.length" class="text-center py-12 text-gray-500">
                Zatiaľ žiadne vykonania tohto workflow.
            </div>
        </div>
    </AdminLayout>
</template>
