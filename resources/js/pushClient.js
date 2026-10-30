export function decodeApplicationKey(value) {
    const binary = atob(value.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
    if (bytes.length !== 65 || bytes[0] !== 4) throw new Error('La clave pública Web Push no es válida.');
    return bytes;
}

export function createPushClient({ request, onState = () => {}, environment = globalThis }) {
    let account = null;
    let generation = 0;
    let registration = null;
    let server = null;
    let busy = false;
    let refreshVersion = 0;
    let mutation = Promise.resolve();
    let state = { busy: false, bound: false, canLink: false, message: 'Comprobando el dispositivo…', error: '' };

    function publish(values) {
        state = { ...state, ...values };
        onState(state);
    }

    function supported() {
        return environment.isSecureContext && environment.Notification?.requestPermission
            && environment.navigator?.serviceWorker && environment.PushManager && environment.crypto?.subtle;
    }

    function serial(action) {
        const pending = mutation.then(action, action);
        mutation = pending.catch(() => {});
        return pending;
    }

    async function installedRegistration() {
        return registration ?? await environment.navigator?.serviceWorker?.getRegistration('/');
    }

    async function closeNotices(worker) {
        for (const notice of await worker?.getNotifications?.() ?? []) notice.close();
    }

    function setAccount(next) {
        if (next?.id === account?.id && next?.rol === account?.rol) return;
        account = next ? { id: next.id, rol: next.rol } : null;
        generation++;
        refreshVersion++;
        server = null;
        publish({ busy: false, bound: false, canLink: false, error: '', message: 'Comprobando el dispositivo…' });
        if (!next && environment.navigator?.serviceWorker) {
            const current = generation;
            void serial(async () => {
                if (current !== generation || account) return;
                const worker = await installedRegistration();
                const subscription = await worker?.pushManager.getSubscription();
                if (current !== generation || account) return;
                await subscription?.unsubscribe();
                await closeNotices(worker);
            }).catch(() => {});
        }
    }

    async function fingerprint(endpoint) {
        const bytes = new TextEncoder().encode(endpoint);
        const hash = await environment.crypto.subtle.digest('SHA-256', bytes);
        return Array.from(new Uint8Array(hash), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    async function refresh() {
        if (busy || !account) return;
        const current = generation;
        const revision = ++refreshVersion;
        const isCurrent = () => current === generation && revision === refreshVersion && !busy;
        if (!supported()) {
            publish({ bound: false, canLink: false, error: '', message: environment.isSecureContext
                ? 'Este navegador no ofrece las funciones necesarias. En iPhone compatible, abre SIGEI desde la pantalla de inicio.'
                : 'Abre SIGEI mediante HTTPS o localhost para vincular este dispositivo.' });
            return;
        }
        try {
            const response = await request('/api/push');
            if (!isCurrent()) return;
            server = response;
            if (!server.public_key) {
                publish({ bound: false, canLink: false, error: '', message: 'Las suscripciones del dispositivo todavía no están preparadas en el servidor.' });
                return;
            }
            decodeApplicationKey(server.public_key);
            registration = await environment.navigator.serviceWorker.register('/sigei-sw.js', { scope: '/', updateViaCache: 'none' });
            if (!isCurrent()) return;
            const subscription = await registration.pushManager.getSubscription();
            const bound = environment.Notification.permission === 'granted' && !!subscription && !!server.subscription
                && await fingerprint(subscription.endpoint) === server.subscription.endpoint_hash;
            if (!isCurrent()) return;
            publish({ bound, canLink: environment.Notification.permission !== 'denied', error: '', message: bound
                ? 'Dispositivo vinculado a esta cuenta. El envío de avisos al celular aún está pendiente de conectar.'
                : environment.Notification.permission === 'denied'
                    ? 'El permiso está bloqueado en el navegador. Revisa los ajustes del sitio y vuelve a comprobarlo.'
                    : 'Puedes vincular este dispositivo con tu cuenta. El envío de avisos todavía está pendiente.' });
        } catch (error) {
            if (isCurrent()) publish({ bound: false, canLink: false, error: error.message, message: 'No se pudo comprobar la vinculación. Vuelve a intentarlo.' });
        }
    }

    async function link() {
        if (busy || !account || !state.canLink || !server?.public_key || !supported()) return;
        const current = generation;
        const expectedAccount = account.id;
        const key = server.public_key;
        const existingState = server.subscription;
        busy = true;
        refreshVersion++;
        publish({ busy: true, error: '' });
        let created = null;
        try {
            const permission = environment.Notification.permission === 'granted'
                ? 'granted' : await environment.Notification.requestPermission();
            if (current !== generation) return;
            if (permission !== 'granted') throw new Error('No se concedió el permiso. El dispositivo no se vinculó.');
            await serial(async () => {
                if (current !== generation) return;
                const worker = registration;
                if (!worker?.active) await environment.navigator.serviceWorker.ready;
                if (current !== generation) return;
                let subscription = await worker.pushManager.getSubscription();
                if (current !== generation) return;
                if (subscription && (!existingState || await fingerprint(subscription.endpoint) !== existingState.endpoint_hash)) {
                    await subscription.unsubscribe();
                    subscription = null;
                }
                if (current !== generation) return;
                if (!subscription) {
                    subscription = await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeApplicationKey(key) });
                    created = subscription;
                }
                if (current !== generation) { await created?.unsubscribe(); return; }
                const response = await request('/api/push', { method: 'POST', body: {
                    ...subscription.toJSON(), cuenta_esperada: expectedAccount, contentEncoding: 'aes128gcm',
                } });
                if (current !== generation) { await created?.unsubscribe(); return; }
                if (!response.subscription?.id || !response.subscription?.binding_id) throw new Error('El servidor no confirmó la vinculación del dispositivo.');
                server = { ...server, ...response };
                publish({ bound: true, canLink: true, message: 'Dispositivo vinculado a esta cuenta. El envío de avisos al celular aún está pendiente de conectar.' });
            });
        } catch (error) {
            try { await created?.unsubscribe(); } catch { /* El servidor mantiene la comprobación de cuenta y vinculación. */ }
            if (current === generation) publish({ bound: false, error: error.message });
        } finally {
            busy = false;
            if (current === generation) publish({ busy: false });
        }
    }

    async function unlink() {
        if (busy || !account) return;
        const current = generation;
        busy = true;
        refreshVersion++;
        publish({ busy: true, error: '' });
        try {
            await serial(async () => {
                if (current !== generation) return;
                await request('/api/push', { method: 'DELETE' });
                if (current !== generation) return;
                server = server ? { ...server, subscription: null } : null;
                publish({ bound: false, message: 'Dispositivo desvinculado de esta cuenta.' });
                const worker = await installedRegistration();
                const subscription = await worker?.pushManager.getSubscription();
                if (current !== generation) return;
                await subscription?.unsubscribe();
                await closeNotices(worker);
            });
        } catch (error) {
            if (current === generation) publish({ error: error.message });
        } finally {
            busy = false;
            if (current === generation) publish({ busy: false });
        }
    }

    return { setAccount, refresh, link, unlink };
}
