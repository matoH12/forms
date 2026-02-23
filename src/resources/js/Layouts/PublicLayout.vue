<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useBranding } from '@/composables/useBranding';
import { useTheme } from '@/composables/useTheme';
import { useI18n } from 'vue-i18n';
import CookieConsent from '@/Components/CookieConsent.vue';

const { t, locale } = useI18n();

defineProps({
    auth: Object,
});

const page = usePage();
const branding = computed(() => page.props.branding || {
    site_name: 'Forms',
    site_subtitle: '',
    organization_name: 'Your Organization',
    footer_text: '',
    logo: '',
});

const logoUrl = computed(() => {
    if (branding.value.logo) {
        return '/storage/' + branding.value.logo;
    }
    return null;
});

// Initialize branding colors
useBranding();

// Theme
const { isDark, toggleTheme } = useTheme();

// Language
const currentLang = ref('system');

const languages = [
    { code: 'sk', name: 'SK' },
    { code: 'en', name: 'EN' },
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

const menuOpen = ref(false);
const userMenuOpen = ref(false);

const toggleUserMenu = () => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900 dark-mode-transition">
        <!-- Top bar with user info -->
        <div class="bg-brand-navy text-white">
            <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between text-sm">
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400">{{ branding.organization_name }}</span>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Language switcher - simple SK/EN buttons -->
                    <div class="flex items-center gap-0.5 bg-gray-800/50 rounded-lg p-0.5">
                        <button
                            v-for="lang in languages"
                            :key="lang.code"
                            @click="setLanguage(lang.code)"
                            class="px-2 py-1 text-xs font-bold rounded-md transition-colors"
                            :class="getEffectiveLanguage(currentLang) === lang.code
                                ? 'bg-brand-gold text-brand-navy'
                                : 'text-gray-400 hover:text-white'"
                        >
                            {{ lang.name }}
                        </button>
                    </div>

                    <!-- Dark mode toggle -->
                    <button
                        @click="toggleTheme"
                        class="p-1.5 rounded-lg hover:bg-gray-800 transition-colors"
                        :title="isDark ? 'Prepnúť na svetlý režim' : 'Prepnúť na tmavý režim'"
                    >
                        <!-- Sun icon -->
                        <svg v-if="isDark" class="w-5 h-5 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Moon icon -->
                        <svg v-else class="w-5 h-5 text-gray-400 hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <!-- User info with dropdown -->
                    <template v-if="auth?.user">
                        <div class="hidden md:block relative">
                            <button
                                @click="toggleUserMenu"
                                class="flex items-center space-x-2 px-2 py-1 rounded-lg hover:bg-gray-800 transition-colors"
                            >
                                <div class="w-7 h-7 bg-gradient-to-br from-brand-gold to-yellow-600 rounded-full flex items-center justify-center text-brand-navy font-semibold text-xs shadow">
                                    {{ auth.user.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span class="text-gray-300">{{ auth.user.name }}</span>
                                <svg class="w-4 h-4 text-gray-400" :class="{ 'rotate-180': userMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown menu -->
                            <div
                                v-if="userMenuOpen"
                                class="absolute right-0 top-full mt-2 w-72 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50"
                            >
                                <!-- Profile header -->
                                <div class="p-4 bg-gray-50 dark:bg-gray-750 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-brand-gold to-yellow-600 rounded-full flex items-center justify-center text-brand-navy font-bold text-lg shadow-md">
                                            {{ auth.user.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-white truncate">{{ auth.user.name }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ auth.user.email }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Menu items -->
                                <div class="py-2">
                                    <Link
                                        href="/profile/settings"
                                        class="flex items-center gap-3 px-4 py-2.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        @click="closeUserMenu"
                                    >
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>{{ t('profile.title') }}</span>
                                    </Link>
                                    <Link
                                        href="/my/submissions"
                                        class="flex items-center gap-3 px-4 py-2.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        @click="closeUserMenu"
                                    >
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span>{{ t('nav.mySubmissions') }}</span>
                                    </Link>
                                    <Link
                                        v-if="auth.user.role && auth.user.role !== 'user'"
                                        href="/admin"
                                        class="flex items-center gap-3 px-4 py-2.5 text-brand-gold hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        @click="closeUserMenu"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <span>{{ t('nav.adminPanel') }}</span>
                                    </Link>
                                </div>

                                <!-- Logout -->
                                <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                    <Link
                                        href="/auth/logout"
                                        method="post"
                                        as="button"
                                        class="flex items-center gap-3 w-full px-4 py-2.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                        @click="closeUserMenu"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        <span>{{ t('common.logout') }}</span>
                                    </Link>
                                </div>
                            </div>

                            <!-- Click outside to close -->
                            <div v-if="userMenuOpen" class="fixed inset-0 z-40" @click="closeUserMenu" />
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Gold accent line -->
        <div class="brand-accent-line"></div>

        <!-- Main header -->
        <header class="bg-white dark:bg-gray-800 shadow-sm relative z-50 dark-mode-transition">
            <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center space-x-3">
                    <!-- Custom logo or default icon -->
                    <template v-if="logoUrl">
                        <img :src="logoUrl" alt="Logo" class="h-10 max-w-32 object-contain" />
                    </template>
                    <template v-else>
                        <div class="w-10 h-10 bg-brand-navy dark:bg-brand-gold rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-brand-gold dark:text-brand-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </template>
                    <div>
                        <span class="text-xl font-bold text-brand-navy dark:text-white">{{ branding.site_name }}</span>
                        <span v-if="branding.site_subtitle" class="hidden md:inline text-sm text-gray-500 dark:text-gray-400 ml-2">{{ branding.site_subtitle }}</span>
                    </div>
                </Link>

                <!-- Desktop navigation -->
                <nav class="hidden md:flex items-center space-x-1">
                    <Link
                        href="/"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-brand-navy dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        {{ t('nav.forms') }}
                    </Link>
                    <template v-if="auth?.user">
                        <Link
                            href="/my/submissions"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-brand-navy dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            {{ t('nav.mySubmissions') }}
                        </Link>
                        <Link
                            v-if="auth.user.role && auth.user.role !== 'user'"
                            href="/admin"
                            class="px-4 py-2 text-brand-gold font-medium hover:bg-brand-gold hover:text-brand-navy rounded-lg transition-colors"
                        >
                            Admin
                        </Link>
                        <Link
                            href="/auth/logout"
                            method="post"
                            as="button"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            {{ t('common.logout') }}
                        </Link>
                    </template>
                    <template v-else>
                        <a href="/auth/login" class="btn btn-primary">
                            {{ t('common.login') }}
                        </a>
                    </template>
                </nav>

                <!-- Mobile menu button -->
                <button @click="toggleMenu" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg v-if="!menuOpen" class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile navigation -->
            <div
                v-if="menuOpen"
                class="md:hidden border-t dark:border-gray-700 bg-white dark:bg-gray-800"
            >
                <nav class="px-4 py-4 space-y-1">
                    <!-- Mobile user info -->
                    <div v-if="auth?.user" class="flex items-center space-x-3 px-3 py-3 mb-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="w-10 h-10 bg-brand-gold rounded-full flex items-center justify-center text-brand-navy font-semibold">
                            {{ auth.user.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ auth.user.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ auth.user.email }}</p>
                        </div>
                    </div>

                    <Link
                        href="/"
                        class="block px-3 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        @click="closeMenu"
                    >
                        {{ t('nav.forms') }}
                    </Link>
                    <template v-if="auth?.user">
                        <Link
                            href="/my/submissions"
                            class="block px-3 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                            @click="closeMenu"
                        >
                            {{ t('nav.mySubmissions') }}
                        </Link>
                        <Link
                            v-if="auth.user.role && auth.user.role !== 'user'"
                            href="/admin"
                            class="block px-3 py-3 text-brand-gold font-medium hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                            @click="closeMenu"
                        >
                            {{ t('nav.adminPanel') }}
                        </Link>
                        <Link
                            href="/auth/logout"
                            method="post"
                            as="button"
                            class="block w-full text-left px-3 py-3 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                            @click="closeMenu"
                        >
                            {{ t('common.logout') }}
                        </Link>
                    </template>
                    <template v-else>
                        <a
                            href="/auth/login"
                            class="block px-3 py-3 text-brand-gold font-medium hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                            @click="closeMenu"
                        >
                            {{ t('common.login') }}
                        </a>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1 max-w-7xl mx-auto px-4 py-6 md:py-8 w-full">
            <slot />
        </main>

        <footer class="bg-brand-navy text-white mt-auto">
            <div class="brand-accent-line"></div>
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
                    <div class="flex items-center space-x-3">
                        <template v-if="logoUrl">
                            <img :src="logoUrl" alt="Logo" class="h-8 max-w-24 object-contain" />
                        </template>
                        <template v-else>
                            <div class="w-8 h-8 bg-brand-gold rounded flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </template>
                        <span class="font-semibold">{{ branding.site_name }} {{ branding.site_subtitle }}</span>
                    </div>
                    <div class="text-gray-400 text-sm">
                        <template v-if="branding.footer_text">
                            {{ branding.footer_text }}
                        </template>
                        <template v-else>
                            &copy; {{ new Date().getFullYear() }} {{ branding.organization_name }}
                        </template>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Cookie Consent Banner -->
        <CookieConsent />
    </div>
</template>
