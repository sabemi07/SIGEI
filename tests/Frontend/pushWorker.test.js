import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../public/sigei-sw.js', import.meta.url), 'utf8');
const id = '12345678-1234-4234-8234-123456789abc';
const binding = 'aaaaaaaa-1234-4234-8234-123456789abc';

function worker(status = 200, isTest = false) {
    const handlers = new Map();
    const calls = [];
    const shown = [];
    const opened = [];
    const scope = {
        location: { origin: 'https://sigei.example.test' },
        addEventListener(type, handler) { handlers.set(type, handler); },
        registration: { async showNotification(title, options) { shown.push({ title, ...options }); } },
        clients: { async matchAll() { return []; }, async openWindow(url) { opened.push(url); } },
    };
    runInNewContext(script, { self: scope, URL, fetch: async (path, options) => {
        calls.push({ path, ...options });
        return { ok: status === 200, json: async () => ({ id, is_test: isTest, title: 'Private text', body: 'Private details' }) };
    } });
    async function dispatch(type, extra) {
        let pending;
        handlers.get(type)({ ...extra, waitUntil(value) { pending = value; } });
        await pending;
    }
    return { handlers, calls, shown, opened, dispatch };
}

test('the worker verifies the current device binding with the server before showing a generic notice', async () => {
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
});

test('a test notification is explicitly identified without using arbitrary provider text', async () => {
    const w = worker(200, true);
    await w.dispatch('push', { data: { json: () => ({ notification_id: id, binding_id: binding }) } });
    assert.match(w.shown[0].title, /Prueba de notificaciones/);
    assert.equal(w.shown[0].body.includes('Private'), false);
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
