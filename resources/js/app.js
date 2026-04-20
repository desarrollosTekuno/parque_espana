import { Inertia } from "@inertiajs/inertia";
import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, usePage } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h, computed } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

import DateFnsAdapter from "@date-io/date-fns";
import "@mdi/font/css/materialdesignicons.css";
import enUS from "date-fns/locale/en-US";
import es from "date-fns/locale/es";
import "sweetalert2/dist/sweetalert2.min.css";
import VueSweetalert2 from "vue-sweetalert2";
import vue3Spinner from "vue3-spinner";
import { createVuetify } from "vuetify";
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";
import { mdi } from "vuetify/iconsets/mdi";
import "vuetify/styles";
import { isLoading } from "./loading";

const options = {
    confirmButtonColor: "#41b882",
    cancelButtonColor: "#ff7674",
};

const vuetify = createVuetify({
    components,
    directives,
    icons: {
        defaultSet: "mdi",
        sets: { mdi },
    },
    date: {
        adapter: DateFnsAdapter,
        locale: { es: es, en: enUS },
    },
    theme: {
        defaultTheme: "myTheme",
        themes: {
            // ── Tema claro (contenido principal) ─────────────────────────────
            myTheme: {
                dark: false,
                colors: {
                    // Paleta oficial
                    primary:    "#0097B2", // Acento       — botones, links, steppers
                    secondary:  "#D4172A", // Secundario   — acciones importantes
                    warning:    "#F4B403", // Secundario 2 — advertencias
                    info:       "#004AAD", // Acento 3     — informativo
                    accent:     "#EE70A8", // Acento 2     — decorativo
                    background: "#FEFEFE", // Principal
                    surface:    "#FEFEFE",

                    // Tokens custom (usados en Navigation y componentes heredados)
                    customPrimary:   "#000000", // Fondo drawer / texto oscuro
                    customSecondary: "#D4172A", // Ítem activo del menú
                    customThird:     "#004AAD", // Banda de perfil de usuario
                    customFourth:    "#0097B2", // Acento interactivo
                    customFifth:     "#F4B403", // Highlight / advertencia
                },
            },
            // ── Tema oscuro (navigation drawer) ──────────────────────────────
            myDarkTheme: {
                dark: true,
                colors: {
                    primary:    "#0097B2",
                    secondary:  "#D4172A",
                    background: "#000000",
                    surface:    "#111111",

                    customPrimary:   "#FEFEFE", // Texto / íconos inactivos
                    customSecondary: "#D4172A", // Ítem activo
                    customThird:     "#004AAD", // Banda de perfil
                    customFourth:    "#0097B2", // Club selector / acento
                    customFifth:     "#F4B403", // Highlight
                },
            },
        },
    },
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(vue3Spinner)
            .use(VueSweetalert2, options);

        // ---- Globales para permisos y roles ----
        const page = usePage();
        vueApp.config.globalProperties.$can = computed(
            () => page.props.auth?.permissions || []
        );
        vueApp.config.globalProperties.$roles = computed(
            () => page.props.auth?.roles || []
        );
        // ----------------------------------------

        Inertia.on("start", () => (isLoading.value = true));
        Inertia.on("finish", () => (isLoading.value = false));

        return vueApp.mount(el);
    },
});
