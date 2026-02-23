/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                brand: {
                    navy: 'var(--color-primary)',
                    gold: 'var(--color-accent)',
                    'gold-light': 'var(--color-accent-light)',
                    'gold-dark': 'var(--color-accent-dark)',
                },
            },
            fontFamily: {
                sans: ['Onest', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
