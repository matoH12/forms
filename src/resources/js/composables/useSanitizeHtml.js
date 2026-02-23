import DOMPurify from 'dompurify';

/**
 * SECURITY: HTML sanitization composable to prevent XSS attacks
 * Uses DOMPurify to clean HTML content before rendering with v-html
 */
export function useSanitizeHtml() {
    /**
     * Sanitize HTML content for safe rendering
     * Allows safe HTML tags for email templates while blocking scripts
     */
    const sanitizeHtml = (html) => {
        if (!html) return '';

        return DOMPurify.sanitize(html, {
            // Allow common email-safe HTML tags
            ALLOWED_TAGS: [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'p', 'br', 'hr',
                'strong', 'b', 'em', 'i', 'u', 's', 'strike',
                'ul', 'ol', 'li',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'div', 'span',
                'a', 'img',
                'blockquote', 'pre', 'code',
            ],
            // Allow safe attributes
            ALLOWED_ATTR: [
                'href', 'src', 'alt', 'title',
                'style', 'class', 'id',
                'width', 'height',
                'border', 'cellpadding', 'cellspacing',
                'align', 'valign',
                'colspan', 'rowspan',
                'target', 'rel',
            ],
            // Block dangerous URL schemes
            ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.-]+(?:[^a-z+.-:]|$))/i,
            // Remove script content completely
            FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button'],
            FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur'],
        });
    };

    /**
     * Sanitize HTML with stricter settings (no links, no images)
     */
    const sanitizeHtmlStrict = (html) => {
        if (!html) return '';

        return DOMPurify.sanitize(html, {
            ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span'],
            ALLOWED_ATTR: ['style', 'class'],
        });
    };

    return {
        sanitizeHtml,
        sanitizeHtmlStrict,
    };
}
