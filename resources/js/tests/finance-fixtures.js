import { execFileSync } from 'node:child_process';

// Renderizado de presentación: modelos nuevos sin persistir y cualquier consulta bloqueada.
export function renderFinanceFixtures() {
    return JSON.parse(execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::connection()->beforeExecuting(function () { throw new RuntimeException('Las fixtures no pueden consultar la base de datos.'); });
$app['request']->setLaravelSession($app['session']->driver());
$app['view']->share('errors', new Illuminate\Support\ViewErrorBag());
$profile = new App\Models\PerfilTributario(['es_salario_integral'=>false]);
$inv = new App\Models\Inversion(['nombre'=>'QA-Inversión ficticia','tipo'=>'CDT','moneda'=>'COP','estado'=>'ACTIVA','nivel_riesgo'=>'BAJO','fecha_apertura'=>'2025-01-01','monto_inicial'=>'10000000.00','tasa_ea'=>'10','plazo_dias'=>365,'fecha_vencimiento'=>'2026-01-01']);
$inv->id='00000000-0000-4000-8000-000000000001';
foreach(['detalleCdt','detalleCuenta','detalleRentaVariable','detalleFic','detalleFpv','detalleCesantias','entidadFinanciera'] as $relation)$inv->setRelation($relation,null);
$inv->setRelation('movimientos',collect([]));
$val = new App\Models\ValoracionInversion(['fecha'=>'2026-01-01','valor'=>'11000000.00']);$val->id='00000000-0000-4000-8000-000000000002';
$mov = new App\Models\MovimientoInversion(['tipo'=>'APORTE','fecha'=>'2025-01-01','monto_cop'=>'10000000.00','es_inicial'=>true]);$mov->id='00000000-0000-4000-8000-000000000003';
$liq = new App\Models\LiquidacionSeguridadSocial(['periodo'=>'2025-03','origen_ingreso'=>'REGISTRADO','ingreso_base'=>'8000000.00','ibc'=>'3200000.00','salud'=>'400000.00','pension'=>'512000.00','fsp'=>'0.00','arl'=>'16800.00','total'=>'928800.00','parametros_usados'=>['anio_vigencia'=>2025,'valor_smmlv'=>'1423500.00']]);
$paginator = fn($rows)=>new Illuminate\Pagination\LengthAwarePaginator(collect($rows),count($rows),20);
$resumen=['salud'=>'400000.00','pension'=>'512000.00','ingreso_no_constitutivo_pila'=>'912000.00'];
$presentacion=['id'=>$inv->id,'nombre'=>$inv->nombre,'tipo'=>'CDT','estado'=>'ACTIVA','moneda'=>'COP','monto_inicial'=>'10000000.00','valor_actual'=>'11000000.00','fecha_ultima_valoracion'=>'2026-01-01','valoracion_desactualizada'=>true,'unidades_actuales'=>null];
$ind=['total_aportes'=>'10000000.00','total_retiros'=>'0.00','valor_actual'=>'11000000.00','capital_aportado_neto'=>'10000000.00','ganancia'=>'1000000.00','roi'=>'10.00','xirr'=>'10.00','rentabilidad_real'=>'4.76','mensajes'=>[]];
$payment=['id'=>'00000000-0000-4000-8000-000000000004','periodo'=>'2025-03','fecha_pago'=>'2025-04-08','numero_planilla'=>'QA-123','es_correccion'=>false,'salud'=>'400000.00','pension'=>'512000.00','fsp'=>'0','arl'=>'16800.00','intereses_mora'=>'0','total'=>'928800.00','gasto'=>['medio_pago'=>'TRANSFERENCIA'],'soporte'=>null,'comparacion'=>['hay_diferencias'=>true,'mensaje'=>'Diferencia ficticia frente a la liquidación.','diferencias'=>[['concepto'=>'salud','diferencia'=>'-1200.00']]]];
$sim = new App\Models\SimulacionInversion(['nombre'=>'QA-Simulación ficticia','alternativas'=>[['nombre'=>'QA-CDT','monto_inicial'=>'10000000.00','tasa_ea'=>'12','plazo_meses'=>12]]]);$sim->id='00000000-0000-4000-8000-000000000005';$sim->creado_en='2025-01-01';
$detail=['id'=>$inv->id,'nombre'=>$inv->nombre,'tipo'=>'CDT','nivel_riesgo'=>'BAJO','valor_actual_cop'=>'11000000.00','porcentaje_portafolio'=>'100.00','roi'=>'10.00','xirr'=>'10.00','fecha_ultima_valoracion'=>'2026-01-01','valoracion_desactualizada'=>true,'estado'=>'ACTIVA','moneda'=>'COP'];
$portafolio=['valor_total'=>'11000000.00','capital_aportado_neto'=>'10000000.00','ganancia'=>'1000000.00','roi'=>'10.00','xirr'=>'10.00','distribucion'=>['por_tipo'=>[['clave'=>'CDT','porcentaje'=>'100.00','valor'=>'11000000.00']],'por_riesgo'=>[['clave'=>'BAJO','porcentaje'=>'100.00','valor'=>'11000000.00']]],'detalle'=>[$detail]];
$cases = [
 'seguridad-social.index'=>['esDobleObligacion'=>false,'esVoluntario'=>true,'hayCalendario'=>true,'digitos'=>'37','periodos'=>[['periodo'=>'2025-03','fecha_limite'=>'2025-04-08','estado'=>'VENCIDO']],'resumenAnual'=>$resumen],
 'seguridad-social.liquidaciones.index'=>['liquidaciones'=>$paginator([$liq])],
 'seguridad-social.asalariado.index'=>['perfil'=>$profile,'ingresosSalario'=>collect([])],
 'seguridad-social.pagos.index'=>['resumenAnual'=>$resumen,'anioActual'=>2025,'pagos'=>$paginator([$payment]),'pagosPresentados'=>collect([$payment])],
 'inversiones.index'=>['tipos'=>App\Models\Inversion::TIPOS,'estados'=>App\Models\Inversion::ESTADOS,'inversiones'=>$paginator([$inv]),'inversionesPresentadas'=>collect([$presentacion]),'entidades'=>collect([]),'fics'=>collect([]),'perfilTributario'=>null,'portafoliosPorProducto'=>[],'rangosRiesgo'=>[]],
 'inversiones.show'=>['inversion'=>$inv,'presentacion'=>$presentacion,'indicadores'=>$ind,'valoraciones'=>collect([$val]),'valoracionesCronologicas'=>collect([$val]),'movimientos'=>collect([$mov]),'valorCausadoPropuesto'=>['fecha'=>'2025-07-02','valor_causado'=>'10486719.00']],
 'inversiones.portafolio'=>['portafolio'=>$portafolio,'fechaCorte'=>'2026-01-01'],
 'inversiones.simulaciones'=>['simulaciones'=>$paginator([$sim]),'maximo'=>20],
];
$output=[];
foreach($cases as $name=>$data){
 $source=file_get_contents(resource_path('views/'.str_replace('.','/',$name).'.blade.php'));
 $source=preg_replace('/@extends\([^\n]*\)\s*|@section\(\x27title\x27[^\n]*\)\s*|@section\(\x27content\x27\)\s*|@endsection/','',$source);
 $output[$name]=Illuminate\Support\Facades\Blade::render($source,$data,true).view('components.ui.confirm-dialog')->render().view('components.ui.toast',['message'=>null])->render();
}
echo json_encode($output,JSON_THROW_ON_ERROR);
`], {encoding: 'utf8', maxBuffer: 8e6}));
}
