<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    announcements: Array,
    auth: Object,
});

// Modal state
const showModal = ref(false);
const editingAnnouncement = ref(null);
const isSubmitting = ref(false);

// Form state
const form = ref({
    title: '',
    message: '',
    type: 'info',
    link_url: '',
    link_text: '',
    is_active: true,
    is_dismissible: true,
    starts_at: '',
    ends_at: '',
    order: 0,
});

const typeOptions = [
    { value: 'info', label: 'Info', color: 'bg-blue-500' },
    { value: 'warning', label: 'Varovanie', color: 'bg-amber-500' },
    { value: 'success', label: 'Uspech', color: 'bg-green-500' },
    { value: 'error', label: 'Chyba', color: 'bg-red-500' },
];

const getTypeColor = (type) => {
    const option = typeOptions.find(o => o.value === type);
    return option?.color || 'bg-blue-500';
};

const getTypeLabel = (type) => {
    const option = typeOptions.find(o => o.value === type);
    return option?.label || type;
};

const openCreateModal = () => {
    editingAnnouncement.value = null;
    form.value = {
        title: '',
        message: '',
        type: 'info',
        link_url: '',
        link_text: '',
        is_active: true,
        is_dismissible: true,
        starts_at: '',
        ends_at: '',
        order: 0,
    };
    showModal.value = true;
};

