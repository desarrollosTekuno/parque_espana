import { Inertia } from "@inertiajs/inertia";
import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, usePage } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h, computed } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

import "@mdi/font/css/materialdesignicons.css";
import "vuetify/styles";
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";
import { mdi } from "vuetify/iconsets/mdi";
import { createVuetify } from "vuetify";
import { es as vuetifyEs } from "vuetify/locale";
import DateFnsAdapter from "@date-io/date-fns";
import esDate from "date-fns/locale/es";
import enUS from "date-fns/locale/en-US";

import VueSweetalert2 from "vue-sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css";
import vue3Spinner from "vue3-spinner";
import { isLoading } from "./loading";
import { registerFirebaseForegroundListener } from "./services/firebaseNotificationService";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const options = {
    confirmButtonColor: "#41b882",
    cancelButtonColor: "#ff7674",
};

const vuetify = createVuetify({
    components,
    directives,

    locale: {
        locale: "es",
        messages: { es: vuetifyEs },
    },

    date: {
        adapter: DateFnsAdapter,
        locale: {
            es: esDate,
            en: enUS,
        },
    },

    icons: {
        defaultSet: "mdi",
        sets: { mdi },
    },

    theme: {
        defaultTheme: "myTheme",
        themes: {
            myTheme: {
                dark: false,
                colors: {
                    primary: "#0097B2",
                    secondary: "#D4172A",
                    warning: "#F4B403",
                    info: "#004AAD",
                    accent: "#EE70A8",
                    background: "#FEFEFE",
                    surface: "#FEFEFE",
                },
            },
            myDarkTheme: {
                dark: true,
                colors: {
                    primary: "#0097B2",
                    secondary: "#D4172A",
                    background: "#000000",
                    surface: "#111111",
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
            import.meta.glob("./Pages/**/*.vue"),
        ),

    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(vue3Spinner)
            .use(VueSweetalert2, options);

        const page = usePage();

        vueApp.config.globalProperties.$can = computed(
            () => page.props.auth?.permissions || [],
        );

        vueApp.config.globalProperties.$roles = computed(
            () => page.props.auth?.roles || [],
        );

        Inertia.on("start", () => (isLoading.value = true));
        Inertia.on("finish", () => (isLoading.value = false));

        registerFirebaseForegroundListener();

        return vueApp.mount(el);
    },
});
