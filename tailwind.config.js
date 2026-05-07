import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            screens: {
                'hd': '1920px',
                'lp': '1366px',
            },
            colors: {
                // Paleta oficial del proyecto
                "principal":    "#FEFEFE", // Fondo / texto sobre oscuro
                "principal2":   "#000000", // Texto oscuro / drawer
                "secundario":   "#D4172A", // Acciones importantes / activo
                "secundario2":  "#F4B403", // Advertencias / highlights
                "acento":       "#0097B2", // Botones primarios / links
                "acento2":      "#EE70A8", // Decorativo / acento suave
                "acento3":      "#004AAD", // Informativo / banda de perfil

                // Aliases para compatibilidad con clases existentes
                "primary":      "#0097B2",
                "secondary":    "#D4172A",
            },
            fontSize: {
                "mxs": "0.666rem",
                "xxs": "0.565rem",
                "53": "15rem",
            },
        },
    },

    plugins: [forms, typography],
};
