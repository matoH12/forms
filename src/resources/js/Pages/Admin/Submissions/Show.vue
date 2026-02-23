<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ImageLightbox from '@/Components/ImageLightbox.vue';
import { useLocalized } from '@/composables/useLocalized';

const props = defineProps({
    submission: Object,
    auth: Object,
});

// Role hierarchy for checking minimum role
const ROLE_HIERARCHY = {
    user: 0,
    viewer: 1,
    approver: 2,
    admin: 3,
    super_admin: 4,
};

const hasMinRole = (minRole) => {
    return (ROLE_HIERARCHY[props.auth?.user?.role] || 0) >= (ROLE_HIERARCHY[minRole] || 0);
};

// Workflow executions state (reactive copy for auto-refresh updates)
const workflowExecutions = ref([...(props.submission.workflow_executions || [])]);

// Auto-refresh state
const autoRefreshEnabled = ref(false);
const autoRefreshInterval = ref(null);
const lastRefreshTime = ref(null);
const isRefreshing = ref(false);

// Check if any execution is running (to show auto-refresh toggle)
const hasActiveExecution = computed(() => {
    return workflowExecutions.value.some(e =>
        ['running', 'pending', 'waiting_approval'].includes(e.status)
    );
});

const refreshWorkflowStatus = async () => {
    if (isRefreshing.value) return;

    isRefreshing.value = true;
    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/workflow-status`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            workflowExecutions.value = data.workflow_executions;
            lastRefreshTime.value = new Date();
        }
    } catch (error) {
        console.error('Failed to refresh workflow status:', error);
    } finally {
        isRefreshing.value = false;
    }
};

const toggleAutoRefresh = () => {
    autoRefreshEnabled.value = !autoRefreshEnabled.value;

    if (autoRefreshEnabled.value) {
        // Initial refresh
        refreshWorkflowStatus();
        // Start interval (every 3 seconds)
        autoRefreshInterval.value = setInterval(refreshWorkflowStatus, 3000);
    } else {
        // Stop interval
        if (autoRefreshInterval.value) {
            clearInterval(autoRefreshInterval.value);
            autoRefreshInterval.value = null;
        }
    }
};

// Stop workflow execution
const stoppingExecution = ref(null);

const stopWorkflowExecution = async (execution) => {
    if (!confirm('Naozaj chcete zastaviť tento workflow?')) return;

    stoppingExecution.value = execution.id;
    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/workflow-executions/${execution.id}/stop`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            // Update the execution in local state
            const index = workflowExecutions.value.findIndex(e => e.id === execution.id);
            if (index > -1) {
                workflowExecutions.value[index] = data.execution;
            }
        } else {
            const error = await response.json();
            alert(error.message || 'Nepodarilo sa zastaviť workflow');
        }
    } catch (error) {
        console.error('Failed to stop workflow:', error);
        alert('Nastala chyba pri zastavovaní workflow');
    } finally {
        stoppingExecution.value = null;
    }
};

// Restart workflow
const restartingWorkflow = ref(false);

const restartWorkflow = async () => {
    if (!confirm('Naozaj chcete spustiť workflow znova?')) return;

    restartingWorkflow.value = true;
    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/workflow-restart`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            // Add new execution to the list
            workflowExecutions.value.unshift(data.execution);
            // Enable auto-refresh
            if (!autoRefreshEnabled.value) {
                toggleAutoRefresh();
            }
        } else {
            const error = await response.json();
            alert(error.message || 'Nepodarilo sa spustiť workflow');
        }
    } catch (error) {
        console.error('Failed to restart workflow:', error);
        alert('Nastala chyba pri spúšťaní workflow');
    } finally {
        restartingWorkflow.value = false;
    }
};

// Cleanup on unmount
onUnmounted(() => {
    if (autoRefreshInterval.value) {
        clearInterval(autoRefreshInterval.value);
    }
});

// Approve/Reject functionality
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const adminResponse = ref('');
const runWorkflow = ref(true);
const processingAction = ref(false);
const currentStatus = ref(props.submission.status);

// Check if form has a workflow assigned
const hasWorkflow = computed(() => {
    return !!props.submission.form?.workflow_id;
});

const openApproveModal = () => {
    adminResponse.value = '';
    runWorkflow.value = true; // Default to running workflow
    showApproveModal.value = true;
};

const openRejectModal = () => {
    adminResponse.value = '';
    showRejectModal.value = true;
};

const closeModals = () => {
    showApproveModal.value = false;
    showRejectModal.value = false;
    adminResponse.value = '';
};

const approveSubmission = async () => {
    processingAction.value = true;
    try {
        const response = await fetch(`/admin/forms/${props.submission.form_id}/submissions/${props.submission.id}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                admin_response: adminResponse.value,
                run_workflow: runWorkflow.value,
            }),
        });

        if (response.ok) {
            currentStatus.value = 'approved';
            closeModals();
            router.reload();
        } else {
            alert('Nepodarilo sa schváliť odpoveď');
        }
    } catch (error) {
        alert('Nastala chyba pri schvaľovaní');
    } finally {
        processingAction.value = false;
    }
};

