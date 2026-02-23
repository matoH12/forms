<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useLocalized } from '@/composables/useLocalized';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { getLocalized } = useLocalized();

const props = defineProps({
    forms: Array,
    featuredForms: Array,
    auth: Object,
    search: String,
    categories: Array,
    selectedCategory: String,
    announcements: Array,
    supportEmail: String,
});

// Get user's first name for greeting
const userFirstName = computed(() => {
    if (!props.auth?.user) return null;
    // Try first_name, then extract from full name, then use name
    if (props.auth.user.first_name) return props.auth.user.first_name;
    if (props.auth.user.name) {
        const parts = props.auth.user.name.split(' ');
        return parts[0];
    }
    return null;
});

// Get greeting based on time of day
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return t('public.greeting.morning');
    if (hour < 18) return t('public.greeting.afternoon');
    return t('public.greeting.evening');
});

// Icons for featured forms (based on settings or category)
const getFeaturedIcon = (form) => {
    // Check if form has custom icon in settings
    const icon = form.settings?.featured_icon;
    if (icon) return icon;

    // Default icons based on common form types
    const name = (getFormName(form) || '').toLowerCase();
    if (name.includes('problém') || name.includes('chyba') || name.includes('incident')) {
        return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
    }
    if (name.includes('prístup') || name.includes('vpn') || name.includes('účet') || name.includes('heslo')) {
        return 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z';
    }
    if (name.includes('potvrdenie') || name.includes('certifikát')) {
        return 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z';
    }
    if (name.includes('žiadosť')) {
        return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    }
    // Default icon
    return 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';
};

// Helper to get localized form name
const getFormName = (form) => {
    return getLocalized(form.name) || form.slug;
};

// Helper to get localized form description
const getFormDescription = (form) => {
    return getLocalized(form.description) || '';
};

const searchQuery = ref(props.search || '');
const selectedCategorySlug = ref(props.selectedCategory || null);
const isSearching = ref(false);

// Dismissed announcements (stored in localStorage)
const dismissedAnnouncements = ref(JSON.parse(localStorage.getItem('dismissedAnnouncements') || '[]'));

const visibleAnnouncements = computed(() => {
    if (!props.announcements) return [];
    return props.announcements.filter(a => !dismissedAnnouncements.value.includes(a.id));
});

const dismissAnnouncement = (announcementId) => {
    dismissedAnnouncements.value.push(announcementId);
    localStorage.setItem('dismissedAnnouncements', JSON.stringify(dismissedAnnouncements.value));
};

const getAnnouncementIcon = (type) => {
    switch (type) {
        case 'warning':
            return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
        case 'error':
            return 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
        case 'success':
            return 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
        default: // info
            return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    }
};

const getAnnouncementColors = (type) => {
    switch (type) {
        case 'warning':
            return {
                bg: 'bg-amber-50 dark:bg-amber-900/20',
                border: 'border-amber-200 dark:border-amber-800',
                text: 'text-amber-800 dark:text-amber-200',
                icon: 'text-amber-500',
            };
        case 'error':
            return {
                bg: 'bg-red-50 dark:bg-red-900/20',
                border: 'border-red-200 dark:border-red-800',
                text: 'text-red-800 dark:text-red-200',
                icon: 'text-red-500',
            };
        case 'success':
            return {
                bg: 'bg-green-50 dark:bg-green-900/20',
                border: 'border-green-200 dark:border-green-800',
                text: 'text-green-800 dark:text-green-200',
                icon: 'text-green-500',
            };
        default: // info
            return {
                bg: 'bg-blue-50 dark:bg-blue-900/20',
                border: 'border-blue-200 dark:border-blue-800',
                text: 'text-blue-800 dark:text-blue-200',
                icon: 'text-blue-500',
            };
    }
};

// View mode: 'grid' or 'list'
const viewMode = ref(localStorage.getItem('formViewMode') || 'grid');

