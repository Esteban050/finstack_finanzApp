import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, validateAmount, addMoney, subtractMoney, parseMoneyInput, percentageOf, parsePercentage, deductionMode } from '../utils/money.js';

test('formato COP sin pérdida de precisión', () => {
    assert.equal(formatMoney('1234567'), '$ 1.234.567');
    assert.equal(formatMoney('1234.50'), '$ 1.234,50');
    assert.equal(formatMoney('999999999999.99'), '$ 999.999.999.999,99');
    assert.equal(formatMoney('-1234.56'), '-$ 1.234,56');
    assert.throws(() => formatMoney(0.1), TypeError);
});
test('RNG-09: extremos y precisión de registro', () => {
    for (const value of ['0.01', '999999999999.99']) assert.equal(validateAmount(value), true);
    for (const value of ['0', '-1', '1000000000000', '0.001', 'NaN', 'Infinity', '1e3', '1,00', '', 10]) assert.equal(validateAmount(value), false);
});
test('operaciones y estimaciones usan decimales exactos', () => {
    assert.equal(addMoney('0.1', '0.2'), '0.30');
    assert.equal(subtractMoney(subtractMoney('5000000', '150000'), '400000'), '4450000.00');
    assert.equal(percentageOf('999999999999.99', '0.01'), '100000000.00');
});
test('entrada localizada estricta sin redondear silenciosamente', () => {
    assert.equal(parseMoneyInput('1.234.567,89'), '1234567.89');
    assert.equal(parseMoneyInput('0,01'), '0.01');
    for (const value of ['1.23', '1,234', '1e3', '1..000', '<script>']) assert.throws(() => parseMoneyInput(value));
});

 test('G-04: porcentajes con BigInt, HALF_UP y edición sin alterar centavos', () => {
    for (const [gross, percent, expected] of [['2300000','16','368000.00'],['5000000','3','150000.00'],['5000000','8','400000.00'],['0.05','50','0.03'],['10','33.33','3.33'],['999999999999.99','19.5','195000000000.00'],['10','0','0.00'],['10','100','10.00']]) assert.equal(percentageOf(gross,percent),expected);
    assert.equal(parsePercentage('19,5'),'19.5');
    for (const value of ['101','-1','1,005','1.5','']) assert.throws(()=>parsePercentage(value));
    assert.equal(deductionMode('150000','5000000').mode,'percent');
    assert.equal(deductionMode('0.01','999999999999.99').mode,'money');
});

test('pegado: símbolo COP y espacios normales/duros mantienen formato estricto',()=>{
    for(const value of ['$ 5.000.000','$\u00a05.000.000',' $ 5.000.000,50 ']) assert.equal(parseMoneyInput(value),value.includes(',50')?'5000000.50':'5000000.00');
    for(const value of ['$ 1.23','$ 5,000,000','$ 1e5']) assert.throws(()=>parseMoneyInput(value));
});
