<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useLocalized } from '@/composables/useLocalized';

const props = defineProps({
    form: Object,
    submissions: Object,
    filters: Object,
    auth: Object,
});

const { getLocalized } = useLocalized();

// Helper to get form name (handles both string and object)
const getFormName = () => {
    return getLocalized(props.form.name) || props.form.slug;
};

const localFilters = ref({ ...props.filters });
const expandedSubmissions = ref({});

const applyFilters = () => {
    router.get(`/admin/forms/${props.form.id}/submissions`, localFilters.value, { preserveState: true });
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

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Čakajúca',
        approved: 'Schválená',
        rejected: 'Zamietnutá',
        processing: 'Spracováva sa',
    };
    return labels[status] || 'Nová';
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
        approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
        rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
        processing: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    };
    return classes[status] || 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
};

const toggleExpand = (id) => {
    expandedSubmissions.value[id] = !expandedSubmissions.value[id];
};

const approveSubmission = (submission) => {
    if (confirm('Naozaj chcete schváliť túto odpoveď?')) {
        router.post(`/admin/forms/${props.form.id}/submissions/${submission.id}/approve`);
    }
};

const rejectSubmission = (submission) => {
    if (confirm('Naozaj chcete zamietnuť túto odpoveď?')) {
        router.post(`/admin/forms/${props.form.id}/submissions/${submission.id}/reject`);
    }
};

const deleteSubmission = (submission) => {
    if (confirm('Naozaj chcete zmazať túto odpoveď? Táto akcia je nevratná.')) {
        router.post(`/admin/forms/${props.form.id}/submissions/${submission.id}`, { _method: 'delete' });
    }
};

const getFieldLabel = (fieldName) => {
    const field = props.form.schema?.fields?.find(f => f.name === fieldName);
    if (field?.label) {
        return getLocalized(field.label);
    }

    // If key itself is a JSON multilingual object string, parse and localize it
    if (typeof fieldName === 'string' && fieldName.startsWith('{')) {
        try {
            const parsed = JSON.parse(fieldName);
            if (parsed && typeof parsed === 'object' && (parsed.sk || parsed.en)) {
                return getLocalized(parsed);
            }
        } catch (e) {
            // Not valid JSON
        }
    }

    return fieldName;
};

const stats = computed(() => {
    const data = props.submissions.data;
    return {
        total: props.submissions.total,
        pending: data.filter(s => !s.status || s.status === 'pending').length,
        approved: data.filter(s => s.status === 'approved').length,
        rejected: data.filter(s => s.status === 'rejected').length,
    };
});

const showExportMenu = ref(false);

const getExportUrl = (format) => {
    const params = new URLSearchParams();
    params.append('format', format);
    if (localFilters.value.status) {
        params.append('status', localFilters.value.status);
    }
    return `/admin/forms/${props.form.id}/submissions/export?${params.toString()}`;
};

const exportData = (format) => {
    window.location.href = getExportUrl(format);
    showExportMenu.value = false;
};
</script>

