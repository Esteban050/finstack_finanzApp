<?php

namespace App\Http\Controllers;

use App\Models\Inversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\ValoracionInversion;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use App\Services\InversionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — RF-039: Valor de mercado de una inversión en una fecha y su
 * histórico. La valoración más reciente es el valor actual (RN-039.3).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class ValoracionInversionController extends Controller
{
    /**
     * RF-039 / CA-039.2: Histórico de valoraciones, de la más antigua a la más
     * reciente, con la serie para el gráfico de línea por fecha. No se pagina:
     * es la serie completa del gráfico.
     */
    public function index(string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $valoraciones = ValoracionInversion::where('inversion_id', $inversion->id)
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'mensaje' => 'Valoraciones consultadas.',
            'data' => [
                'moneda' => $inversion->moneda,
                'valoraciones' => $valoraciones,
                'serie' => $valoraciones->map(fn (ValoracionInversion $valoracion) => [
                    'fecha' => $valoracion->fecha->toDateString(),
                    'valor' => $valoracion->valor,
                ])->values(),
                'valoracion_desactualizada' => InversionService::valoracionDesactualizada(
                    $inversion,
                    InversionService::valorActual($inversion),
                    CarbonImmutable::today()
                ),
            ],
        ]);
    }

    /**
     * RN-039.1 / RN-039.2: Registrar el valor de mercado en una fecha. Se da
     * de una de tres formas: el valor total; el precio unitario, que se
     * multiplica por las unidades a esa fecha (inversiones con unidades); o
     * aceptando el valor causado (CDT y bonos). Otra valoración en la misma
     * fecha la reemplaza.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $validated = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$inversion->fecha_apertura->toDateString()],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'precio_unitario' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,16}(\.\d{1,8})?$/'],
            'usar_valor_causado' => ['nullable', 'boolean'],
        ], $this->mensajes());

        if ($inversion->estaLiquidada()) {
            throw ValidationException::withMessages([
                'inversion' => 'La inversión está liquidada: su valor actual es 0 (RN-038.3) y no admite valoraciones.',
            ]);
        }

        $fecha = CarbonImmutable::parse($validated['fecha']);
        $atributos = $this->atributosValoracion($inversion, $validated, $fecha);

        $valoracion = DB::transaction(function () use ($inversion, $fecha, $atributos) {
            $anterior = ValoracionInversion::where('inversion_id', $inversion->id)
                ->where('fecha', $fecha->toDateString())
                ->first()
                ?->toArray();

            $valoracion = ValoracionInversion::updateOrCreate(
                ['inversion_id' => $inversion->id, 'fecha' => $fecha->toDateString()],
                array_merge($atributos, ['usuario_id' => Auth::id()])
            );

            BitacoraService::registrar(
                accion: 'REGISTRAR_VALORACION_INVERSION',
                entidadAfectada: 'valoraciones_inversion',
                entidadId: $valoracion->id,
                valorAnterior: $anterior,
                valorNuevo: $valoracion->fresh()->toArray(),
                justificacion: $anterior === null
                    ? "RF-039: valoración de la inversión {$inversion->id}."
                    : "RF-039: valoración de la inversión {$inversion->id} reemplazada en la misma fecha.",
                usuarioId: Auth::id()
            );

            return $valoracion->fresh();
        });

        return response()->json([
            'mensaje' => 'Valoración registrada.',
            'data' => [
                'valoracion' => $valoracion,
                'inversion' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
            ],
        ], 201);
    }

    /**
     * RNG-07: Eliminar una valoración registrada por el Usuario. La inicial y
     * la de la liquidación las crea el Sistema y no se eliminan.
     */
    public function destroy(string $id, string $valoracionId): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $valoracion = ValoracionInversion::where('inversion_id', $inversion->id)
            ->whereKey($valoracionId)
            ->first();

        if ($valoracion === null) {
            abort(404);
        }

        if (in_array($valoracion->origen, ['INICIAL', 'LIQUIDACION'], true)) {
            throw ValidationException::withMessages([
                'valoracion' => 'Esta valoración la creó el Sistema (monto inicial o liquidación total) y no se elimina.',
            ]);
        }

        DB::transaction(function () use ($valoracion) {
            $anterior = $valoracion->toArray();
            $valoracion->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_VALORACION_INVERSION',
                entidadAfectada: 'valoraciones_inversion',
                entidadId: $valoracion->id,
                valorAnterior: $anterior,
                justificacion: 'RF-039: eliminación de una valoración registrada por el Usuario.',
                usuarioId: Auth::id()
            );
        });

        return response()->json([
            'mensaje' => 'Valoración eliminada.',
            'data' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
        ]);
    }

    /**
     * RN-039.2 / CA-039.1: Valor causado propuesto a una fecha (por defecto,
     * hoy) para CDT y bonos.
     */
    public function valorCausado(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $validated = $request->validate([
            'fecha' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.$inversion->fecha_apertura->toDateString()],
        ], $this->mensajes());

        $this->exigirTasa($inversion);

        $fecha = CarbonImmutable::parse($validated['fecha'] ?? CarbonImmutable::today());

        return response()->json([
            'mensaje' => 'Valor causado calculado. Puede aceptarlo o registrar otro valor.',
            'data' => array_merge(InversionService::valorCausado($inversion, $fecha), [
                'moneda' => $inversion->moneda,
            ]),
        ]);
    }

    /**
     * RN-039.1 / RN-039.2: valor y origen de la valoración según la forma en
     * que se dio.
     *
     * @throws ValidationException
     */
    private function atributosValoracion(Inversion $inversion, array $validated, CarbonImmutable $fecha): array
    {
        if ((bool) ($validated['usar_valor_causado'] ?? false)) {
            $this->exigirTasa($inversion);

            return [
                'valor' => InversionService::valorCausado($inversion, $fecha)['valor_causado'],
                'precio_unitario' => null,
                'unidades' => null,
                'origen' => 'CAUSADO',
            ];
        }

        if (isset($validated['precio_unitario'])) {
            if (! $inversion->tieneUnidades()) {
                throw ValidationException::withMessages([
                    'precio_unitario' => 'El precio unitario solo aplica a Acciones, ETF y Criptoactivos (RN-039.1). Indique el valor total.',
                ]);
            }

            $unidades = InversionService::unidadesActuales($inversion, $fecha);
            $valor = IngresoService::redondear(bcmul((string) $validated['precio_unitario'], $unidades, 10));

            if (! IngresoService::montoEnRango($valor) && bccomp($valor, '0', 2) !== 0) {
                throw ValidationException::withMessages([
                    'precio_unitario' => 'El valor resultante supera el máximo permitido de 999.999.999.999,99.',
                ]);
            }

            return [
                'valor' => $valor,
                'precio_unitario' => (string) $validated['precio_unitario'],
                'unidades' => $unidades,
                'origen' => 'MANUAL',
            ];
        }

        if (! isset($validated['valor'])) {
            throw ValidationException::withMessages([
                'valor' => 'Indique el valor total de la inversión en la fecha (RN-039.1).',
            ]);
        }

        return [
            'valor' => (string) $validated['valor'],
            'precio_unitario' => null,
            'unidades' => null,
            'origen' => 'MANUAL',
        ];
    }

    /** @throws ValidationException */
    private function exigirTasa(Inversion $inversion): void
    {
        if (! $inversion->tieneTasa()) {
            throw ValidationException::withMessages([
                'inversion' => 'El valor causado solo se propone para CDT y Bonos/TES (RN-039.2).',
            ]);
        }
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function inversionPropia(string $id): Inversion
    {
        $inversion = Inversion::find($id);

        if ($inversion !== null) {
            return $inversion;
        }

        $esDeOtro = Inversion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'inversiones',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    private function mensajes(): array
    {
        return [
            'fecha.required' => 'Indique la fecha de valoración.',
            'fecha.date' => 'La fecha no tiene un formato válido (AAAA-MM-DD).',
            'fecha.before_or_equal' => 'La fecha de valoración no puede ser posterior a hoy (RN-039.1).',
            'fecha.after_or_equal' => 'La fecha de valoración no puede ser anterior a la apertura de la inversión.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo (RN-039.1).',
            'valor.max' => 'El valor no puede superar 999.999.999.999,99.',
            'valor.regex' => 'El valor admite hasta 12 dígitos enteros y 2 decimales.',
            'precio_unitario.gt' => 'El precio unitario debe ser mayor que cero.',
            'precio_unitario.regex' => 'El precio unitario admite hasta 8 decimales.',
            'usar_valor_causado.boolean' => 'La marca de valor causado debe ser verdadero o falso.',
        ];
    }
}
