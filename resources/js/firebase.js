import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";
import axios from 'axios';

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY || "AIzaSyAxjH7hHTj5TbTfT5WUyLFV0Jsc6nQx8_o",
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || "nusuk-930f3.firebaseapp.com",
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || "nusuk-930f3",
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || "nusuk-930f3.firebasestorage.app",
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || "982933275772",
  appId: import.meta.env.VITE_FIREBASE_APP_ID || "1:982933275772:web:b7ec5412c43e63f21f35f9"
};

const app = initializeApp(firebaseConfig);
const messaging = typeof window !== 'undefined' && 'serviceWorker' in navigator ? getMessaging(app) : null;

export const requestFirebaseToken = async () => {
    if (!messaging) return null;
    
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            // NOTE: Add your VAPID key in production for extra security if needed
            const currentToken = await getToken(messaging, { 
                vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY 
            });
            
            if (currentToken) {
                // Send token to our server
                await axios.post('/api/fcm-token', {
                    token: currentToken,
                    device_type: /Mac|iPod|iPhone|iPad/.test(navigator.userAgent) ? 'ios' : 
                                 /Android/.test(navigator.userAgent) ? 'android' : 'web'
                });
                return currentToken;
            }
        }
    } catch (error) {
        console.error('An error occurred while retrieving token. ', error);
    }
    return null;
};

export const onMessageListener = () =>
  new Promise((resolve) => {
    if (messaging) {
        onMessage(messaging, (payload) => {
            resolve(payload);
        });
    }
  });

export { app, messaging };
