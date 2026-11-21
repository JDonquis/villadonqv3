self.addEventListener("push", function (event) {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {
            title: "VillaDonq",
            body: event.data ? event.data.text() : "Tienes una nueva notificación.",
        };
    }

    const title = payload.title || "VillaDonq";
    const options = {
        body: payload.body || "Tienes una nueva notificación.",
        icon: payload.icon || "/img/icon-192x192.png",
        badge: payload.badge || "/img/icon-144x144.png",
        tag: payload.tag || "villadonq",
        data: {
            url: payload.data?.url || payload.url || "/dashboard",
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", function (event) {
    event.notification.close();

    const url = event.notification.data?.url || "/dashboard";

    event.waitUntil(
        self.clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then(function (clientList) {
                for (const client of clientList) {
                    if ("focus" in client) {
                        if ("navigate" in client) {
                            client.navigate(url);
                        }
                        return client.focus();
                    }
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(url);
                }
            }),
    );
});

self.addEventListener("pushsubscriptionchange", function (event) {
    event.waitUntil(
        self.registration.pushManager
            .subscribe(event.oldSubscription?.options || { userVisibleOnly: true })
            .then(function () {
                // The application will refresh the subscription on its next visit.
            })
            .catch(function () {}),
    );
});
