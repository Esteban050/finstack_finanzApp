<?php

namespace App\Http\Controllers;

use App\Models\ParametroNormativo;
use App\Services\Renta\CalculadoraRentaService;
use App\Services\Renta\CalendarioVencimientoService;
use App\Services\Renta\ConsolidadoRentaService;
use App\Services\Renta\EstimacionRentaService;
use App\Services\Renta\EvaluacionObligacionService;
use App\Services\Renta\InformacionComplementariaService;
use App\Services\RentaAnioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class DeclaracionRentaController extends Controller
{
    public function __construct(
        protected ConsolidadoRentaService $consolidadoService,
        protected InformacionComplementariaService $complementariaService,
        protected EvaluacionObligacionService $evaluacionService,
        protected EstimacionRentaService $estimacionService,
        protected CalendarioVencimientoService $vencimientoService,
        protected CalculadoraRentaService $calculadoraService
    ) {}

    /**
     * Entrada principal al módulo de Renta. Redirige al año relevante: el año en curso con registros (H27), o el último con datos.
     */
    public function index(Request $request, ?int $anio = null): RedirectResponse
    {
        $anio = $anio ?? (int) $request->query('anio', RentaAnioService::porDefecto((string) auth()->id()));

        return redirect()->route('renta.consolidado', ['anio' => $anio]);
    }

    /**
     * Pantalla 01: Consolidado y evaluación del año gravable · RF-043 & RF-045
     */
    public function consolidado(Request $request, int $anio): View
    {
        $usuario = auth()->user();
        $consolidado = $this->consolidadoService->consolidar($usuario, $anio);
        // Se sincroniza la declaración con lo registrado (ingresos, inversiones, perfil) antes de evaluar.
        $declaracion = $this->complementariaService->obtenerDeclaracion($usuario, $anio, true);
        $evaluacion = $this->evaluacionService->evaluar($usuario, $anio);
        $estimacion = $this->estimacionService->estimar($usuario, $anio);
        $parametros = $consolidado['parametros'];

        return view('renta.consolidado', compact('usuario', 'anio', 'consolidado', 'declaracion', 'evaluacion', 'estimacion', 'parametros'));
    }

    /**
     * Pantalla 02: Información complementaria y pre-llenado de inversiones · RF-044
     */
    public function complementaria(Request $request, int $anio): View
    {
        $usuario = auth()->user();
        $declaracion = $this->complementariaService->obtenerDeclaracion($usuario, $anio, true);
        $consolidado = $declaracion->datos_consolidado_cache ?? $this->consolidadoService->consolidar($usuario, $anio);
        $ajustes = $declaracion->ajustes;

        return view('renta.complementaria', compact('usuario', 'anio', 'declaracion', 'consolidado', 'ajustes'));
    }

    /**
     * Procesa y valida el formulario de información complementaria y ajustes manuales (RF-044).
     */
    public function guardarComplementaria(Request $request, int $anio): RedirectResponse
    {
        $usuario = auth()->user();

        $validated = $request->validate([
            'c1_ajuste_manual_cop' => ['nullable', 'numeric'],
            'c1_inmuebles_valor_fiscal_cop' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'c2_ajuste_manual_cop' => ['nullable', 'numeric'],
            'justificacion_ajuste' => ['nullable', 'string', 'max:500'],
            'c3_consumos_tarjeta_cop' => ['nullable', 'numeric', 'min:0'],
            'c4_compras_totales_cop' => ['nullable', 'numeric', 'min:0'],
            'c5_consignaciones_declaradas_cop' => ['nullable', 'numeric', 'min:0'],
            'c5_confirmado' => ['nullable', 'boolean'],
            'accion' => ['nullable', 'string'],
        ], [
            'c1_inmuebles_valor_fiscal_cop.min' => 'El valor fiscal de los inmuebles no puede ser negativo.',
            'c1_inmuebles_valor_fiscal_cop.max' => 'El valor fiscal de los inmuebles no puede superar $ 999.999.999.999,99.',
        ]);

        $this->complementariaService->guardarInformacionComplementaria($usuario, $anio, $validated);

        if ($request->input('accion') === 'guardar_y_evaluar') {
            return redirect()->route('renta.consolidado', ['anio' => $anio])
                ->with('status', 'Información complementaria actualizada y evaluación ejecutada con éxito.');
        }

        return redirect()->route('renta.complementaria', ['anio' => $anio])
            ->with('status', 'Información complementaria guardada correctamente.');
    }

    /**
     * Pantalla 03 & 04: Evaluación de la obligación de declarar renta · RF-045
     * (Integrada en la vista unificada de Consolidado y Evaluación)
     */
    public function evaluacion(Request $request, int $anio): View
    {
        return $this->consolidado($request, $anio);
    }

    /**
     * Pantalla 05: Estimación del impuesto (Cédula General) · RF-046
     */
    public function estimacion(Request $request, int $anio): View
    {
        $usuario = auth()->user();
        $declaracion = $this->complementariaService->obtenerDeclaracion($usuario, $anio, true);
        $estimacion = $this->estimacionService->estimar($usuario, $anio);

        return view('renta.estimacion', compact('usuario', 'anio', 'estimacion', 'declaracion'));
    }

    /**
     * Pantalla 06: Vencimiento y recordatorios · RF-047
     */
    public function vencimiento(Request $request, int $anio): View
    {
        $usuario = auth()->user();
        $this->complementariaService->obtenerDeclaracion($usuario, $anio, true);
        $vencimiento = $this->vencimientoService->obtenerVencimientoYRecordatorios($usuario, $anio);
        $declaracion = $vencimiento['declaracion'];

        return view('renta.vencimiento', compact('usuario', 'anio', 'vencimiento', 'declaracion'));
    }

    /**
     * Acción "Marcar declaración como presentada" · RF-047 RN-047.2
     */
    public function marcarPresentada(Request $request, int $anio): RedirectResponse
    {
        $usuario = auth()->user();

        $validated = $request->validate([
            'fecha_presentacion' => ['required', 'date'],
            'numero_radicado_dian' => ['nullable', 'string', 'max:100'],
        ], [
            'fecha_presentacion.required' => 'La fecha de presentación es obligatoria.',
            'fecha_presentacion.date' => 'La fecha de presentación debe ser una fecha válida.',
        ]);

        $this->vencimientoService->marcarComoPresentada(
            $usuario,
            $anio,
            $validated['fecha_presentacion'],
            $validated['numero_radicado_dian'] ?? null
        );

        return redirect()->route('renta.vencimiento', ['anio' => $anio])
            ->with('status', 'La declaración ha sido marcada como presentada y los recordatorios pendientes han sido cancelados.');
    }

    /**
     * Pantalla 07: Descarga / visualización de la constancia de evaluación en PDF · RN-045.6
     */
    public function descargarPdf(Request $request, int $anio): Response
    {
        $usuario = auth()->user();
        $this->complementariaService->obtenerDeclaracion($usuario, $anio, true);
        $evaluacion = $this->evaluacionService->evaluar($usuario, $anio);
        $estimacion = $this->estimacionService->estimar($usuario, $anio);
        $declaracion = $evaluacion['declaracion'];
        $parametros = $evaluacion['parametros'];

        // Si se solicita formato vista previa HTML o si no se fuerza descarga PDF
        if ($request->query('preview') === '1' || $request->query('html') === '1') {
            return response()->view('renta.pdf.constancia', compact('usuario', 'anio', 'evaluacion', 'estimacion', 'declaracion', 'parametros'));
        }

        try {
            $pdf = Pdf::loadView('renta.pdf.constancia', compact('usuario', 'anio', 'evaluacion', 'estimacion', 'declaracion', 'parametros'))
                ->setPaper('a4', 'portrait');

            return $pdf->download("constancia_evaluacion_renta_{$anio}_{$usuario->numero_documento}.pdf");
        } catch (\Throwable $e) {
            // Fallback a vista imprimible si hay algún problema con DomPDF
            return response()->view('renta.pdf.constancia', compact('usuario', 'anio', 'evaluacion', 'estimacion', 'declaracion', 'parametros'));
        }
    }

    /**
     * Pantalla: Calculadora y simulador de Declaración de Renta
     */
    public function calculadora(Request $request, int $anio): View
    {
        $usuario = auth()->user();
        $vigencias = ParametroNormativo::where('es_activa', true)
            ->orderByDesc('anio_vigencia')->pluck('anio_vigencia')->map(fn ($year) => (int) $year)->all();
        $sinVigencia = ! in_array($anio, $vigencias, true);

        return view('renta.calculadora', compact('usuario', 'anio', 'vigencias', 'sinVigencia'));
    }

    /**
     * Endpoint API/AJAX para ejecutar la simulación de cálculo
     */
    public function calcularSimulacion(Request $request, int $anio): JsonResponse
    {
        $validated = validator($request->all(), [
            'patrimonio_bruto_cop' => ['nullable', 'numeric', 'min:0'],
            'ingresos_brutos_cop' => ['nullable', 'numeric', 'min:0'],
            'consumos_tarjeta_cop' => ['nullable', 'numeric', 'min:0'],
            'compras_totales_cop' => ['nullable', 'numeric', 'min:0'],
            'consignaciones_cop' => ['nullable', 'numeric', 'min:0'],
            'ingresos_trabajo_cop' => ['nullable', 'numeric', 'min:0'],
            'ingresos_capital_cop' => ['nullable', 'numeric', 'min:0'],
            'ingresos_no_laborales_cop' => ['nullable', 'numeric', 'min:0'],
            'cesantias_cop' => ['nullable', 'numeric', 'min:0'],
            'salud_pension_cop' => ['nullable', 'numeric', 'min:0'],
            'componente_inflacionario_cop' => ['nullable', 'numeric', 'min:0'],
            'fpv_afc_cop' => ['nullable', 'numeric', 'min:0'],
            'cesantias_exentas_cop' => ['nullable', 'numeric', 'min:0'],
            'retenciones_fuente_cop' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        try {
            $resultado = $this->calculadoraService->calcularCompleto($validated, $anio);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }
}
