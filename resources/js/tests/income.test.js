import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { catalogVisual, subcategoryIcons } from '../income/catalog.js';
import { ENABLE_FOREIGN_CURRENCY } from '../config/currency.js';
import { incomeSummary, validateIncome, validateSource, validateRecurrence, suggestClassification, validateIncomeFilters } from '../income/rules.js';

const valid = { fuente_id: 'source-a', fecha_recepcion: '2026-01-01', monto_original: '5000000', retencion_fuente: '150000', aportes_descontados: '400000' };
test('catálogo: los 12 hex usan tokens y los valores desconocidos usan slate', () => {
    const palette = Object.fromEntries([...fs.readFileSync('resources/css/tokens.css', 'utf8').matchAll(/--cat-(\w+):\s*(#[\dA-Fa-f]{6})/g)].map((match) => [match[1], match[2]]));
    assert.equal(Object.keys(palette).length, 12);
    for (const [name, hex] of Object.entries(palette)) {
        assert.deepEqual(catalogVisual({ icono: 'cat-vivienda', color_hex: hex.toLowerCase() }, palette), { iconName: 'cat-vivienda', colorVar: `var(--cat-${name})` });
    }
    assert.deepEqual(catalogVisual({ icono: 'desconocido', color_hex: '#000000' }, palette), { iconName: 'cat-otros', colorVar: 'var(--cat-slate)' });
    assert.equal(catalogVisual({}, palette).colorVar, 'var(--cat-slate)');
    assert.equal(ENABLE_FOREIGN_CURRENCY, false);
});
test('catálogos heredados mantienen sus valores y se presentan con íconos del kit', () => {
    assert.equal(catalogVisual({ icono: 'home' }, {}).iconName, 'cat-vivienda');
    assert.equal(catalogVisual({ icono: 'utensils' }, {}).iconName, 'cat-alimentacion');
    assert.equal(catalogVisual({ icono: 'ellipsis-h' }, {}).iconName, 'cat-otros');
    assert.equal(catalogVisual({ typeName: 'Salario' }, {}).iconName, 'src-salario');
    assert.equal(catalogVisual({ typeName: 'Arrendamientos' }, {}).iconName, 'src-arrendamientos');
    assert.equal(catalogVisual({ icono: 'src-dividendos', typeName: 'Salario' }, {}).iconName, 'src-dividendos');
});
test('CA-021.1: el neto se calcula exactamente y solo con strings decimales', () => {
    assert.equal(incomeSummary(valid).net, '4450000.00');
    assert.deepEqual(validateIncome(valid), {});
    assert.equal(incomeSummary({ monto_original: '0.3', retencion_fuente: '0.1', aportes_descontados: '0.2' }).net, '0.00');
});
test('retención, negativos, rango máximo y fechas futuras se rechazan por campo', () => {
    assert.equal(validateIncome({ ...valid, retencion_fuente: '5000000.01' }).retencion_fuente[0], 'La retención no puede superar el monto bruto');
    assert.ok(validateIncome({ ...valid, aportes_descontados: '-1' }).aportes_descontados);
    assert.ok(validateIncome({ ...valid, monto_original: '1000000000000' }).monto_original);
    assert.match(validateIncome({ ...valid, fecha_recepcion: '2999-01-01' }).fecha_recepcion[0], /recurrente/);
    assert.ok(validateIncome({ ...valid, descripcion: 'a'.repeat(256) }).descripcion);
});
test('la unicidad de fuentes excluye el registro editado y usa el mensaje exacto', () => {
    const source = { id: 'a', nombre: 'Mi salario' };
    const values = { nombre: ' mi SALARIO ', tipo_fuente_id: 'tipo', clasificacion_tributaria: 'RENTAS_TRABAJO' };
    assert.equal(validateSource(values, [source]).nombre[0], 'Ya existe una fuente con ese nombre');
    assert.deepEqual(validateSource(values, [source], 'a'), {});
    assert.ok(validateSource({ ...values, pagador_entidad: 'x'.repeat(81) }).pagador_entidad);
});
test('sugerencias centrales del catálogo, editables y sin inventar un tipo', () => {
    for (const name of ['Salario', 'Honorarios/Servicios']) assert.equal(suggestClassification(name), 'RENTAS_TRABAJO');
    assert.equal(suggestClassification('Arrendamientos'), 'RENTAS_CAPITAL');
    assert.equal(suggestClassification('Rendimientos financieros'), 'RENTAS_CAPITAL');
    assert.equal(suggestClassification('Pensión'), 'PENSIONES');
    assert.equal(suggestClassification('Dividendos'), 'DIVIDENDOS');
    assert.equal(suggestClassification('Tipo nuevo'), '');
});
test('recurrencias: límites excluyentes, máximo 360 y fecha de fin coherente', () => {
    const base = { periodicidad: 'MENSUAL', fecha_inicio: '2026-01-30', modo_confirmacion: 'REQUIERE_CONFIRMACION' };
    assert.deepEqual(validateRecurrence({ ...base, max_ocurrencias: '360' }), {});
    assert.ok(validateRecurrence({ ...base, max_ocurrencias: '361' }).max_ocurrencias);
    assert.ok(validateRecurrence({ ...base, fecha_fin: '2026-01-29' }).fecha_fin);
    assert.ok(validateRecurrence({ ...base, fecha_fin: '2026-02-28', max_ocurrencias: '10' }).fecha_fin);
    assert.ok(validateRecurrence(base, 'count').max_ocurrencias);
});
test('los filtros preservan límites exactos sin sumar la página', () => {
    assert.ok(validateIncomeFilters({ monto_min: '999999999999.99', monto_max: '1' }).monto_max);
    const list = fs.readFileSync('resources/views/components/income/table.blade.php', 'utf8');
    for (const key of ['bruto', 'retencion', 'neto']) assert.ok(list.includes(`$totals->${key}`));
    assert.ok(list.includes('No hay ingresos para los filtros seleccionados'));
    assert.doesNotMatch(list, /number_format|parseFloat|\.reduce\(/);
    assert.ok(fs.readFileSync('resources/views/ingresos/fuentes/index.blade.php', 'utf8').includes('tieneIngresos()'));
});

test('G-04: neto cero válido y descuentos conjuntos sobre bruto rechazados',()=>{
    assert.deepEqual(validateIncome({...valid,retencion_fuente:'5000000',aportes_descontados:'0'}),{});
    assert.ok(validateIncome({...valid,retencion_fuente:'3000000',aportes_descontados:'2500000'}).aportes_descontados);
});

test('G-05: hermanas del inventario usan claves existentes y distintas', async()=>{
    const {ICONS}=await import('../ui/icons/icons.js');
    for(const icon of Object.values(subcategoryIcons)) assert.ok(Object.hasOwn(ICONS,icon));
    const icons=Object.values(subcategoryIcons);
    for(let i=0;i<icons.length;i+=2) assert.notEqual(icons[i],icons[i+1]);
    assert.equal(catalogVisual({nombre:'Arriendo',icono:'home'},{}).iconName,'src-arrendamientos');
});

test('QA-008: revalidación compartida sincroniza error y ARIA en 0→1 y 361→360',async()=>{
    const {mapFieldErrors,bindErrorRevalidation}=await import('../ui/foundation.js');
    for(const [bad,good] of [['0','1'],['361','360']]) {
        const attributes={},error={hidden:true,textContent:''}, input={id:'count',setAttribute:(k,v)=>attributes[k]=v,focus:()=>{}};
        const field={dataset:{field:'max_ocurrencias'},querySelector:s=>s==='[data-field-error]'?error:input,querySelectorAll:()=>[input]};
        const listeners={}; const form={querySelectorAll:()=>[field],querySelector:()=>input,addEventListener:(name,fn)=>listeners[name]=fn};
        let value=bad; const validate=()=>validateRecurrence({fecha_inicio:'2030-01-01',periodicidad:'MENSUAL',modo_confirmacion:'REQUIERE_CONFIRMACION',max_ocurrencias:value},'count');
        mapFieldErrors(form,validate()); assert.equal(attributes['aria-invalid'],'true');
        bindErrorRevalidation(form,validate); value=good; listeners.input();
        assert.equal(error.hidden,true); assert.equal(error.textContent,''); assert.equal(attributes['aria-invalid'],'false');
    }
});
