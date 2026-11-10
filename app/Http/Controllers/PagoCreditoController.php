<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\Gasto;
use App\Models\PagoCredito;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\PagoCreditoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-08 — Pagos de un crédito (documento de créditos, fase 2): registro con
 * imputación (Créditos RF-29..RF-32), abonos y prepago (RF-34..RF-37),
 * simulación previa (RF-36) y reverso del último pago (RF-33). Cada pago
 * genera su gasto en MOD-04.
 *
 * Solo responde JSON.
 */
class PagoCreditoController extends Controller
{
    /**
     * Créditos RF-31 / RF-33: pagos del crédito, del primero al último, con su
     * detalle de imputación; los reversados aparecen marcados.
     */
    public function index(string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $pagos = PagoCredito::where('credito_id', $credito->id)
            ->with('imputaciones.cuota')
            ->orderBy('numero')
            ->get();

        return response()->json([
            'mensaje' => 'Pagos consultados.',
            'data' => $pagos->map(fn (PagoCredito $p) => $this->presentar($p))->all(),
        ]);
    }

    /**
     * Créditos RF-36: muestra cómo se aplicaría el pago (imputación, plan
     * nuevo y ahorro de intereses) sin guardar nada.
     */
    public function simular(Request $request, string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $datos = $this->validar($request, $credito);

        return response()->json([
            'mensaje' => 'Pago simulado. No se guardó.',
            'data' => PagoCreditoService::imputar($credito, $datos),
        ]);
    }

    /**
     * Créditos RF-29..RF-32, RF-34, RF-35, RF-37: registra el pago. El crédito
     * se bloquea mientras se imputa, para que dos pagos simultáneos no se
     * apliquen sobre el mismo saldo.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);

        $pago = DB::transaction(function () use ($request, $credito) {
            $bloqueado = Credito::whereKey($credito->id)->lockForUpdate()->first();
            $datos = $this->validar($request, $bloqueado);

            return PagoCreditoService::registrar($bloqueado, $datos);
        });

        $credito = $credito->fresh();
        $situacion = PagoCreditoService::situacion($credito, CarbonImmutable::parse($pago->fecha->toDateString()));

        return response()->json([
            'mensaje' => 'Pago registrado.',
            'data' => [
                'pago' => $this->presentar($pago->load('imputaciones.cuota')),
                'estado_cuenta' => PagoCreditoService::estadoCuenta($credito, $situacion),
            ],
        ], 201);
    }

    /**
     * Créditos RF-33: reversa el último pago vigente, con un motivo.
     */
    public function reversar(Request $request, string $id, string $pagoId): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $validated = $request->validate(
            ['motivo' => ['required', 'string', 'min:10', 'max:200']],
            [
                'motivo.required' => 'Indique el motivo del reverso.',
                'motivo.min' => 'El motivo del reverso debe tener al menos 10 caracteres.',
                'motivo.max' => 'El motivo del reverso admite máximo 200 caracteres.',
            ]
        );

        $pago = PagoCredito::where('credito_id', $credito->id)->whereKey($pagoId)->first();
        if ($pago === null) {
            abort(404);
        }

        $pago = DB::transaction(function () use ($credito, $pago, $validated) {
            $bloqueado = Credito::whereKey($credito->id)->lockForUpdate()->first();

            return PagoCreditoService::reversar($bloqueado, $pago, trim($validated['motivo']));
        });

