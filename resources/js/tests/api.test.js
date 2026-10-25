import test from 'node:test';
import assert from 'node:assert/strict';
import { safeMessage, validResourcePath, contractPayload, request } from '../api/index.js';

test('RNF-012: los mensajes técnicos no salen hacia la UI', () => {
    for (const message of ['SQLSTATE 42000', 'Exception: host secreto', 'trace at example.php', null]) assert.equal(safeMessage(message), 'No fue posible completar la operación. Intenta nuevamente.');
    assert.equal(safeMessage('El monto debe ser mayor que $ 0.'), 'El monto debe ser mayor que $ 0.');
});

test('QA-004: claves de validación se sustituyen por un mensaje de campo',()=>{assert.equal(safeMessage('validation.digits'),'Revisa este campo');assert.equal(safeMessage('auth.failed'),'Revisa este campo');});

test('QA-009: UUID inválido no origina petición y payload excluye extras/porcentajes',()=>{
    assert.equal(validResourcePath('/ingresos/no-es-uuid/editar'),false);
    assert.equal(validResourcePath('/gastos/categorias/01a17221-d619-7218-9004-a1a7575ff8cb'),true);
    assert.deepEqual(contractPayload('/ingresos','POST',{monto_original:'10.00',retencion_fuente:'3.00',porcentaje_retencion:'30',usuario_id:'extra',moneda:'XXX'}),{monto_original:'10.00',retencion_fuente:'3.00',moneda:'COP'});
    assert.equal(safeMessage('select nombre from usuarios'),'No fue posible completar la operación. Intenta nuevamente.');
});
test('QA-009: respuesta 500 no filtra message/trace y 419 exige recarga',async()=>{
    const previous={window:globalThis.window,document:globalThis.document,fetch:globalThis.fetch};
    globalThis.window={location:{origin:'http://127.0.0.1:8000',assign:()=>{}}};
    globalThis.document={body:{dataset:{loginUrl:'/login'}},querySelector:()=>({content:'test'})};
    try {
        for(const status of [500,419,403,404,422]) {
            globalThis.fetch=async()=>({status,ok:false,redirected:false,json:async()=>({message:'SQLSTATE select secreto',trace:['secreto'],error_id:'QA-123',errors:{telefono:['validation.digits']}})});
            await assert.rejects(request('/ingresos'),error=> !/SQLSTATE|secreto/.test(error.message) && (status!==422 || error.errors.telefono[0]==='Revisa este campo') && (status!==419 || /Recarga/.test(error.message)));
        }
    } finally {Object.assign(globalThis,previous);}
});
