import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalDecimal, nestedFinanceValues, validateFinance, parameterText, financeMessage } from '../finance/rules.js';
import { contractPayload, validResourcePath, request } from '../api/index.js';
import { renderFinanceFixtures } from './finance-fixtures.js';

test('avisos financieros conservan la explicación sin códigos ni paréntesis vacíos',()=>{
    assert.equal(financeMessage('XIRR no calculable: menos de 30 días (RN-040.2). Se muestra solo el ROI.'), 'XIRR no calculable: menos de 30 días. Se muestra solo el ROI.');
    assert.equal(financeMessage('Tasa efectiva anual (EA).'), 'Tasa efectiva anual (EA).');
    assert.equal(financeMessage('SQLSTATE select datos'), 'No fue posible completar la operación. Intenta nuevamente.');
});

test('C/D: decimales localizados conservan ocho decimales y límites de tasa y comisión',()=>{
    assert.equal(canonicalDecimal('0,00000001',{min:'0.00000001',precision:8}),'0.00000001');
    assert.equal(canonicalDecimal('-50',{min:'-50',max:'100',precision:4}),'-50');
    assert.equal(canonicalDecimal('100',{min:'-50',max:'100',precision:4}),'100');
    for(const value of ['-50,0001','100,0001','1e3','1.000','NaN']) assert.throws(()=>canonicalDecimal(value,{min:'-50',max:'100',precision:4}));
    assert.throws(()=>canonicalDecimal('10,0001',{max:'10',precision:4}));
});
test('C: los parámetros mantienen vigencia, precisión monetaria y texto del acto administrativo',()=>{
    assert.equal(parameterText('acto_administrativo','Res. DIAN 2026'),'Res. DIAN 2026');
    assert.equal(parameterText('anio_vigencia',2026),'2026');
    assert.equal(parameterText('valor_smmlv','1550000.00'),'$\u00a01.550.000');
    assert.equal(parameterText('tarifa_fsp','0.000'),'0,00');
    assert.equal(parameterText('pensionado',false),'No');
});
test('C/D: las alternativas mantienen el orden tras quitar/agregar y requieren capital o aportes',()=>{
    const parsed=nestedFinanceValues([['alternativas[0][nombre]','QA-A'],['alternativas[2][nombre]','QA-B'],['nombre','QA-Comparación']]);
    assert.deepEqual(parsed.alternativas,[{nombre:'QA-A'},{nombre:'QA-B'}]);
    assert.ok(validateFinance({alternativas:[{monto_inicial:'0',aporte_periodico:'0'}]},'simulation')['alternativas.0.monto_inicial']);
    assert.deepEqual(validateFinance({alternativas:[{monto_inicial:'0',aporte_periodico:'0.01'}]},'simulation'),{});
    assert.ok(validateFinance({alternativas:Array.from({length:5},()=>({monto_inicial:'1'}))},'simulation').alternativas);
});
test('C/D: payload permite solo campos reales y metas del contrato, COP fijo y sin datos autoritativos',()=>{
    assert.deepEqual(contractPayload('/inversiones','POST',{nombre:'QA-CDT',moneda:'XXX',monto_inicial:'0.01',usuario_id:'ajeno',rentabilidad_esperada_porc:'15',tasa_cambio_usada:'42'}),{nombre:'QA-CDT',moneda:'COP',monto_inicial:'0.01',rentabilidad_esperada_porc:'15'});
    assert.deepEqual(contractPayload('/seguridad-social/asalariado/aplicar','POST',{ingreso_id:'id',total_aportes:'1',salario:'1'}),{ingreso_id:'id'});
    assert.deepEqual(contractPayload('/inversiones/simulaciones','POST',{nombre:'QA-Sim',alternativas:[{nombre:'QA-A',monto_inicial:'1',valor_final_neto:'1000'}]}),{nombre:'QA-Sim',alternativas:[{nombre:'QA-A',monto_inicial:'1'}]});
    assert.equal(validResourcePath('/inversiones/no-uuid/movimientos'),false);
    assert.equal(validResourcePath('/seguridad-social/pagos/no-uuid/soporte'),false);
    assert.equal(validResourcePath('/inversiones/simulaciones/calcular'),true);
    assert.equal(validResourcePath('/inversiones/reportes/retenciones'),true);
    assert.equal(validResourcePath('/inversiones/reportes/utilidades'),true);
    assert.equal(validResourcePath('/inversiones/reportes/retenciones/exportar'),true);
    assert.equal(validResourcePath('/inversiones/reportes/no-existe'),false);
});
test('C/D: soporte multipart usa CSRF y omite archivos/campos fuera de contrato',async()=>{
    const previous={window:globalThis.window,document:globalThis.document,fetch:globalThis.fetch};
    globalThis.window={location:{origin:'http://127.0.0.1:8000'}};
    globalThis.document={querySelector:()=>({content:'qa-csrf'}),body:{dataset:{loginUrl:'/login'}}};
    try {
        globalThis.fetch=async(url,options)=>{assert.equal(options.headers['X-CSRF-TOKEN'],'qa-csrf');assert.equal(options.headers['Content-Type'],undefined);assert.equal(options.body.has('usuario_id'),false);assert.equal(options.body.get('salud'),'0.05');return {ok:true,status:201,json:async()=>({data:{}})};};
        const data=new FormData();data.set('salud','0.05');data.set('usuario_id','ajeno');
        await request('/seguridad-social/pagos',{method:'POST',data});
    } finally {Object.assign(globalThis,previous);}
});
test('C/D: ocho vistas Blade reales renderizan sin consultas ni escrituras en BD',()=>{
    const rendered=renderFinanceFixtures();assert.equal(Object.keys(rendered).length,8);
    for(const [name,html] of Object.entries(rendered)){
        assert.doesNotMatch(html,/<\/?x-|\{\{|@(?:props|if|foreach|include)\(/,name);
        assert.match(html,/Valores estimados con fines informativos/);
        const ids=[...html.replace(/<template[\s\S]*?<\/template>/g,'').matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);assert.equal(ids.length,new Set(ids).size,name+' IDs únicos');
        assert.doesNotMatch(html,/cdn\.jsdelivr|onsubmit=|\b(?:RF|RN|RNG|CA)-\d|<select[^>]*name="moneda"/);
    }
    assert.match(rendered['seguridad-social.pagos.index'],/data-sign-money="-1200.00"/);
    assert.match(rendered['inversiones.simulaciones'],/max="480"/);
    assert.match(rendered['inversiones.simulaciones'],/data-max="10"/);
    assert.match(rendered['inversiones.show'],/QA-Inversión ficticia/);
    assert.match(rendered['inversiones.show'],/id="cdtProjectionCanvas"/);
    assert.match(rendered['inversiones.show'],/Registrar Transacción/);
    assert.doesNotMatch(rendered['inversiones.show'],/id="val_valor"/); // CDT: cálculo causado, sin valoración manual.
});