// Save view mode to localStorage
watch(viewMode, (newValue) => {
    localStorage.setItem('formViewMode', newValue);
});

// Sticky search bar
const isSearchSticky = ref(false);
const searchBoxRef = ref(null);
const searchBoxTop = ref(0);

// Mobile search panel
const showMobileSearch = ref(false);
const mobileSearchInput = ref(null);

// Category swipe
const categoryContainer = ref(null);
let touchStartX = 0;
let touchEndX = 0;

const handleTouchStart = (e) => {
    touchStartX = e.touches[0].clientX;
};

const handleTouchMove = (e) => {
    touchEndX = e.touches[0].clientX;
};

const handleTouchEnd = () => {
    const swipeDistance = touchStartX - touchEndX;
    if (categoryContainer.value) {
        categoryContainer.value.scrollLeft += swipeDistance * 0.5;
    }
};

// Scroll handler for sticky search
const handleScroll = () => {
    if (searchBoxTop.value > 0) {
        isSearchSticky.value = window.scrollY > searchBoxTop.value + 100;
    }
};

onMounted(() => {
    // Get search box position
    if (searchBoxRef.value) {
        searchBoxTop.value = searchBoxRef.value.offsetTop;
    }
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

// Focus mobile search when opened
watch(showMobileSearch, (show) => {
    if (show) {
        setTimeout(() => {
            mobileSearchInput.value?.focus();
        }, 100);
    }
});

// Update URL with search and category
const updateFilters = (params = {}) => {
    const queryParams = {};

    if (searchQuery.value && searchQuery.value.length >= 2) {
        queryParams.search = searchQuery.value;
    }

    if (selectedCategorySlug.value) {
        queryParams.category = selectedCategorySlug.value;
    }

    // Merge with provided params
    Object.assign(queryParams, params);

    router.get('/', queryParams, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            isSearching.value = false;
        }
    });
};

// Debounced search
let searchTimeout = null;
watch(searchQuery, (newValue) => {
    clearTimeout(searchTimeout);

    if (newValue.length === 0) {
        isSearching.value = true;
        updateFilters({ search: undefined });
        return;
    }

    if (newValue.length < 2) return;

    isSearching.value = true;
    searchTimeout = setTimeout(() => {
        updateFilters();
    }, 300);
});

const selectCategory = (slug) => {
    if (selectedCategorySlug.value === slug) {
        selectedCategorySlug.value = null;
    } else {
        selectedCategorySlug.value = slug;
    }
    isSearching.value = true;
    updateFilters({ category: selectedCategorySlug.value || undefined });
};

const clearSearch = () => {
    searchQuery.value = '';
    selectedCategorySlug.value = null;
    showMobileSearch.value = false;
    router.get('/', {}, { preserveState: true, replace: true });
};

const clearCategoryFilter = () => {
    selectedCategorySlug.value = null;
    updateFilters({ category: undefined });
};

// Get selected category object
const selectedCategoryObj = computed(() => {
    if (!selectedCategorySlug.value || !props.categories) return null;
    return props.categories.find(c => c.slug === selectedCategorySlug.value);
});
</script>

