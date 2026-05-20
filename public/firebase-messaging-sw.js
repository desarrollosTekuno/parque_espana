importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey:            'AIzaSyBD1S8I0lFhuGn2AfeSft-wVltDRy2QxTk',
    authDomain:        'parqueespana-72f8f.firebaseapp.com',
    projectId:         'parqueespana-72f8f',
    storageBucket:     'parqueespana-72f8f.firebasestorage.app',
    messagingSenderId: '775812930675',
    appId:             '1:775812930675:web:36f2ba5752c3d3f03c9e06',
});

const messaging = firebase.messaging();
