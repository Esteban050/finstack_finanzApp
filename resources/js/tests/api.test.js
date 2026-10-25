import test from 'node:test';
import assert from 'node:assert/strict';
import { safeMessage, getTRMByDate } from '../api/index.js';

test('RNF-012: los mensajes técnicos no salen hacia la UI', () => {
    for (const message of ['SQLSTATE 42000', 'Exception: host secreto', 'trace at example.php', null]) assert.equal(safeMessage(message), 'No fue posible completar la operación. Intenta nuevamente.');
    assert.equal(safeMessage('RNG-09: el monto debe ser mayor que 0.'), 'RNG-09: el monto debe ser mayor que 0.');
});
test('TRM sin contrato falla explícitamente y no genera una tasa simulada', async () => {
    await assert.rejects(getTRMByDate('2026-03-15'), /No fue posible consultar la TRM/);
});
