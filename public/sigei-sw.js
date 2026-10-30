const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let payload;
        try { payload = event.data?.json(); } catch { return; }
        if (!uuid.test(payload?.notification_id ?? '') || !uuid.test(payload?.binding_id ?? '')) return;
        try {
            const response = await fetch(`/api/push/notifications/${encodeURIComponent(payload.notification_id)}`, {
                credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-SIGEI-Push-Binding': payload.binding_id },
            });
            if (!response.ok) return;
            const notice = await response.json();
            if (notice.id !== payload.notification_id) return;
            await self.registration.showNotification('SIGEI · Aviso institucional', {
                body: 'Abre el sistema para consultar un aviso de tu cuenta.',
                tag: `sigei-${notice.id}`, data: { id: notice.id },
            });
        } catch { /* Una sesión sin verificar o sin conexión no autoriza mostrar datos de incidentes. */ }
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const id = event.notification.data?.id;
    if (!uuid.test(id ?? '')) return;
    event.waitUntil((async () => {
        const url = new URL(`/?aviso=${encodeURIComponent(id)}`, self.location.origin).href;
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find(client => client.url === url);
        if (existing) await existing.focus();
        else await self.clients.openWindow(url);
    })());
});

self.addEventListener('pushsubscriptionchange', event => {
    event.waitUntil((async () => {
        for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
            client.postMessage({ type: 'sigei-push-changed' });
        }
    })());
});
