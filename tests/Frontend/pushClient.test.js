import test from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto, createHash } from 'node:crypto';
import { createPushClient, decodeApplicationKey } from '../../resources/js/pushClient.js';

const key = Buffer.concat([Buffer.from([4]), Buffer.alloc(64, 7)]).toString('base64url');

function fixture({ permission = 'default', bound = false, requestOverride } = {}) {
    const calls = [];
    const browserActions = [];
    let state;
    let sequence = 0;
    let subscription = null;
    let serverSubscription = null;
    const makeSubscription = () => {
        const endpoint = `https://fcm.googleapis.com/test-${++sequence}`;
        const item = {
            endpoint,
            toJSON: () => ({ endpoint, expirationTime: null, keys: { p256dh: key, auth: Buffer.alloc(16, 1).toString('base64url') } }),
            async unsubscribe() {
                browserActions.push(`unsubscribe:${endpoint}`);
                if (subscription === item) subscription = null;
                return true;
            },
        };
        return item;
    };
    const fingerprint = endpoint => createHash('sha256').update(endpoint).digest('hex');
    if (bound) {
        subscription = makeSubscription();
        serverSubscription = { id: 1, binding_id: 'binding-before', endpoint_hash: fingerprint(subscription.endpoint) };
    }
    const worker = {
        active: {},
        getNotifications: async () => [],
        pushManager: {
            async getSubscription() { return subscription; },
            async subscribe(options) {
                browserActions.push('subscribe');
                assert.equal(options.userVisibleOnly, true);
                assert.equal(options.applicationServerKey.length, 65);
                subscription = makeSubscription();
                return subscription;
            },
        },
    };
    const environment = {
        isSecureContext: true, PushManager: {}, crypto: webcrypto,
        Notification: {
            permission,
            async requestPermission() {
                browserActions.push('permission');
                environment.Notification.permission = 'granted';
                return 'granted';
            },
        },
        navigator: { serviceWorker: {
            ready: Promise.resolve(worker),
            async register(path, options) {
                browserActions.push('register');
                assert.equal(path, '/sigei-sw.js');
                assert.equal(options.scope, '/');
                return worker;
            },
            async getRegistration() { return worker; },
        } },
    };
    const request = async (path, options = {}) => {
        calls.push({ path, ...options });
        if (requestOverride) return requestOverride(path, options);
        if (options.method === 'POST') {
            serverSubscription = { id: 1, binding_id: 'new-binding', endpoint_hash: fingerprint(options.body.endpoint) };
        }
        if (options.method === 'DELETE') serverSubscription = null;
        return { public_key: key, subscription: serverSubscription, delivery_available: false };
    };
    const client = createPushClient({ request, environment, onState: value => { state = value; } });
    client.setAccount({ id: 7, rol: 'Responsable' });
    return { client, calls, environment, browserActions, getState: () => state, getSubscription: () => subscription };
}

test('permission alone never becomes a linked device and checking never prompts or subscribes', async () => {
    const f = fixture({ permission: 'granted' });
    await f.client.refresh();
    assert.equal(f.getState().bound, false);
    assert.equal(f.getState().canLink, true);
    assert.equal(f.browserActions.includes('permission'), false);
    assert.equal(f.browserActions.includes('subscribe'), false);
    assert.equal(f.calls.some(call => call.method === 'POST'), false);
});

test('an explicit click creates a browser subscription and binds it to the expected account', async () => {
    const f = fixture();
    await f.client.refresh();
    await f.client.link();
    const write = f.calls.find(call => call.method === 'POST');
    assert.equal(write.body.cuenta_esperada, 7);
    assert.equal(write.body.contentEncoding, 'aes128gcm');
    assert.equal('user_id' in write.body, false);
    assert.equal('device_id' in write.body, false);
    assert.equal(f.getState().bound, true);
    assert.match(f.getState().message, /envío.*pendiente/);
});

test('a matching browser endpoint and server binding are both required to show linked status', async () => {
    const f = fixture({ permission: 'granted', bound: true });
    await f.client.refresh();
    assert.equal(f.getState().bound, true);
    assert.equal(f.browserActions.includes('subscribe'), false);
});

test('denying permission never registers a subscription in Laravel', async () => {
    const f = fixture();
    f.environment.Notification.requestPermission = async () => 'denied';
    await f.client.refresh();
    await f.client.link();
    assert.equal(f.getState().bound, false);
    assert.match(f.getState().error, /No se concedió/);
    assert.equal(f.calls.some(call => call.method === 'POST'), false);
});

test('switching accounts while the permission prompt is pending cannot bind the old request to the new account', async () => {
    const f = fixture();
    let resolvePermission;
    f.environment.Notification.requestPermission = () => new Promise(resolve => { resolvePermission = resolve; });
    await f.client.refresh();
    const pending = f.client.link();
    f.client.setAccount({ id: 8, rol: 'Administrador' });
    resolvePermission('granted');
    await pending;
    assert.equal(f.calls.some(call => call.method === 'POST'), false);
    assert.equal(f.getState().bound, false);
});

test('a stale account response cannot install the worker or show another account as linked', async () => {
    let resolveResponse;
    const f = fixture({ requestOverride: () => new Promise(resolve => { resolveResponse = resolve; }) });
    const pending = f.client.refresh();
    f.client.setAccount({ id: 8, rol: 'Administrador' });
    resolveResponse({ public_key: key, subscription: { id: 1 } });
    await pending;
    assert.equal(f.browserActions.includes('register'), false);
    assert.equal(f.getState().bound, false);
});

test('a failed server registration unsubscribes the newly created endpoint and never reports success', async () => {
    const f = fixture({ requestOverride: async (_path, options) => {
        if (options.method === 'POST') throw new Error('network failure');
        return { public_key: key, subscription: null };
    } });
    await f.client.refresh();
    await f.client.link();
    assert.equal(f.getState().bound, false);
    assert.equal(f.getSubscription(), null);
    assert.match(f.getState().error, /network failure/);
});

test('server revocation precedes browser unsubscribe and failed revocation keeps the linked state', async () => {
    const f = fixture({ permission: 'granted', bound: true, requestOverride: async (_path, options) => {
        if (options.method === 'DELETE') throw new Error('revocation failed');
        return { public_key: key, subscription: { id: 1, binding_id: 'binding', endpoint_hash: createHash('sha256').update('https://fcm.googleapis.com/test-1').digest('hex') } };
    } });
    await f.client.refresh();
    await f.client.unlink();
    assert.equal(f.getState().bound, true);
    assert.equal(f.browserActions.some(action => action.startsWith('unsubscribe:')), false);
    assert.match(f.getState().error, /revocation failed/);
});

test('successful revocation removes both server binding and local subscription', async () => {
    const f = fixture({ permission: 'granted', bound: true });
    await f.client.refresh();
    await f.client.unlink();
    assert.equal(f.calls.at(-1).method, 'DELETE');
    assert.equal(f.getState().bound, false);
    assert.equal(f.getSubscription(), null);
});

test('HTTP outside a secure context cannot request permission or register a device', async () => {
    const f = fixture();
    f.environment.isSecureContext = false;
    await f.client.refresh();
    await f.client.link();
    assert.equal(f.calls.length, 0);
    assert.match(f.getState().message, /HTTPS/);
});

test('application server keys must have the expected P-256 public key format', () => {
    assert.equal(decodeApplicationKey(key).length, 65);
    assert.throws(() => decodeApplicationKey('bad-key'));
});
