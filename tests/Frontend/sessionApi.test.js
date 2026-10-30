import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError, createSessionApi } from '../../resources/js/sessionApi.js';

function json(data, status = 200) {
    return new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } });
}

function stubFetch(t, handler) {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = handler;
    t.after(() => { globalThis.fetch = originalFetch; });
}

test('uses the same-origin session and its current CSRF token for writes', async t => {
    const calls = [];
    stubFetch(t, async (path, options) => {
        calls.push({ path, ...options });
        return json({ csrf_token: calls.length === 1 ? 'token-before-login' : 'token-after-login' });
    });
    const api = createSessionApi();
    await api.request('/api/session');
    await api.request('/api/login', { method: 'POST', body: { email: 'cuenta@example.test', password: 'test-only-password' } });
    await api.request('/api/logout', { method: 'POST' });

    assert.equal(calls[1].credentials, 'same-origin');
    assert.equal(calls[1].cache, 'no-store');
    assert.equal(calls[1].headers['X-CSRF-TOKEN'], 'token-before-login');
    assert.equal(calls[2].headers['X-CSRF-TOKEN'], 'token-after-login');
    assert.equal(calls[1].headers.Accept, 'application/json');
    assert.equal(JSON.parse(calls[1].body).email, 'cuenta@example.test');
});

test('401 invokes session cleanup and rejects the protected response', async t => {
    const state = { user: { id: 1 }, users: [{ id: 1 }] };
    stubFetch(t, async () => json({ message: 'Unauthenticated.' }, 401));
    const api = createSessionApi({ onUnauthorized() { state.user = null; state.users = []; } });

    await assert.rejects(api.request('/api/users'), error => error instanceof ApiError && error.status === 401);
    assert.equal(state.user, null);
    assert.deepEqual(state.users, []);
});

test('419 refreshes CSRF without replaying the failed write', async t => {
    const calls = [];
    stubFetch(t, async (path, options) => {
        calls.push({ path, ...options });
        if (path === '/api/users' && calls.filter(call => call.path === '/api/users').length === 1) return json({}, 419);
        return json({ csrf_token: 'refreshed-token', user: { id: 1 } });
    });
    const api = createSessionApi({ async onCsrfExpired() { await api.request('/api/session'); } });

    await assert.rejects(api.request('/api/users', { method: 'POST', body: { nombre: 'Cuenta' } }), error => error.status === 419);
    assert.deepEqual(calls.map(call => [call.path, call.method]), [['/api/users', 'POST'], ['/api/session', 'GET']]);
    await api.request('/api/users', { method: 'POST', body: { nombre: 'Cuenta' } });
    assert.equal(calls[2].headers['X-CSRF-TOKEN'], 'refreshed-token');
});

test('a failed logout connection does not invoke session cleanup', async t => {
    let cleaned = false;
    stubFetch(t, async () => { throw new TypeError('Network error'); });
    const api = createSessionApi({ onUnauthorized() { cleaned = true; } });

    await assert.rejects(api.request('/api/logout', { method: 'POST' }), error => error.status === 0);
    assert.equal(cleaned, false);
});

test('validation failures remain errors and do not invoke session cleanup', async t => {
    let cleaned = false;
    stubFetch(t, async () => json({ errors: { correo: ['Ese correo ya está registrado.'] } }, 422));
    const api = createSessionApi({ onUnauthorized() { cleaned = true; } });

    await assert.rejects(api.request('/api/users', { method: 'POST', body: {} }), error => error.status === 422 && error.message === 'Ese correo ya está registrado.');
    assert.equal(cleaned, false);
});

test('403 asks the application to refresh permissions before exposing the error', async t => {
    let refreshed = false;
    stubFetch(t, async () => json({}, 403));
    const api = createSessionApi({ async onForbidden() { refreshed = true; } });

    await assert.rejects(api.request('/api/users'), error => error.status === 403);
    assert.equal(refreshed, true);
});

test('server failures use a generic message instead of exposing debug content', async t => {
    stubFetch(t, async () => json({ message: 'SQLSTATE debug details', errors: { connection: ['database debug'] } }, 500));
    const api = createSessionApi();

    await assert.rejects(api.request('/api/users'), error => error.status === 500 && !error.message.includes('SQLSTATE') && !error.message.includes('debug'));
});
