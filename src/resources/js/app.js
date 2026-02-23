import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import i18n from './i18n';

createInertiaApp({
    title: (title) => title ? `${title} - Formuláre` : 'Formuláre',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Set locale from user settings
        const userLocale = props.initialPage?.props?.auth?.user?.settings?.language;

        // Helper to detect browser language
        const detectBrowserLang = () => {
            const browserLang = navigator.language || navigator.userLanguage || '';
            return browserLang.toLowerCase().startsWith('sk') ? 'sk' : 'en';
        };

        if (userLocale && userLocale !== 'system') {
            // User has explicit language preference
            i18n.global.locale.value = userLocale;
        } else {
            // No user preference or "system" - detect from browser
            i18n.global.locale.value = detectBrowserLang();
        }

        app.use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#3B82F6',
    },
});
