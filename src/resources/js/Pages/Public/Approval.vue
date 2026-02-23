<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref } from 'vue';
import { useLocalized } from '@/composables/useLocalized';

const { getLocalized } = useLocalized();

const props = defineProps({
    approval: Object,
    submission: Object,
    form: Object,
    auth: Object,
});

// Helper to get localized form name
const getFormName = () => {
    if (!props.form) return '';
    return getLocalized(props.form.name) || props.form.slug || '';
};

const comment = ref('');
const processing = ref(false);
const completed = ref(props.approval.status !== 'pending');

const approve = () => {
    processing.value = true;
    // Token in request body instead of URL for security
    const form = useForm({
        token: props.approval.token,
        comment: comment.value
    });
    form.post('/approvals/approve', {
        onSuccess: () => {
            completed.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const reject = () => {
    processing.value = true;
    // Token in request body instead of URL for security
    const form = useForm({
        token: props.approval.token,
        comment: comment.value
    });
    form.post('/approvals/reject', {
        onSuccess: () => {
            completed.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Schválenie žiadosti" />
    <PublicLayout :auth="auth">
        <div class="max-w-2xl mx-auto">
            <div class="card">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Schválenie žiadosti</h1>

                <div v-if="completed" class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                        :class="approval.status === 'approved' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                        <svg class="w-8 h-8" :class="approval.status === 'approved' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="approval.status === 'approved'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <p class="text-lg font-medium" :class="approval.status === 'approved' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'">
                        {{ approval.status === 'approved' ? 'Žiadosť bola schválená' : 'Žiadosť bola zamietnutá' }}
                    </p>
                </div>

                <template v-else>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                        <h2 class="font-medium text-gray-900 dark:text-white mb-2">{{ getFormName() }}</h2>
                        <dl class="space-y-2 text-sm">
                            <div v-for="(value, key) in submission.data" :key="key" class="flex">
                                <dt class="w-1/3 text-gray-500 dark:text-gray-400">{{ key }}:</dt>
                                <dd class="w-2/3 text-gray-900 dark:text-white">{{ value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mb-6">
                        <label class="form-label">Komentár (voliteľný)</label>
                        <textarea v-model="comment" class="form-input" rows="3" placeholder="Pridajte komentár k vášmu rozhodnutiu..." />
                    </div>

                    <div class="flex gap-4">
                        <button
                            @click="approve"
                            :disabled="processing"
                            class="btn btn-success flex-1"
                        >
                            Schváliť
                        </button>
                        <button
                            @click="reject"
                            :disabled="processing"
                            class="btn btn-danger flex-1"
                        >
                            Zamietnuť
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
