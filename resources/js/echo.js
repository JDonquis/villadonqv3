import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { displayAlert } from "./stores/alertStore";
import { pushNotification } from "./stores/notifications";

let echo = null;
let subscribedUserId = null;

export function initEcho(userId) {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!userId || !key) {
        return null;
    }

    if (echo && subscribedUserId === userId) {
        return echo;
    }

    if (!echo) {
        window.Pusher = Pusher;

        const scheme = import.meta.env.VITE_REVERB_SCHEME ?? "https";
        const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === "https" ? 443 : 80));

        echo = new Echo({
            broadcaster: "reverb",
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === "https",
            enabledTransports: ["ws", "wss"],
            authEndpoint: "/broadcasting/auth",
            withCredentials: true,
        });
    }

    if (subscribedUserId) {
        echo.leave(`App.Models.User.${subscribedUserId}`);
    }

    subscribedUserId = userId;

    echo
        .private(`App.Models.User.${userId}`)
        .notification((notification) => {
            pushNotification(notification);
            displayAlert({
                type: "info",
                message: notification.title || "Tienes una nueva notificación.",
            });
        });

    return echo;
}

export function disconnectEcho() {
    if (echo && subscribedUserId) {
        echo.leave(`App.Models.User.${subscribedUserId}`);
    }

    echo?.disconnect();
    echo = null;
    subscribedUserId = null;
}
