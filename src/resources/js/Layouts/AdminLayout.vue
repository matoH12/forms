<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useBranding } from '@/composables/useBranding';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

// Use shared auth data from Inertia instead of props
const page = usePage();
const auth = computed(() => page.props.auth);
const pendingCount = computed(() => page.props.pendingSubmissionsCount || 0);

const { isDark, toggleTheme } = useTheme();

// Initialize branding colors
useBranding();

// Language
const currentLang = ref('system');

const languages = [
    { code: 'sk', name: 'SK', fullName: 'Slovensky' },
    { code: 'en', name: 'EN', fullName: 'English' },
];

// Detect system language
const getSystemLanguage = () => {
    if (typeof navigator === 'undefined') return 'en';
    const browserLang = navigator.language || navigator.userLanguage;
    return browserLang?.toLowerCase().startsWith('sk') ? 'sk' : 'en';
};

// Get effective language (resolves 'system' to actual language)
const getEffectiveLanguage = (lang) => {
    return lang === 'system' ? getSystemLanguage() : lang;
};

// Initialize language from user settings or localStorage
onMounted(() => {
    const userLang = page.props?.auth?.user?.settings?.language;
    if (userLang) {
        currentLang.value = userLang;
    } else {
        const storedLang = localStorage.getItem('language');
        if (storedLang) {
            currentLang.value = storedLang;
        }
    }
    locale.value = getEffectiveLanguage(currentLang.value);
});

const setLanguage = async (lang) => {
    currentLang.value = lang;
    locale.value = getEffectiveLanguage(lang);
    localStorage.setItem('language', lang);

    // Save to server if user is logged in
    if (page.props?.auth?.user) {
        try {
            await fetch('/profile/settings/language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ language: lang }),
            });
        } catch (e) {
            console.warn('Failed to save language preference:', e);
        }
    }
};

// Current display language (for the button)
const displayLang = computed(() => {
    const effectiveLang = getEffectiveLanguage(currentLang.value);
    return languages.find(l => l.code === effectiveLang) || languages[0];
});

const menuOpen = ref(false);
const userMenuOpen = ref(false);

const toggleUserMenu = () => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

/**
 * SECURITY NOTE: Client-side role checks are for UI/UX only!
 * These checks hide menu items for better user experience.
 * Actual authorization is enforced by backend middleware:
 *   - 'admin' middleware: requires viewer+ role
 *   - 'role:admin' middleware: requires admin+ role
 *   - 'role:super_admin' middleware: requires super_admin role
 *
 * An attacker could bypass client-side checks, but backend
 * will return 403 Forbidden for unauthorized access.
 */
const ROLE_HIERARCHY = {
    user: 0,
    viewer: 1,
    approver: 2,
    admin: 3,
    super_admin: 4,
};

/**
 * UI-only role check - DO NOT rely on this for security
 * Backend middleware provides actual authorization
 */
const hasMinRole = (minRole) => {
    const userRole = auth.value?.user?.role || 'user';
    return (ROLE_HIERARCHY[userRole] || 0) >= (ROLE_HIERARCHY[minRole] || 0);
};

// Role label for display
const getRoleLabel = computed(() => {
    const role = auth.value?.user?.role;
    switch (role) {
        case 'super_admin': return t('users.roles.super_admin');
        case 'admin': return t('users.roles.admin');
        case 'approver': return t('users.roles.approver');
        case 'viewer': return t('users.roles.viewer');
        default: return t('users.roles.user');
    }
});

