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

import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const options = {
  confirmButtonColor: "#41b882",
  cancelButtonColor: "#ff7674",
};

const firebaseConfig = {
  apiKey: "AIzaSyCN_i3EqP6rd4XMt_G4-H8HUrG04Zx2bJo",
  authDomain: "parques-8e912.firebaseapp.com",
  projectId: "parques-8e912",
  storageBucket: "parques-8e912.firebasestorage.app",
  messagingSenderId: "888727738482",
  appId: "1:888727738482:web:654be235028bfabaaf4aeb",
};

const FIREBASE_VAPID_KEY = "BDfdU8G7-wVj2JpDxz-3G1ya8TfDC42rrrH4eCFfUeQMKmksjcyt52VBf2ltKmMVd637QsALt0alAtivWQk0mnQ";
const SANCTUM_TEST_TOKEN = "PEGA_AQUI_TU_TOKEN_SANCTUM";

const firebaseApp = initializeApp(firebaseConfig);
const messaging = getMessaging(firebaseApp);

const requestFirebaseTokenAndSendTest = async () => {
  try {
    if (!("Notification" in window)) {
      console.warn("Este navegador no soporta notificaciones.");
      return;
    }

    const permission = await Notification.requestPermission();

    if (permission !== "granted") {
      console.warn("Permiso de notificaciones no concedido.");
      return;
    }

    const token = await getToken(messaging, {
      vapidKey: FIREBASE_VAPID_KEY,
    });

    if (!token) {
      console.warn("No se pudo generar token FCM.");
      return;
    }

    console.log("FCM TOKEN:", token);

    const response = await fetch("http://127.0.0.1:8000/api/v1/firebase/test", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${SANCTUM_TEST_TOKEN}`,
      },
      body: JSON.stringify({
        token,
        title: "Prueba Firebase",
        body: "Push enviada automáticamente desde Vue/Inertia",
        data: {
          type: "auto_test",
          source: "app_js",
        },
      }),
    });

    const json = await response.json();

    console.log("Respuesta API Firebase:", json);
  } catch (error) {
    console.error("Error en prueba Firebase:", error);
  }
};

const listenFirebaseMessages = () => {
  onMessage(messaging, (payload) => {
    console.log("Notificación recibida en foreground:", payload);

    if (payload.notification) {
      new Notification(payload.notification.title || "Notificación", {
        body: payload.notification.body || "",
      });
    }
  });
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
      import.meta.glob("./Pages/**/*.vue")
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
      () => page.props.auth?.permissions || []
    );

    vueApp.config.globalProperties.$roles = computed(
      () => page.props.auth?.roles || []
    );

    Inertia.on("start", () => (isLoading.value = true));
    Inertia.on("finish", () => (isLoading.value = false));

        listenFirebaseMessages();

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
                requestFirebaseTokenAndSendTest();
            });

            document.body.appendChild(button);
        });

    return vueApp.mount(el);
  },
});
