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
import { requestFirebaseNotificationPermission } from "./firebase";
import { registerFirebaseForegroundListener } from "./services/firebaseNotificationService";
import { customToastSwal } from "./utils/swal";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const options = {
    confirmButtonColor: "#41b882",
    cancelButtonColor: "#ff7674",
};

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api/v1";
const SANCTUM_TEST_TOKEN = import.meta.env.VITE_SANCTUM_TEST_TOKEN || "";

const sendFirebasePushTest = async () => {
    try {
        const token = await requestFirebaseNotificationPermission();

        if (!token) {
            customToastSwal({
                icon: "warning",
                title: "Firebase",
                text: "No se pudo generar el token FCM.",
            });
            return;
        }

        const headers = {
            Accept: "application/json",
            "Content-Type": "application/json",
        };

        if (SANCTUM_TEST_TOKEN) {
            headers.Authorization = `Bearer ${SANCTUM_TEST_TOKEN}`;
        }

        const response = await fetch(`${API_BASE_URL}/firebase/test`, {
            method: "POST",
            headers,
            body: JSON.stringify({
                token,
                title: "Prueba Firebase",
                body: "Push enviada desde app.js",
                data: {
                    type: "manual_test",
                },
            }),
        });

        const json = await response.json();

        customToastSwal({
            icon: json.success ? "success" : "error",
            title: json.success ? "Firebase OK" : "Firebase Error",
            text: json.message || "Respuesta recibida",
        });
    } catch (error) {
        customToastSwal({
            icon: "error",
            title: "Firebase Error",
            text: "Ocurrio un error enviando la prueba.",
        });
        console.error(error);
    }
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

        window.addEventListener("load", () => {
            const button = document.createElement("button");

            button.innerText = "Probar Firebase";
            button.style.position = "fixed";
            button.style.right = "20px";
            button.style.bottom = "20px";
            button.style.zIndex = "99999";
            button.style.padding = "12px 18px";
            button.style.background = "#0097B2";
            button.style.color = "#fff";
            button.style.border = "none";
            button.style.borderRadius = "8px";
            button.style.cursor = "pointer";

            button.addEventListener("click", () => {
                sendFirebasePushTest();
            });

            document.body.appendChild(button);
        });

        return vueApp.mount(el);
    },
});
