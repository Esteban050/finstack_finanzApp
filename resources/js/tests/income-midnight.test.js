import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { todayBogota } from '../utils/date.js';
import { validateIncome } from '../income/rules.js';

// CP-ING-027 · RNG-03: el navegador toma "hoy" en America/Bogota, no en UTC.
const ingreso = (fecha) => ({ fuente_id: 'f', fecha_recepcion: fecha, monto_original: '1000000.00' });
const en = (instanteUtc, fn) => {
    mock.timers.enable({ apis: ['Date'], now: new Date(instanteUtc) });
    try { fn(); } finally { mock.timers.reset(); }
};

test('23:59 del 31/10 en Bogotá (04:59 UTC del 01/11): hoy es 31/10 y el 01/11 es futuro', () => en('2026-11-01T04:59:00Z', () => {
    assert.equal(todayBogota(), '2026-10-31');
    assert.equal(validateIncome(ingreso('2026-10-31')).fecha_recepcion, undefined);
    assert.ok(validateIncome(ingreso('2026-11-01')).fecha_recepcion);
}));

test('23:59:59 del 31/10 en Bogotá sigue siendo 31/10', () => en('2026-11-01T04:59:59Z', () => {
    assert.equal(todayBogota(), '2026-10-31');
    assert.ok(validateIncome(ingreso('2026-11-01')).fecha_recepcion);
}));

test('00:00:00 del 01/11 en Bogotá (05:00 UTC) ya es 01/11', () => en('2026-11-01T05:00:00Z', () => {
    assert.equal(todayBogota(), '2026-11-01');
    assert.equal(validateIncome(ingreso('2026-11-01')).fecha_recepcion, undefined);
    assert.ok(validateIncome(ingreso('2026-11-02')).fecha_recepcion);
}));
