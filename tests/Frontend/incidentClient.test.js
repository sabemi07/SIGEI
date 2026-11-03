import test from 'node:test';
import assert from 'node:assert/strict';
import { loadAllPages, matchesIncidentSearch, requireIncidentReceipt } from '../../resources/js/incidentClient.js';

test('loads every page before returning the report collection and removes repeated rows', async () => {
    const calls = [];
    const rows = await loadAllPages(async path => {
        calls.push(path);
        return { data: calls.length === 1 ? [{ folio: 'SIG-1' }] : [{ folio: 'SIG-1' }, { folio: 'SIG-2' }], meta: { last_page: 2 } };
    }, '/api/incidents?scope=all');
    assert.equal(calls.length, 2);
    assert.match(calls[1], /scope=all&per_page=100&page=2$/);
    assert.equal(rows.length, 2);
});

test('a later page failure never returns partial figures or CSV rows', async () => {
    let calls = 0;
    await assert.rejects(loadAllPages(async () => {
        if (++calls === 2) throw new Error('offline');
        return { data: [{ folio: 'SIG-1' }], meta: { last_page: 2 } };
    }, '/api/incidents'), /offline/);
});

test('different notifications for the same incident remain separate', async () => {
    const rows = await loadAllPages(async () => ({
        data: [{ id: 'notice-one', folio: 'SIG-1' }, { id: 'notice-two', folio: 'SIG-1' }],
        meta: { last_page: 1 },
    }), '/api/notifications');
    assert.equal(rows.length, 2);
});

test('a response from the previous account is discarded before another page is requested', async () => {
    let current = true;
    let calls = 0;
    const rows = await loadAllPages(async () => {
        calls++;
        current = false;
        return { data: [{ folio: 'private-old-account' }], meta: { last_page: 2 } };
    }, '/api/incidents', { isCurrent: () => current });
    assert.equal(rows, null);
    assert.equal(calls, 1);
});

test('only a valid server receipt can become a success and private fields are excluded', () => {
    assert.throws(() => requireIncidentReceipt({ folio: 'DEMO-1', fecha: '29/10/2026', hora: '10:00' }));
    assert.throws(() => requireIncidentReceipt({ folio: 'SIG-TEST', fecha: '29/10/2026', hora: '10:00' }));
    assert.throws(() => requireIncidentReceipt({}));
    const receipt = requireIncidentReceipt({ folio: 'SIG-01ARZ3NDEKTSV4RRFFQ69G5FAV', fecha: '29/10/2026', hora: '10:00', observaciones: 'internal', reportanteId: 99 });
    assert.equal(receipt.folio, 'SIG-01ARZ3NDEKTSV4RRFFQ69G5FAV');
    assert.equal('observaciones' in receipt, false);
    assert.equal('reportanteId' in receipt, false);
});

test('a receipt includes the short folio alongside its original identifier', () => {
    const data = { folio: 'SIG-01ARZ3NDEKTSV4RRFFQ69G5FAV', folioCorto: 'SIG-02AF58', fecha: '29/10/2026', hora: '10:00' };
    const receipt = requireIncidentReceipt(data);
    assert.equal(receipt.folioCorto, 'SIG-02AF58');
    assert.equal(receipt.folio, data.folio);
    assert.throws(() => requireIncidentReceipt({ ...data, folioCorto: 'SIG-2AF58' }));
});

test('incident search accepts the six characters, both full folios and legacy demo entries', () => {
    const incident = { folio: 'SIG-01ARZ3NDEKTSV4RRFFQ69G5FAV', folioCorto: 'SIG-02AF58', tipo: 'Accidente', ubicacion: 'Edificio A' };

    for (const query of [' 02af58 ', 'SIG-02AF58', incident.folio, 'edificio', 'accidente', '']) {
        assert.equal(matchesIncidentSearch(incident, query), true);
    }
    assert.equal(matchesIncidentSearch(incident, 'SIG-05AF58'), false);
    assert.equal(matchesIncidentSearch({ folio: 'SIG-2026-001' }, '2026-001'), true);
});
