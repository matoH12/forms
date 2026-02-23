<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, watch } from 'vue';
import { useLocalized } from '@/composables/useLocalized';

const props = defineProps({
    submissions: Object,
    forms: Array,
    filters: Object,
    counts: Object,
    auth: Object,
});

const { getLocalized } = useLocalized();

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    return getLocalized(form.name) || form.slug || '';
};

const localFilters = ref({ ...props.filters });

// Modal state
const showModal = ref(false);
const modalAction = ref('');
const selectedSubmission = ref(null);
const adminResponse = ref('');

// Bulk selection state
const selectedIds = ref([]);
const isBulkMode = ref(false);

// Computed for select all checkbox
const allSelected = computed(() => {
    return props.submissions.data.length > 0 &&
        selectedIds.value.length === props.submissions.data.length;
});

const someSelected = computed(() => {
    return selectedIds.value.length > 0 &&
        selectedIds.value.length < props.submissions.data.length;
});

// Clear selection when page changes or filters change
watch(() => props.submissions.data, () => {
    selectedIds.value = [];
});

const toggleSelection = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index > -1) {
        selectedIds.value.splice(index, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.submissions.data.map(s => s.id);
    }
};

const clearSelection = () => {
    selectedIds.value = [];
    isBulkMode.value = false;
};

// Bulk modal state
const showBulkModal = ref(false);
const bulkAction = ref('');
const bulkResponse = ref('');
const bulkProcessing = ref(false);

const openBulkApproveModal = () => {
    bulkAction.value = 'approve';
    bulkResponse.value = '';
    showBulkModal.value = true;
};

const openBulkRejectModal = () => {
    bulkAction.value = 'reject';
    bulkResponse.value = '';
    showBulkModal.value = true;
};

const closeBulkModal = () => {
    showBulkModal.value = false;
    bulkAction.value = '';
    bulkResponse.value = '';
};

const submitBulkAction = () => {
    bulkProcessing.value = true;
    const endpoint = bulkAction.value === 'approve'
        ? '/admin/submissions/bulk-approve'
        : '/admin/submissions/bulk-reject';

    router.post(endpoint, {
        ids: selectedIds.value,
        admin_response: bulkResponse.value,
    }, {
        onSuccess: () => {
            closeBulkModal();
            clearSelection();
        },
        onFinish: () => {
            bulkProcessing.value = false;
        },
    });
};

const bulkDelete = () => {
    if (confirm(`Naozaj chcete zmazat ${selectedIds.value.length} odpovedi?`)) {
        router.post('/admin/submissions/bulk-delete', {
            ids: selectedIds.value,
        }, {
            onSuccess: () => {
                clearSelection();
            },
        });
    }
};

const setStatusFilter = (status) => {
    localFilters.value.status = status;
    applyFilters();
};

const applyFilters = () => {
    router.get('/admin/submissions', localFilters.value, { preserveState: true });
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

const formatDateShort = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
    });
};

const openApproveModal = (submission) => {
    selectedSubmission.value = submission;
    modalAction.value = 'approve';
    adminResponse.value = '';
    showModal.value = true;
};

const openRejectModal = (submission) => {
    selectedSubmission.value = submission;
    modalAction.value = 'reject';
    adminResponse.value = '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedSubmission.value = null;
    adminResponse.value = '';
};

const submitAction = () => {
    const endpoint = `/admin/submissions/${selectedSubmission.value.id}/${modalAction.value}`;
    router.post(endpoint, { admin_response: adminResponse.value }, {
        onSuccess: () => closeModal(),
    });
};

const deleteSubmission = (submission) => {
    if (confirm('Naozaj chcete zmazat tuto odpoved?')) {
        router.post(`/admin/submissions/${submission.id}`, { _method: 'delete' });
    }
};

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Nova',
        submitted: 'Nova',
        approved: 'Schvalena',
        rejected: 'Zamietnuta',
        processing: 'Spracovava sa',
    };
    return labels[status] || 'Nova';
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
        submitted: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
        approved: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
        rejected: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
        processing: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    };
    return classes[status] || 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
};

const getFieldPreview = (data) => {
    if (!data) return '-';
    const entries = Object.entries(data);
    if (entries.length === 0) return '-';
    const [key, value] = entries[0];
    const displayValue = Array.isArray(value) ? value.join(', ') : String(value);
    return displayValue.length > 30 ? displayValue.substring(0, 30) + '...' : displayValue;
};