        return response()->json([
            'mensaje' => 'Pago reversado.',
            'data' => $this->presentar($pago->load('imputaciones.cuota')),
        ]);
    }

    /**
     * Créditos RF-29 / RF-32 / RF-34: datos del pago. La fecha va entre el
     * desembolso y hoy, y no puede ser anterior al último pago vigente: así la
     * imputación y la mora siempre se calculan sobre la historia completa.
     *
     * @throws ValidationException
     */
    private function validar(Request $request, Credito $credito): array
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'valor' => ['required', 'regex:/^\d{1,12}$/', 'not_regex:/^0+$/'],
            'medio' => ['required', Rule::in(Gasto::MEDIOS_PAGO)],
            'referencia' => ['nullable', 'string', 'max:60'],
            'aplicacion_excedente' => ['nullable', Rule::in(PagoCredito::APLICACIONES_EXCEDENTE)],
            'reducir' => ['nullable', 'prohibited_unless:aplicacion_excedente,ABONO_CAPITAL', Rule::in(PagoCredito::REDUCCIONES)],
        ], [
            'fecha.required' => 'Indique la fecha del pago.',
            'fecha.date_format' => 'La fecha del pago debe tener el formato AAAA-MM-DD.',
            'valor.required' => 'Indique el valor del pago.',
            'valor.regex' => 'El valor del pago va en pesos enteros, sin decimales ni separadores.',
            'valor.not_regex' => 'El valor del pago debe ser mayor que cero.',
            'medio.required' => 'Indique el medio de pago.',
            'medio.in' => 'El medio de pago debe ser efectivo, tarjeta débito, tarjeta de crédito, transferencia, billetera digital u otro.',
            'referencia.max' => 'La referencia admite máximo 60 caracteres.',
            'aplicacion_excedente.in' => 'El excedente se aplica como ANTICIPO o ABONO_CAPITAL.',
            'reducir.prohibited_unless' => 'Reducir plazo o cuota solo aplica a un abono a capital.',
            'reducir.in' => 'El abono a capital reduce el PLAZO o la CUOTA.',
        ]);

        $fecha = CarbonImmutable::parse($validated['fecha']);
        $desembolso = CarbonImmutable::parse($credito->fecha_inicio->toDateString());
        $ultimo = PagoCredito::where('credito_id', $credito->id)->vigentes()->max('fecha');

        $error = match (true) {
            $fecha->gt(CarbonImmutable::today()) => 'La fecha del pago no puede ser futura.',
            $fecha->lt($desembolso) => 'La fecha del pago no puede ser anterior al desembolso ('.$desembolso->format('d/m/Y').').',
            $ultimo !== null && $fecha->lt(CarbonImmutable::parse($ultimo)) => 'La fecha del pago no puede ser anterior al último pago vigente ('.CarbonImmutable::parse($ultimo)->format('d/m/Y').'). Si hay que registrar uno anterior, reverse primero los posteriores.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['fecha' => $error]);
        }

        if (isset($validated['referencia'])) {
            $validated['referencia'] = trim($validated['referencia']);
        }

        return $validated;
    }

    /** Créditos RF-31: el pago con su detalle de imputación y su resumen por concepto. */
    private function presentar(PagoCredito $pago): array
    {
        $imputaciones = $pago->imputaciones->map(fn ($i) => [
            'cuota_numero' => $i->cuota?->numero,
            'concepto' => $i->concepto,
            'valor' => PagoCreditoService::peso($i->valor),
        ])->all();

        return [
            'id' => $pago->id,
            'numero' => $pago->numero,
            'fecha' => $pago->fecha->toDateString(),
            'valor' => PagoCreditoService::peso($pago->valor),
            'medio' => $pago->medio,
            'referencia' => $pago->referencia,
            'aplicacion_excedente' => $pago->aplicacion_excedente,
            'reducir' => $pago->reducir,
            'version_plan_generada' => $pago->version_plan_generada,
            'gasto_id' => $pago->gasto_id,
            'gasto_intereses_id' => $pago->gasto_intereses_id,
            'reversado' => $pago->reversado_en !== null,
            'reversado_en' => $pago->reversado_en?->toIso8601String(),
            'motivo_reverso' => $pago->motivo_reverso,
            'imputaciones' => $imputaciones,
            'resumen' => PagoCreditoService::resumen($imputaciones),
        ];
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function creditoPropio(string $id): Credito
    {
        $credito = Credito::find($id);

        if ($credito !== null) {
            return $credito;
        }

        $esDeOtro = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'creditos',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
