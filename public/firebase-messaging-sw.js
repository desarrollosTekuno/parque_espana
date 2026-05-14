importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyCN_i3EqP6rd4XMt_G4-H8HUrG04Zx2bJo",
    authDomain: "parques-8e912.firebaseapp.com",
    projectId: "parques-8e912",
    storageBucket: "parques-8e912.firebasestorage.app",
    messagingSenderId: "888727738482",
    appId: "1:888727738482:web:654be235028bfabaaf4aeb"
});

const messaging = firebase.messaging();
