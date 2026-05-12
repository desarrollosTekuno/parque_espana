import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const firebaseConfig = {
    apiKey: "AIzaSyCN_i3EqP6rd4XMt_G4-H8HUrG04Zx2bJo",
    authDomain: "parques-8e912.firebaseapp.com",
    projectId: "parques-8e912",
    storageBucket: "parques-8e912.firebasestorage.app",
    messagingSenderId: "888727738482",
    appId: "1:888727738482:web:654be235028bfabaaf4aeb"
};

const app = initializeApp(firebaseConfig);

const messaging = getMessaging(app);

export async function requestFirebaseNotificationPermission() {

    try {

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            console.warn('Permiso de notificaciones denegado');
            return null;
        }

        const token = await getToken(messaging, {
            vapidKey: 'jsqH_udIlx86Fs1IFJr79dPZ9AYfDFsnY2vWpiJtIIs'
        });

        console.log('FCM TOKEN:', token);

        return token;

    } catch (error) {

        console.error('Error obteniendo token Firebase', error);

        return null;

    }

}

export function listenFirebaseMessages() {

    onMessage(messaging, (payload) => {

        console.log('Notificacion recibida foreground:', payload);

    });

}
