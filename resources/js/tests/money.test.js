import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, formatTRM, validateAmount, addMoney, subtractMoney, parseMoneyInput, estimateCOP, percentageOf } from '../utils/money.js';

test('formatos COP y USD sin pérdida de precisión', () => {
    assert.equal(formatMoney('1234567'), '$ 1.234.567');
    assert.equal(formatMoney('1234.50'), '$ 1.234,50');
    assert.equal(formatMoney('1234.5', 'USD'), 'US$ 1.234,50');
    assert.equal(formatMoney('0', 'USD'), 'US$ 0,00');
    assert.equal(formatMoney('999999999999.99'), '$ 999.999.999.999,99');
    assert.equal(formatMoney('-1234.56'), '-$ 1.234,56');
    assert.throws(() => formatMoney(0.1), TypeError);
    assert.throws(() => formatMoney('1', 'EUR'), RangeError);
});
test('RNG-09: extremos y precisión de registro', () => {
    for (const value of ['0.01', '999999999999.99']) assert.equal(validateAmount(value), true);
    for (const value of ['0', '-1', '1000000000000', '0.001', 'NaN', 'Infinity', '1e3', '1,00', '', 10]) assert.equal(validateAmount(value), false);
});
test('operaciones y estimaciones usan decimales exactos', () => {
    assert.equal(addMoney('0.1', '0.2'), '0.30');
    assert.equal(subtractMoney(subtractMoney('5000000', '150000'), '400000'), '4450000.00');
    assert.equal(estimateCOP('100', '4123.4567'), '412345.67');
    assert.equal(percentageOf('999999999999.99', '0.01'), '100000000.00');
});
test('entrada localizada estricta sin redondear silenciosamente', () => {
    assert.equal(parseMoneyInput('1.234.567,89'), '1234567.89');
    assert.equal(parseMoneyInput('0,01'), '0.01');
    for (const value of ['1.23', '1,234', '1e3', '1..000', '<script>']) assert.throws(() => parseMoneyInput(value));
});
test('la TRM muestra toda su precisión almacenada', () => {
    assert.equal(formatTRM('4123.4567'), '4.123,4567 COP/USD');
    assert.throws(() => formatTRM('0'), RangeError);
});
