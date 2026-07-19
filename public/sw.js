const TASKS_URL = "/courier-portal/tasks";

self.addEventListener("push", (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (_error) {
        payload = { body: event.data ? event.data.text() : "Anda mendapatkan tugas pengiriman baru." };
    }

    const title = payload.title || "Tugas Pengiriman Baru";
    const url = payload.url || TASKS_URL;
    const options = {
        body: payload.body || "Buka portal kurir untuk melihat tugas.",
        icon: payload.icon || "/favicon.ico",
        badge: payload.badge || "/favicon.ico",
        tag: payload.tag || `courier-task-${Date.now()}`,
        data: { ...payload, url },
        renotify: true,
        requireInteraction: true,
        silent: false,
        vibrate: [220, 100, 220, 100, 360],
    };

    event.waitUntil(Promise.all([
        self.registration.showNotification(title, options),
        typeof self.navigator?.setAppBadge === "function"
            ? self.navigator.setAppBadge(1).catch(() => undefined)
            : Promise.resolve(),
        self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
            clients.forEach((client) => client.postMessage({ type: "courier-push", payload }));
        }),
    ]));
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const targetUrl = new URL(event.notification.data?.url || TASKS_URL, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(async (clients) => {
            const client = clients.find((item) => item.url.startsWith(self.location.origin));
            if (client) {
                await client.focus();
                if ("navigate" in client) await client.navigate(targetUrl);
                client.postMessage({ type: "courier-notification-opened" });
                return;
            }
            await self.clients.openWindow(targetUrl);
        }),
    );
});

self.addEventListener("notificationclose", () => {
    if (typeof self.navigator?.clearAppBadge === "function") {
        self.navigator.clearAppBadge().catch(() => undefined);
    }
});
