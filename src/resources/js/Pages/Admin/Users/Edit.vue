<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    user: Object,
    auth: Object,
    forms: Array,
    allowedFormIds: Array,
    roles: Array,
});

const form = useForm({
    name: props.user.name,
    role: props.user.role || 'user',
    can_see_all_forms: props.user.can_see_all_forms || false,
    allowed_form_ids: props.allowedFormIds || [],
});

const getRoleLabel = (role) => {
    switch (role) {
        case 'super_admin': return t('users.roles.super_admin');
        case 'admin': return t('users.roles.admin');
        case 'approver': return t('users.roles.approver');
        case 'viewer': return t('users.roles.viewer');
        default: return t('users.roles.user');
    }
};

// Check if current user can edit role (only super_admin can edit roles)
const canEditRole = computed(() => {
    // Can't edit own role if you're super_admin (would lock yourself out)
    if (props.user.id === props.auth.user.id && props.user.role === 'super_admin') {
        return false;
    }
    return true;
});

// Check if user has admin panel access (for showing form permissions)
const hasAdminAccess = computed(() => {
    return ['viewer', 'approver', 'admin', 'super_admin'].includes(form.role);
});

const searchForm = ref('');

const filteredForms = computed(() => {
    if (!searchForm.value) return props.forms;
    const search = searchForm.value.toLowerCase();
    return props.forms.filter(f => f.name.toLowerCase().includes(search));
});

const toggleForm = (formId) => {
    const index = form.allowed_form_ids.indexOf(formId);
    if (index > -1) {
        form.allowed_form_ids.splice(index, 1);
    } else {
        form.allowed_form_ids.push(formId);
    }
};

const selectAllForms = () => {
    form.allowed_form_ids = props.forms.map(f => f.id);
};

const deselectAllForms = () => {
    form.allowed_form_ids = [];
};

const submit = () => {
    form.transform(data => ({ ...data, _method: 'PUT' })).post(`/admin/users/${props.user.id}`);
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
</script>

<template>
    <Head :title="`Upraviť: ${user.name}`" />
    <AdminLayout :auth="auth">
        <div class="max-w-2xl">
            <div class="mb-6">
                <Link href="/admin/users" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 text-sm">
                    ← Späť na zoznam
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Upraviť používateľa</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="card">
                    <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-600">
                        <div class="w-16 h-16 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-2xl text-gray-600 dark:text-gray-300 font-medium">
                            {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ user.first_name && user.last_name
                                    ? `${user.first_name} ${user.last_name}`
                                    : user.name }}
                            </h2>
                            <p class="text-gray-500 dark:text-gray-400">
                                <span v-if="user.login" class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded mr-2">{{ user.login }}</span>
                                {{ user.email }}
                            </p>
                            <p class="text-sm text-gray-400 dark:text-gray-500">Registrovaný: {{ formatDate(user.created_at) }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Meno</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="form-input"
                                required
                            />
                            <p v-if="form.errors.name" class="text-red-500 dark:text-red-400 text-sm mt-1">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="form-label">Email</label>
                            <input
                                :value="user.email"
                                type="email"
                                class="form-input bg-gray-100 dark:bg-gray-700"
                                disabled
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Email nie je možné zmeniť (spravuje Keycloak)</p>
                        </div>

                        <div>
                            <label class="form-label">Keycloak ID</label>
                            <input
                                :value="user.keycloak_id || 'Nie je priradený'"
                                type="text"
                                class="form-input bg-gray-100 dark:bg-gray-700"
                                disabled
                            />
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-600">
                            <label class="form-label">{{ t('users.role') }}</label>
                            <select
                                v-model="form.role"
                                class="form-input"
                                :disabled="!canEditRole"
                            >
                                <option value="user">{{ t('users.roles.user') }} - {{ t('users.roleDesc.user') }}</option>
                                <option value="viewer">{{ t('users.roles.viewer') }} - {{ t('users.roleDesc.viewer') }}</option>
                                <option value="approver">{{ t('users.roles.approver') }} - {{ t('users.roleDesc.approver') }}</option>
                                <option value="admin">{{ t('users.roles.admin') }} - {{ t('users.roleDesc.admin') }}</option>
                                <option value="super_admin">{{ t('users.roles.super_admin') }} - {{ t('users.roleDesc.super_admin') }}</option>
                            </select>
                            <p v-if="!canEditRole" class="text-yellow-600 dark:text-yellow-400 text-sm mt-2">
                                {{ t('users.cannotEditOwnRole') }}
                            </p>
                            <p v-if="form.errors.role" class="text-red-500 dark:text-red-400 text-sm mt-1">
                                {{ form.errors.role }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form Permissions (only for users with admin access) -->
                <div v-if="hasAdminAccess" class="card mt-6">
                    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Prístup k formulárom (admin)</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Bežní používatelia vidia automaticky všetky verejné a aktívne formuláre. Pre adminov je potrebné explicitne nastaviť, ktoré formuláre môžu vidieť.
                    </p>

                    <div class="space-y-4">
                        <!-- All forms toggle -->
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="form.can_see_all_forms"
                                type="checkbox"
                                class="w-5 h-5 text-blue-600 rounded"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">Prístup ku všetkým formulárom</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Admin bude mať prístup ku všetkým formulárom v systéme (vrátane neaktívnych a súkromných).
                                </p>
                            </div>
                        </label>

                        <!-- Individual form selection -->
                        <div v-if="!form.can_see_all_forms" class="pt-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between mb-3">
                                <label class="form-label mb-0">Vybrať konkrétne formuláre</label>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="selectAllForms"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        Vybrať všetko
                                    </button>
                                    <span class="text-gray-400">|</span>
                                    <button
                                        type="button"
                                        @click="deselectAllForms"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:underline"
                                    >
                                        Zrušiť výber
                                    </button>
                                </div>
                            </div>

                            <!-- Search -->
                            <input
                                v-model="searchForm"
                                type="text"
                                placeholder="Vyhľadať formulár..."
                                class="form-input mb-3"
                            />

                            <!-- Forms list -->
                            <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                                <div v-if="filteredForms.length === 0" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    Žiadne formuláre nenájdené
                                </div>
                                <label
                                    v-for="formItem in filteredForms"
                                    :key="formItem.id"
                                    class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="form.allowed_form_ids.includes(formItem.id)"
                                        @change="toggleForm(formItem.id)"
                                        class="w-4 h-4 text-blue-600 rounded"
                                    />
                                    <span class="text-gray-900 dark:text-gray-100">{{ formItem.name }}</span>
                                </label>
                            </div>

                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Vybraných: {{ form.allowed_form_ids.length }} z {{ forms.length }} formulárov
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <Link href="/admin/users" class="btn btn-secondary">
                        Zrušiť
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primary"
                    >
                        {{ form.processing ? 'Ukladám...' : 'Uložiť zmeny' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