const showExportMenu = ref(false);

const getExportUrl = (format) => {
    const params = new URLSearchParams();
    params.append('format', format);
    if (localFilters.value.status && localFilters.value.status !== 'all') {
        params.append('status', localFilters.value.status);
    }
    if (localFilters.value.form_id) {
        params.append('form_id', localFilters.value.form_id);
    }
    return `/admin/submissions/export?${params.toString()}`;
};

const exportData = (format) => {
    window.location.href = getExportUrl(format);
    showExportMenu.value = false;
};
</script>

<template>
    <Head title="Odpovede" />
    <AdminLayout :auth="auth">
        <div class="mb-4 md:mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-gray-100">Odpovede</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm md:text-base">Spravujte odpovede z formularov</p>
            </div>
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
                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 z-10"
                >
                    <button
                        @click="exportData('csv')"
                        class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center text-gray-700 dark:text-gray-200"
                    >
                        <svg class="w-5 h-5 mr-3 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export do CSV
                    </button>
                    <button
                        v-if="localFilters.form_id"
                        @click="exportData('xlsx')"
                        class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center border-t border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200"
                    >
                        <svg class="w-5 h-5 mr-3 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export do Excel
                    </button>
                    <p v-if="!localFilters.form_id" class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-600">
                        Excel export je dostupny len pre konkretny formular
                    </p>
                </div>
                <!-- Backdrop to close menu -->
                <div
                    v-if="showExportMenu"
                    class="fixed inset-0 z-0"
                    @click="showExportMenu = false"
                />
            </div>
        </div>

        <!-- Status tabs - scrollable on mobile -->
        <div class="flex gap-2 mb-4 md:mb-6 overflow-x-auto pb-2 -mx-4 px-4 md:mx-0 md:px-0">
            <button
                @click="setStatusFilter('pending')"
                class="px-3 md:px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 whitespace-nowrap text-sm md:text-base flex-shrink-0"
                :class="filters.status === 'pending'
                    ? 'bg-blue-600 text-white'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600'"
            >
                Nove
                <span class="px-2 py-0.5 text-xs rounded-full"
                    :class="filters.status === 'pending' ? 'bg-blue-500' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300'">
                    {{ counts?.pending || 0 }}
                </span>
            </button>
            <button
                @click="setStatusFilter('approved')"
                class="px-3 md:px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 whitespace-nowrap text-sm md:text-base flex-shrink-0"
                :class="filters.status === 'approved'
                    ? 'bg-green-600 text-white'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600'"
            >
                Schvalene
                <span class="px-2 py-0.5 text-xs rounded-full"
                    :class="filters.status === 'approved' ? 'bg-green-500' : 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300'">
                    {{ counts?.approved || 0 }}
                </span>
            </button>
            <button
                @click="setStatusFilter('rejected')"
                class="px-3 md:px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 whitespace-nowrap text-sm md:text-base flex-shrink-0"
                :class="filters.status === 'rejected'
                    ? 'bg-red-600 text-white'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600'"
            >
                Zamietnute
                <span class="px-2 py-0.5 text-xs rounded-full"
                    :class="filters.status === 'rejected' ? 'bg-red-500' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'">
                    {{ counts?.rejected || 0 }}
                </span>
            </button>
            <button
                @click="setStatusFilter('all')"
                class="px-3 md:px-4 py-2 rounded-lg font-medium transition-colors whitespace-nowrap text-sm md:text-base flex-shrink-0"
                :class="filters.status === 'all'
                    ? 'bg-gray-600 text-white'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600'"
            >
                Vsetky
            </button>
        </div>

        <!-- Bulk Action Bar -->
        <div
            v-if="selectedIds.length > 0"
            class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-40 bg-gray-900 dark:bg-gray-700 text-white px-4 py-3 rounded-lg shadow-xl flex items-center gap-4"
        >
            <span class="font-medium">
                Vybranych: {{ selectedIds.length }}
            </span>
            <div class="h-4 w-px bg-gray-600"></div>
            <button
                @click="openBulkApproveModal"
                class="px-3 py-1.5 bg-green-500 hover:bg-green-600 rounded text-sm font-medium"
            >
                Schvalit vsetky
            </button>
            <button
                @click="openBulkRejectModal"
                class="px-3 py-1.5 bg-red-500 hover:bg-red-600 rounded text-sm font-medium"
            >
                Zamietnut vsetky
            </button>
            <button
                @click="bulkDelete"
                class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium"
            >
                Zmazat
            </button>
            <div class="h-4 w-px bg-gray-600"></div>
            <button
                @click="clearSelection"
                class="p-1.5 hover:bg-gray-700 dark:hover:bg-gray-600 rounded"
                title="Zrusit vyber"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Filters -->
        <div class="card mb-4 md:mb-6">
            <div class="flex flex-col md:flex-row gap-3 md:gap-4 md:items-end">
                <div class="w-full md:w-64">
                    <label class="form-label">Formular</label>
                    <select v-model="localFilters.form_id" class="form-input" @change="applyFilters">
                        <option value="">Vsetky formulare</option>
                        <option v-for="form in forms" :key="form.id" :value="form.id">
                            {{ getFormName(form) }}
                        </option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="form-label">Hladat</label>
                    <input
                        v-model="localFilters.search"
                        type="text"
                        class="form-input"
                        placeholder="Hladat v odpovediach..."
                        @keyup.enter="applyFilters"
                    />
                </div>
                <button @click="applyFilters" class="btn btn-secondary w-full md:w-auto">
                    Hladat
                </button>
            </div>
        </div>

        <!-- Submissions list -->
        <div class="card">
            <!-- Select All Header -->
            <div v-if="submissions.data.length" class="px-4 py-2 border-b border-gray-200 dark:border-gray-600 flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="allSelected"
                        :indeterminate="someSelected"
                        @change="toggleSelectAll"
                        class="w-4 h-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
                    />
                    <span class="text-sm text-gray-600 dark:text-gray-300">
                        {{ allSelected ? 'Zrusit vyber' : 'Vybrat vsetko' }}
                    </span>
                </label>
                <span v-if="selectedIds.length > 0" class="text-sm text-gray-500 dark:text-gray-400">
                    ({{ selectedIds.length }} vybranych)
                </span>
            </div>

            <div v-if="submissions.data.length" class="divide-y divide-gray-200 dark:divide-gray-600 -mx-4 md:mx-0">
                <div
                    v-for="submission in submissions.data"
                    :key="submission.id"
                    class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700"
                    :class="{ 'bg-blue-50 dark:bg-blue-900/20': selectedIds.includes(submission.id) }"
                >
                    <!-- Mobile layout -->
                    <div class="flex flex-col gap-3">
                        <!-- Header row -->
                        <div class="flex items-start justify-between gap-2">
                            <!-- Checkbox -->
                            <div class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(submission.id)"
                                    @change="toggleSelection(submission.id)"
                                    class="w-4 h-4 mt-1 text-blue-600 rounded border-gray-300 dark:border-gray-600 focus:ring-blue-500"
                                />
                                <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-900 dark:text-gray-100 text-sm md:text-base">
                                        {{ submission.user?.first_name && submission.user?.last_name
                                            ? `${submission.user.first_name} ${submission.user.last_name}`
                                            : (submission.user?.name || 'Anonymný') }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 text-xs rounded-full font-medium"
                                        :class="getStatusClass(submission.status)"
                                    >
                                        {{ getStatusLabel(submission.status) }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="submission.user_login" class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded mr-2">{{ submission.user_login }}</span>
                                    <span v-if="submission.user?.email">{{ submission.user.email }}</span>
                                </div>
                                <div class="flex items-center gap-2 md:gap-4 text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1 flex-wrap">
                                    <span>{{ getFormName(submission.form) }}</span>
                                    <span class="hidden md:inline">{{ formatDate(submission.created_at) }}</span>
                                    <span class="md:hidden">{{ formatDateShort(submission.created_at) }}</span>
                                </div>
                                <p class="text-xs md:text-sm text-gray-600 dark:text-gray-300 mt-1 truncate">
                                    {{ getFieldPreview(submission.data) }}
                                </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions row -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <button
                                v-if="submission.status !== 'approved'"
                                @click="openApproveModal(submission)"
                                class="px-3 py-1.5 text-xs md:text-sm bg-green-500 text-white hover:bg-green-600 rounded font-medium"
                            >
                                Schvalit
                            </button>
                            <button
                                v-if="submission.status !== 'rejected'"
                                @click="openRejectModal(submission)"
                                class="px-3 py-1.5 text-xs md:text-sm bg-red-500 text-white hover:bg-red-600 rounded font-medium"
                            >
                                Zamietnut
                            </button>
                            <Link
                                :href="`/admin/submissions/${submission.id}`"
                                class="px-3 py-1.5 text-xs md:text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 rounded font-medium"
                            >
                                Detail
                            </Link>
                            <button
                                @click="deleteSubmission(submission)"
                                class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 rounded ml-auto"
                                title="Zmazat"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-8 md:py-12 text-gray-500 dark:text-gray-400">
                <svg class="w-10 h-10 md:w-12 md:h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p v-if="filters.status === 'pending'">Ziadne nove odpovede</p>
                <p v-else-if="filters.status === 'approved'">Ziadne schvalene odpovede</p>
                <p v-else-if="filters.status === 'rejected'">Ziadne zamietnute odpovede</p>
                <p v-else>Ziadne odpovede neboli najdene</p>
            </div>

            <!-- Pagination -->
            <div v-if="submissions.last_page > 1" class="mt-4 flex justify-center gap-1 md:gap-2 pt-4 border-t border-gray-200 dark:border-gray-600 flex-wrap">
                <Link
                    v-for="page in submissions.last_page"
                    :key="page"
                    :href="`/admin/submissions?page=${page}&status=${filters.status || ''}`"
                    class="px-2 md:px-3 py-1 rounded text-sm"
                    :class="page === submissions.current_page ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >
                    {{ page }}
                </Link>
            </div>
        </div>

        <!-- Approve/Reject Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-4 md:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ modalAction === 'approve' ? 'Schvalit ziadost' : 'Zamietnut ziadost' }}
                    </h3>

                    <div class="mb-4">
                        <label class="form-label">
                            Odpoved pre ziada (volitelne)
                        </label>
                        <textarea
                            v-model="adminResponse"
                            class="form-input"
                            rows="4"
                            :placeholder="modalAction === 'approve'
                                ? 'Napriklad: Vasa ziadost bola schvalena...'
                                : 'Napriklad: Ziadost bola zamietnuta z dovodu...'"
                        ></textarea>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button
                            @click="closeModal"
                            class="btn btn-secondary"
                        >
                            Zrusit
                        </button>
                        <button
                            @click="submitAction"
                            class="btn"
                            :class="modalAction === 'approve' ? 'btn-success' : 'btn-danger'"
                        >
                            {{ modalAction === 'approve' ? 'Schvalit' : 'Zamietnut' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Approve/Reject Modal -->
        <div v-if="showBulkModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-4 md:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ bulkAction === 'approve' ? 'Hromadne schvalenie' : 'Hromadne zamietnutie' }}
                    </h3>

                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        {{ bulkAction === 'approve'
                            ? `Schvalujete ${selectedIds.length} odpovedi`
                            : `Zamietate ${selectedIds.length} odpovedi`
                        }}
                    </p>

                    <div class="mb-4">
                        <label class="form-label">
                            Odpoved pre ziadatelov (volitelne)
                        </label>
                        <textarea
                            v-model="bulkResponse"
                            class="form-input"
                            rows="4"
                            :placeholder="bulkAction === 'approve'
                                ? 'Napriklad: Vasa ziadost bola schvalena...'
                                : 'Napriklad: Ziadost bola zamietnuta z dovodu...'"
                        ></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Tato odpoved bude odoslana vsetkym ziadatelom
                        </p>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button
                            @click="closeBulkModal"
                            class="btn btn-secondary"
                            :disabled="bulkProcessing"
                        >
                            Zrusit
                        </button>
                        <button
                            @click="submitBulkAction"
                            class="btn"
                            :class="bulkAction === 'approve' ? 'btn-success' : 'btn-danger'"
                            :disabled="bulkProcessing"
                        >
                            <span v-if="bulkProcessing">Spracovava sa...</span>
                            <span v-else>
                                {{ bulkAction === 'approve' ? `Schvalit ${selectedIds.length}` : `Zamietnut ${selectedIds.length}` }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
