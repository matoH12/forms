<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    mailSettings: Object,
    keycloakSettings: Object,
    brandingSettings: Object,
    backupSettings: Object,
    localBackups: Array,
    apiTokens: Array,
    auth: Object,
});

const logoFile = ref(null);
const logoUploading = ref(false);
const logoDeleting = ref(false);

const currentLogoUrl = computed(() => {
    if (props.brandingSettings.logo) {
        return '/storage/' + props.brandingSettings.logo;
    }
    return null;
});

/**
 * SECURITY: Password fields are initialized empty to prevent exposure in Vue DevTools.
 * We track whether a password is already set on the server using hasXxxPassword computed props.
 * If user submits with empty password field, backend keeps existing password.
 */
const hasMailPassword = computed(() => props.mailSettings.password === '********');
const hasKeycloakSecret = computed(() => props.keycloakSettings.client_secret === '********');

const form = useForm({
    host: props.mailSettings.host || '',
    port: props.mailSettings.port || 587,
    username: props.mailSettings.username || '',
    password: '', // SECURITY: Don't pre-populate passwords
    encryption: props.mailSettings.encryption || 'tls',
    from_address: props.mailSettings.from_address || '',
    from_name: props.mailSettings.from_name || '',
});

const keycloakForm = useForm({
    base_url: props.keycloakSettings.base_url || '',
    realm: props.keycloakSettings.realm || '',
    client_id: props.keycloakSettings.client_id || '',
    client_secret: '', // SECURITY: Don't pre-populate secrets
    redirect_uri: props.keycloakSettings.redirect_uri || '',
});

const testForm = useForm({
    email: '',
});

const brandingForm = useForm({
    site_name: props.brandingSettings.site_name || 'Formuláre',
    site_subtitle: props.brandingSettings.site_subtitle || '',
    organization_name: props.brandingSettings.organization_name || '',
    footer_text: props.brandingSettings.footer_text || '',
    primary_color: props.brandingSettings.primary_color || '#1e3a5f',
    accent_color: props.brandingSettings.accent_color || '#c9a227',
    support_email: props.brandingSettings.support_email || '',
});

const showPassword = ref(false);
const showClientSecret = ref(false);
const testEmailSent = ref(false);

// Backup & Restore
const backupInProgress = ref(false);
const restoreInProgress = ref(false);
const includeSubmissions = ref(false);
const restoreFile = ref(null);
const restoreData = ref(null);
const restoreOptions = ref({
    restore_categories: true,
    restore_forms: true,
    restore_email_templates: true,
    restore_workflows: true,
    restore_settings: true,
});
const restoreResult = ref(null);

// SECURITY: Track if passwords are already set on server
const hasFtpPassword = computed(() => props.backupSettings?.ftp_password === '********');
const hasS3Secret = computed(() => props.backupSettings?.s3_secret === '********');

// Backup Settings Form
const backupSettingsForm = useForm({
    enabled: props.backupSettings?.enabled || false,
    frequency: props.backupSettings?.frequency || 'daily',
    time: props.backupSettings?.time || '02:00',
    include_submissions: props.backupSettings?.include_submissions || false,
    retention_local: props.backupSettings?.retention_local || 10,
    // FTP
    ftp_enabled: props.backupSettings?.ftp_enabled || false,
    ftp_host: props.backupSettings?.ftp_host || '',
    ftp_port: props.backupSettings?.ftp_port || 21,
    ftp_username: props.backupSettings?.ftp_username || '',
    ftp_password: '', // SECURITY: Don't pre-populate passwords
    ftp_path: props.backupSettings?.ftp_path || '/',
    ftp_passive: props.backupSettings?.ftp_passive ?? true,
    ftp_ssl: props.backupSettings?.ftp_ssl || false,
    ftp_retention: props.backupSettings?.ftp_retention || 10,
    // S3
    s3_enabled: props.backupSettings?.s3_enabled || false,
    s3_key: props.backupSettings?.s3_key || '',
    s3_secret: '', // SECURITY: Don't pre-populate secrets
    s3_region: props.backupSettings?.s3_region || 'eu-central-1',
    s3_bucket: props.backupSettings?.s3_bucket || '',
    s3_endpoint: props.backupSettings?.s3_endpoint || '',
    s3_path: props.backupSettings?.s3_path || '',
    s3_use_path_style: props.backupSettings?.s3_use_path_style || false,
    s3_retention: props.backupSettings?.s3_retention || 10,
});

const showFtpPassword = ref(false);
const showS3Secret = ref(false);
const testingFtp = ref(false);
const testingS3 = ref(false);
const runningBackupNow = ref(false);
const selectedBackupDestinations = ref(['local']);
const localBackupsList = ref(props.localBackups || []);
const deletingBackup = ref(null);

// API Tokens
const apiTokenForm = useForm({
    name: '',
    expires_in_days: 365,
});
const newCreatedToken = ref(null);
const tokenDeleting = ref(null);

const expirationOptions = [
    { value: 7, label: '7 dní' },
    { value: 30, label: '30 dní' },
    { value: 90, label: '90 dní' },
    { value: 180, label: '6 mesiacov' },
    { value: 365, label: '1 rok' },
    { value: 730, label: '2 roky' },
    { value: 0, label: 'Nikdy (bez expirácie)' },
];