// All navigation items with minimum role requirements
const allNavigationItems = computed(() => [
    { name: t('dashboard.title'), href: '/admin', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', minRole: 'viewer' },
    { name: t('nav.forms'), href: '/admin/forms', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', minRole: 'viewer' },
    { name: t('nav.categories'), href: '/admin/categories', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', minRole: 'admin' },
    { name: t('nav.submissions'), href: '/admin/submissions', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', badge: 'pending', minRole: 'viewer' },
    { name: t('nav.workflows'), href: '/admin/workflows', icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', minRole: 'admin' },
    { name: t('nav.emailTemplates'), href: '/admin/email-templates', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', minRole: 'admin' },
    { name: t('nav.announcements'), href: '/admin/announcements', icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', minRole: 'admin' },
    { name: t('nav.users'), href: '/admin/users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', minRole: 'super_admin' },
    { name: t('nav.auditLogs'), href: '/admin/audit-logs', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', minRole: 'viewer' },
    { name: t('nav.settings'), href: '/admin/settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', minRole: 'super_admin' },
]);

/**
 * Filter navigation based on user's role (UI only)
 * Backend routes are protected by middleware - see routes/web.php
 */
const navigation = computed(() => {
    return allNavigationItems.value.filter(item => hasMinRole(item.minRole));
});

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
        <!-- Mobile header -->
        <div class="lg:hidden fixed top-0 left-0 right-0 z-30 bg-brand-navy text-white p-4 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold">{{ t('nav.forms') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <!-- Language toggle mobile -->
                <div class="flex gap-0.5">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        @click="setLanguage(lang.code)"
                        class="px-2 py-1 rounded text-xs font-bold transition-colors"
                        :class="getEffectiveLanguage(currentLang) === lang.code
                            ? 'text-brand-gold bg-gray-800'
                            : 'text-gray-400 hover:text-white'"
                    >
                        {{ lang.name }}
                    </button>
                </div>
                <!-- Theme toggle mobile -->
                <button
                    @click="toggleTheme"
                    class="p-2 rounded-lg hover:bg-gray-800 transition-colors"
                    :title="isDark ? 'Prepnut na svetly rezim' : 'Prepnut na tmavy rezim'"
                >
                    <!-- Sun icon -->
                    <svg v-if="isDark" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                    <!-- Moon icon -->
                    <svg v-else class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                </button>
                <button @click="toggleMenu" class="p-2 rounded-lg hover:bg-gray-800">
                    <svg v-if="!menuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu overlay -->
        <div
            v-if="menuOpen"
            class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40"
            @click="closeMenu"
        />

        <!-- Sidebar - Fixed on desktop -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 bg-brand-navy text-white flex flex-col transform transition-transform duration-300 ease-in-out"
            :class="menuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="p-4 border-b border-gray-700 hidden lg:block">
                <h1 class="text-xl font-bold">{{ t('nav.forms') }}</h1>
                <p class="text-sm text-gray-400">{{ t('nav.adminPanel') }}</p>
            </div>

            <!-- Mobile close button area -->
            <div class="lg:hidden p-4 border-b border-gray-700 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold">{{ t('nav.forms') }}</h1>
                    <p class="text-sm text-gray-400">{{ t('nav.adminPanel') }}</p>
                </div>
                <button @click="closeMenu" class="p-2 rounded-lg hover:bg-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center px-3 py-3 lg:py-2 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
                    @click="closeMenu"
                >
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="flex-1">{{ item.name }}</span>
                    <!-- Pending badge -->
                    <span
                        v-if="item.badge === 'pending' && pendingCount > 0"
                        class="ml-2 px-2 py-0.5 text-xs font-medium bg-yellow-500 text-yellow-900 rounded-full"
                    >
                        {{ pendingCount > 99 ? '99+' : pendingCount }}
                    </span>
                </Link>
            </nav>

            <!-- Theme & Language toggle desktop -->
            <div class="hidden lg:flex px-4 py-3 border-t border-gray-700 gap-2">
                <!-- Language buttons -->
                <div class="flex gap-1">
                    <button
                        v-for="lang in languages"
                        :key="lang.code"
                        @click="setLanguage(lang.code)"
                        class="px-2 py-2 rounded-lg text-xs font-bold transition-colors"
                        :class="getEffectiveLanguage(currentLang) === lang.code
                            ? 'bg-gray-800 text-brand-gold'
                            : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
                        :title="lang.fullName"
                    >
                        {{ lang.name }}
                    </button>
                </div>

                <!-- Theme toggle -->
                <button
                    @click="toggleTheme"
                    class="flex items-center flex-1 px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
                >
                    <!-- Sun icon -->
                    <svg v-if="isDark" class="w-5 h-5 mr-2 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                    <!-- Moon icon -->
                    <svg v-else class="w-5 h-5 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                    <span class="text-sm">{{ isDark ? t('profile.themeLight') : t('profile.themeDark') }}</span>
                </button>
            </div>

            <div class="p-4 border-t border-gray-700 relative">
                <!-- User menu button -->
                <button
                    @click="toggleUserMenu"
                    class="w-full flex items-center p-2 rounded-lg hover:bg-gray-800 transition-colors"
                >
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-gold to-yellow-600 text-brand-navy rounded-full flex items-center justify-center flex-shrink-0 font-semibold shadow-md">
                        {{ auth?.user?.name?.charAt(0) || '?' }}
                    </div>
                    <div class="ml-3 flex-1 min-w-0 text-left">
                        <p class="text-sm font-medium truncate">{{ auth?.user?.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ auth?.user?.email }}</p>
                    </div>
                    <svg
                        class="w-4 h-4 text-gray-400 transition-transform"
                        :class="{ 'rotate-180': userMenuOpen }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>

                <!-- User dropdown menu -->
                <div
                    v-if="userMenuOpen"
                    class="absolute bottom-full left-4 right-4 mb-2 bg-gray-800 rounded-lg shadow-xl border border-gray-700 overflow-hidden z-50"
                >
                    <!-- Profile header -->
                    <div class="p-4 bg-gray-750 border-b border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-brand-gold to-yellow-600 text-brand-navy rounded-full flex items-center justify-center font-bold text-lg shadow-md">
                                {{ auth?.user?.name?.charAt(0) || '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-white truncate">{{ auth?.user?.name }}</p>
                                <p class="text-sm text-gray-400 truncate">{{ auth?.user?.email }}</p>
                                <span
                                    class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-red-500/20 text-red-300': auth?.user?.role === 'super_admin',
                                        'bg-purple-500/20 text-purple-300': auth?.user?.role === 'admin',
                                        'bg-blue-500/20 text-blue-300': auth?.user?.role === 'approver',
                                        'bg-green-500/20 text-green-300': auth?.user?.role === 'viewer',
                                        'bg-gray-500/20 text-gray-300': auth?.user?.role === 'user',
                                    }"
                                >
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ getRoleLabel }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Menu items -->
                    <div class="py-2">
                        <Link
                            href="/profile/settings"
                            class="flex items-center gap-3 px-4 py-2.5 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                            @click="closeUserMenu"
                        >
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <div>
                                <p class="font-medium">{{ t('common.profile') }}</p>
                                <p class="text-xs text-gray-500">{{ t('profile.preferences') }}</p>
                            </div>
                        </Link>

                        <Link
                            href="/"
                            class="flex items-center gap-3 px-4 py-2.5 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                            @click="closeUserMenu"
                        >
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <div>
                                <p class="font-medium">{{ t('nav.publicInterface') }}</p>
                                <p class="text-xs text-gray-500">{{ t('nav.home') }}</p>
                            </div>
                        </Link>
                    </div>

                    <!-- Logout -->
                    <div class="border-t border-gray-700 py-2">
                        <Link
                            href="/auth/logout"
                            method="post"
                            as="button"
                            class="flex items-center gap-3 w-full px-4 py-2.5 text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors"
                            @click="closeUserMenu"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            {{ t('common.logout') }}
                        </Link>
                    </div>
                </div>

                <!-- Click outside to close -->
                <div
                    v-if="userMenuOpen"
                    class="fixed inset-0 z-40"
                    @click="closeUserMenu"
                />
            </div>
        </aside>

        <!-- Main content - with left margin for sidebar on desktop -->
        <main class="lg:ml-64 min-h-screen pt-16 lg:pt-0 overflow-x-hidden">
            <div class="p-4 lg:p-6 min-w-0">
                <slot />
            </div>
        </main>
    </div>
</template>