const openEditModal = (announcement) => {
    editingAnnouncement.value = announcement;
    form.value = {
        title: announcement.title,
        message: announcement.message,
        type: announcement.type,
        link_url: announcement.link_url || '',
        link_text: announcement.link_text || '',
        is_active: announcement.is_active,
        is_dismissible: announcement.is_dismissible,
        starts_at: announcement.starts_at ? announcement.starts_at.split('T')[0] : '',
        ends_at: announcement.ends_at ? announcement.ends_at.split('T')[0] : '',
        order: announcement.order,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingAnnouncement.value = null;
};

const submitForm = () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    const url = editingAnnouncement.value
        ? `/admin/announcements/${editingAnnouncement.value.id}`
        : '/admin/announcements';

    const data = editingAnnouncement.value
        ? { _method: 'PUT', ...form.value }
        : form.value;

    router.post(url, data, {
        onSuccess: () => {
            closeModal();
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const deleteAnnouncement = (announcement) => {
    if (confirm(`Naozaj chcete zmazat oznamenie "${announcement.title}"?`)) {
        router.post(`/admin/announcements/${announcement.id}`, { _method: 'delete' });
    }
};

const toggleActive = (announcement) => {
    router.post(`/admin/announcements/${announcement.id}`, {
        _method: 'PUT',
        ...announcement,
        is_active: !announcement.is_active,
    }, {
        preserveScroll: true,
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('sk-SK');
};

const isExpired = (announcement) => {
    if (!announcement.ends_at) return false;
    return new Date(announcement.ends_at) < new Date();
};

const isPending = (announcement) => {
    if (!announcement.starts_at) return false;
    return new Date(announcement.starts_at) > new Date();
};
</script>

<template>
    <Head title="Oznamenia" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Oznamenia</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Spravujte oznamenia zobrazene na uvodnej stranke</p>
            </div>
            <button @click="openCreateModal" class="btn btn-primary flex-shrink-0">
                + Nove oznamenie
            </button>
        </div>

        <!-- Announcements list -->
        <div class="card">
            <div v-if="announcements.length" class="space-y-4">
                <div
                    v-for="announcement in announcements"
                    :key="announcement.id"
                    class="flex items-start justify-between p-4 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg"
                    :class="{
                        'opacity-50': !announcement.is_active || isExpired(announcement),
                    }"
                >
                    <div class="flex items-start space-x-4 flex-1">
                        <!-- Type indicator -->
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                            :class="getTypeColor(announcement.type)"
                        >
                            <svg v-if="announcement.type === 'info'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else-if="announcement.type === 'warning'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg v-else-if="announcement.type === 'success'" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ announcement.title }}</h3>
                                <span
                                    class="px-2 py-0.5 text-xs rounded-full font-medium"
                                    :class="getTypeColor(announcement.type) + ' text-white'"
                                >
                                    {{ getTypeLabel(announcement.type) }}
                                </span>
                                <span v-if="isExpired(announcement)" class="px-2 py-0.5 text-xs rounded-full font-medium bg-gray-400 text-white">
                                    Expirovane
                                </span>
                                <span v-else-if="isPending(announcement)" class="px-2 py-0.5 text-xs rounded-full font-medium bg-purple-500 text-white">
                                    Planovane
                                </span>
                                <span v-else-if="!announcement.is_active" class="px-2 py-0.5 text-xs rounded-full font-medium bg-gray-400 text-white">
                                    Neaktivne
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ announcement.message }}</p>
                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="announcement.link_url" class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    {{ announcement.link_text || 'Link' }}
                                </span>
                                <span v-if="announcement.starts_at || announcement.ends_at" class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ formatDate(announcement.starts_at) }} - {{ formatDate(announcement.ends_at) }}
                                </span>
                                <span v-if="announcement.creator" class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    {{ announcement.creator.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center space-x-2 ml-4">
                        <button
                            @click="toggleActive(announcement)"
                            class="p-2 rounded-lg transition-colors"
                            :class="announcement.is_active
                                ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30'
                                : 'text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'"
                            :title="announcement.is_active ? 'Deaktivovat' : 'Aktivovat'"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="announcement.is_active" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path v-if="announcement.is_active" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                        <button
                            @click="openEditModal(announcement)"
                            class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                            title="Upravit"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            @click="deleteAnnouncement(announcement)"
                            class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                            title="Zmazat"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                    </svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Ziadne oznamenia</p>
                <button @click="openCreateModal" class="btn btn-primary">
                    Vytvorit prve oznamenie
                </button>
            </div>
        </div>

        <!-- Modal for create/edit -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen px-4">
                        <div class="fixed inset-0 bg-black/50" @click="closeModal"></div>

                        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                                {{ editingAnnouncement ? 'Upravit oznamenie' : 'Nove oznamenie' }}
                            </h2>

                            <form @submit.prevent="submitForm" class="space-y-4">
                                <!-- Title -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Nadpis *
                                    </label>
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                        placeholder="Napr. Planovana odstávka systemu"
                                    />
                                </div>

                                <!-- Message -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Sprava *
                                    </label>
                                    <textarea
                                        v-model="form.message"
                                        required
                                        rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                        placeholder="Detailny popis oznamenia..."
                                    ></textarea>
                                </div>

                                <!-- Type -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Typ
                                    </label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <button
                                            v-for="type in typeOptions"
                                            :key="type.value"
                                            type="button"
                                            @click="form.type = type.value"
                                            class="px-3 py-2 rounded-lg border-2 text-sm font-medium transition-all"
                                            :class="form.type === type.value
                                                ? type.color + ' text-white border-transparent'
                                                : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300'"
                                        >
                                            {{ type.label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Link -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            URL odkazu
                                        </label>
                                        <input
                                            v-model="form.link_url"
                                            type="url"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                            placeholder="https://..."
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Text odkazu
                                        </label>
                                        <input
                                            v-model="form.link_text"
                                            type="text"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                            placeholder="Viac info"
                                        />
                                    </div>
                                </div>

                                <!-- Dates -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Zobrazit od
                                        </label>
                                        <input
                                            v-model="form.starts_at"
                                            type="date"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Zobrazit do
                                        </label>
                                        <input
                                            v-model="form.ends_at"
                                            type="date"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <!-- Order -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Poradie (nizšie = vyssie)
                                    </label>
                                    <input
                                        v-model.number="form.order"
                                        type="number"
                                        min="0"
                                        class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-brand-gold focus:border-brand-gold dark:bg-gray-700 dark:text-white"
                                    />
                                </div>

                                <!-- Toggles -->
                                <div class="flex items-center gap-6">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="w-4 h-4 text-brand-gold border-gray-300 rounded focus:ring-brand-gold"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Aktivne</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            v-model="form.is_dismissible"
                                            type="checkbox"
                                            class="w-4 h-4 text-brand-gold border-gray-300 rounded focus:ring-brand-gold"
                                        />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Mozno skryt</span>
                                    </label>
                                </div>

                                <!-- Actions -->
                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <button
                                        type="button"
                                        @click="closeModal"
                                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                    >
                                        Zrusit
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="isSubmitting"
                                        class="btn btn-primary"
                                    >
                                        {{ isSubmitting ? 'Ukladam...' : (editingAnnouncement ? 'Ulozit' : 'Vytvorit') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </AdminLayout>
</template>
