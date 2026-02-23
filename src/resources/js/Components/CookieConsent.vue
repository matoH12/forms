<script setup>
import { ref, onMounted } from 'vue';

const COOKIE_CONSENT_KEY = 'cookie_consent';
const COOKIE_PREFERENCES_KEY = 'cookie_preferences';

const showBanner = ref(false);
const showSettings = ref(false);

// Cookie categories
const preferences = ref({
    necessary: true, // Always enabled, cannot be disabled
    functional: false,
    analytics: false,
});

// Check if consent was already given
onMounted(() => {
    const consent = localStorage.getItem(COOKIE_CONSENT_KEY);
    if (!consent) {
        showBanner.value = true;
    } else {
        // Load saved preferences
        const savedPrefs = localStorage.getItem(COOKIE_PREFERENCES_KEY);
        if (savedPrefs) {
            try {
                const parsed = JSON.parse(savedPrefs);
                preferences.value = { ...preferences.value, ...parsed, necessary: true };
            } catch (e) {
                // Invalid JSON, ignore
            }
        }
    }
});

const saveConsent = (acceptAll = false) => {
    if (acceptAll) {
        preferences.value = {
            necessary: true,
            functional: true,
            analytics: true,
        };
    }

    localStorage.setItem(COOKIE_CONSENT_KEY, new Date().toISOString());
    localStorage.setItem(COOKIE_PREFERENCES_KEY, JSON.stringify(preferences.value));

    showBanner.value = false;
    showSettings.value = false;

    // Emit event for other components to react
    window.dispatchEvent(new CustomEvent('cookie-consent-updated', {
        detail: preferences.value
    }));
};

const acceptAll = () => {
    saveConsent(true);
};

const acceptSelected = () => {
    saveConsent(false);
};

const rejectAll = () => {
    preferences.value = {
        necessary: true,
        functional: false,
        analytics: false,
    };
    saveConsent(false);
};

const openSettings = () => {
    showSettings.value = true;
};

const closeSettings = () => {
    showSettings.value = false;
};
</script>

<template>
    <!-- Cookie Banner -->
    <Teleport to="body">
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <div
                v-if="showBanner && !showSettings"
                class="fixed bottom-0 left-0 right-0 z-[100] bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-2xl safe-area-bottom"
            >
                <div class="max-w-7xl mx-auto px-4 py-4 md:py-6">
                    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                        <!-- Icon and Text -->
                        <div class="flex items-start gap-3 flex-1">
                            <div class="w-10 h-10 bg-brand-gold/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-brand-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-sm md:text-base">
                                    Tento web pouziva cookies
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Pouzivame cookies na zabezpecenie zakladnej funkcionality stranky a na zlepsenie vasho pouzivatelskeho zazitku.
                                    Kliknutim na "Prijat vsetky" suhlasit s pouzitim vsetkych cookies.
                                    <button
                                        @click="openSettings"
                                        class="text-brand-gold hover:underline font-medium"
                                    >
                                        Upravit nastavenia
                                    </button>
                                </p>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex flex-col sm:flex-row gap-2 lg:flex-shrink-0">
                            <button
                                @click="rejectAll"
                                class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Odmietnut volitelne
                            </button>
                            <button
                                @click="openSettings"
                                class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                Nastavenia
                            </button>
                            <button
                                @click="acceptAll"
                                class="px-4 py-2.5 text-sm font-medium text-white bg-brand-gold hover:bg-brand-gold-dark rounded-lg transition-colors"
                            >
                                Prijat vsetky
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Cookie Settings Modal -->
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showSettings"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="closeSettings"></div>

                <!-- Modal -->
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                Nastavenia cookies
                            </h2>
                            <button
                                @click="closeSettings"
                                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-4 space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Vyberte, ktore kategorie cookies chcete povolit. Nevyhnutne cookies su vzdy aktivne, pretoze su potrebne pre zakladnu funkcionalitu stranky.
                        </p>

                        <!-- Necessary Cookies -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    Nevyhnutne cookies
                                </h3>
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">
                                    Vzdy aktivne
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Tieto cookies su nevyhnutne pre funkcnost stranky. Zahrnaju prihlasenie, bezpecnost a zakladne nastavenia.
                            </p>
                        </div>

                        <!-- Functional Cookies -->
                        <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    Funkcne cookies
                                </h3>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="preferences.functional"
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-gold/20 dark:peer-focus:ring-brand-gold/30 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-brand-gold"></div>
                                </label>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Umoznuju rozsirenu funkcionalitu ako ulozenie vasich preferencii (jazyk, tema, zobrazenie).
                            </p>
                        </div>

                        <!-- Analytics Cookies -->
                        <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    Analyticke cookies
                                </h3>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="preferences.analytics"
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-gold/20 dark:peer-focus:ring-brand-gold/30 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-brand-gold"></div>
                                </label>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Pomahaju nam pochopit, ako pouzivate nasu stranku, aby sme ju mohli zlepsovat.
                            </p>
                        </div>

                        <!-- Privacy Policy Link -->
                        <div class="pt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Viac informacii najdete v nasich
                                <a href="/privacy-policy" class="text-brand-gold hover:underline">
                                    Zasadach ochrany osobnych udajov
                                </a>.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button
                                @click="rejectAll"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                            >
                                Odmietnut volitelne
                            </button>
                            <button
                                @click="acceptSelected"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                Ulozit vyber
                            </button>
                            <button
                                @click="acceptAll"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-brand-gold hover:bg-brand-gold-dark rounded-lg transition-colors"
                            >
                                Prijat vsetky
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