const createApiToken = () => {
    apiTokenForm.post('/admin/settings/api-tokens', {
        preserveScroll: true,
        onSuccess: (page) => {
            apiTokenForm.reset();
            if (page.props.flash?.newToken) {
                newCreatedToken.value = page.props.flash.newToken;
            }
        },
    });
};

const deleteApiToken = (tokenId) => {
    if (!confirm('Naozaj chcete zmazať tento API token? Táto akcia je nevratná.')) return;

    tokenDeleting.value = tokenId;
    router.post(`/admin/settings/api-tokens/${tokenId}`, { _method: 'delete' }, {
        preserveScroll: true,
        onFinish: () => {
            tokenDeleting.value = null;
        },
    });
};

const copyToken = async (token) => {
    try {
        await navigator.clipboard.writeText(token);
        alert('Token bol skopírovaný do schránky');
    } catch (err) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = token;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Token bol skopírovaný do schránky');
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'Nikdy';
    return new Date(dateString).toLocaleString('sk-SK');
};

const saveSettings = () => {
    form.post('/admin/settings/mail', {
        preserveScroll: true,
    });
};

const saveKeycloakSettings = () => {
    keycloakForm.post('/admin/settings/keycloak', {
        preserveScroll: true,
    });
};

const testKeycloakConnection = () => {
    keycloakForm.post('/admin/settings/keycloak/test', {
        preserveScroll: true,
    });
};

const sendTestEmail = () => {
    testForm.post('/admin/settings/mail/test', {
        preserveScroll: true,
        onSuccess: () => {
            testEmailSent.value = true;
            setTimeout(() => testEmailSent.value = false, 5000);
        },
    });
};

const saveBrandingSettings = () => {
    brandingForm.post('/admin/settings/branding', {
        preserveScroll: true,
    });
};

const handleLogoSelect = (event) => {
    logoFile.value = event.target.files[0];
};

const uploadLogo = () => {
    if (!logoFile.value) return;

    logoUploading.value = true;

    const formData = new FormData();
    formData.append('logo', logoFile.value);

    router.post('/admin/settings/logo', formData, {
        preserveScroll: true,
        onFinish: () => {
            logoUploading.value = false;
            logoFile.value = null;
            // Reset file input
            const input = document.getElementById('logo-input');
            if (input) input.value = '';
        },
    });
};

const deleteLogo = () => {
    if (!confirm('Naozaj chcete odstrániť logo?')) return;

    logoDeleting.value = true;

    router.post('/admin/settings/logo', { _method: 'delete' }, {
        preserveScroll: true,
        onFinish: () => {
            logoDeleting.value = false;
        },
    });
};

