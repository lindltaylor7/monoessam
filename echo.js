import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    unavailableTimeout: 10000,
});

window.Echo.connector.pusher.connection.bind('error', function(err) {
    if (err && err.error && err.error.data && err.error.data.code === 403) {
        console.log('Acceso denegado al canal privado (403). Deteniendo Echo...');
        window.Echo.disconnect();
    }
});