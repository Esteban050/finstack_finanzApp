import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { debtClassification, creditPayload, paymentPayload, recordPayload, planSummary, ratePercent, abonoComparison } from '../credits/rules.js';
import { contractPayload, validResourcePath } from '../api/index.js';

test('créditos: semáforo distingue fronteras y ausencia de ingresos',()=>{
    assert.equal(debtClassification(null).tone,'neutral');
    assert.equal(debtClassification('30.00').tone,'success');
    assert.equal(debtClassification('30.01').tone,'warning');
    assert.equal(debtClassification('40.00').tone,'warning');
    assert.equal(debtClassification('40.01').tone,'danger');
    assert.equal(debtClassification('37.50').label,'Amarillo · moderado');
});
test('créditos: tasas usan coma, precisión exacta y campos condicionales del contrato',()=>{
    const result=creditPayload({tipo_credito:'LIBRE_INVERSION',tipo_tasa:'MENSUAL',tasa:'1,123456',capitalizacion:'MENSUAL',valor_inmueble:'999',es_vis:true,plazo_meses:'12',numero_cuotas:'12'});
    assert.deepEqual(result.errors,{});assert.equal(result.payload.tasa,'1.123456');
    for(const key of ['capitalizacion','valor_inmueble','es_vis','numero_cuotas'])assert.equal(Object.hasOwn(result.payload,key),false);
    assert.ok(creditPayload({tasa:'1,1234567'}).errors.tasa);
    assert.ok(creditPayload({tasa:'1e3'}).errors.tasa);
    assert.equal(ratePercent('0.1956'),'19,56 %');
});
test('créditos: pagos excluyen extras y no envían reducir para anticipo',()=>{
    assert.equal(paymentPayload({valor:'1000000.00'}).valor,'1000000');
    assert.equal(paymentPayload({valor:'1000000.01'}).valor,'1000000.01');
    assert.equal(creditPayload({cargo_fijo:'8500.00'}).payload.cargo_fijo,'8500');
    assert.ok(creditPayload({cargo_fijo:'8500.01'}).errors.cargo_fijo);
    assert.deepEqual(paymentPayload({fecha:'2026-11-11',valor:'100',medio:'EFECTIVO',reducir:'PLAZO',aplicacion_excedente:'ANTICIPO',usuario_id:'ajeno',referencia:''}),{fecha:'2026-11-11',valor:'100',medio:'EFECTIVO',aplicacion_excedente:'ANTICIPO'});
    assert.deepEqual(contractPayload('/creditos/id/pagos','POST',{valor:'100',saldo_capital:'1',usuario_id:'otro',medio:'EFECTIVO'}),{valor:'100',medio:'EFECTIVO'});
    assert.deepEqual(contractPayload('/creditos','POST',{banco:'QA-Entidad',tasa:'1.5',saldo_actual:'100',cuotas_pagadas:'2',moneda:'USD'}),{banco:'QA-Entidad',tasa:'1.5'});
});
test('créditos: rutas de recursos y reversos requieren UUID válidos',()=>{
    const id='00000000-0000-4000-8000-000000000001';
    assert.equal(validResourcePath('/creditos/calcular'),true);
    assert.equal(validResourcePath('/creditos/endeudamiento'),true);
    assert.equal(validResourcePath('/creditos/no-es-uuid'),false);
    assert.equal(validResourcePath(`/creditos/${id}/pagos/${id}/reversar`),true);
    assert.equal(validResourcePath(`/creditos/${id}/pagos/no-es-uuid/reversar`),false);
    assert.equal(validResourcePath(`/creditos/${id}/desconocido`),false);
});
test('créditos: editar preserva las condiciones reales y el resumen usa importes recibidos',()=>{
    const result=recordPayload({banco:'QA',proposito:'QA-Crédito',monto:'999999999999.99',tipo_tasa:'MENSUAL',tasa_ingresada:'1.123456',tasa_mora_ea:'0.12345678',fecha_inicio:'2026-01-31T00:00:00.000Z',plazo_meses:12,numero_cuotas:12});
    assert.equal(result.tasa,'1.123456');assert.equal(result.tasa_mora,'12.345678');assert.equal(result.fecha_inicio,'2026-01-31');assert.equal(result.monto,'999999999999.99');assert.equal(Object.hasOwn(result,'numero_cuotas'),false);
    assert.deepEqual(planSummary({cuota:'2',total_intereses:'17'}),[['Primera cuota','$\u00a02'],['Total de intereses','$\u00a017']]);
});
test('créditos: servicio real reproduce francés, alemán, tasa cero y saldo final exacto sin BD',()=>{
    const data=JSON.parse(execFileSync('php',['-r',String.raw`
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->beforeExecuting(function(){throw new RuntimeException('No se permite consultar la BD en esta prueba.');});
$ea=App\Services\CreditoService::tasaEa('1','MENSUAL');
$base=['monto'=>'10000000','tasa_ea'=>$ea,'tasa_periodica'=>App\Services\CreditoService::tasaPeriodica($ea,'MENSUAL'),'periodicidad'=>'MENSUAL','numero_cuotas'=>12,'fecha_inicio'=>'2026-01-31','sistema_amortizacion'=>'FRANCES'];
echo json_encode(['frances'=>App\Services\CreditoService::calcular($base),'aleman'=>App\Services\CreditoService::calcular(array_merge($base,['sistema_amortizacion'=>'ALEMAN'])),'cero'=>App\Services\CreditoService::calcular(array_merge($base,['tasa_ea'=>'0','tasa_periodica'=>'0'])),'conversion'=>App\Services\CreditoService::porcentaje(App\Services\CreditoService::tasaEa('1.5','MENSUAL'))]);
`],{encoding:'utf8',maxBuffer:1e6}));
    assert.equal(data.frances.cuota,'888488');assert.equal(data.frances.total_capital,'10000000');assert.equal(data.frances.tabla.at(-1).saldo_final,'0');
    assert.equal(data.frances.tabla[0].fecha,'2026-02-28');assert.equal(data.aleman.tabla.at(-1).saldo_final,'0');assert.equal(data.aleman.total_capital,'10000000');assert.equal(data.cero.total_intereses,'0');assert.equal(data.cero.tabla.at(-1).saldo_final,'0');assert.match(data.conversion,/^19\.56/);
});
test('créditos: Blade real compila con CSRF y componentes compartidos sin consultar la BD',()=>{
    const html=execFileSync('php',['-r',String.raw`
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->beforeExecuting(function(){throw new RuntimeException('No se permite consultar la BD en esta prueba.');});
$app['request']->setLaravelSession($app['session']->driver());$app['view']->share('errors',new Illuminate\Support\ViewErrorBag());
$source=file_get_contents(resource_path('views/creditos/index.blade.php'));
$source=preg_replace('/@extends\([^\n]*\)\s*|@section\(\x27title\x27[^\n]*\)\s*|@section\(\x27content\x27\)\s*|@endsection/','',$source);
echo Illuminate\Support\Facades\Blade::render($source,['creditoId'=>null],true);
`],{encoding:'utf8',maxBuffer:2e6});
    assert.match(html,/data-credit-app/);assert.match(html,/name="_token"/);assert.match(html,/name="seguro_porc_mensual"/);assert.doesNotMatch(html,/<\/?x-|\{\{|<svg[^>]*onload/);
    const ids=[...html.replace(/<template[\s\S]*?<\/template>/g,'').matchAll(/\bid="([^"]+)"/g)].map(x=>x[1]);assert.equal(ids.length,new Set(ids).size);
});

test('créditos: comparación muestra solo cuotas futuras y suma strings del servidor',()=>{
 const result=abonoComparison([{fecha:'2026-11-01',cuota:'100',interes_pendiente:'10'},{fecha:'2026-12-01',cuota:'100',interes_pendiente:'20'},{fecha:'2027-01-01',cuota:'100',interes_pendiente:'15'}],{fecha:'2026-11-11',plan_nuevo:[{cuota:'90',interes:'18'},{cuota:'90',interes:'10'}],resumen:{INTERES_CORRIENTE:'2'}});
 assert.deepEqual(result,{cuotaAntes:'100',cuotaDespues:'90',cuotasAntes:2,cuotasDespues:2,interesesAntes:'35',interesesDespues:'28'});
});

test('lint: la deuda heredada es inmutable y no admite infracciones nuevas',async()=>{
 const {financialLintStatus}=await import('../../../scripts/legacy-financial-baseline.mjs');
 const {readFileSync}=await import('node:fs');
 const file='resources/views/renta/estimacion.blade.php',source=readFileSync(file,'utf8');
 assert.equal(financialLintStatus(file,source),'legacy');
 assert.equal(financialLintStatus(file,source+'\nconst nueva = parseFloat(1);'),'error');
 assert.equal(financialLintStatus('resources/views/creditos/nuevo.blade.php','const n=parseFloat(1);'),'error');
 assert.equal(financialLintStatus('resources/views/creditos/index.blade.php','const n=decimal("1");'),'clean');
 const config=readFileSync('tailwind.config.js','utf8');assert.doesNotMatch(config,/storage\/framework\/views/);
});
