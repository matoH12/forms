import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Composable for handling localized values in form fields
 */
export function useLocalized() {
    const { locale } = useI18n();

    /**
     * Get localized value from an object or string
     * @param {Object|string} value - The value to get (can be {sk: '', en: ''} or just a string)
     * @param {string} fallbackLang - Fallback language if current locale not found
     * @returns {string}
     */
    const getLocalized = (value, fallbackLang = 'sk') => {
        if (value === null || value === undefined) {
            return '';
        }

        // If it's already a string, return it
        if (typeof value === 'string') {
            return value;
        }

        // If it's an object with language keys
        if (typeof value === 'object') {
            // Try current locale first
            if (value[locale.value]) {
                return value[locale.value];
            }
            // Try fallback language
            if (value[fallbackLang]) {
                return value[fallbackLang];
            }
            // Return first available value
            const keys = Object.keys(value);
            if (keys.length > 0) {
                return value[keys[0]] || '';
            }
        }

        return '';
    };

    /**
     * Get localized field label
     */
    const getFieldLabel = (field) => {
        return getLocalized(field?.label);
    };

    /**
     * Get localized field placeholder
     */
    const getFieldPlaceholder = (field) => {
        return getLocalized(field?.placeholder);
    };

    /**
     * Get localized checkbox label
     */
    const getCheckboxLabel = (field) => {
        return getLocalized(field?.checkboxLabel);
    };

    /**
     * Get localized option label for select/radio
     */
    const getOptionLabel = (option) => {
        return getLocalized(option?.label);
    };

    /**
     * Get localized form name
     */
    const getFormName = (form) => {
        return getLocalized(form?.name);
    };

    /**
     * Get localized form description
     */
    const getFormDescription = (form) => {
        return getLocalized(form?.description);
    };

    return {
        locale,
        getLocalized,
        getFieldLabel,
        getFieldPlaceholder,
        getCheckboxLabel,
        getOptionLabel,
        getFormName,
        getFormDescription,
    };
}
