<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\DeclaracionRenta;
use App\Models\DetalleCdt;
use App\Models\DetalleCesantias;
use App\Models\DetalleCuenta;
use App\Models\DetalleFic;
use App\Models\DetalleFpv;
use App\Models\DetalleRentaVariable;
use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\Renta\ConsolidadoRentaService;
use App\Services\Renta\EstimacionRentaService;
use App\Services\Renta\EvaluacionObligacionService;
use App\Services\Renta\InformacionComplementariaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDeclaracionRentaSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = Usuario::all();

        if ($usuarios->isEmpty()) {
            $this->command->warn('No hay usuarios en la base de datos.');
            return;
        }

        // Obtener categorías base
        $catSalario = Categoria::where('nombre', 'Salario Base')->first() 
            ?? Categoria::where('nombre', 'Salario')->first();
        $catConsultoria = Categoria::where('nombre', 'Consultorías')->first()
            ?? Categoria::where('nombre', 'Honorarios/Servicios')->first()
            ?? $catSalario;

        $catSupermercado = Categoria::where('nombre', 'Supermercado')->first()
            ?? Categoria::where('nombre', 'Alimentación')->first();
        $catVivienda = Categoria::where('nombre', 'Arriendo / Cuota hipotecaria')->first()
            ?? Categoria::where('nombre', 'Vivienda')->first();
        $catTarjetas = Categoria::where('nombre', 'Tarjetas de Crédito')->first()
            ?? Categoria::where('nombre', 'Obligaciones financieras')->first();

        $catPadreAlimentacion = Categoria::where('nombre', 'Alimentación')->first() ?? $catSupermercado;
        $catPadreVivienda = Categoria::where('nombre', 'Vivienda')->first() ?? $catVivienda;
        $catPadreFinanciera = Categoria::where('nombre', 'Obligaciones financieras')->first() ?? $catTarjetas;

        foreach ($usuarios as $usuario) {
            $this->command->info("Poblando datos de prueba para el usuario: {$usuario->correo}");

            // 1. Perfil Tributario
            $perfil = PerfilTributario::firstOrNew(['usuario_id' => $usuario->id]);
            $perfil->fecha_nacimiento = '1990-05-15';
            $perfil->ciudad_residencia = 'Bogotá D.C.';
            $perfil->telefono = '3001234567';
            $perfil->responsable_iva = false;
            $perfil->ultimos_digitos_nit = '37';
            $perfil->personas_a_cargo = 0;
            $perfil->ocupaciones = ['ASALARIADO', 'INVERSIONISTA'];
            $perfil->es_salario_integral = false;
            $perfil->vigente_desde = '2025-01-01';
            $perfil->vigente_hasta = null;
            $perfil->save();

            // 2. Fuentes de Ingreso
            $fuenteTrabajo = FuenteIngreso::firstOrCreate(
                [
                    'usuario_id' => $usuario->id,
                    'nombre' => 'Empresa Principal S.A.S.',
                ],
                [
                    'tipo_fuente_id' => $catSalario->id,
                    'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                ]
            );

            $fuenteCesantias = FuenteIngreso::firstOrCreate(
                [
                    'usuario_id' => $usuario->id,
                    'nombre' => 'Cesantías Anuales',
                ],
                [
                    'tipo_fuente_id' => $catSalario->id,
                    'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                ]
            );

            $fuenteNoLaboral = FuenteIngreso::firstOrCreate(
                [
                    'usuario_id' => $usuario->id,
                    'nombre' => 'Consultoría Freelance',
                ],
                [
                    'tipo_fuente_id' => $catConsultoria->id,
                    'clasificacion_tributaria' => 'RENTAS_NO_LABORALES',
                ]
            );

            // 3. Limpiar registros previos de prueba 2025 para idempotencia
            Ingreso::withoutGlobalScopes()->where('usuario_id', $usuario->id)->whereBetween('fecha_recepcion', ['2025-01-01', '2025-12-31'])->forceDelete();
            
            $inversionesIds = Inversion::withoutGlobalScopes()->where('usuario_id', $usuario->id)->pluck('id');
            MovimientoInversion::withoutGlobalScopes()->whereIn('inversion_id', $inversionesIds)->forceDelete();
            DetalleCdt::whereIn('inversion_id', $inversionesIds)->delete();
            DetalleCuenta::whereIn('inversion_id', $inversionesIds)->delete();
            DetalleFic::whereIn('inversion_id', $inversionesIds)->delete();
            DetalleRentaVariable::whereIn('inversion_id', $inversionesIds)->delete();
            DetalleFpv::whereIn('inversion_id', $inversionesIds)->delete();
            DetalleCesantias::whereIn('inversion_id', $inversionesIds)->delete();
            Inversion::withoutGlobalScopes()->where('usuario_id', $usuario->id)->forceDelete();

            DB::table('gastos')->where('usuario_id', $usuario->id)->whereBetween('fecha', ['2025-01-01', '2025-12-31'])->delete();

            // 4. Crear 12 Ingresos Laborales Mensuales (2025)
            // $10.000.000 mensual = $120.000.000 total.
            // Aportes salud/pensión: $800.000 mensual = $9.600.000 total.
            // Retención en la fuente laboral: $260.000 mensual = $3.120.000 total.
            for ($m = 1; $m <= 12; $m++) {
                $mesStr = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                $dia = ($m == 2) ? 28 : 30;

                Ingreso::create([
                    'usuario_id' => $usuario->id,
                    'fuente_id' => $fuenteTrabajo->id,
                    'fecha_recepcion' => "2025-{$mesStr}-{$dia}",
                    'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                    'moneda' => 'COP',
                    'monto_original' => 10000000.00,
                    'tasa_cambio_usada' => 1.0000,
                    'monto_bruto_cop' => 10000000.00,
                    'retencion_fuente_cop' => 260000.00,
                    'aportes_descontados_cop' => 800000.00,
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Salario mensual mes {$mesStr} de 2025",
                ]);
            }

            // Ingreso por Cesantías consignadas en febrero 2025 ($8.000.000)
            Ingreso::create([
                'usuario_id' => $usuario->id,
                'fuente_id' => $fuenteCesantias->id,
                'fecha_recepcion' => '2025-02-14',
                'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                'moneda' => 'COP',
                'monto_original' => 8000000.00,
                'tasa_cambio_usada' => 1.0000,
                'monto_bruto_cop' => 8000000.00,
                'retencion_fuente_cop' => 0.00,
                'aportes_descontados_cop' => 0.00,
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => 'Cesantías anuales causadas y consignadas',
            ]);

            // Ingreso No Laboral por Consultoría en junio 2025 ($2.000.000)
            Ingreso::create([
                'usuario_id' => $usuario->id,
                'fuente_id' => $fuenteNoLaboral->id,
                'fecha_recepcion' => '2025-06-15',
                'clasificacion_tributaria' => 'RENTAS_NO_LABORALES',
                'moneda' => 'COP',
                'monto_original' => 2000000.00,
                'tasa_cambio_usada' => 1.0000,
                'monto_bruto_cop' => 2000000.00,
                'retencion_fuente_cop' => 200000.00,
                'aportes_descontados_cop' => 200000.00,
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => 'Consultoría técnica especializada',
            ]);

            // 5. Inversiones y Rendimientos Financieros (6 Productos del ERS)

            // 5.1 CDT Bancolombia ($50M capital, $6M rendimiento, $240k retención 4%)
            $invCdt = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'CDT Tradicional Bancolombia',
                'tipo' => 'CDT',
                'entidad' => 'Bancolombia',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-01-15',
                'monto_inicial' => 50000000.00,
                'tasa_ea' => 12.0000,
                'plazo_dias' => 180,
                'fecha_vencimiento' => '2026-01-15',
                'estado' => 'ACTIVA',
            ]);
            DetalleCdt::create([
                'inversion_id' => $invCdt->id,
                'capital' => 50000000.00,
                'tasa_valor' => 12.0000,
                'tipo_tasa' => 'EA',
                'periodicidad_nominal' => 'SEMESTRAL',
                'plazo_dias' => 180,
                'fecha_vencimiento' => '2026-01-15',
                'periodicidad_pago' => 'SEMESTRAL',
                'es_cdat' => false,
                'base_dias' => 365,
            ]);
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invCdt->id,
                'tipo' => 'ABONO_INTERES',
                'fecha' => '2025-07-15',
                'monto' => 6000000.00,
                'monto_cop' => 6000000.00,
                'monto_bruto' => 6000000.00,
                'retencion_aplicada' => 240000.00, // 4% de $6M
                'nota' => 'Intereses causados semestre 1',
            ]);

            // 5.2 Cuenta de Alto Rendimiento ($30M saldo, $3.5M rendimientos, $245k retención 7%)
            $invCuenta = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'Cuenta de Ahorros Nu Colombia',
                'tipo' => 'CUENTA_ALTO_RENDIMIENTO',
                'entidad' => 'Nu Colombia',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-01-01',
                'monto_inicial' => 30000000.00,
                'tasa_ea' => 13.0000,
                'estado' => 'ACTIVA',
            ]);
            DetalleCuenta::create([
                'inversion_id' => $invCuenta->id,
                'saldo_inicial' => 30000000.00,
                'tasa_ea' => 13.0000,
                'es_cuenta_gmf_exenta' => true,
                'base_dias' => 365,
                'periodicidad_abono' => 'MENSUAL',
            ]);
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invCuenta->id,
                'tipo' => 'ABONO_INTERES',
                'fecha' => '2025-11-30',
                'monto' => 3500000.00,
                'monto_cop' => 3500000.00,
                'monto_bruto' => 3500000.00,
                'retencion_aplicada' => 245000.00, // 7% de $3.5M
                'nota' => 'Rendimientos acumulados del año',
            ]);

            // 5.3 FIC Fiducuenta Davivienda ($25M saldo, $2.5M rendimientos, $195k retención)
            $invFic = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'Fondo Abierto Fiducuenta Davivienda',
                'tipo' => 'FIC',
                'entidad' => 'Fiduciaria Davivienda',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-02-01',
                'monto_inicial' => 25000000.00,
                'estado' => 'ACTIVA',
            ]);
            $ficCatalogo = DB::table('fic_catalogo')->first();

            DetalleFic::create([
                'inversion_id' => $invFic->id,
                'fic_catalogo_id' => $ficCatalogo ? $ficCatalogo->id : null,
                'unidades' => 2500.00000000,
                'valor_unidad_compra' => 10000.00000000,
                'valor_unidad_actual' => 11000.00000000,
                'pacto_permanencia_dias' => 0,
                'comision_administracion_ea' => 1.50,
            ]);
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invFic->id,
                'tipo' => 'RENDIMIENTO',
                'fecha' => '2025-10-31',
                'monto' => 2500000.00,
                'monto_cop' => 2500000.00,
                'monto_bruto' => 2500000.00,
                'retencion_aplicada' => 195000.00,
                'nota' => 'Rendimientos trimestrales fondo conservador',
            ]);

            // 5.4 Acciones Ecopetrol BVC ($20M saldo, Venta con $2.5M utilidad exenta Art. 36-1)
            $invAccion = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'Acciones Ordinarias Ecopetrol S.A.',
                'tipo' => 'ACCION_ETF',
                'entidad' => 'Trii Comisionista BVC',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-02-10',
                'monto_inicial' => 20000000.00,
                'unidades_iniciales' => 8000.00000000,
                'precio_unitario_compra' => 2500.00000000,
                'simbolo' => 'ECOPETROL',
                'estado' => 'ACTIVA',
            ]);
            DetalleRentaVariable::create([
                'inversion_id' => $invAccion->id,
                'simbolo' => 'ECOPETROL',
                'nombre_instrumento' => 'Ecopetrol S.A.',
                'tipo' => 'ACCION',
                'mercado' => 'BVC',
                'esta_inscrito_bvc' => true,
                'unidades_total' => 8000.00000000,
                'costo_promedio_unitario' => 2500.00000000,
                'fecha_primera_compra' => '2025-02-10',
                'tipo_reparto' => 'DISTRIBUTIVO',
                'valor_actual_unidad' => 2800.00000000,
            ]);
            // Venta parcial < 2 años con utilidad de $2.500.000
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invAccion->id,
                'tipo' => 'VENTA',
                'fecha' => '2025-09-15',
                'unidades' => 1000.00000000,
                'monto' => 5000000.00,
                'monto_cop' => 5000000.00,
                'monto_bruto' => 5000000.00,
                'retencion_aplicada' => 0.00,
                'nota' => 'Venta en BVC exenta de renta según Art. 36-1 E.T.',
            ]);

            // 5.5 Fondo Voluntario de Pensión (FPV) Skandia ($45M saldo, $15M aporte 2025)
            $invFpv = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'Fondo Voluntario de Pensión Skandia Multiplus',
                'tipo' => 'FPV',
                'entidad' => 'Skandia Pensiones y Cesantías',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-03-01',
                'monto_inicial' => 45000000.00,
                'estado' => 'ACTIVA',
            ]);
            DetalleFpv::create([
                'inversion_id' => $invFpv->id,
                'administradora' => 'Skandia',
                'tipo_vinculacion' => 'DIRECTO',
                'saldo_actual' => 45000000.00,
                'unidades' => 4500.00000000,
                'valor_unidad' => 10000.00000000,
            ]);
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invFpv->id,
                'tipo' => 'APORTE',
                'fecha' => '2025-03-01',
                'monto' => 15000000.00,
                'monto_cop' => 15000000.00,
                'monto_bruto' => 15000000.00,
                'retencion_aplicada' => 0.00,
                'nota' => 'Aporte voluntario para optimización tributaria Art. 126-1 E.T.',
            ]);

            // 5.6 Cesantías Protección ($45M saldo, $8.5M aporte 2025)
            $invCesantias = Inversion::create([
                'usuario_id' => $usuario->id,
                'nombre' => 'Fondo de Cesantías Protección',
                'tipo' => 'CESANTIAS',
                'entidad' => 'Protección S.A.',
                'moneda' => 'COP',
                'fecha_apertura' => '2025-02-14',
                'monto_inicial' => 45000000.00,
                'estado' => 'ACTIVA',
            ]);
            DetalleCesantias::create([
                'inversion_id' => $invCesantias->id,
                'fondo' => 'Protección',
                'regimen' => 'ANUALIZADO',
                'saldo' => 45000000.00,
                'tasa_interes_esperada' => 8.5000,
                'plazo_dias' => 365,
            ]);
            MovimientoInversion::create([
                'usuario_id' => $usuario->id,
                'inversion_id' => $invCesantias->id,
                'tipo' => 'APORTE',
                'fecha' => '2025-02-14',
                'monto' => 8500000.00,
                'monto_cop' => 8500000.00,
                'monto_bruto' => 8500000.00,
                'retencion_aplicada' => 0.00,
                'nota' => 'Consignación patronal de cesantías e intereses',
            ]);

            // 6. Gastos de Ejemplo en 2025
            for ($mes = 1; $mes <= 12; $mes++) {
                $mesPadded = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);

                // Alimentación
                DB::table('gastos')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'usuario_id' => $usuario->id,
                    'categoria_id' => $catPadreAlimentacion->id,
                    'subcategoria_id' => $catSupermercado->id,
                    'fecha' => "2025-{$mesPadded}-10",
                    'moneda' => 'COP',
                    'monto_original' => 1800000.00,
                    'tasa_cambio_usada' => 1.0000,
                    'monto_cop' => 1800000.00,
                    'naturaleza' => 'VARIABLE',
                    'necesidad' => 'ESENCIAL',
                    'medio_pago' => 'TARJETA_DEBITO',
                    'relevancia_tributaria' => 'NO_DEDUCIBLE',
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Mercado quincenal mes {$mesPadded}",
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ]);

                // Pago cuota tarjeta de crédito
                DB::table('gastos')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'usuario_id' => $usuario->id,
                    'categoria_id' => $catPadreFinanciera->id,
                    'subcategoria_id' => $catTarjetas->id,
                    'fecha' => "2025-{$mesPadded}-20",
                    'moneda' => 'COP',
                    'monto_original' => 2500000.00,
                    'tasa_cambio_usada' => 1.0000,
                    'monto_cop' => 2500000.00,
                    'naturaleza' => 'VARIABLE',
                    'necesidad' => 'ESENCIAL',
                    'medio_pago' => 'TRANSFERENCIA',
                    'relevancia_tributaria' => 'NO_DEDUCIBLE',
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Pago tarjeta de crédito mes {$mesPadded}",
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ]);
            }

            // 7. Consolidar e Inicializar Declaración de Renta 2025
            $complementariaService = app(InformacionComplementariaService::class);
            $evaluacionService = app(EvaluacionObligacionService::class);
            $estimacionService = app(EstimacionRentaService::class);

            $declaracion = $complementariaService->obtenerDeclaracion($usuario, 2025, true);

            // Guardar Información complementaria idéntica al escenario oficial de mockups:
            // C3 (Tarjeta): $60.000.000
            // C4 (Compras): $66.500.000 (cerca del 95%)
            // C5 (Consignaciones): $152.000.000 (confirmado)
            $complementariaService->guardarInformacionComplementaria($usuario, 2025, [
                'c3_consumos_tarjeta_cop' => 60000000.00,
                'c4_compras_totales_cop' => 66500000.00,
                'c5_consignaciones_declaradas_cop' => 152000000.00,
                'c5_confirmado' => true,
            ]);

            // Ejecutar evaluación y estimación
            $evaluacionService->evaluar($usuario, 2025);
            $estimacionService->estimar($usuario, 2025);

            $this->command->info("✓ Usuario {$usuario->correo} configurado exitosamente con escenario de renta 2025.");
        }
    }
}
