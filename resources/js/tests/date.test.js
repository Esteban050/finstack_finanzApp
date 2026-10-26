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

test('QA-007: periodos civiles preservan enero, bisiesto y límites de trimestre',async()=>{
    const {periodRange}=await import('../utils/date.js');
    assert.deepEqual(periodRange('mes-anterior','2026-01-05'),{from:'2025-12-01',to:'2025-12-31'});
    assert.deepEqual(periodRange('mes-anterior','2024-03-01'),{from:'2024-02-01',to:'2024-02-29'});
    assert.deepEqual(periodRange('trimestre','2026-10-25'),{from:'2026-10-01',to:'2026-10-25'});
});