// Backup functions
const createBackup = async () => {
    backupInProgress.value = true;
    try {
        const response = await fetch('/admin/settings/backup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                include_submissions: includeSubmissions.value,
            }),
        });

        if (!response.ok) throw new Error('Backup zlyhal');

        const data = await response.json();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `backup_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (error) {
        alert('Chyba pri vytvarani zalohy: ' + error.message);
    } finally {
        backupInProgress.value = false;
    }
};

const handleRestoreFileSelect = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    restoreFile.value = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            restoreData.value = JSON.parse(e.target.result);
            if (!restoreData.value.backup_type || restoreData.value.backup_type !== 'full') {
                alert('Neplatny format zalohy');
                restoreData.value = null;
                restoreFile.value = null;
            }
        } catch (err) {
            alert('Chyba pri citani suboru: ' + err.message);
            restoreData.value = null;
            restoreFile.value = null;
        }
    };
    reader.readAsText(file);
};

const performRestore = async () => {
    if (!restoreData.value) return;
    if (!confirm('Naozaj chcete obnovit data zo zalohy? Existujuce data mozu byt prepísané.')) return;

    restoreInProgress.value = true;
    restoreResult.value = null;

    try {
        const response = await fetch('/admin/settings/restore', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                backup: restoreData.value,
                ...restoreOptions.value,
            }),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Obnovenie zlyhalo');
        }

        restoreResult.value = result.results;
        restoreData.value = null;
        restoreFile.value = null;
        // Reset file input
        const input = document.getElementById('restore-input');
        if (input) input.value = '';

    } catch (error) {
        alert('Chyba pri obnove: ' + error.message);
    } finally {
        restoreInProgress.value = false;
    }
};

const cancelRestore = () => {
    restoreData.value = null;
    restoreFile.value = null;
    restoreResult.value = null;
    const input = document.getElementById('restore-input');
    if (input) input.value = '';
};

// Backup Settings Functions
const saveBackupSettings = () => {
    backupSettingsForm.post('/admin/settings/backup/settings', {
        preserveScroll: true,
    });
};

const testFtpConnection = async () => {
    testingFtp.value = true;
    try {
        const response = await fetch('/admin/settings/backup/test-ftp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                host: backupSettingsForm.ftp_host,
                port: backupSettingsForm.ftp_port,
                username: backupSettingsForm.ftp_username,
                password: backupSettingsForm.ftp_password,
                path: backupSettingsForm.ftp_path,
                passive: backupSettingsForm.ftp_passive,
                ssl: backupSettingsForm.ftp_ssl,
            }),
        });
        const result = await response.json();
        alert(result.message);
    } catch (error) {
        alert('Chyba: ' + error.message);
    } finally {
        testingFtp.value = false;
    }
};

const testS3Connection = async () => {
    testingS3.value = true;
    try {
        const response = await fetch('/admin/settings/backup/test-s3', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                key: backupSettingsForm.s3_key,
                secret: backupSettingsForm.s3_secret,
                region: backupSettingsForm.s3_region,
                bucket: backupSettingsForm.s3_bucket,
                endpoint: backupSettingsForm.s3_endpoint,
                path: backupSettingsForm.s3_path,
                use_path_style: backupSettingsForm.s3_use_path_style,
            }),
        });
        const result = await response.json();
        alert(result.message);
    } catch (error) {
        alert('Chyba: ' + error.message);
    } finally {
        testingS3.value = false;
    }
};

const runBackupNow = async () => {
    if (selectedBackupDestinations.value.length === 0) {
        alert('Vyberte aspon jedno cielove umiestnenie');
        return;
    }
    runningBackupNow.value = true;
    try {
        const response = await fetch('/admin/settings/backup/run', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                destinations: selectedBackupDestinations.value,
                include_submissions: includeSubmissions.value,
            }),
        });
        const result = await response.json();
        if (result.success) {
            alert('Zaloha bola uspesne vytvorena!');
            // Refresh local backups list
            refreshLocalBackups();
        } else if (result.errors) {
            alert('Zaloha dokoncena s chybami: ' + Object.values(result.errors).join(', '));
            refreshLocalBackups();
        } else {
            alert('Chyba: ' + (result.error || 'Neznama chyba'));
        }
    } catch (error) {
        alert('Chyba: ' + error.message);
    } finally {
        runningBackupNow.value = false;
    }
};

const refreshLocalBackups = async () => {
    try {
        const response = await fetch('/admin/settings/backup/local');
        localBackupsList.value = await response.json();
    } catch (error) {
        console.error('Failed to refresh backups', error);
    }
};

const downloadLocalBackup = (filename) => {
    window.location.href = '/admin/settings/backup/local/' + encodeURIComponent(filename);
};

const deleteLocalBackup = async (filename) => {
    if (!confirm('Naozaj chcete zmazat tuto zalohu?')) return;

    deletingBackup.value = filename;
    try {
        const response = await fetch('/admin/settings/backup/local/' + encodeURIComponent(filename), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-HTTP-Method-Override': 'DELETE',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({}),
        });
        if (response.ok) {
            refreshLocalBackups();
        } else {
            alert('Nepodarilo sa zmazat zalohu');
        }
    } catch (error) {
        alert('Chyba: ' + error.message);
    } finally {
        deletingBackup.value = null;
    }
};

const formatBytes = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Head title="Nastavenia" />
    <AdminLayout :auth="auth">
        <div class="mb-4 md:mb-6">
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-gray-100">Nastavenia</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm md:text-base">Spravujte nastavenia aplikacie</p>
        </div>

        <!-- Flash messages -->
        <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-lg">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg">
            {{ $page.props.flash.error }}
        </div>

        <!-- API Tokens Section -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                <svg class="w-5 h-5 inline-block mr-2 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                API Tokeny a Dokumentácia
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
                Spravujte systémové API tokeny pre prístup k REST API. Tokeny sú nezávislé od používateľov.
            </p>

            <!-- Swagger Documentation Link -->
            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-md font-semibold text-blue-800 dark:text-blue-300">API Dokumentácia (Swagger)</h3>
                        <p class="text-blue-600 dark:text-blue-400 text-sm">Prehliadajte a testujte API endpointy</p>
                    </div>
                    <a
                        href="/api/documentation"
                        target="_blank"
                        class="btn btn-primary"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        Otvoriť Swagger UI
                    </a>
                </div>
            </div>

            <!-- New Token Created Alert -->
            <div v-if="newCreatedToken" class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-md font-semibold text-green-800 dark:text-green-300 mb-2">Token bol úspešne vytvorený!</h3>
                        <p class="text-green-600 dark:text-green-400 text-sm mb-3">
                            Skopírujte si token teraz. Po zatvorení tohto oznámenia ho už nebudete môcť zobraziť.
                        </p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 p-2 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-600 rounded text-sm font-mono break-all">
                                {{ newCreatedToken }}
                            </code>
                            <button
                                @click="copyToken(newCreatedToken)"
                                class="btn btn-secondary flex-shrink-0"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                        </div>
                        <button
                            @click="newCreatedToken = null"
                            class="mt-3 text-sm text-green-700 dark:text-green-400 hover:underline"
                        >
                            Zatvoriť oznámenie
                        </button>
                    </div>
                </div>
            </div>

            <!-- Create New Token Form -->
            <form @submit.prevent="createApiToken" class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">Vytvoriť nový token</h3>
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1">
                        <input
                            v-model="apiTokenForm.name"
                            type="text"
                            class="form-input"
                            placeholder="Názov tokenu (napr. 'CMDB integrácia')"
                            required
                        />
                        <p v-if="apiTokenForm.errors.name" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ apiTokenForm.errors.name }}</p>
                    </div>
                    <div class="w-full md:w-48">
                        <select
                            v-model="apiTokenForm.expires_in_days"
                            class="form-input"
                        >
                            <option v-for="option in expirationOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="apiTokenForm.errors.expires_in_days" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ apiTokenForm.errors.expires_in_days }}</p>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="apiTokenForm.processing || !apiTokenForm.name"
                    >
                        <svg v-if="apiTokenForm.processing" class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ apiTokenForm.processing ? 'Vytváram...' : 'Vytvoriť token' }}
                    </button>
                </div>
            </form>

            <!-- Existing Tokens List -->
            <div>
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">Existujúce tokeny</h3>

                <div v-if="apiTokens && apiTokens.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Názov</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vytvoril</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vytvorené</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expirácia</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Posledné použitie</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Akcie</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="token in apiTokens" :key="token.id" :class="{ 'opacity-60': token.is_expired }">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ token.name }}</span>
                                    <span v-if="token.is_expired" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300">
                                        Expirovaný
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ token.creator?.name || 'Neznámy' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(token.created_at) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span v-if="!token.expires_at" class="text-green-600 dark:text-green-400">
                                        Nikdy
                                    </span>
                                    <span v-else-if="token.is_expired" class="text-red-600 dark:text-red-400">
                                        {{ formatDate(token.expires_at) }}
                                    </span>
                                    <span v-else class="text-gray-500 dark:text-gray-400">
                                        {{ formatDate(token.expires_at) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(token.last_used_at) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <button
                                        @click="deleteApiToken(token.id)"
                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                        :disabled="tokenDeleting === token.id"
                                    >
                                        <svg v-if="tokenDeleting === token.id" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    <p>Žiadne API tokeny</p>
                    <p class="text-sm">Vytvorte nový token pre prístup k REST API</p>
                </div>
            </div>

            <!-- API Usage Example -->
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">Príklad použitia API</h3>
                <div class="bg-gray-900 dark:bg-gray-950 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-green-400 font-mono">curl -X GET "/api/v1/submissions/approved" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"</pre>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
                    Nahraďte <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">YOUR_API_TOKEN</code> vaším vygenerovaným tokenom.
                </p>
            </div>
        </div>

        <!-- Branding Settings -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                <svg class="w-5 h-5 inline-block mr-2 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
                Vzhľad a branding
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Nastavte názov, logo a farby vašej aplikácie.</p>

            <form @submit.prevent="saveBrandingSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Názov stránky</label>
                        <input
                            v-model="brandingForm.site_name"
                            type="text"
                            class="form-input"
                            placeholder="Formuláre"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Hlavný názov zobrazený v hlavičke</p>
                        <p v-if="brandingForm.errors.site_name" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.site_name }}</p>
                    </div>

                    <div>
                        <label class="form-label">Podnadpis / Skratka</label>
                        <input
                            v-model="brandingForm.site_subtitle"
                            type="text"
                            class="form-input"
                            placeholder="MyRealm"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Zobrazí sa vedľa názvu (napr. skratka organizácie)</p>
                        <p v-if="brandingForm.errors.site_subtitle" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.site_subtitle }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Názov organizácie</label>
                        <input
                            v-model="brandingForm.organization_name"
                            type="text"
                            class="form-input"
                            placeholder="Your Organization Name"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Plný názov organizácie (zobrazí sa v hornej lište a v päte)</p>
                        <p v-if="brandingForm.errors.organization_name" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.organization_name }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Vlastný text v päte (voliteľné)</label>
                        <textarea
                            v-model="brandingForm.footer_text"
                            class="form-input"
                            rows="2"
                            placeholder="Napríklad: Všetky práva vyhradené | Kontakt: info@example.com"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Ak je prázdne, zobrazí sa © rok + názov organizácie</p>
                        <p v-if="brandingForm.errors.footer_text" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.footer_text }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Email podpory (pre verejné rozhranie)</label>
                        <input
                            v-model="brandingForm.support_email"
                            type="email"
                            class="form-input"
                            placeholder="helpdesk@example.com"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Zobrazí sa v sekcii "Nenašli ste čo hľadáte?" na verejnej stránke</p>
                        <p v-if="brandingForm.errors.support_email" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.support_email }}</p>
                    </div>

                    <div>
                        <label class="form-label">Primárna farba</label>
                        <div class="flex gap-2">
                            <input
                                v-model="brandingForm.primary_color"
                                type="color"
                                class="w-12 h-10 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                            />
                            <input
                                v-model="brandingForm.primary_color"
                                type="text"
                                class="form-input flex-1"
                                placeholder="#1e3a5f"
                            />
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Hlavná farba (hlavička, tlačidlá)</p>
                        <p v-if="brandingForm.errors.primary_color" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.primary_color }}</p>
                    </div>

                    <div>
                        <label class="form-label">Akcentová farba</label>
                        <div class="flex gap-2">
                            <input
                                v-model="brandingForm.accent_color"
                                type="color"
                                class="w-12 h-10 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
                            />
                            <input
                                v-model="brandingForm.accent_color"
                                type="text"
                                class="form-input flex-1"
                                placeholder="#c9a227"
                            />
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Sekundárna farba (akcenty, linky)</p>
                        <p v-if="brandingForm.errors.accent_color" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ brandingForm.errors.accent_color }}</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="brandingForm.processing"
                    >
                        {{ brandingForm.processing ? 'Ukladám...' : 'Uložiť vzhľad' }}
                    </button>
                </div>
            </form>

            <!-- Logo Upload Section -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">Logo aplikácie</h3>

                <!-- Current logo preview -->
                <div v-if="currentLogoUrl" class="mb-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Aktuálne logo:</p>
                    <div class="flex items-center gap-4">
                        <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                            <img :src="currentLogoUrl" alt="Logo" class="max-h-16 max-w-48 object-contain" />
                        </div>
                        <button
                            @click="deleteLogo"
                            class="btn btn-danger"
                            :disabled="logoDeleting"
                        >
                            <svg v-if="logoDeleting" class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ logoDeleting ? 'Odstraňujem...' : 'Odstrániť logo' }}
                        </button>
                    </div>
                </div>

                <!-- Upload form -->
                <div class="flex flex-col md:flex-row gap-3 items-start">
                    <div class="flex-1">
                        <input
                            id="logo-input"
                            type="file"
                            accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp"
                            @change="handleLogoSelect"
                            class="form-input file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-brand-navy file:text-white hover:file:bg-brand-navy/80 dark:file:bg-brand-gold dark:file:text-brand-navy"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Podporované formáty: PNG, JPG, SVG, WebP. Max. veľkosť: 2 MB.</p>
                    </div>
                    <button
                        @click="uploadLogo"
                        class="btn btn-primary"
                        :disabled="!logoFile || logoUploading"
                    >
                        <svg v-if="logoUploading" class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ logoUploading ? 'Nahrávam...' : 'Nahrať logo' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Backup & Restore Section -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                <svg class="w-5 h-5 inline-block mr-2 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
                Zaloha a obnovenie
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">
                Vytvorte kompletnu zalohu vsetkych dat alebo obnovte system zo zalohy.
            </p>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Create Backup -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Vytvorit zalohu
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
                        Exportuje formulare, kategorie, workflow, email sablony a nastavenia.
                    </p>

                    <label class="flex items-center gap-2 mb-4 cursor-pointer">
                        <input
                            v-model="includeSubmissions"
                            type="checkbox"
                            class="w-4 h-4 text-teal-600 rounded"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Zahrnout aj odoslane formulare (moze byt velky subor)
                        </span>
                    </label>

                    <button
                        @click="createBackup"
                        :disabled="backupInProgress"
                        class="btn btn-primary w-full"
                    >
                        <svg v-if="backupInProgress" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ backupInProgress ? 'Vytvaram zalohu...' : 'Stiahnut zalohu' }}
                    </button>
                </div>

                <!-- Restore Backup -->
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Obnovit zo zalohy
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
                        Nahrajte subor zalohy pre obnovenie dat.
                    </p>

                    <div v-if="!restoreData">
                        <input
                            id="restore-input"
                            type="file"
                            accept=".json"
                            @change="handleRestoreFileSelect"
                            class="form-input file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 dark:file:bg-orange-900/50 dark:file:text-orange-300"
                        />
                    </div>

                    <!-- Restore preview and options -->
                    <div v-if="restoreData" class="space-y-4">
                        <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Informacie o zalohe:</p>
                            <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <li>Datum: {{ new Date(restoreData.backup_date).toLocaleString('sk-SK') }}</li>
                                <li>Kategorii: {{ restoreData.stats?.categories_count || 0 }}</li>
                                <li>Formularov: {{ restoreData.stats?.forms_count || 0 }}</li>
                                <li>Email sablon: {{ restoreData.stats?.email_templates_count || 0 }}</li>
                                <li>Workflow: {{ restoreData.stats?.workflows_count || 0 }}</li>
                                <li v-if="restoreData.stats?.submissions_count !== 'not_included'">
                                    Odpovedí: {{ restoreData.stats?.submissions_count || 0 }}
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Co obnovit:</p>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="restoreOptions.restore_categories" type="checkbox" class="w-4 h-4 text-orange-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Kategorie</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="restoreOptions.restore_forms" type="checkbox" class="w-4 h-4 text-orange-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Formulare (vcetne workflow)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="restoreOptions.restore_email_templates" type="checkbox" class="w-4 h-4 text-orange-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Email sablony</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="restoreOptions.restore_workflows" type="checkbox" class="w-4 h-4 text-orange-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Samostatne workflow</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="restoreOptions.restore_settings" type="checkbox" class="w-4 h-4 text-orange-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Nastavenia vzhľadu</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="performRestore"
                                :disabled="restoreInProgress"
                                class="btn btn-primary flex-1"
                            >
                                <svg v-if="restoreInProgress" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ restoreInProgress ? 'Obnovujem...' : 'Obnovit' }}
                            </button>
                            <button
                                @click="cancelRestore"
                                :disabled="restoreInProgress"
                                class="btn btn-secondary"
                            >
                                Zrusit
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restore Results -->
            <div v-if="restoreResult" class="mt-6 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg">
                <h4 class="text-md font-semibold text-green-800 dark:text-green-300 mb-3">Obnovenie dokoncene!</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-green-600 dark:text-green-400">Kategorie:</span>
                        <span class="ml-1 text-green-800 dark:text-green-200">{{ restoreResult.categories?.imported || 0 }} novych, {{ restoreResult.categories?.updated || 0 }} aktualizovanych</span>
                    </div>
                    <div>
                        <span class="text-green-600 dark:text-green-400">Formulare:</span>
                        <span class="ml-1 text-green-800 dark:text-green-200">{{ restoreResult.forms?.imported || 0 }} importovanych</span>
                    </div>
                    <div>
                        <span class="text-green-600 dark:text-green-400">Email sablony:</span>
                        <span class="ml-1 text-green-800 dark:text-green-200">{{ restoreResult.email_templates?.imported || 0 }} novych, {{ restoreResult.email_templates?.updated || 0 }} aktualizovanych</span>
                    </div>
                    <div>
                        <span class="text-green-600 dark:text-green-400">Workflow:</span>
                        <span class="ml-1 text-green-800 dark:text-green-200">{{ restoreResult.workflows?.imported || 0 }} importovanych</span>
                    </div>
                </div>
                <button @click="restoreResult = null" class="mt-3 text-sm text-green-700 dark:text-green-400 hover:underline">
                    Zavriet
                </button>
            </div>

            <!-- Warning -->
            <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-sm text-yellow-800 dark:text-yellow-200 font-medium">Dolezite upozornenie</p>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                            Zaloha neobsahuje citlive udaje ako SMTP hesla alebo Keycloak client secret.
                            Obnovene formulare a workflow su nastavene ako neaktivne pre bezpecnost.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Scheduled Backup Settings -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">Automaticke zalohy</h3>

                <form @submit.prevent="saveBackupSettings" class="space-y-6">
                    <!-- Enable scheduled backups -->
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="backupSettingsForm.enabled" type="checkbox" class="w-5 h-5 text-teal-600 rounded" />
                        <div>
                            <span class="font-medium text-gray-900 dark:text-gray-100">Povolit automaticke zalohy</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Automaticky vytvarat zalohy podla rozvrhu</p>
                        </div>
                    </label>

                    <div v-if="backupSettingsForm.enabled" class="space-y-4 pl-8">
                        <div class="grid md:grid-cols-4 gap-4">
                            <div>
                                <label class="form-label">Frekvencia</label>
                                <select v-model="backupSettingsForm.frequency" class="form-input">
                                    <option value="daily">Denne</option>
                                    <option value="weekly">Tyzdenne (nedela)</option>
                                    <option value="monthly">Mesacne (1. den)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Cas zalohy</label>
                                <input v-model="backupSettingsForm.time" type="time" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Pocet lokalnych zaloh</label>
                                <input v-model.number="backupSettingsForm.retention_local" type="number" min="1" max="100" class="form-input" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer pb-2">
                                    <input v-model="backupSettingsForm.include_submissions" type="checkbox" class="w-4 h-4 text-teal-600 rounded" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Zahrnnut odpovede</span>
                                </label>
                            </div>
                        </div>

                        <!-- FTP Settings -->
                        <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <label class="flex items-center gap-3 cursor-pointer mb-4">
                                <input v-model="backupSettingsForm.ftp_enabled" type="checkbox" class="w-5 h-5 text-blue-600 rounded" />
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">FTP</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">Zalohovanie na FTP server</span>
                                </div>
                            </label>

                            <div v-if="backupSettingsForm.ftp_enabled" class="space-y-4">
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="form-label">FTP Host</label>
                                        <input v-model="backupSettingsForm.ftp_host" type="text" class="form-input" placeholder="ftp.example.com" />
                                    </div>
                                    <div>
                                        <label class="form-label">Port</label>
                                        <input v-model.number="backupSettingsForm.ftp_port" type="number" class="form-input" />
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label">Pouzivatel</label>
                                        <input v-model="backupSettingsForm.ftp_username" type="text" class="form-input" />
                                    </div>
                                    <div>
                                        <label class="form-label">Heslo</label>
                                        <div class="relative">
                                            <input v-model="backupSettingsForm.ftp_password" :type="showFtpPassword ? 'text' : 'password'" class="form-input pr-10" :placeholder="hasFtpPassword ? 'Heslo je nastavene' : 'Zadajte heslo'" />
                                            <button type="button" @click="showFtpPassword = !showFtpPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path v-if="showFtpPassword" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <p v-if="hasFtpPassword" class="text-green-600 dark:text-green-400 text-xs mt-1">Heslo je nastavene</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="form-label">Cesta</label>
                                        <input v-model="backupSettingsForm.ftp_path" type="text" class="form-input" placeholder="/backups" />
                                    </div>
                                    <div>
                                        <label class="form-label">Pocet zaloh</label>
                                        <input v-model.number="backupSettingsForm.ftp_retention" type="number" min="1" class="form-input" />
                                    </div>
                                    <div class="flex items-end gap-4 pb-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="backupSettingsForm.ftp_passive" type="checkbox" class="w-4 h-4 rounded" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Passive</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="backupSettingsForm.ftp_ssl" type="checkbox" class="w-4 h-4 rounded" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">SSL</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="button" @click="testFtpConnection" :disabled="testingFtp" class="btn btn-secondary">
                                    {{ testingFtp ? 'Testujem...' : 'Otestovat FTP' }}
                                </button>
                            </div>
                        </div>

                        <!-- S3 Settings -->
                        <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <label class="flex items-center gap-3 cursor-pointer mb-4">
                                <input v-model="backupSettingsForm.s3_enabled" type="checkbox" class="w-5 h-5 text-orange-600 rounded" />
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">S3 / MinIO</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">Zalohovanie na S3 kompatibilne ulozisko</span>
                                </div>
                            </label>

                            <div v-if="backupSettingsForm.s3_enabled" class="space-y-4">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label">Access Key</label>
                                        <input v-model="backupSettingsForm.s3_key" type="text" class="form-input" />
                                    </div>
                                    <div>
                                        <label class="form-label">Secret Key</label>
                                        <div class="relative">
                                            <input v-model="backupSettingsForm.s3_secret" :type="showS3Secret ? 'text' : 'password'" class="form-input pr-10" :placeholder="hasS3Secret ? 'Secret je nastaveny' : 'Zadajte secret key'" />
                                            <button type="button" @click="showS3Secret = !showS3Secret" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path v-if="showS3Secret" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <p v-if="hasS3Secret" class="text-green-600 dark:text-green-400 text-xs mt-1">Secret je nastaveny</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="form-label">Region</label>
                                        <input v-model="backupSettingsForm.s3_region" type="text" class="form-input" placeholder="eu-central-1" />
                                    </div>
                                    <div>
                                        <label class="form-label">Bucket</label>
                                        <input v-model="backupSettingsForm.s3_bucket" type="text" class="form-input" />
                                    </div>
                                    <div>
                                        <label class="form-label">Cesta v bucket</label>
                                        <input v-model="backupSettingsForm.s3_path" type="text" class="form-input" placeholder="backups" />
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="form-label">Endpoint (pre MinIO/custom S3)</label>
                                        <input v-model="backupSettingsForm.s3_endpoint" type="text" class="form-input" placeholder="https://minio.example.com" />
                                    </div>
                                    <div>
                                        <label class="form-label">Pocet zaloh</label>
                                        <input v-model.number="backupSettingsForm.s3_retention" type="number" min="1" class="form-input" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="backupSettingsForm.s3_use_path_style" type="checkbox" class="w-4 h-4 rounded" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Path style endpoint (pre MinIO)</span>
                                    </label>
                                    <button type="button" @click="testS3Connection" :disabled="testingS3" class="btn btn-secondary">
                                        {{ testingS3 ? 'Testujem...' : 'Otestovat S3' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary" :disabled="backupSettingsForm.processing">
                            {{ backupSettingsForm.processing ? 'Ukladam...' : 'Ulozit nastavenia zaloh' }}
                        </button>
                    </div>
                </form>

                <!-- Run Backup Now -->
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">Spustit zalohu teraz</h4>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="local" v-model="selectedBackupDestinations" class="w-4 h-4 rounded" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Lokalne</span>
                            </label>
                            <label v-if="backupSettingsForm.ftp_enabled" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="ftp" v-model="selectedBackupDestinations" class="w-4 h-4 rounded" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">FTP</span>
                            </label>
                            <label v-if="backupSettingsForm.s3_enabled" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="s3" v-model="selectedBackupDestinations" class="w-4 h-4 rounded" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">S3</span>
                            </label>
                        </div>
                        <button @click="runBackupNow" :disabled="runningBackupNow || selectedBackupDestinations.length === 0" class="btn btn-primary">
                            {{ runningBackupNow ? 'Vytvaram zalohu...' : 'Spustit zalohu' }}
                        </button>
                    </div>
                </div>

                <!-- Local Backups List -->
                <div v-if="localBackupsList.length > 0" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">Lokalne zalohy</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subor</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Velkost</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Datum</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Akcie</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="backup in localBackupsList" :key="backup.filename">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ backup.filename }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ formatBytes(backup.size) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ backup.created_at }}</td>
                                    <td class="px-4 py-2 text-right space-x-2">
                                        <button @click="downloadLocalBackup(backup.filename)" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                            Stiahnut
                                        </button>
                                        <button @click="deleteLocalBackup(backup.filename)" :disabled="deletingBackup === backup.filename" class="text-red-600 dark:text-red-400 hover:underline text-sm">
                                            {{ deletingBackup === backup.filename ? '...' : 'Zmazat' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keycloak Settings -->
        <div class="card mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                <svg class="w-5 h-5 inline-block mr-2 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                Nastavenia Keycloak (SSO)
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Nakonfigurujte pripojenie k Keycloak serveru pre autentifikaciu.</p>

            <form @submit.prevent="saveKeycloakSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="form-label">Keycloak Base URL</label>
                        <input
                            v-model="keycloakForm.base_url"
                            type="url"
                            class="form-input"
                            placeholder="https://sso.example.com"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Zakladna URL adresa Keycloak servera (bez /auth)</p>
                        <p v-if="keycloakForm.errors.base_url" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ keycloakForm.errors.base_url }}</p>
                    </div>

                    <div>
                        <label class="form-label">Realm</label>
                        <input
                            v-model="keycloakForm.realm"
                            type="text"
                            class="form-input"
                            placeholder="MyRealm"
                        />
                        <p v-if="keycloakForm.errors.realm" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ keycloakForm.errors.realm }}</p>
                    </div>

                    <div>
                        <label class="form-label">Client ID</label>
                        <input
                            v-model="keycloakForm.client_id"
                            type="text"
                            class="form-input"
                            placeholder="your-client-id"
                        />
                        <p v-if="keycloakForm.errors.client_id" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ keycloakForm.errors.client_id }}</p>
                    </div>

                    <div>
                        <label class="form-label">Client Secret</label>
                        <div class="relative">
                            <input
                                v-model="keycloakForm.client_secret"
                                :type="showClientSecret ? 'text' : 'password'"
                                class="form-input pr-10"
                                :placeholder="hasKeycloakSecret ? 'Secret je nastaveny' : 'Zadajte client secret'"
                            />
                            <button
                                type="button"
                                @click="showClientSecret = !showClientSecret"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                <svg v-if="showClientSecret" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="hasKeycloakSecret" class="text-green-600 dark:text-green-400 text-xs mt-1">Secret je nastaveny. Nechajte prazdne ak nechcete zmenit.</p>
                        <p v-else class="text-gray-500 dark:text-gray-400 text-xs mt-1">Zadajte client secret z Keycloak</p>
                        <p v-if="keycloakForm.errors.client_secret" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ keycloakForm.errors.client_secret }}</p>
                    </div>

                    <div>
                        <label class="form-label">Redirect URI</label>
                        <input
                            v-model="keycloakForm.redirect_uri"
                            type="url"
                            class="form-input"
                            placeholder="http://localhost:8080/auth/callback"
                        />
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">URL kam bude pouzivatel presmerovany po prihlaseni</p>
                        <p v-if="keycloakForm.errors.redirect_uri" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ keycloakForm.errors.redirect_uri }}</p>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between gap-3">
                    <button
                        type="button"
                        @click="testKeycloakConnection"
                        class="btn btn-secondary"
                        :disabled="keycloakForm.processing"
                    >
                        {{ keycloakForm.processing ? 'Testujem...' : 'Otestovat pripojenie' }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="keycloakForm.processing"
                    >
                        {{ keycloakForm.processing ? 'Ukladam...' : 'Ulozit nastavenia Keycloak' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Mail Settings -->
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                <svg class="w-5 h-5 inline-block mr-2 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Nastavenia emailu (SMTP)
            </h2>

            <form @submit.prevent="saveSettings" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">SMTP Server</label>
                        <input
                            v-model="form.host"
                            type="text"
                            class="form-input"
                            placeholder="smtp.example.com"
                        />
                        <p v-if="form.errors.host" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.host }}</p>
                    </div>

                    <div>
                        <label class="form-label">Port</label>
                        <input
                            v-model="form.port"
                            type="number"
                            class="form-input"
                            placeholder="587"
                        />
                        <p v-if="form.errors.port" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.port }}</p>
                    </div>

                    <div>
                        <label class="form-label">Pouzivatelske meno</label>
                        <input
                            v-model="form.username"
                            type="text"
                            class="form-input"
                            placeholder="user@example.com"
                        />
                        <p v-if="form.errors.username" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="form-label">Heslo</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="form-input pr-10"
                                :placeholder="hasMailPassword ? 'Heslo je nastavene' : 'Zadajte heslo'"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="hasMailPassword" class="text-green-600 dark:text-green-400 text-xs mt-1">Heslo je nastavene. Nechajte prazdne ak nechcete zmenit.</p>
                        <p v-else class="text-gray-500 dark:text-gray-400 text-xs mt-1">Zadajte heslo pre SMTP autentifikaciu</p>
                        <p v-if="form.errors.password" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="form-label">Sifrovanie</label>
                        <select v-model="form.encryption" class="form-input">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="null">Ziadne</option>
                        </select>
                        <p v-if="form.errors.encryption" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.encryption }}</p>
                    </div>

                    <div>
                        <label class="form-label">Email odosielatela</label>
                        <input
                            v-model="form.from_address"
                            type="email"
                            class="form-input"
                            placeholder="noreply@example.com"
                        />
                        <p v-if="form.errors.from_address" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.from_address }}</p>
                    </div>

                    <div>
                        <label class="form-label">Meno odosielatela</label>
                        <input
                            v-model="form.from_name"
                            type="text"
                            class="form-input"
                            placeholder="Formulare"
                        />
                        <p v-if="form.errors.from_name" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ form.errors.from_name }}</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Ukladam...' : 'Ulozit nastavenia emailu' }}
                    </button>
                </div>
            </form>

            <!-- Test Email Section -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                <h3 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">Otestovat odosielanie emailov</h3>
                <form @submit.prevent="sendTestEmail" class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1">
                        <input
                            v-model="testForm.email"
                            type="email"
                            class="form-input"
                            placeholder="vas@email.com"
                            required
                        />
                        <p v-if="testForm.errors.email" class="text-red-500 dark:text-red-400 text-sm mt-1">{{ testForm.errors.email }}</p>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-secondary"
                        :disabled="testForm.processing"
                    >
                        {{ testForm.processing ? 'Odosielam...' : 'Odoslat testovaci email' }}
                    </button>
                </form>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
                    Odosle testovaci email na zadanu adresu pre overenie nastaveni.
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
