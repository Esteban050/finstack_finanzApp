import test from 'node:test';
import assert from 'node:assert/strict';
import { safeMessage } from '../api/index.js';

test('RNF-012: los mensajes técnicos no salen hacia la UI', () => {
    for (const message of ['SQLSTATE 42000', 'Exception: host secreto', 'trace at example.php', null]) assert.equal(safeMessage(message), 'No fue posible completar la operación. Intenta nuevamente.');
    assert.equal(safeMessage('El monto debe ser mayor que $ 0.'), 'El monto debe ser mayor que $ 0.');
});
