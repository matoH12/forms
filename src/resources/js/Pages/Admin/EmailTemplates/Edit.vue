<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useSanitizeHtml } from '@/composables/useSanitizeHtml';

// SECURITY: Sanitize HTML to prevent XSS in preview
const { sanitizeHtml } = useSanitizeHtml();

const props = defineProps({
    template: Object,
});

const form = useForm({
    name: props.template.name,
    subject: props.template.subject,
    body_html: props.template.body_html,
    body_text: props.template.body_text || '',
    include_submission_data: props.template.include_submission_data,
    is_default: props.template.is_default,
    is_active: props.template.is_active,
});

const showPreview = ref(false);
const previewHtml = ref('');
const previewSubject = ref('');
const showDeleteModal = ref(false);

const submit = () => {
    form.transform(data => ({ ...data, _method: 'PUT' })).post(`/admin/email-templates/${props.template.id}`);
};

const deleteTemplate = () => {
    router.post(`/admin/email-templates/${props.template.id}`, { _method: 'delete' });
};

const generatePreview = () => {
    let html = form.body_html;
    let subject = form.subject;

    // Replace placeholders with sample data
    const replacements = {
        '{{form_name}}': 'Ukazkovy formular',
        '{{user_name}}': 'Jan Ukazka',
        '{{user_email}}': 'jan.ukazka@example.com',
        '{{submission_id}}': '1',
        '{{submission_date}}': new Date().toLocaleString('sk-SK'),
    };

    for (const [key, value] of Object.entries(replacements)) {
        html = html.replaceAll(key, value);
        subject = subject.replaceAll(key, value);
    }

    // Add sample submission data table if enabled
    if (form.include_submission_data) {
        html += generateSampleTable();
    }

    // SECURITY: Sanitize HTML before rendering to prevent XSS
    previewHtml.value = sanitizeHtml(html);
    previewSubject.value = subject;
    showPreview.value = true;
};

const generateSampleTable = () => {
    return `
<div style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
    <h3 style="margin: 0 0 16px 0; color: #374151; font-size: 16px;">Prehlad vasich odpovedi</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">Meno a priezvisko</td>
            <td style="padding: 12px 8px; color: #111827;">Jan Ukazka</td>
        </tr>
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">Email</td>
            <td style="padding: 12px 8px; color: #111827;">jan.ukazka@example.com</td>
        </tr>
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">Telefon</td>
            <td style="padding: 12px 8px; color: #111827;">+421 900 123 456</td>
        </tr>
        <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">Sprava</td>
            <td style="padding: 12px 8px; color: #111827;">Toto je ukazkova sprava pre nahlad emailu.</td>
        </tr>
    </table>
</div>`;
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6">
            <Link href="/admin/email-templates" class="text-brand-primary dark:text-brand-gold hover:underline text-sm">
                &larr; Spat na sablony
            </Link>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Upravit sablonu</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Nazov sablony *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-primary dark:focus:ring-brand-gold focus:border-transparent"
                            placeholder="napr. Dakovny email"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Predmet emailu *
                        </label>
                        <input
                            v-model="form.subject"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-primary dark:focus:ring-brand-gold focus:border-transparent"
                            required
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Pouzite <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{<!-- -->{form_name}<!-- -->}</code> pre nazov formulara
                        </p>
                        <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Obsah emailu (HTML) *
                        </label>
                        <textarea
                            v-model="form.body_html"
                            rows="12"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-brand-primary dark:focus:ring-brand-gold focus:border-transparent font-mono text-sm"
                            required
                        />
                        <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-600">{{ form.errors.body_html }}</p>
                    </div>

                    <!-- Placeholders help -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Dostupne premenne</h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{form_name}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">Nazov formulara</span>
                            </div>
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{user_name}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">Meno pouzivatela</span>
                            </div>
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{user_email}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">Email pouzivatela</span>
                            </div>
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{submission_date}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">Datum odoslania</span>
                            </div>
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{submission_id}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">ID odpovede</span>
                            </div>
                            <div>
                                <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded text-blue-800 dark:text-blue-200">{<!-- -->{submission.nazov_pola}<!-- -->}</code>
                                <span class="text-blue-700 dark:text-blue-300 ml-1">Hodnota pola</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.include_submission_data"
                                type="checkbox"
                                class="w-4 h-4 text-brand-primary dark:text-brand-gold rounded border-gray-300 dark:border-gray-600 focus:ring-brand-primary dark:focus:ring-brand-gold"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Pridat rekapitulaciu odpovedi</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="w-4 h-4 text-brand-primary dark:text-brand-gold rounded border-gray-300 dark:border-gray-600 focus:ring-brand-primary dark:focus:ring-brand-gold"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Predvolena sablona</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="w-4 h-4 text-brand-primary dark:text-brand-gold rounded border-gray-300 dark:border-gray-600 focus:ring-brand-primary dark:focus:ring-brand-gold"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Aktivna</span>
                        </label>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn btn-primary disabled:opacity-50"
                        >
                            {{ form.processing ? 'Ukladám...' : 'Uložiť zmeny' }}
                        </button>
                        <button
                            type="button"
                            @click="generatePreview"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        >
                            Nahlad
                        </button>
                        <button
                            type="button"
                            @click="showDeleteModal = true"
                            class="px-4 py-2 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                        >
                            Zmazat
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Nahlad emailu</h3>

                <div v-if="showPreview" class="space-y-4">
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Predmet:</div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ previewSubject }}
                        </div>
                    </div>

                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white">
                        <div v-html="previewHtml" />
                    </div>
                </div>

                <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <p>Kliknite na "Nahlad" pre zobrazenie</p>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showDeleteModal = false" />
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Zmazat sablonu?</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Naozaj chcete zmazat sablonu "{{ template.name }}"? Tuto akciu nie je mozne vratit spat.
                </p>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="showDeleteModal = false"
                        class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                    >
                        Zrusit
                    </button>
                    <button
                        @click="deleteTemplate"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                        Zmazat
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
