import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

const firebaseVapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;

const hasFirebaseConfig =
    !!firebaseConfig.apiKey &&
    !!firebaseConfig.authDomain &&
    !!firebaseConfig.projectId &&
    !!firebaseConfig.storageBucket &&
    !!firebaseConfig.messagingSenderId &&
    !!firebaseConfig.appId;

const canUseFirebaseMessaging =
    hasFirebaseConfig &&
    typeof window !== "undefined" &&
    window.isSecureContext &&
    typeof navigator !== "undefined" &&
    "serviceWorker" in navigator;

const app = hasFirebaseConfig ? initializeApp(firebaseConfig) : null;
const messaging = app && canUseFirebaseMessaging ? getMessaging(app) : null;

export async function requestFirebaseNotificationPermission() {
    if (!hasFirebaseConfig || !messaging) {
        console.warn("Firebase no configurado: notificaciones desactivadas");
        return null;
    }

    if (typeof window === "undefined" || !("Notification" in window)) {
        return null;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission !== "granted") {
            console.warn("Permiso de notificaciones denegado");
            return null;
        }

        const token = await getToken(messaging, {
            vapidKey: firebaseVapidKey,
        });

        return token;
    } catch (error) {
        console.error("Error obteniendo token Firebase", error);
        return null;
    }
}

export function listenFirebaseMessages(callback = null) {
    if (!hasFirebaseConfig || !messaging) {
        return;
    }

    onMessage(messaging, (payload) => {
        if (typeof callback === "function") {
            callback(payload);
            return;
        }

        console.log("Notificacion recibida foreground:", payload);
    });
}