<template>
    <Head :title="`Odpovede: ${form.name}`" />
    <AdminLayout :auth="auth">
        <div class="mb-6">
            <Link href="/admin/forms" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-sm">
                ← Späť na formuláre
            </Link>
            <div class="flex items-center justify-between mt-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Odpovede: {{ getFormName() }}</h1>
                    <p class="text-gray-500 dark:text-gray-400">/forms/{{ form.slug }}</p>
                </div>
                <div class="flex gap-2">
                    <!-- Export dropdown -->
                    <div class="relative" v-if="submissions.data.length">
                        <button
                            @click="showExportMenu = !showExportMenu"
                            class="btn btn-secondary inline-flex items-center"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            v-if="showExportMenu"
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-10"
                        >
                            <button
                                @click="exportData('csv')"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center text-gray-700 dark:text-gray-300"
                            >
                                <svg class="w-5 h-5 mr-3 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export do CSV
                            </button>
                            <button
                                @click="exportData('xlsx')"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center border-t border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300"
                            >
                                <svg class="w-5 h-5 mr-3 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export do Excel
                            </button>
                        </div>
                        <!-- Backdrop to close menu -->
                        <div
                            v-if="showExportMenu"
                            class="fixed inset-0 z-0"
                            @click="showExportMenu = false"
                        />
                    </div>
                    <Link :href="`/admin/forms/${form.id}/edit`" class="btn btn-secondary">
                        Upraviť formulár
                    </Link>
                    <Link :href="`/forms/${form.slug}`" target="_blank" class="btn btn-secondary">
                        Zobraziť formulár
                    </Link>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.total }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Celkom</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.pending }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Čakajúce</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.approved }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Schválené</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.rejected }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Zamietnuté</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="flex gap-4 items-end">
                <div class="w-48">
                    <label class="form-label">Stav</label>
                    <select v-model="localFilters.status" class="form-input">
                        <option value="">Všetky</option>
                        <option value="pending">Čakajúce</option>
                        <option value="approved">Schválené</option>
                        <option value="rejected">Zamietnuté</option>
                    </select>
                </div>
                <button @click="applyFilters" class="btn btn-primary">
                    Filtrovať
                </button>
            </div>
        </div>

        <!-- Submissions list -->
        <div class="card">
            <div v-if="submissions.data.length" class="space-y-4">
                <div
                    v-for="submission in submissions.data"
                    :key="submission.id"
                    class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
                >
                    <!-- Header row -->
                    <div
                        class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                        @click="toggleExpand(submission.id)"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium">
                                {{ submission.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ submission.user?.name || 'Anonymný používateľ' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ submission.user?.email || submission.ip_address }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span
                                class="px-2 py-1 text-xs rounded-full font-medium"
                                :class="getStatusClass(submission.status)"
                            >
                                {{ getStatusLabel(submission.status) }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(submission.created_at) }}
                            </span>
                            <svg
                                class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-transform"
                                :class="{ 'rotate-180': expandedSubmissions[submission.id] }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Expanded content -->
                    <div v-if="expandedSubmissions[submission.id]" class="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <!-- Submission data -->
                        <div class="mb-4">
                            <h3 class="font-medium mb-2 text-gray-900 dark:text-gray-100">Odpovede:</h3>
                            <div class="grid gap-2">
                                <div
                                    v-for="(value, key) in submission.data"
                                    :key="key"
                                    class="grid grid-cols-3 gap-4 py-2 border-b border-gray-200 dark:border-gray-700 last:border-0"
                                >
                                    <span class="text-gray-600 dark:text-gray-400 font-medium">{{ getFieldLabel(key) }}</span>
                                    <span class="col-span-2 text-gray-900 dark:text-gray-100">
                                        <template v-if="Array.isArray(value)">
                                            {{ value.join(', ') }}
                                        </template>
                                        <template v-else-if="typeof value === 'boolean'">
                                            {{ value ? 'Áno' : 'Nie' }}
                                        </template>
                                        <template v-else>
                                            {{ value || '-' }}
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                v-if="submission.status !== 'approved'"
                                @click.stop="approveSubmission(submission)"
                                class="px-4 py-2 text-sm bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/50 rounded font-medium"
                            >
                                Schváliť
                            </button>
                            <button
                                v-if="submission.status !== 'rejected'"
                                @click.stop="rejectSubmission(submission)"
                                class="px-4 py-2 text-sm bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 rounded font-medium"
                            >
                                Zamietnuť
                            </button>
                            <button
                                @click.stop="deleteSubmission(submission)"
                                class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded font-medium"
                            >
                                Zmazať
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>Zatiaľ žiadne odpovede</p>
                <p class="text-sm mt-1">Odpovede sa tu zobrazia po odoslaní formulára</p>
            </div>

            <!-- Pagination -->
            <div v-if="submissions.last_page > 1" class="mt-4 flex justify-center gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                <Link
                    v-for="page in submissions.last_page"
                    :key="page"
                    :href="`/admin/forms/${form.id}/submissions?page=${page}`"
                    class="px-3 py-1 rounded"
                    :class="page === submissions.current_page ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >
                    {{ page }}
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
