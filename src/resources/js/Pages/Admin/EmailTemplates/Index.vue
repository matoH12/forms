<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    templates: Object,
});

const decodePaginationLabel = (label) => {
    const txt = document.createElement('textarea');
    txt.innerHTML = label;
    return txt.value;
};
</script>

<template>
    <AdminLayout>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Emailove sablony</h1>
                <p class="text-gray-600 dark:text-gray-400">Spravujte emailove sablony pre potvrdzujuce emaily</p>
            </div>
            <Link
                href="/admin/email-templates/create"
                class="btn btn-primary flex-shrink-0"
            >
                Nová šablóna
            </Link>
        </div>

        <div v-if="templates.data.length" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Nazov
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Predmet
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Pouzite
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Stav
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Akcie
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="template in templates.data" :key="template.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ template.name }}
                                        <span v-if="template.is_default" class="ml-2 px-2 py-0.5 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                                            Predvolena
                                        </span>
                                        <span v-if="template.system_type" class="ml-2 px-2 py-0.5 text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded-full">
                                            Systemova
                                        </span>
                                    </div>
                                    <div v-if="template.creator" class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ template.creator.name }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900 dark:text-white">{{ template.subject }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ template.forms_count }} {{ template.forms_count === 1 ? 'formular' : 'formularov' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="template.is_active
                                    ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'"
                                class="px-2 py-1 text-xs rounded-full"
                            >
                                {{ template.is_active ? 'Aktivna' : 'Neaktivna' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <Link
                                :href="`/admin/email-templates/${template.id}/edit`"
                                class="text-brand-primary hover:text-brand-primary-dark dark:text-brand-gold dark:hover:text-brand-gold-light"
                            >
                                Upravit
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="templates.links && templates.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <nav class="flex justify-center gap-1">
                    <template v-for="link in templates.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 text-sm rounded transition-colors"
                            :class="link.active
                                ? 'bg-brand-gold text-brand-navy'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                        >{{ decodePaginationLabel(link.label) }}</Link>
                        <span
                            v-else
                            class="px-3 py-1 text-sm text-gray-400 dark:text-gray-500"
                        >{{ decodePaginationLabel(link.label) }}</span>
                    </template>
                </nav>
            </div>
        </div>

        <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Ziadne emailove sablony</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Vytvorte svoju prvu sablonu pre potvrdzujuce emaily</p>
            <Link
                href="/admin/email-templates/create"
                class="btn btn-primary"
            >
                Vytvoriť šablónu
            </Link>
        </div>
    </AdminLayout>
</template>