<template>
    <Head title="Formuláre" />
    <PublicLayout :auth="auth">
        <!-- Announcements Banner -->
        <div v-if="visibleAnnouncements.length" class="mb-6 space-y-3">
            <div
                v-for="announcement in visibleAnnouncements"
                :key="announcement.id"
                :class="[
                    getAnnouncementColors(announcement.type).bg,
                    getAnnouncementColors(announcement.type).border,
                    'border rounded-xl p-4'
                ]"
            >
                <div class="flex items-start gap-3">
                    <svg
                        class="w-5 h-5 flex-shrink-0 mt-0.5"
                        :class="getAnnouncementColors(announcement.type).icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getAnnouncementIcon(announcement.type)" />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <h4
                            class="font-semibold text-sm"
                            :class="getAnnouncementColors(announcement.type).text"
                        >
                            {{ announcement.title }}
                        </h4>
                        <p
                            class="text-sm mt-1"
                            :class="getAnnouncementColors(announcement.type).text"
                        >
                            {{ announcement.message }}
                        </p>
                        <a
                            v-if="announcement.link_url"
                            :href="announcement.link_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-sm font-medium mt-2 hover:underline"
                            :class="getAnnouncementColors(announcement.type).text"
                        >
                            {{ announcement.link_text || 'Viac info' }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                    <button
                        v-if="announcement.is_dismissible"
                        @click="dismissAnnouncement(announcement.id)"
                        class="p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors flex-shrink-0"
                        :class="getAnnouncementColors(announcement.type).text"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sticky Search Bar (appears on scroll) -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="-translate-y-full"
                enter-to-class="translate-y-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-y-0"
                leave-to-class="-translate-y-full"
            >
                <div
                    v-if="isSearchSticky"
                    class="fixed top-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 shadow-lg border-b border-gray-200 dark:border-gray-700 py-3 px-4"
                >
                    <div class="max-w-4xl mx-auto">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Vyhľadať formulár... (napr. cloud, webhosting, VPN)"
                                class="w-full pl-10 pr-10 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand-gold focus:ring-1 focus:ring-brand-gold dark:bg-gray-800 dark:text-white"
                            />
                            <button
                                v-if="searchQuery"
                                @click="clearSearch"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Mobile Search Panel -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showMobileSearch"
                    class="fixed inset-0 z-50 bg-white dark:bg-gray-900 md:hidden"
                >
                    <div class="p-4">
                        <div class="flex items-center gap-3 mb-4">
                            <button
                                @click="showMobileSearch = false"
                                class="p-2 -ml-2 text-gray-500 hover:text-gray-700 dark:text-gray-400"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <div class="flex-1 relative">
                                <input
                                    ref="mobileSearchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Vyhľadať formulár... (napr. cloud, webhosting, VPN)"
                                    class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-700 rounded-xl focus:border-brand-gold dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Zadajte názov formulára alebo kľúčové slová
                        </p>
                        <!-- Quick category buttons -->
                        <div v-if="categories && categories.length" class="space-y-2">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Kategórie</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="category in categories"
                                    :key="category.id"
                                    @click="selectCategory(category.slug); showMobileSearch = false"
                                    class="px-3 py-2 rounded-lg text-sm font-medium transition-all"
                                    :class="selectedCategorySlug === category.slug
                                        ? 'text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                                    :style="selectedCategorySlug === category.slug ? { backgroundColor: category.color } : {}"
                                >
                                    {{ getLocalized(category.name) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Hero section - Personalized -->
        <div class="text-center mb-8">
            <!-- Personalized greeting for logged in users -->
            <template v-if="auth?.user && userFirstName">
                <h1 class="text-3xl md:text-4xl font-bold text-brand-navy dark:text-white mb-3">
                    {{ greeting }}, {{ userFirstName }}! 👋
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    {{ t('public.whatDoYouNeed') }}
                </p>
            </template>
            <!-- Default for guests -->
            <template v-else>
                <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-navy dark:bg-brand-gold rounded-2xl mb-6">
                    <svg class="w-8 h-8 text-brand-gold dark:text-brand-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-brand-navy dark:text-white mb-4">
                    {{ t('public.formSystem') }}
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    {{ t('public.formSystemDesc') }}
                </p>
            </template>
        </div>

        <!-- Search box -->
        <div ref="searchBoxRef" class="max-w-2xl mx-auto mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg v-if="!isSearching" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-brand-gold animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="`${t('public.searchPlaceholder')} (${t('public.searchExamples')})`"
                    class="w-full pl-12 pr-12 py-4 text-lg border-2 border-gray-200 dark:border-gray-700 rounded-xl focus:border-brand-gold focus:ring-2 focus:ring-brand-gold/20 dark:bg-gray-800 dark:text-white transition-all"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-2">
                {{ t('public.searchHint') }}
            </p>
        </div>

        <!-- Quick Actions - Featured Forms -->
        <div v-if="featuredForms && featuredForms.length && !search && !selectedCategory" class="mb-10">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center justify-center">
                <svg class="w-5 h-5 mr-2 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {{ t('public.quickActions') }}
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                <Link
                    v-for="form in featuredForms"
                    :key="form.id"
                    :href="`/forms/${form.slug}`"
                    class="group relative bg-white dark:bg-gray-800 rounded-2xl p-4 md:p-6 border-2 border-gray-100 dark:border-gray-700 hover:border-brand-gold dark:hover:border-brand-gold hover:shadow-2xl hover:-translate-y-2 transition-all duration-300"
                >
                    <!-- Icon -->
                    <div
                        class="w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center mb-3 md:mb-4 group-hover:scale-110 transition-transform"
                        :style="form.category ? { backgroundColor: form.category.color + '15' } : {}"
                        :class="!form.category && 'bg-brand-gold/10'"
                    >
                        <svg
                            class="w-6 h-6 md:w-7 md:h-7"
                            :style="form.category ? { color: form.category.color } : {}"
                            :class="!form.category && 'text-brand-gold'"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getFeaturedIcon(form)" />
                        </svg>
                    </div>
                    <!-- Title -->
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm md:text-base mb-1 line-clamp-2 group-hover:text-brand-gold transition-colors">
                        {{ getFormName(form) }}
                    </h3>
                    <!-- Description -->
                    <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400 line-clamp-2 hidden md:block">
                        {{ getFormDescription(form) || 'Vyplniť formulár' }}
                    </p>
                    <!-- Arrow indicator -->
                    <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Category filter with swipe -->
        <div v-if="categories && categories.length" class="mb-8">
            <div
                ref="categoryContainer"
                class="flex md:flex-wrap md:justify-center gap-2 overflow-x-auto scrollbar-hide pb-2 -mx-4 px-4 md:mx-0 md:px-0"
                @touchstart="handleTouchStart"
                @touchmove="handleTouchMove"
                @touchend="handleTouchEnd"
            >
                <button
                    v-for="category in categories"
                    :key="category.id"
                    @click="selectCategory(category.slug)"
                    class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium transition-all whitespace-nowrap flex-shrink-0"
                    :class="selectedCategorySlug === category.slug
                        ? 'text-white shadow-md'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                    :style="selectedCategorySlug === category.slug ? { backgroundColor: category.color } : {}"
                >
                    <svg v-if="category.icon" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="category.icon" />
                    </svg>
                    {{ getLocalized(category.name) }}
                </button>
            </div>
        </div>

        <!-- Active filters info + View toggle -->
        <div v-if="search || selectedCategory || forms.length" class="mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                        <span class="w-1 h-6 bg-brand-gold rounded mr-3"></span>
                        <span v-if="search">{{ t('public.searchResults') }}</span>
                        <span v-else-if="selectedCategoryObj">{{ getLocalized(selectedCategoryObj.name) }}</span>
                        <span v-else>{{ auth?.user ? t('public.availableForms') : t('public.publicForms') }}</span>
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        ({{ forms.length }} {{ forms.length === 1 ? t('nav.forms').toLowerCase().slice(0, -1) : t('nav.forms').toLowerCase() }})
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- View toggle -->
                    <div class="hidden sm:flex items-center bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                        <button
                            @click="viewMode = 'grid'"
                            class="p-2 rounded-md transition-all"
                            :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-700 shadow text-brand-gold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            title="Mriežka"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            class="p-2 rounded-md transition-all"
                            :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow text-brand-gold' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            title="Zoznam"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                    <!-- Clear filters -->
                    <button
                        v-if="search || selectedCategory"
                        @click="clearSearch"
                        class="text-sm text-brand-gold hover:text-brand-gold-dark font-medium"
                    >
                        {{ t('public.clearFilters') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Forms grid/list -->
        <div v-if="forms.length" class="space-y-6 pb-20 md:pb-6">
            <!-- Grid View -->
            <div v-if="viewMode === 'grid'" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link
                    v-for="form in forms"
                    :key="form.id"
                    :href="`/forms/${form.slug}`"
                    class="card-brand group flex flex-col"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center group-hover:scale-110 transition-all"
                            :style="form.category ? { backgroundColor: form.category.color + '20' } : {}"
                            :class="!form.category && 'bg-brand-navy/10 dark:bg-brand-gold/20'"
                        >
                            <svg class="w-5 h-5" :style="form.category ? { color: form.category.color } : {}" :class="!form.category && 'text-brand-navy dark:text-brand-gold'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Category badge -->
                            <span
                                v-if="form.category"
                                class="px-2 py-1 text-xs rounded-full font-medium text-white"
                                :style="{ backgroundColor: form.category.color }"
                            >
                                {{ getLocalized(form.category.name) }}
                            </span>
                            <span
                                v-if="auth?.user && form.is_public !== undefined"
                                class="px-2 py-1 text-xs rounded-full font-medium"
                                :class="form.is_public ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'"
                            >
                                {{ form.is_public ? t('public.public') : t('public.private') }}
                            </span>
                        </div>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-brand-gold dark:group-hover:text-brand-gold transition-colors">
                        {{ getFormName(form) }}
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 flex-1">
                        {{ getFormDescription(form) || t('public.fillOutForm') }}
                    </p>
                    <!-- CTA Button -->
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <span class="inline-flex items-center text-sm font-semibold text-brand-gold group-hover:text-brand-gold-dark transition-colors">
                            {{ t('public.fillOutForm') }}
                            <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>
                    </div>
                </Link>
            </div>

            <!-- List View -->
            <div v-else class="space-y-3">
                <Link
                    v-for="form in forms"
                    :key="form.id"
                    :href="`/forms/${form.slug}`"
                    class="card-brand group flex items-center gap-4"
                >
                    <div
                        class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-all"
                        :style="form.category ? { backgroundColor: form.category.color + '20' } : {}"
                        :class="!form.category && 'bg-brand-navy/10 dark:bg-brand-gold/20'"
                    >
                        <svg class="w-6 h-6" :style="form.category ? { color: form.category.color } : {}" :class="!form.category && 'text-brand-navy dark:text-brand-gold'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-brand-gold transition-colors truncate">
                                {{ getFormName(form) }}
                            </h3>
                            <span
                                v-if="form.category"
                                class="px-2 py-0.5 text-xs rounded-full font-medium text-white flex-shrink-0"
                                :style="{ backgroundColor: form.category.color }"
                            >
                                {{ getLocalized(form.category.name) }}
                            </span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2">
                            {{ getFormDescription(form) || t('public.fillOutForm') }}
                        </p>
                    </div>
                    <!-- CTA Button -->
                    <span class="hidden sm:inline-flex items-center px-4 py-2 bg-brand-gold/10 text-brand-gold font-semibold text-sm rounded-lg group-hover:bg-brand-gold group-hover:text-white transition-all flex-shrink-0">
                        {{ t('public.fill') }}
                        <svg class="w-4 h-4 ml-1.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </span>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-brand-gold group-hover:translate-x-1 transition-all flex-shrink-0 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-16 pb-24 md:pb-16">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full mb-6">
                <svg v-if="search || selectedCategory" class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <svg v-else class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                {{ search || selectedCategory ? t('public.noResults') : t('public.noForms') }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md mx-auto">
                <template v-if="search">
                    {{ t('public.noResultsFor', { query: search }) }}
                </template>
                <template v-else-if="selectedCategoryObj">
                    {{ t('public.noCategoryResults', { category: getLocalized(selectedCategoryObj.name) }) }}
                </template>
                <template v-else>
                    {{ t('public.noFormsAvailable') }}
                </template>
            </p>

            <!-- Search suggestions -->
            <div v-if="search" class="mb-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ t('public.trySuggestions') }}</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <button
                        v-for="suggestion in ['VPN', 'heslo', 'prístup', 'účet', 'cloud', 'email']"
                        :key="suggestion"
                        @click="searchQuery = suggestion"
                        class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-brand-gold/20 dark:hover:bg-brand-gold/20 text-gray-700 dark:text-gray-300 hover:text-brand-gold dark:hover:text-brand-gold text-sm rounded-full transition-colors"
                    >
                        {{ suggestion }}
                    </button>
                </div>
            </div>

            <!-- Category suggestions when in category filter -->
            <div v-else-if="selectedCategoryObj && categories && categories.length > 1" class="mb-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ t('public.tryOtherCategory') }}</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <button
                        v-for="category in categories.filter(c => c.slug !== selectedCategorySlug).slice(0, 4)"
                        :key="category.id"
                        @click="selectCategory(category.slug)"
                        class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-brand-gold/20 text-gray-700 dark:text-gray-300 hover:text-brand-gold text-sm rounded-full transition-colors"
                    >
                        {{ getLocalized(category.name) }}
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8">
                <button v-if="search || selectedCategory" @click="clearSearch" class="btn btn-primary inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ t('public.showAllForms') }}
                </button>
                <a v-if="!auth?.user" href="/auth/login" class="btn btn-primary inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    {{ t('common.login') }}
                </a>
            </div>

            <!-- Help box -->
            <div v-if="supportEmail" class="max-w-md mx-auto p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <h3 class="font-semibold text-blue-900 dark:text-blue-300 text-sm">{{ t('public.needHelp') }}</h3>
                        <p class="text-blue-700 dark:text-blue-400 text-sm mt-1">
                            {{ t('public.contactIt') }}
                        </p>
                        <a :href="`mailto:${supportEmail}`" class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline mt-2">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ supportEmail }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick link for logged in users (hidden on mobile, shown in bottom nav) -->
        <div v-if="auth?.user" class="mt-12 hidden md:block">
            <Link href="/my/submissions" class="card group hover:shadow-lg transition-shadow block">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-brand-gold/20 dark:bg-brand-gold/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('public.mySubmissions') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('public.viewSubmissionStatus') }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-brand-gold group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </Link>
        </div>

        <!-- Mobile Bottom Navigation -->
        <Teleport to="body">
            <nav class="fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 md:hidden safe-area-bottom">
                <div class="flex items-center justify-around h-16">
                    <!-- Home -->
                    <Link
                        href="/"
                        class="flex flex-col items-center justify-center flex-1 h-full text-brand-gold"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-xs mt-1">{{ t('public.home') }}</span>
                    </Link>

                    <!-- Search -->
                    <button
                        @click="showMobileSearch = true"
                        class="flex flex-col items-center justify-center flex-1 h-full text-gray-500 dark:text-gray-400"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span class="text-xs mt-1">{{ t('public.search') }}</span>
                    </button>

                    <!-- My Submissions -->
                    <Link
                        v-if="auth?.user"
                        href="/my/submissions"
                        class="flex flex-col items-center justify-center flex-1 h-full text-gray-500 dark:text-gray-400"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span class="text-xs mt-1">{{ t('public.submissions') }}</span>
                    </Link>

                    <!-- Profile / Login -->
                    <Link
                        v-if="auth?.user"
                        href="/profile"
                        class="flex flex-col items-center justify-center flex-1 h-full text-gray-500 dark:text-gray-400"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-xs mt-1">{{ t('public.profile') }}</span>
                    </Link>
                    <a
                        v-else
                        href="/auth/login"
                        class="flex flex-col items-center justify-center flex-1 h-full text-gray-500 dark:text-gray-400"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <span class="text-xs mt-1">{{ t('common.login') }}</span>
                    </a>
                </div>
            </nav>
        </Teleport>
    </PublicLayout>
</template>

<style scoped>
/* Hide scrollbar for categories */
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Safe area for bottom navigation (iPhone notch) */
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
