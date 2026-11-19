import { execFileSync } from 'node:child_process';
import test from 'node:test';
import assert from 'node:assert/strict';
import { budgetPayload, budgetState, pesos, notificationTarget } from '../planning/rules.js';
import { contractPayload, validResourcePath } from '../api/index.js';
import { validateReportDates, reportDate, signedMoney, comparisonPercent } from '../reports/index.js';
import { socialResult } from '../social/index.js';
const parent='00000000-0000-4000-8000-000000000001',child='00000000-0000-4000-8000-000000000002',categories=[{id:parent,nombre:'QA-Padre'},{id:child,nombre:'QA-Hija',categoria_padre_id:parent}];
test('G: payload decimal exacto, opcionales fuera del contrato y umbrales de ejecución',()=>{
 const result=budgetPayload([{categoria_id:parent,modo_asignacion:'PESOS',valor:'999.999.999.999'}],categories,'10',false,true);assert.deepEqual(result.errors,{});assert.equal(result.payload.lineas[0].monto_asignado,'999999999999');
 assert.equal(budgetState('79.99').label,'En control');assert.equal(budgetState('80').label,'En riesgo');assert.equal(budgetState('100').label,'Excedido');assert.equal(budgetState(null).label,'No calculable');
 for(const invalid of ['0','-1','0,01','1e5','1.000.000.000.000'])assert.throws(()=>pesos(invalid));
 const picked=contractPayload('/presupuestos/2026-11','PUT',{...result.payload,usuario_id:parent,lineas:[{...result.payload.lineas[0],gastado:'100',porcentaje_ingreso:'25'}]});assert.equal(picked.usuario_id,undefined);assert.equal(picked.lineas[0].gastado,undefined);assert.equal(picked.lineas[0].porcentaje_ingreso,undefined);
});
test('G: no permite duplicados, superposición padre/hija ni porcentajes sin base',()=>{
 const row={categoria_id:parent,modo_asignacion:'PESOS',valor:'10'};assert.ok(budgetPayload([row,row],categories,'1',false,false).errors['lineas.1.categoria_id']);assert.ok(budgetPayload([row,{...row,categoria_id:child}],categories,'1',false,false).errors['lineas.1.categoria_id']);
 const pct={...row,modo_asignacion:'PORC_INGRESO',valor:'19,50'};assert.equal(budgetPayload([pct],categories,'100',true,false).payload.lineas[0].porcentaje_ingreso,'19.50');assert.ok(budgetPayload([pct],categories,'0',false,false).errors['lineas.0.valor']);for(const invalid of ['0','100,01','-1','1.5','1,555'])assert.ok(budgetPayload([{...pct,valor:invalid}],categories,'100',false,false).errors['lineas.0.valor']);
});
test('G: UUID y destinos de notificaciones válidos; seguridad no se puede desactivar',()=>{
 assert.equal(validResourcePath('/presupuestos/2026-11/lineas/'+parent),true);assert.equal(validResourcePath('/presupuestos/2026-13/base'),false);assert.equal(validResourcePath('/notificaciones/no-uuid/leida'),false);assert.equal(validResourcePath('/notificaciones/'+parent+'/leida'),true);
 assert.equal(notificationTarget({datos:{mes:'2026-11',linea_id:parent}}),'/presupuestos/2026-11/lineas/'+parent);assert.equal(notificationTarget({datos:{mes:'2026-99',linea_id:parent}}),null);
 const payload=contractPayload('/perfil/alertas','PUT',{reglas:[{tipo:'SEGURIDAD_CUENTA',activa:false},{tipo:'UMBRAL_LINEA',activa:true,parametros:{umbrales:[80,100],usuario_id:parent}}],resumen:{frecuencia:'NINGUNA',bloques:['ALERTAS'],usuario_id:parent}});assert.equal(payload.reglas.length,1);assert.equal(payload.reglas[0].parametros.usuario_id,undefined);assert.equal(payload.resumen.usuario_id,undefined);
});
test('H: fechas civiles, rango invertido y equivalencia de periodos',()=>{
 assert.ok(validateReportDates({fecha_desde:'2026-02-30'}).fecha_desde);assert.ok(validateReportDates({fecha_desde:'2026-11-19',fecha_hasta:'2026-11-01'}).fecha_desde);assert.deepEqual(validateReportDates({desde_a:'2026-10-01',hasta_a:'2026-10-31',desde_b:'2026-11-01',hasta_b:'2026-11-30'}),{});assert.ok(validateReportDates({desde_a:'2026-10-01',hasta_a:'2026-10-31',desde_b:'2026-11-01',hasta_b:'2026-11-10'}).desde_b);
});
test('C: resultados visibles conservan los valores autoritativos y total de descuentos exacto',()=>{const r=socialResult({salario:'5000000.00',salario_neto_estimado:'4450000.00',total_aportes:'400000.00',retencion_fuente:'150000.00',salud:'200000.00'});assert.equal(r.totalDescuentos,'550000.00');assert.equal(r.salarioNeto,'4450000.00');assert.equal(r.salud,'200000.00');assert.equal(r.totalPagar,'400000.00');});

