import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/sigei-sw.js', import.meta.url), 'utf8');
const id = '12345678-1234-4234-8234-123456789abc';
const binding = 'aaaaaaaa-1234-4234-8234-123456789abc';

function worker(status = 200, notice = {}) {
    const handlers = new Map();
    const calls = [];
    const shown = [];
    const opened = [];
    const lifecycle = [];
    const scope = {
        location: { origin: 'https://sigei.example.test' },
        addEventListener(type, handler) { handlers.set(type, handler); },
        async skipWaiting() { lifecycle.push('install'); },
        registration: { async showNotification(title, options) { shown.push({ title, ...options }); } },
        clients: { async matchAll() { return []; }, async openWindow(url) { opened.push(url); }, async claim() { lifecycle.push('activate'); } },
    };
    runInNewContext(script, { self: scope, URL, fetch: async (path, options) => {
        calls.push({ path, ...options });
        return { ok: status === 200, json: async () => ({ id, kind: 'incident.updated', is_test: false, title: 'Private text', body: 'Private details', ...notice }) };
    } });
    async function dispatch(type, extra) {
        let pending;
        handlers.get(type)({ ...extra, waitUntil(value) { pending = value; } });
        await pending;
    }
    return { handlers, calls, shown, opened, lifecycle, dispatch };
}

test('the worker verifies the current device binding before showing the event type without private text', async () => {
    const w = worker();
    await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.equal(w.calls[0].credentials, 'same-origin');
    assert.equal(w.calls[0].cache, 'no-store');
    assert.equal(w.calls[0].headers['X-SIGEI-Push-Binding'], binding);
    assert.equal(w.shown.length, 1);
    assert.equal(w.shown[0].title.includes('Private'), false);
    assert.equal(w.shown[0].body.includes('Private'), false);
    assert.equal(w.calls[0].method, undefined);
});

test('invalid payloads and revoked bindings cannot show a notice', async () => {
    const invalid = worker();
    await invalid.dispatch('push', { data: { json: () => ({ notification_id: '../../secret', binding_id: binding }) } });
    assert.equal(invalid.calls.length, 0);
    const revoked = worker(404);
    await revoked.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.equal(revoked.shown.length, 0);
    const mismatched = worker(200, { id: binding });
    await mismatched.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.equal(mismatched.shown.length, 0);
});

test('a test notification is explicitly identified without using arbitrary provider text', async () => {
    const w = worker(200, { kind: 'push.test', is_test: true });
    await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.match(w.shown[0].title, /Prueba de notificaciones/);
    assert.equal(w.shown[0].body.includes('Private'), false);
});

for (const [kind, expectedTitle] of [
    ['incident.created', 'SIGEI · Nueva alerta'],
    ['incident.assigned', 'SIGEI · Alerta asignada'],
    ['incident.updated', 'SIGEI · Seguimiento actualizado'],
]) {
    test(`a verified ${kind} notice displays the event type without exposing case content`, async () => {
        const w = worker(200, { kind });
        await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
        assert.equal(w.shown[0].title, expectedTitle);
        assert.equal(w.shown[0].body.includes('Private'), false);
        assert.deepEqual(Object.keys(w.shown[0].data), ['id']);
        assert.equal(w.shown[0].data.id, id);
    });
}

test('an unknown event type falls back safely without trusting arbitrary or inherited text', async () => {
    for (const kind of ['unrecognized', 'constructor', '__proto__', undefined]) {
        const w = worker(200, { kind });
        await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
        assert.equal(w.shown[0].title, 'SIGEI · Aviso institucional');
        assert.equal(w.shown[0].body.includes('Private'), false);
    }
});

test('an event type in the provider payload cannot override the event verified by the server', async () => {
    const w = worker(200, { kind: 'incident.assigned' });
    await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding, kind: 'push.test', title: 'Injected title', body: 'Injected body' }) } });
    assert.equal(w.shown[0].title, 'SIGEI · Alerta asignada');
    assert.equal(w.shown[0].body.includes('Injected'), false);
});

test('an older server can still identify a test notice without an event type', async () => {
    const w = worker(200, { kind: undefined, is_test: true });
    await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.equal(w.shown[0].title, 'SIGEI · Prueba de notificaciones');
});

test('a worker update installs and activates without waiting for existing pages to close', async () => {
    const w = worker();
    await w.dispatch('install');
    await w.dispatch('activate');
    assert.deepEqual(w.lifecycle, ['install', 'activate']);
});

test('notification clicks can only open a same-origin path built from an opaque notification id', async () => {
    const w = worker();
    await w.dispatch('notificationclick', { notification: { data: { id, url: 'https://evil.test/' }, close() {} } });
    assert.equal(w.opened[0], `https://sigei.example.test/?aviso=${id}`);
    await w.dispatch('notificationclick', { notification: { data: { id: '../api/private' }, close() {} } });
    assert.equal(w.opened.length, 1);
});

test('the worker does not intercept or cache incident requests or the public panic screen', () => {
    assert.equal(worker().handlers.has('fetch'), false);
});
