import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { PAYMENT_LABELS, validateExpense, validateTags, validateBulk, validateCategory, selectionWith, validateTagDraft, categoryPayload } from '../expenses/rules.js';

const catalog = [{id: 'housing', nombre: 'Vivienda', activa: true, es_predeterminada: true, subcategorias: [{id: 'rent', nombre: 'Arriendo', activa: true, naturaleza_sugerida: 'FIJO', necesidad_sugerida: 'ESENCIAL'}]}, {id: 'empty', nombre: 'Sin hijas', activa: true, subcategorias: []}];
const expense = {fecha: '2026-01-01', monto_original: '999999999999.99', categoria_id: 'housing', subcategoria_id: 'rent', medio_pago: 'TARJETA_CREDITO', naturaleza: 'FIJO', necesidad: 'ESENCIAL', relevancia_tributaria: 'NO_DEDUCIBLE', etiquetas: []};
test('RN-025: monto decimal exacto, seis medios, fecha civil y subcategoría obligatoria', () => {
    assert.deepEqual(validateExpense(expense, catalog), {});
    assert.deepEqual(Object.values(PAYMENT_LABELS), ['Efectivo','Tarjeta débito','Tarjeta de crédito','Transferencia','Billetera digital','Otro']);
    for (const value of ['0','-1','1000000000000','0.001']) assert.ok(validateExpense({...expense, monto_original: value}, catalog).monto_original);
    assert.ok(validateExpense({...expense, fecha: '2026-02-30'}, catalog).fecha);
    assert.ok(validateExpense({...expense, fecha: '2999-01-01'}, catalog).fecha);
    assert.ok(validateExpense({...expense, subcategoria_id: ''}, catalog).subcategoria_id);
    assert.ok(validateExpense({...expense, categoria_id: 'empty'}, catalog).subcategoria_id);
    const inactive = structuredClone(catalog); inactive[0].subcategorias[0].activa = false;
    assert.ok(validateExpense(expense, inactive).subcategoria_id);
    assert.ok(validateExpense({...expense, medio_pago: 'PSE'}, catalog).medio_pago);
});
test('RN-026: dimensiones editables, subtipo y límites de etiquetas y textos', () => {
    assert.deepEqual(validateExpense({...expense, naturaleza: 'VARIABLE', necesidad: 'DISCRECIONAL'}, catalog), {});
    assert.ok(validateExpense({...expense, relevancia_tributaria: 'POTENCIALMENTE_DEDUCIBLE'}, catalog).subtipo_deduccion);
    assert.deepEqual(validateExpense({...expense, relevancia_tributaria: 'POTENCIALMENTE_DEDUCIBLE', subtipo_deduccion: 'GMF'}, catalog), {});
    assert.equal(validateTags(Array.from({length:10},(_,i)=>`etiqueta-${i}`)), '');
    assert.match(validateTags(Array(11).fill('viaje')), /10/);
    assert.match(validateTags(['x'.repeat(31)]), /2 y 30/);
    assert.match(validateTags(['x']), /2 y 30/);
    assert.match(validateTagDraft('viaje', []), /Agrega/);
    assert.match(validateTagDraft('x'.repeat(31), []), /30/);
    assert.equal(validateTagDraft('', []), '');
    assert.ok(validateExpense({...expense, comercio_beneficiario: 'x'.repeat(81), descripcion: 'x'.repeat(256)}, catalog).descripcion);
});
test('RN-028: recurrencia usa fechas futuras y mantiene límites compartidos', () => {
    const recurring = {...expense, fecha_inicio:'2999-01-01', periodicidad:'MENSUAL', modo_confirmacion:'REQUIERE_CONFIRMACION'};
    assert.deepEqual(validateExpense(recurring, catalog, {recurrent:true}), {});
    assert.ok(validateExpense({...recurring,max_ocurrencias:'361'}, catalog, {recurrent:true,limit:'count'}).max_ocurrencias);
    assert.ok(validateExpense({...recurring,fecha_fin:'2999-02-01',max_ocurrencias:'5'}, catalog, {recurrent:true}).fecha_fin);
});
test('RN-029: bloquea selección 101 sin perder las primeras 100; una operación masiva', () => {
    let ids = new Set(); for (let i=0;i<100;i++) ids=selectionWith(ids,`id-${i}`,true).ids;
    const blocked = selectionWith(ids,'extra',true); assert.equal(blocked.blocked,true); assert.equal(blocked.ids.size,100);
    assert.equal(selectionWith(ids,'id-0',false).ids.size,99);
    assert.deepEqual(validateBulk({ids:[...ids],naturaleza:'FIJO'},catalog),{});
    assert.ok(validateBulk({ids:[...ids,'extra'],naturaleza:'FIJO'},catalog).ids);
    assert.ok(validateBulk({ids:['one'],categoria_id:'housing'},catalog).subcategoria_id);
    assert.ok(validateBulk({ids:['one']},catalog).cambios);
    assert.deepEqual(validateBulk({ids:['one'],etiquetas:[]},catalog),{});
});
test('RN-027: topes, unicidad por nivel y respaldo visual del catálogo', () => {
    const roots = Array.from({length:50},(_,i)=>({id:String(i),nombre:`Propia ${i}`,es_predeterminada:false}));
    assert.equal(validateCategory({nombre:'Otra'},roots).nombre[0],'Alcanzaste el máximo de 50 categorías');
    assert.deepEqual(validateCategory({nombre:'Editada'},roots,{id:'1'}),{});
    assert.ok(validateCategory({nombre:' vivienda '},catalog).nombre);
    const children = {...catalog[0], subcategorias:Array.from({length:30},(_,i)=>({id:String(i),nombre:`Sub ${i}`,es_predeterminada:false}))};
    assert.match(validateCategory({nombre:'Otra'},[children],{parentId:'housing'}).nombre[0],/30/);
    const node = fs.readFileSync('resources/views/components/expense/category-node.blade.php','utf8');
    assert.match(node,/\$owned = !\$category->es_predeterminada/);
    assert.match(node,/CategoriaGastoService::tieneGastos/);
});
test('RN-029: totales autoritativos y ausencia de campos ajenos al alcance', () => {
    const table = fs.readFileSync('resources/views/components/expense/table.blade.php','utf8');
    for (const total of ['monto','tarjeta_credito','potencialmente_deducible']) assert.ok(table.includes(`$totals->${total}`));
    assert.doesNotMatch(table,/number_format|\.reduce\(/);
    const form = fs.readFileSync('resources/views/components/expense/form.blade.php','utf8');
    assert.doesNotMatch(form,/cuenta|saldo|presupuesto|adjunto|factura|sincroniz/i);
    assert.match(table,/No hay gastos para los filtros seleccionados/);
});
test('Blade real: fila pendiente, totales independientes y plantilla editable sin guardar modelos', {skip: !fs.existsSync('vendor/autoload.php')}, () => {
    const rendered = JSON.parse(execFileSync('php', ['scripts/render-expense-fixtures.php'], {encoding:'utf8', maxBuffer:5e6}));
    assert.match(rendered.table,/data-money="999999\.99"/);
    assert.match(rendered.table,/data-money="125000\.00"/);
    assert.match(rendered.table,/data-expense-confirm/);
    assert.match(rendered.table,/data-expense-select/);
    assert.match(rendered.recurrences,/Editar plantilla/);
    assert.match(rendered.recurrences,/data-limit="none"/);
    assert.match(rendered.recurrences,/name="es_monto_estimado" value="1" checked/);
    const ids = [...rendered.recurrences.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
    assert.equal(ids.length,new Set(ids).size,'IDs únicos entre confirmación, edición y creación');
});

test('G-01: sugerencias envían enums exactos y dejan de estar inválidas al corregir',()=>{
    const v={nombre:'QA-Hija',naturaleza_sugerida:'FIJO',necesidad_sugerida:'ESENCIAL'};
    assert.deepEqual(validateCategory(v,catalog,{parentId:'housing'}),{});
    assert.ok(validateCategory({...v,naturaleza_sugerida:'Fijo'},catalog,{parentId:'housing'}).naturaleza_sugerida);
    assert.ok(validateCategory({...v,necesidad_sugerida:''},catalog,{parentId:'housing'}).necesidad_sugerida);
});

test('G-02: opcionales incompatibles se omiten sin bloquear; payload permitido',()=>{
    assert.deepEqual(categoryPayload({nombre:'QA-Test',icono:'cat-vivienda',color_hex:'#FB923C',rol:'ADMIN'},['home'],['#2B6CB0']).data,{nombre:'QA-Test'});
    assert.deepEqual(categoryPayload({nombre:'QA-Test',icono:'home',color_hex:'#2B6CB0'},['home'],['#2B6CB0']).data,{nombre:'QA-Test',icono:'home',color_hex:'#2B6CB0'});
    assert.deepEqual(categoryPayload({nombre:'QA-Test'},[],[]).warnings,[]);
});

test('G-02: todas las opciones aceptadas por el contrato se conservan sin avisos',()=>{
    const source=fs.readFileSync('app/Services/CategoriaGastoService.php','utf8');
    const read=(name)=>[...source.match(new RegExp('public const '+name+' = \\[([\\s\\S]*?)\\];'))[1].matchAll(/'([^']+)'/g)].map(m=>m[1]);
    const icons=read('ICONOS'),colors=read('PALETA');
    for(const icono of icons) assert.deepEqual(categoryPayload({nombre:'QA-Test',icono},icons,colors).warnings,[]);
    for(const color_hex of colors) assert.deepEqual(categoryPayload({nombre:'QA-Test',color_hex},icons,colors).warnings,[]);
});
