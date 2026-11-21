import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { complementaryTotal, validateRentaAmounts, registerRenta } from '../renta/index.js';
import { simulateBenefits } from '../renta/benefits.js';
import { projectedInterest } from '../finance/projections.js';
import { contractPayload } from '../api/index.js';

test('renta: admite pesos y centavos, cero y ajustes exactos sin exigir múltiplos de mil', () => {
    assert.deepEqual(validateRentaAmounts({ingresos_brutos_cop:'1234567.89',patrimonio_bruto_cop:'0'}),{});
    for (const raw of ['-1','1000000000000','0.001','1e3']) assert.ok(validateRentaAmounts({ingresos_brutos_cop:raw}).ingresos_brutos_cop);
    assert.equal(complementaryTotal('60000000.01','-1000.50'),'59998999.51');
    assert.equal(complementaryTotal('999999999999.99','0.01'),'1000000000000.00');
});

test('rentista: modalidad y gastos llegan al contrato de simulación, sin campos ajenos', () => {
    // Compatibilidad con origen_monto incorporado por el equipo en c9823c1.
    for (const origen_monto of ['INGRESOS','MANUAL']) assert.deepEqual(contractPayload('/seguridad-social/calcular','POST',{tipo_cotizante:'INDEPENDIENTE',periodo:'2026-11',monto_contrato:'5000000.00',origen_monto,usuario_id:'ajeno'}),{tipo_cotizante:'INDEPENDIENTE',periodo:'2026-11',monto_contrato:'5000000.00',origen_monto});
    assert.deepEqual(contractPayload('/seguridad-social/liquidaciones/simular','POST',{periodo:'2026-11',ingreso_base:'5000000.00',es_rentista:'1',gastos:'1000000.00',usuario_id:'ajeno'}),{periodo:'2026-11',ingreso_base:'5000000.00',es_rentista:'1',gastos:'1000000.00'});
});

test('proyección: conserva centavos y tasa pactada, con capital grande y tasa cero', () => {
    assert.equal(projectedInterest('999999999999.99','0.01','365'),'100000000.00');
    assert.equal(projectedInterest('1234567.89','0','365'),'0.00');
    assert.equal(projectedInterest('1000','10','365'),'100.00');
});

test('beneficios: conserva los tramos existentes y los saldos con centavos', () => {
    const base={uvt:'50000',rlg:'100000000',impuesto:'10000000',retenciones:'0.01'};
    const result=simulateBenefits(base,{});
    assert.equal(result.impuesto,'10000000.00');
    assert.equal(result.saldo,'9999999.99');
    const upper=simulateBenefits({...base,rlg:'1000000000'},{});
    assert.equal(upper.impuesto,'314105000.00');
});

test('cobertura: ningún monto sigue como number sin adaptador; años y plazos se conservan', () => {
    const walk=dir=>fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?walk(path.join(dir,entry.name)):[path.join(dir,entry.name)]);
    const exclusions=/^(?:anio|anio_vigencia|personas_a_cargo|numero_cuotas|max_ocurrencias|sim_cuenta_dias)$|plazo/;
    const missing=[];
    for (const file of walk('resources/views').filter(file=>file.endsWith('.blade.php'))) {
        for (const [tag] of fs.readFileSync(file,'utf8').matchAll(/<(?:input|x-text-input)\b[^>]*type="number"[^>]*>/g)) {
            const key=(tag.match(/\bname="([^"]+)"/)||tag.match(/\bid="([^"]+)"/)||[])[1]||'';
            if (!tag.includes('data-value-input')&&!exclusions.test(key)) missing.push(file+': '+key);
        }
    }
    assert.deepEqual(missing,[]);
});

test('renta: una respuesta tardía no restaura el resultado tras editar un monto', async () => {
    const previous={window:globalThis.window,document:globalThis.document,fetch:globalThis.fetch,FormData:globalThis.FormData};
    globalThis.window={location:{origin:'http://127.0.0.1:8000'}};
    globalThis.document={body:{dataset:{loginUrl:'/login'}},querySelector:()=>({content:'ficticio'})};
    globalThis.FormData=class extends Map {constructor(){super([['ingresos_brutos_cop','1234567.89']]);}};
    let reply, sent;
    globalThis.fetch=(_url,options)=>{sent=JSON.parse(options.body);return new Promise(resolve=>{reply=()=>resolve({ok:true,status:200,json:async()=>({obligacion:{criterios:{}},liquidacion:{impuesto_cop:'0'}})});});};
    try {
        const factories={};registerRenta({data:(name,factory)=>{factories[name]=factory;}});
        const form={querySelectorAll:()=>[],querySelector:()=>null};
        const state={...factories.rentaCalculator(),$root:{dataset:{calculateUrl:'/renta/2025/calculadora/calcular'},querySelector:()=>form},$nextTick:()=>{}};
        const pending=state.calculate();
        assert.equal(state.calculating,true);
        assert.equal(sent.ingresos_brutos_cop,'1234567.89');
        assert.equal(Object.keys(sent).length,5);
        state.invalidate();reply();await pending;
        assert.equal(state.result,null);assert.equal(state.calculating,false);
    } finally {Object.assign(globalThis,previous);}
});
