import test from 'node:test';
import assert from 'node:assert/strict';
import { formatDate, todayBogota } from '../utils/date.js';

test('fecha civil conserva el día y usa DD/MM/AAAA', () => {
    assert.equal(formatDate('2026-03-15'), '15/03/2026');
    assert.equal(formatDate('2024-02-29'), '29/02/2024');
});
test('instantes se muestran en America/Bogota', () => {
    assert.equal(formatDate('2026-03-15T02:00:00Z'), '14/03/2026');
    assert.match(todayBogota(), /^\d{4}-\d{2}-\d{2}$/);
});
test('rechaza fechas imposibles o instantes sin zona explícita', () => {
    for (const value of ['2026-02-29', '2026-13-01', '2026-03-15T02:00:00', '', null]) assert.throws(() => formatDate(value));
});
