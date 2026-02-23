import { watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Lighten a hex color by a percentage
 */
function lightenColor(hex, percent) {
    const num = parseInt(hex.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const R = Math.min(255, (num >> 16) + amt);
    const G = Math.min(255, ((num >> 8) & 0x00FF) + amt);
    const B = Math.min(255, (num & 0x0000FF) + amt);
    return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
}

/**
 * Darken a hex color by a percentage
 */
function darkenColor(hex, percent) {
    const num = parseInt(hex.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const R = Math.max(0, (num >> 16) - amt);
    const G = Math.max(0, ((num >> 8) & 0x00FF) - amt);
    const B = Math.max(0, (num & 0x0000FF) - amt);
    return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
}

/**
 * Apply branding colors to CSS variables
 */
function applyBrandingColors(branding) {
    if (!branding) return;

    const root = document.documentElement;

    if (branding.primary_color) {
        root.style.setProperty('--color-primary', branding.primary_color);
        root.style.setProperty('--color-primary-light', lightenColor(branding.primary_color, 20));
        root.style.setProperty('--color-primary-dark', darkenColor(branding.primary_color, 15));
    }

    if (branding.accent_color) {
        root.style.setProperty('--color-accent', branding.accent_color);
        root.style.setProperty('--color-accent-light', lightenColor(branding.accent_color, 15));
        root.style.setProperty('--color-accent-dark', darkenColor(branding.accent_color, 15));
    }
}

export function useBranding() {
    const page = usePage();

    const initBranding = () => {
        if (page.props.branding) {
            applyBrandingColors(page.props.branding);
        }
    };

    onMounted(() => {
        initBranding();
    });

    // Watch for changes in branding
    watch(
        () => page.props.branding,
        (newBranding) => {
            if (newBranding) {
                applyBrandingColors(newBranding);
            }
        },
        { deep: true }
    );

    return {
        initBranding,
        applyBrandingColors,
    };
}
