import { createI18n } from 'vue-i18n';
import sk from './locales/sk.json';
import en from './locales/en.json';

// Detect browser language
const detectBrowserLanguage = () => {
    if (typeof navigator === 'undefined') return 'en';
    const browserLang = navigator.language || navigator.userLanguage || '';
    return browserLang.toLowerCase().startsWith('sk') ? 'sk' : 'en';
};

// Get initial locale - check localStorage first, then detect from browser
const getInitialLocale = () => {
    if (typeof localStorage !== 'undefined') {
        const stored = localStorage.getItem('language');
        if (stored && stored !== 'system') {
            return stored;
        }
    }
    return detectBrowserLanguage();
};

const i18n = createI18n({
    legacy: false,
    locale: getInitialLocale(),
    fallbackLocale: 'en',
    messages: {
        sk,
        en,
    },
});

export default i18n;
