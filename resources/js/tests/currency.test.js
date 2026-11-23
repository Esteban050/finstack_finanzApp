import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import { test } from 'node:test';
import { mountCurrencySelector } from '../ui/currency.js';

function openExpenseForm(t) {
    const cop = Object.assign(new EventTarget(), { value: 'COP' });
    const usd = Object.assign(new EventTarget(), { value: 'USD' });
    const date = Object.assign(new EventTarget(), { value: '2026-10-14' });
    const rate = { value: '4.100,0000' };
    const hint = { textContent: '' };
    const box = { hidden: false };
    const calls = [];
    let selected = usd;

    const form = {
        querySelectorAll: (key) => ({
            '[data-currency-radio]': [cop, usd],
            '[data-trm-hint]': [hint],
            '[data-trm-suggestion]': [box],
        })[key] ?? [],
        querySelector(key) {
            if (key === '[data-currency-radio]:checked') return selected;
            return key === '[name=tasa_cambio_usada]' ? rate : date;
        },
    };

    t.mock.method(globalThis, 'fetch', (url, { signal }) => new Promise(resolve => {
        calls.push({
            date: url.searchParams.get('fecha'),
            path: url.pathname,
            signal,
            respond: (body, status = 200) => resolve(Response.json(body, { status })),
        });
    }));
    globalThis.window = { location: { origin: 'https://finanzapp.test' } };
    globalThis.document = { querySelectorAll: () => [form], querySelector: () => null };
    t.after(() => {
        delete globalThis.window;
        delete globalThis.document;
    });

    mountCurrencySelector();

    return {
        calls, hint, rate, box,
        changeDate(value) {
            date.value = value;
            date.dispatchEvent(new Event('change'));
        },
        selectCop() {
            selected = cop;
            cop.dispatchEvent(new Event('change'));
        },
    };
}

test('TRM ignores an earlier response after changing the expense date', async t => {
    const form = openExpenseForm(t);
    const first = form.calls[0];
    assert.equal(first.path, '/tasas-cambio/trm');
    assert.equal(first.date, '2026-10-14');

    form.changeDate('2026-10-15');
    assert.equal(first.signal.aborted, true);
    form.calls[1].respond({ data: { valor: '3900.35', es_tasa_anterior: true } });
    await setImmediate();
    const validHint = form.hint.textContent;
    assert.match(validHint, /15\/10\/2026.*3\.900,35.*tasa anterior disponible/);

    first.respond({ data: { valor: '4400.00' } });
    await setImmediate();
    assert.equal(form.hint.textContent, validHint);
    assert.equal(form.rate.value, '4.100,0000');
});

test('TRM keeps the manual value on errors and clears the suggestion on COP', async t => {
    const form = openExpenseForm(t);
    form.calls[0].respond({}, 503);
    await setImmediate();
    assert.match(form.hint.textContent, /No hay TRM disponible.*manualmente/);
    assert.equal(form.rate.disabled, false);

    form.changeDate('2026-10-15');
    form.calls[1].respond({}, 500);
    await setImmediate();
    assert.match(form.hint.textContent, /No se pudo consultar/);

    form.changeDate('2026-10-16');
    form.selectCop();
    const last = form.calls[2];
    assert.equal(last.signal.aborted, true);
    last.respond({ data: { valor: '4000' } });
    await setImmediate();
    assert.equal(form.hint.textContent, '');
    assert.equal(form.box.hidden, true);
    assert.equal(form.rate.disabled, true);
    assert.equal(form.rate.value, '4.100,0000');
});