const rejectSubmission = async () => {
    processingAction.value = true;
    try {
        const response = await fetch(`/admin/forms/${props.submission.form_id}/submissions/${props.submission.id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ admin_response: adminResponse.value }),
        });

        if (response.ok) {
            currentStatus.value = 'rejected';
            closeModals();
            router.reload();
        } else {
            alert('Nepodarilo sa zamietnuť odpoveď');
        }
    } catch (error) {
        alert('Nastala chyba pri zamietnutí');
    } finally {
        processingAction.value = false;
    }
};

// Local reactive copy of submission data for immediate UI updates
const submissionData = reactive({ ...props.submission.data });

// Comments
const comments = ref([...(props.submission.comments || [])]);
const newComment = ref('');
const addingComment = ref(false);
const editingCommentId = ref(null);
const editCommentContent = ref('');

const addComment = async () => {
    if (!newComment.value.trim()) return;

    addingComment.value = true;

    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ content: newComment.value }),
        });

        if (response.ok) {
            const result = await response.json();
            comments.value.unshift(result.comment);
            newComment.value = '';
        } else {
            alert('Nepodarilo sa pridať komentár');
        }
    } catch (error) {
        alert('Nastala chyba pri pridávaní komentára');
    } finally {
        addingComment.value = false;
    }
};

const startEditComment = (comment) => {
    editingCommentId.value = comment.id;
    editCommentContent.value = comment.content;
};

const cancelEditComment = () => {
    editingCommentId.value = null;
    editCommentContent.value = '';
};

const saveEditComment = async (comment) => {
    if (!editCommentContent.value.trim()) return;

    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/comments/${comment.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-HTTP-Method-Override': 'PUT',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ content: editCommentContent.value }),
        });

        if (response.ok) {
            const result = await response.json();
            const index = comments.value.findIndex(c => c.id === comment.id);
            if (index > -1) {
                comments.value[index] = result.comment;
            }
            cancelEditComment();
        } else {
            alert('Nepodarilo sa upraviť komentár');
        }
    } catch (error) {
        alert('Nastala chyba pri úprave komentára');
    }
};

const deleteComment = async (comment) => {
    if (!confirm('Naozaj chcete zmazať tento komentár?')) return;

    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/comments/${comment.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-HTTP-Method-Override': 'DELETE',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        });

        if (response.ok) {
            comments.value = comments.value.filter(c => c.id !== comment.id);
        } else {
            alert('Nepodarilo sa zmazať komentár');
        }
    } catch (error) {
        alert('Nastala chyba pri mazaní komentára');
    }
};

const formatCommentDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const { getLocalized } = useLocalized();

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    return getLocalized(form.name) || form.slug || '';
};

// Get localized field label for submission data key
const getFieldDisplayLabel = (key) => {
    // Try to find the field in form schema by name
    const fields = props.submission.form?.schema?.fields || [];
    const field = fields.find(f => f.name === key);
    if (field?.label) {
        return getLocalized(field.label);
    }

    // If key itself is a JSON multilingual object string, parse and localize it
    if (typeof key === 'string' && key.startsWith('{')) {
        try {
            const parsed = JSON.parse(key);
            if (parsed && typeof parsed === 'object' && (parsed.sk || parsed.en)) {
                return getLocalized(parsed);
            }
        } catch (e) {
            // Not valid JSON, return as-is
        }
    }

    return key;
};

// Lightbox state
const lightboxOpen = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const openLightbox = (images, index = 0) => {
    lightboxImages.value = Array.isArray(images) ? images : [images];
    lightboxIndex.value = index;
    lightboxOpen.value = true;
};

const closeLightbox = () => {
    lightboxOpen.value = false;
};

// Delete file
const deletingFile = ref(null);

const deleteFile = async (fieldName, index = null) => {
    if (!confirm('Naozaj chcete zmazať tento súbor?')) {
        return;
    }

    deletingFile.value = `${fieldName}-${index}`;

    try {
        const response = await fetch(`/admin/submissions/${props.submission.id}/file`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-HTTP-Method-Override': 'DELETE',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ field: fieldName, index: index }),
        });

        if (response.ok) {
            const result = await response.json();
            // Update local data
            Object.assign(submissionData, result.data);
        } else {
            alert('Nepodarilo sa zmazať súbor');
        }
    } catch (error) {
        alert('Nastala chyba pri mazaní súboru');
    } finally {
        deletingFile.value = null;
    }
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

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// Check if value is a file object
const isFileObject = (value) => {
    return value && typeof value === 'object' && value.path && value.original_name;
};

// Check if value is array of file objects
const isFileArray = (value) => {
    return Array.isArray(value) && value.length > 0 && isFileObject(value[0]);
};

// Check if file is an image
const isImageFile = (file) => {
    if (!file?.mime_type) return false;
    return file.mime_type.startsWith('image/');
};
</script>

<template>
    <Head :title="`Odpoveď #${submission.id}`" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div class="min-w-0">
                <Link href="/admin/submissions" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 text-sm">
                    ← Späť na zoznam
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Odpoveď #{{ submission.id }}</h1>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-6">
                <!-- Submission data -->
                <div class="card">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Dáta odpovede</h2>
                    <dl class="space-y-3">
                        <div v-for="(value, key) in submissionData" :key="key" class="flex border-b border-gray-200 dark:border-gray-600 pb-2 last:border-0">
                            <dt class="w-1/3 text-gray-500 dark:text-gray-400 font-medium">{{ getFieldDisplayLabel(key) }}</dt>
                            <dd class="w-2/3 text-gray-700 dark:text-gray-300">
                                <template v-if="typeof value === 'boolean' || value === '1' || value === '0'">
                                    <span :class="(value === true || value === '1') ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ (value === true || value === '1') ? 'Áno' : 'Nie' }}
                                    </span>
                                </template>
                                <!-- Single file -->
                                <template v-else-if="isFileObject(value)">
                                    <div class="flex items-center gap-3">
                                        <!-- Image preview -->
                                        <button v-if="isImageFile(value)" @click="openLightbox(value)" class="flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                                            <img
                                                :src="value.url"
                                                :alt="value.original_name"
                                                class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 transition-colors cursor-pointer"
                                            />
                                        </button>
                                        <!-- File icon for non-images -->
                                        <div v-else class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-600 rounded-lg flex-shrink-0">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <a
                                                :href="value.url"
                                                target="_blank"
                                                class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                            >
                                                {{ value.original_name }}
                                            </a>
                                            <p class="text-gray-400 dark:text-gray-500 text-xs">{{ formatFileSize(value.size) }}</p>
                                        </div>
                                        <!-- Download button -->
                                        <a
                                            :href="value.url"
                                            :download="value.original_name"
                                            class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                            title="Stiahnuť súbor"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <!-- Delete button -->
                                        <button
                                            @click="deleteFile(key)"
                                            :disabled="deletingFile === `${key}-null`"
                                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                            title="Zmazať súbor"
                                        >
                                            <svg v-if="deletingFile === `${key}-null`" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                                <!-- Multiple files -->
                                <template v-else-if="isFileArray(value)">
                                    <div class="space-y-3">
                                        <div
                                            v-for="(file, fileIndex) in value"
                                            :key="fileIndex"
                                            class="flex items-center gap-3"
                                        >
                                            <!-- Image preview -->
                                            <button v-if="isImageFile(file)" @click="openLightbox(value.filter(f => isImageFile(f)), value.filter(f => isImageFile(f)).indexOf(file))" class="flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                                                <img
                                                    :src="file.url"
                                                    :alt="file.original_name"
                                                    class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 transition-colors cursor-pointer"
                                                />
                                            </button>
                                            <!-- File icon for non-images -->
                                            <div v-else class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-600 rounded-lg flex-shrink-0">
                                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <a
                                                    :href="file.url"
                                                    target="_blank"
                                                    class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                                >
                                                    {{ file.original_name }}
                                                </a>
                                                <p class="text-gray-400 dark:text-gray-500 text-xs">{{ formatFileSize(file.size) }}</p>
                                            </div>
                                            <!-- Download button -->
                                            <a
                                                :href="file.url"
                                                :download="file.original_name"
                                                class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                                title="Stiahnuť súbor"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                            <!-- Delete button -->
                                            <button
                                                @click="deleteFile(key, fileIndex)"
                                                :disabled="deletingFile === `${key}-${fileIndex}`"
                                                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                                title="Zmazať súbor"
                                            >
                                                <svg v-if="deletingFile === `${key}-${fileIndex}`" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="Array.isArray(value)">
                                    {{ value.join(', ') }}
                                </template>
                                <template v-else>
                                    {{ value || '-' }}
                                </template>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Workflow executions -->
                <div v-if="workflowExecutions.length || submission.form?.workflow_id" class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Workflow vykonania
                            <span v-if="isRefreshing" class="ml-2">
                                <svg class="w-4 h-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                        </h2>

                        <div class="flex items-center gap-3">
                            <!-- Auto-refresh toggle -->
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Auto-refresh</span>
                                <button
                                    @click="toggleAutoRefresh"
                                    class="relative w-10 h-5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="autoRefreshEnabled ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-600'"
                                >
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                        :class="autoRefreshEnabled ? 'translate-x-5' : ''"
                                    ></span>
                                </button>
                            </label>

                            <!-- Manual refresh button -->
                            <button
                                @click="refreshWorkflowStatus"
                                :disabled="isRefreshing"
                                class="p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                title="Obnoviť stav"
                            >
                                <svg class="w-5 h-5" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>

                            <!-- Restart workflow button (admin+ only) -->
                            <button
                                v-if="hasMinRole('admin') && submission.form?.workflow_id"
                                @click="restartWorkflow"
                                :disabled="restartingWorkflow"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                            >
                                <svg v-if="restartingWorkflow" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Spustiť znova</span>
                            </button>
                        </div>
                    </div>

                    <!-- Last refresh time -->
                    <p v-if="lastRefreshTime && autoRefreshEnabled" class="text-xs text-gray-400 dark:text-gray-500 mb-3">
                        Posledná aktualizácia: {{ lastRefreshTime.toLocaleTimeString('sk-SK') }}
                    </p>

                    <div v-if="workflowExecutions.length" class="space-y-4">
                        <div v-for="execution in workflowExecutions" :key="execution.id" class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ execution.workflow?.name }}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 text-xs rounded-full"
                                        :class="{
                                            'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': execution.status === 'completed',
                                            'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': execution.status === 'waiting_approval',
                                            'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300': execution.status === 'running' || execution.status === 'pending',
                                            'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': execution.status === 'failed',
                                            'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300': execution.status === 'stopped',
                                        }"
                                    >
                                        {{ execution.status === 'stopped' ? 'zastavený' : execution.status }}
                                    </span>

                                    <!-- Stop button (admin+ only, only for active executions) -->
                                    <button
                                        v-if="hasMinRole('admin') && ['running', 'pending', 'waiting_approval'].includes(execution.status)"
                                        @click="stopWorkflowExecution(execution)"
                                        :disabled="stoppingExecution === execution.id"
                                        class="p-1 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                                        title="Zastaviť workflow"
                                    >
                                        <svg v-if="stoppingExecution === execution.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 6h12v12H6z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Logs -->
                            <div v-if="execution.logs?.length" class="mt-3 text-sm">
                                <p class="text-gray-500 dark:text-gray-400 mb-2">Logy:</p>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded p-2 max-h-40 overflow-y-auto font-mono text-xs text-gray-700 dark:text-gray-300">
                                    <div v-for="(log, i) in execution.logs" :key="i" class="py-1">
                                        <span class="text-gray-400 dark:text-gray-500">{{ log.timestamp?.split('T')[1]?.split('.')[0] }}</span>
                                        <span class="ml-2">{{ log.message }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Approval requests -->
                            <div v-if="execution.approval_requests?.length" class="mt-3">
                                <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">Schválenia:</p>
                                <div class="space-y-2">
                                    <div v-for="approval in execution.approval_requests" :key="approval.id" class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-700 p-2 rounded text-gray-700 dark:text-gray-300">
                                        <span>{{ approval.approver_email }}</span>
                                        <span class="px-2 py-0.5 text-xs rounded-full"
                                            :class="{
                                                'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': approval.status === 'approved',
                                                'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': approval.status === 'rejected',
                                                'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': approval.status === 'pending',
                                            }"
                                        >
                                            {{ approval.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No executions yet -->
                    <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <p>Zatiaľ nebolo spustené žiadne workflow.</p>
                        <button
                            v-if="hasMinRole('admin')"
                            @click="restartWorkflow"
                            :disabled="restartingWorkflow"
                            class="mt-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                        >
                            <svg v-if="restartingWorkflow" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span>{{ restartingWorkflow ? 'Spúšťam...' : 'Spustiť workflow' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="card">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        Interné poznámky
                        <span v-if="comments.length" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            ({{ comments.length }})
                        </span>
                    </h2>

                    <!-- Add comment form -->
                    <div class="mb-4">
                        <textarea
                            v-model="newComment"
                            class="form-input w-full"
                            rows="3"
                            placeholder="Pridať internú poznámku..."
                            :disabled="addingComment"
                        ></textarea>
                        <div class="flex justify-end mt-2">
                            <button
                                @click="addComment"
                                :disabled="addingComment || !newComment.trim()"
                                class="btn btn-primary"
                            >
                                <span v-if="addingComment">Pridávam...</span>
                                <span v-else>Pridať poznámku</span>
                            </button>
                        </div>
                    </div>

                    <!-- Comments list -->
                    <div v-if="comments.length" class="space-y-4">
                        <div
                            v-for="comment in comments"
                            :key="comment.id"
                            class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                        >
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100 text-sm">
                                        {{ comment.user?.name || comment.user?.email }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatCommentDate(comment.created_at) }}
                                    </span>
                                </div>
                                <div v-if="comment.user_id === auth.user.id" class="flex gap-1">
                                    <button
                                        @click="startEditComment(comment)"
                                        class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded"
                                        title="Upraviť"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="deleteComment(comment)"
                                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded"
                                        title="Zmazať"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Edit mode -->
                            <div v-if="editingCommentId === comment.id">
                                <textarea
                                    v-model="editCommentContent"
                                    class="form-input w-full"
                                    rows="3"
                                ></textarea>
                                <div class="flex justify-end gap-2 mt-2">
                                    <button @click="cancelEditComment" class="btn btn-secondary text-sm">
                                        Zrušiť
                                    </button>
                                    <button @click="saveEditComment(comment)" class="btn btn-primary text-sm">
                                        Uložiť
                                    </button>
                                </div>
                            </div>

                            <!-- View mode -->
                            <p v-else class="text-gray-700 dark:text-gray-300 text-sm whitespace-pre-wrap">
                                {{ comment.content }}
                            </p>
                        </div>
                    </div>

                    <p v-else class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                        Zatiaľ žiadne poznámky
                    </p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="card">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Informácie</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Formulár</dt>
                            <dd>
                                <Link :href="`/admin/forms/${submission.form.id}/edit`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ getFormName(submission.form) }}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Stav</dt>
                            <dd>
                                <span class="px-2 py-1 text-xs rounded-full"
                                    :class="{
                                        'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': currentStatus === 'completed' || currentStatus === 'approved',
                                        'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': currentStatus === 'processing',
                                        'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300': currentStatus === 'submitted',
                                        'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': currentStatus === 'rejected',
                                    }"
                                >
                                    {{ currentStatus === 'approved' ? 'schválené' : currentStatus === 'rejected' ? 'zamietnuté' : currentStatus }}
                                </span>
                            </dd>
                        </div>

                        <!-- Approve/Reject buttons -->
                        <div v-if="currentStatus === 'submitted' || currentStatus === 'processing'" class="pt-3 border-t border-gray-200 dark:border-gray-600">
                            <dt class="text-gray-500 dark:text-gray-400 mb-2">Akcie</dt>
                            <dd class="flex flex-col gap-2">
                                <button
                                    @click="openApproveModal"
                                    class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg flex items-center justify-center gap-2 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Schváliť
                                </button>
                                <button
                                    @click="openRejectModal"
                                    class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg flex items-center justify-center gap-2 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Zamietnuť
                                </button>
                            </dd>
                        </div>

                        <!-- Show admin response if exists -->
                        <div v-if="submission.admin_response" class="pt-3 border-t border-gray-200 dark:border-gray-600">
                            <dt class="text-gray-500 dark:text-gray-400">Odpoveď admina</dt>
                            <dd class="text-gray-700 dark:text-gray-300 text-sm mt-1 whitespace-pre-wrap">{{ submission.admin_response }}</dd>
                        </div>

                        <!-- Show reviewed info -->
                        <div v-if="submission.reviewed_at">
                            <dt class="text-gray-500 dark:text-gray-400">Spracované</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ formatDate(submission.reviewed_at) }}</dd>
                        </div>
                        <div v-if="submission.reviewer">
                            <dt class="text-gray-500 dark:text-gray-400">{{ currentStatus === 'approved' ? 'Schválil' : 'Zamietol' }}</dt>
                            <dd class="text-gray-700 dark:text-gray-300">
                                {{ submission.reviewer.first_name && submission.reviewer.last_name
                                    ? `${submission.reviewer.first_name} ${submission.reviewer.last_name}`
                                    : submission.reviewer.name }}
                                <span v-if="submission.reviewer.email" class="text-xs text-gray-500 dark:text-gray-400 block">
                                    {{ submission.reviewer.email }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Používateľ</dt>
                            <dd class="text-gray-700 dark:text-gray-300">
                                <template v-if="submission.user">
                                    <div class="font-medium">
                                        {{ submission.user.first_name && submission.user.last_name
                                            ? `${submission.user.first_name} ${submission.user.last_name}`
                                            : submission.user.name }}
                                    </div>
                                    <div v-if="submission.user_login" class="text-xs font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded inline-block mt-1">{{ submission.user_login }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ submission.user.email }}</div>
                                </template>
                                <template v-else>
                                    Anonymný
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">IP adresa</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ submission.ip_address || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Odoslané</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ formatDate(submission.created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Image Lightbox -->
        <ImageLightbox
            :images="lightboxImages"
            :initial-index="lightboxIndex"
            :show="lightboxOpen"
            @close="closeLightbox"
        />

        <!-- Approve Modal -->
        <div v-if="showApproveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Schváliť odpoveď
                    </h3>
                </div>
                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Správa pre žiadateľa (voliteľné)
                        </label>
                        <textarea
                            v-model="adminResponse"
                            class="form-input w-full"
                            rows="4"
                            placeholder="Vaša odpoveď bude odoslaná žiadateľovi emailom..."
                        ></textarea>
                    </div>

                    <!-- Workflow option - only show if form has workflow -->
                    <label v-if="hasWorkflow" class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="runWorkflow"
                            class="w-5 h-5 text-blue-600 rounded mt-0.5"
                        />
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">Spustiť workflow</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Po schválení sa automaticky spustí priradený workflow. Odznačte, ak chcete schváliť bez spustenia workflow.
                            </p>
                        </div>
                    </label>
                </div>
                <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                    <button
                        @click="closeModals"
                        :disabled="processingAction"
                        class="btn btn-secondary"
                    >
                        Zrušiť
                    </button>
                    <button
                        @click="approveSubmission"
                        :disabled="processingAction"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50"
                    >
                        <span v-if="processingAction">Spracovávam...</span>
                        <span v-else>Schváliť</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Zamietnuť odpoveď
                    </h3>
                </div>
                <div class="p-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Dôvod zamietnutia (voliteľné)
                    </label>
                    <textarea
                        v-model="adminResponse"
                        class="form-input w-full"
                        rows="4"
                        placeholder="Dôvod zamietnutia bude odoslaný žiadateľovi emailom..."
                    ></textarea>
                </div>
                <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                    <button
                        @click="closeModals"
                        :disabled="processingAction"
                        class="btn btn-secondary"
                    >
                        Zrušiť
                    </button>
                    <button
                        @click="rejectSubmission"
                        :disabled="processingAction"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50"
                    >
                        <span v-if="processingAction">Spracovávam...</span>
                        <span v-else>Zamietnuť</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
