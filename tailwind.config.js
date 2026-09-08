import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#eff8ff',
                    100: '#d1e9ff',
                    200: '#b2ddff',
                    300: '#84caff',
                    400: '#53b1fd',
                    500: '#2e90fa',
                    600: '#1570ef',
                    700: '#175cd3',
                    800: '#1849a9',
                    900: '#194185',
                    950: '#102a56',
                },
            },
            boxShadow: {
                'theme-xs': '0 1px 2px 0 rgb(16 24 40 / 0.05)',
                'theme-sm': '0 1px 3px 0 rgb(16 24 40 / 0.10), 0 1px 2px -1px rgb(16 24 40 / 0.10)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};
