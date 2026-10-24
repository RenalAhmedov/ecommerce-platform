import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Rendered by the layout only when broadcasting is on. The WebSocket goes to
// the address the page came from: Nginx forwards /app to the Reverb container.
const key = document.querySelector('meta[name="reverb-key"]')?.content;

if (key) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: window.location.hostname,
        wsPort: window.location.port || 80,
        wssPort: window.location.port || 443,
        forceTLS: window.location.protocol === 'https:',
        enabledTransports: ['ws', 'wss'],
    });
}
