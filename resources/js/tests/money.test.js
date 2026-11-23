import test from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, formatMoneyDraft, formatQuantity, formatCanonicalValue, parseLocalizedValue, validateAmount, addMoney, subtractMoney, parseMoneyInput, percentageOf, parsePercentage, deductionMode } from '../utils/money.js';

test('captura común: conserva ocho decimales, ceros finales y límites sin transformar texto inválido', () => {
    assert.equal(parseLocalizedValue('1.234.567,89'), '1234567.89');
    assert.equal(parseLocalizedValue('1,12345678', {precision: 8}), '1.12345678');
    assert.equal(formatCanonicalValue('1234567.12000000'), '1.234.567,12000000');
    assert.equal(parseLocalizedValue('4.500', {precision: 0}), '4500');
    for (const value of ['1e3', '1..000', '1,123', '12.34']) assert.throws(() => parseLocalizedValue(value));
    assert.throws(() => parseLocalizedValue('-1.000', {min: '0'}));
    assert.throws(() => parseLocalizedValue('1.000,01', {max: '1000'}));
    assert.equal(parseLocalizedValue('-1.000,50', {min: '-2000'}), '-1000.50');
});

test('unidades y precios conservan ocho decimales con separadores de Colombia',()=>{
    assert.equal(formatQuantity('0.00000001'),'0,00000001');
    assert.equal(formatQuantity('100000000.00000001'),'100.000.000,00000001');
    assert.equal(formatQuantity('200000000'),'200.000.000');
});

test('formato COP sin pérdida de precisión', () => {
    assert.equal(formatMoney('1234567'), '$\u00a01.234.567');
    assert.equal(formatMoney('1234.50'), '$\u00a01.234,50');
    assert.equal(formatMoney('999999999999.99'), '$\u00a0999.999.999.999,99');
    assert.equal(formatMoney('-1234.56'), '-$\u00a01.234,56');
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


test('edición visual: agrupación de miles y millones sin alterar el decimal enviado',()=>{
 for(const [raw,shown,canonical] of [['1234','1.234','1234.00'],['1234567',"1.234.567",'1234567.00'],['1234567,89',"1.234.567,89",'1234567.89'],['999999999999,99',"999.999.999.999,99",'999999999999.99'],['$ 5000000',"5.000.000",'5000000.00']]){
  assert.equal(formatMoneyDraft(raw),shown);assert.equal(parseMoneyInput(shown),canonical);
 }
 for(const invalid of ['1.5','1e5','1..000','5,000,000']){assert.equal(formatMoneyDraft(invalid),invalid);assert.throws(()=>parseMoneyInput(formatMoneyDraft(invalid)));}
 assert.equal(formatMoneyDraft('1234,'),'1.234,');assert.equal(formatMoneyDraft('1234,005'),'1.234,005');assert.throws(()=>parseMoneyInput(formatMoneyDraft('1234,005')));
 assert.equal(formatMoneyDraft('1.2345',{regroup:true}),'12.345');
});

import { groupMoneyControl } from '../ui/money-format.js';

test('edición incremental y borrado reagrupan; pegado inválido conserva la validación estricta',()=>{
 const input={value:'',selectionStart:0,selectionEnd:0,matches:()=>false};
 for(const digit of '1234567'){input.value+=digit;groupMoneyControl(input,input.value,{type:'input',inputType:'insertText'});}
 assert.equal(input.value,"1.234.567");assert.equal(parseMoneyInput(input.value),'1234567.00');
 input.value='12.345.67';groupMoneyControl(input,input.value,{type:'input',inputType:'deleteContentBackward'});assert.equal(input.value,"1.234.567");
 input.value='1.5';groupMoneyControl(input,input.value,{type:'input',inputType:'insertFromPaste'});assert.equal(input.value,'1.5');assert.throws(()=>parseMoneyInput(input.value));
});

import { registerFoundation } from '../ui/foundation.js';

test('MoneyInput compartido mantiene el valor canónico enviado y la retención exacta',()=>{
 const factories={};registerFoundation({data:(name,factory)=>{factories[name]=factory;}});
 const visible={value:"1.234.567,89",selectionStart:12,selectionEnd:12,matches:()=>false,required:true,setAttribute(){},setCustomValidity(message){this.error=message;}};
 const hidden={value:''},error={textContent:'',hidden:true};
 const field={dataset:{field:'monto_original'},querySelector:selector=>selector==='input[type=text]'?visible:error};
 const root={dataset:{allowZero:'false'},closest:selector=>selector==='form'?{querySelector:()=>({value:'5000000.00'})}:field,querySelector:()=>hidden};
 const state={...factories.moneyInput(),$root:root,$dispatch(){},display:visible.value};state.validate();
 assert.equal(hidden.value,'1234567.89');assert.equal(state.invalid,false);
 state.mode='percent';state.display='3';root.dataset.allowZero='true';state.validate();assert.equal(hidden.value,'150000.00');assert.equal(state.preview,'= $\u00a0150.000');
});

import { exceedsMoneyDigits } from '../utils/money.js';
test('RNG-09: el campo de monto no admite más de 12 enteros ni más de 2 decimales', () => {
    for (const ok of ['', '1', '999.999.999.999', '999.999.999.999,99', '999999999999,9', '000.000.000.000.001', '-999.999.999.999,99', '$ 1.000,5'])
        assert.equal(exceedsMoneyDigits(ok), false, ok);
    for (const malo of ['1.000.000.000.000', '9999999999999', '999.999.999.999,999', '1,123', '-1.000.000.000.000'])
        assert.equal(exceedsMoneyDigits(malo), true, malo);
});

import { cleanMoneyInsert } from '../utils/money.js';
test('campo de dinero: solo números, puntos y comas', () => {
    for (const ok of ['1', '1.000', '1.000,50', ',', '.', '']) assert.equal(cleanMoneyInsert(ok), ok, ok);
    for (const malo of ['a', 'e', '1e5', ' ', '$', '#', '%', '+', '-', '1 000', 'ñ', '€']) assert.equal(cleanMoneyInsert(malo), null, malo);
    assert.equal(cleanMoneyInsert('-', { allowNegative: true }), '-');
    // Al pegar "$ 1.000" se limpia; con letras se rechaza.
    assert.equal(cleanMoneyInsert('$ 1.000,50', { pasted: true }), '1.000,50');
    assert.equal(cleanMoneyInsert('1.000 COP', { pasted: true }), null);
});

test('campos con más precisión (tasas, precios) conservan sus decimales', () => {
    assert.equal(exceedsMoneyDigits('1,12345678', 8), false);
    assert.equal(exceedsMoneyDigits('1,123456789', 8), true);
    assert.equal(exceedsMoneyDigits('1,1234', 4), false);
    assert.equal(exceedsMoneyDigits('1,123', 0), true);
});
