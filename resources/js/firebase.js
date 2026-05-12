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

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

export async function requestFirebaseNotificationPermission() {
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
    onMessage(messaging, (payload) => {
        if (typeof callback === "function") {
            callback(payload);
            return;
        }

        console.log("Notificacion recibida foreground:", payload);
    });
}
