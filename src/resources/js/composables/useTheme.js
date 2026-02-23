import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const isDark = ref(false);
const initialized = ref(false);

export function useTheme() {
    const page = usePage();

    const initTheme = () => {
        if (initialized.value) {
            return;
        }

        // Priority: 1. User's saved setting from DB, 2. localStorage, 3. System preference
        const userSettings = page.props?.auth?.user?.settings;
        const userTheme = userSettings?.theme;

        if (userTheme && userTheme !== 'system') {
            // User has explicit preference saved in DB
            isDark.value = userTheme === 'dark';
            localStorage.setItem('theme', userTheme);
        } else if (userTheme === 'system' || !userTheme) {
            // System preference or no saved preference
            const stored = localStorage.getItem('theme');
            if (stored && stored !== 'system') {
                isDark.value = stored === 'dark';
            } else {
                // Use system preference
                isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }
        }

        applyTheme();
        initialized.value = true;
    };

    const applyTheme = () => {
        if (isDark.value) {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.body.classList.remove('dark');
        }
    };

    const toggleTheme = () => {
        isDark.value = !isDark.value;
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
        applyTheme();

        // Save to server if user is logged in
        saveThemeToServer(isDark.value ? 'dark' : 'light');
    };

    const setTheme = (theme) => {
        if (theme === 'system') {
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
            localStorage.setItem('theme', 'system');
        } else {
            isDark.value = theme === 'dark';
            localStorage.setItem('theme', theme);
        }
        applyTheme();

        // Save to server if user is logged in
        saveThemeToServer(theme);
    };

    const saveThemeToServer = async (theme) => {
        // Only save if user is logged in
        if (!page.props?.auth?.user) {
            return;
        }

        try {
            await fetch('/profile/settings/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ theme }),
            });
        } catch (e) {
            // Silent fail - localStorage still works
            console.warn('Failed to save theme preference:', e);
        }
    };

    // Watch for system theme changes
    if (typeof window !== 'undefined') {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            const stored = localStorage.getItem('theme');
            if (!stored || stored === 'system') {
                isDark.value = e.matches;
                applyTheme();
            }
        });
    }

    onMounted(() => {
        initTheme();
    });

    return {
        isDark,
        toggleTheme,
        setTheme,
        initTheme,
    };
}
