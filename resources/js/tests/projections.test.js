import test from 'node:test';
import assert from 'node:assert/strict';
import { goalFromInput, dailyProjection, addMonthsCivil } from '../finance/projections.js';

test('meta en pesos o porcentaje usa decimales exactos y explicita redondeo del porcentaje persistible',()=>{
    assert.deepEqual(goalFromInput('10','percent','1000000.00'),{percent:'10.0000',gain:'100000.00',rounded:false});
    assert.deepEqual(goalFromInput('100.000','money','1000000.00'),{percent:'10.0000',gain:'100000.00',rounded:false});
    assert.equal(goalFromInput('0,01','money','999999999999.99').rounded,true);
    assert.throws(()=>goalFromInput('10001','percent','1000000'));
    assert.throws(()=>goalFromInput('NaN','percent','1000000'));
    assert.deepEqual(goalFromInput('','percent',''),{percent:'',gain:'',rounded:false});
});
test('fechas de meta respetan enero, febrero bisiesto y fin de mes',()=>{
    assert.equal(addMonthsCivil('2025-01-31',1),'2025-02-28');
    assert.equal(addMonthsCivil('2024-01-31',1),'2024-02-29');
    assert.equal(addMonthsCivil('2024-02-29',12),'2025-02-28');
});
test('comparación diaria concilia movimientos, no inventa cotizaciones y deja futuro real vacío',()=>{
    const data={opening:'2025-01-01',initial:'1000000',goalPercent:'10',months:12,rate:null,valuations:[{date:'2025-01-01',value:'1000000'},{date:'2026-01-01',value:'1100000'}],flows:[{date:'2025-01-01',type:'APORTE',amount:'1000000'},{date:'2025-06-01',type:'RENDIMIENTO',amount:'300000'},{date:'2025-06-01',type:'RETENCION',amount:'30000'},{date:'2025-06-01',type:'COMISION',amount:'1000'}]};
    const result=dailyProjection(data,'2026-01-02','2026-01-01');
    assert.equal(result.rows.length,367);
    assert.equal(result.earned,'369000.00');
    assert.equal(result.goal,'100000.00');assert.equal(result.remaining,'0.00');
    assert.equal(result.rows.at(-1).actual,null);assert.equal(result.rows.at(-1).expected,'100000.00');
    assert.equal(result.rows.filter(row=>row.valued).length,2);
    assert.throws(()=>dailyProjection(data,'2056-01-02','2026-01-01'));
});
test('proyección de CDT reproduce el valor causado de referencia sin float sobre dinero',()=>{
    const result=dailyProjection({opening:'2025-01-01',initial:'10000000',goalPercent:null,months:null,rate:'10',flows:[{date:'2025-01-01',type:'APORTE',amount:'10000000'}],valuations:[{date:'2025-01-01',value:'10000000'}]},'2025-07-02','2025-07-02');
    assert.equal(result.rows.at(-1).caused,'486719.23');
    assert.equal(result.goal,null);assert.equal(result.remaining,null);
});