test('G: acepta UUID del catálogo global igual que Laravel sin restringir versión',()=>{const id='40000000-0000-0000-0001-000000000002';const result=budgetPayload([{categoria_id:id,modo_asignacion:'PESOS',valor:'100'}],[{id,nombre:'Alimentación'}],'100',false,false);assert.deepEqual(result.errors,{});assert.equal(validResourcePath('/gastos/categorias/'+id),true);});

test('G/H: las ocho configuraciones de vistas y el PDF con indicador compuesto compilan sin consultar la BD',()=>{
 const output=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['-r',String.raw`
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->beforeExecuting(function(){throw new RuntimeException('No consultar BD en fixtures.');});
$app['request']->setLaravelSession($app['session']->driver());$app['view']->share('errors',new Illuminate\Support\ViewErrorBag());
$out=[];foreach(['budgets','alerts','notifications','dashboard','indicators','cash','matrix','compare'] as $page)$out[$page]=view('planning._workspace',['workspacePage'=>$page])->render();
$user=new App\Models\Usuario(['correo'=>'qa@example.test']);
$out['pdf']=view('reportes.pdf.indicadores',['usuario'=>$user,'reporte'=>['mes'=>'2026-11','aviso_legal'=>'Ejemplo QA','indicadores'=>[['nombre'=>'Regla 50 / 30 / 20','valor'=>['esenciales'=>50,'discrecionales'=>30,'ahorro'=>20]]]]])->render();
echo json_encode($out,JSON_THROW_ON_ERROR);
`],{encoding:'utf8',maxBuffer:4e6}));
 assert.equal(Object.keys(output).length,9);for(const html of Object.values(output))assert.doesNotMatch(html,/<\/?x-|\{\{|@(?:if|foreach|include)\(/);assert.match(output.pdf,/Esenciales: 50 %/);assert.match(output.budgets,/data-financial-workspace/);
});

test('H: los límites UTC serializados vuelven a la fecha de Bogotá sin correr el fin de mes',()=>{assert.equal(reportDate('2026-11-01T04:59:59.999999Z'),'2026-10-31');assert.equal(reportDate('2026-10-01T05:00:00.000000Z'),'2026-10-01');assert.equal(reportDate('2026-10-31'),'2026-10-31');});
test('H: variaciones conservan signo y N/A, incluso cuando ambos periodos valen cero',()=>{assert.equal(signedMoney('200000'),'+ $ 200.000');assert.equal(comparisonPercent({variacion_porc:null,variacion_porc_texto:'N/A'}),'N/A');assert.equal(comparisonPercent({variacion_porc:null,variacion_porc_texto:'0,00%'}),'0,00%');assert.equal(comparisonPercent({variacion_porc:'20'}),'+ 20,00 %');});
