import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Configure Laravel Reverb (WebSocket server)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'your-reverb-app-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    cluster: import.meta.env.VITE_REVERB_APP_CLUSTER || 'mt1',
});

// Helper: Listen to order status updates
window.LeSGoRealtime = {
    /**
     * Subscribe to user's private channel
     */
    subscribeToUserChannel(userId, callbacks = {}) {
        const channel = window.Echo.private(`user.${userId}`);
        
        if (callbacks.orderStatusUpdated) {
            channel.listen('.order.status.updated', callbacks.orderStatusUpdated);
        }
        
        if (callbacks.chatMessageSent) {
            channel.listen('.chat.message.sent', callbacks.chatMessageSent);
        }
        
        if (callbacks.driverLocationUpdated) {
            channel.listen('.driver.location.updated', callbacks.driverLocationUpdated);
        }
        
        if (callbacks.notificationSent) {
            channel.listen('.notification.sent', callbacks.notificationSent);
        }
        
        if (callbacks.geofenceEvent) {
            channel.listen('.geofence.event.triggered', callbacks.geofenceEvent);
        }

        return channel;
    },

    /**
     * Subscribe to order channel
     */
    subscribeToOrderChannel(orderId, callbacks = {}) {
        const channel = window.Echo.private(`order.${orderId}`);
        
        if (callbacks.orderStatusUpdated) {
            channel.listen('.order.status.updated', callbacks.orderStatusUpdated);
        }
        
        if (callbacks.driverLocationUpdated) {
            channel.listen('.driver.location.updated', callbacks.driverLocationUpdated);
        }
        
        if (callbacks.chatMessageSent) {
            channel.listen('.chat.message.sent', callbacks.chatMessageSent);
        }

        return channel;
    },

    /**
     * Subscribe to conversation channel
     */
    subscribeToConversation(conversationId, callbacks = {}) {
        const channel = window.Echo.private(`conversation.${conversationId}`);
        
        if (callbacks.chatMessageSent) {
            channel.listen('.chat.message.sent', callbacks.chatMessageSent);
        }
        
        if (callbacks.typingIndicator) {
            channel.listen('.user.typing', callbacks.typingIndicator);
        }
        
        if (callbacks.readReceipt) {
            channel.listen('.message.read', callbacks.readReceipt);
        }

        return channel;
    },

    /**
     * Subscribe to nearby drivers (public channel)
     */
    subscribeToNearbyDrivers(callbacks = {}) {
        const channel = window.Echo.channel('drivers.nearby');
        
        if (callbacks.driverLocationUpdated) {
            channel.listen('.driver.location.updated', callbacks.driverLocationUpdated);
        }

        return channel;
    },

    /**
     * Unsubscribe from all channels
     */
    leaveAll() {
        window.Echo.leave('user.*');
        window.Echo.leave('order.*');
        window.Echo.leave('conversation.*');
        window.Echo.leave('drivers.nearby');
    },
};
