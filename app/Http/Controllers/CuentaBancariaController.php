<?php

namespace App\Http\Controllers;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Services\BitacoraService;
use App\Services\ConsumoTarjetaService;
use App\Services\SaldoCuentaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Cuentas de débito/ahorro y tarjetas de crédito registradas desde Mi perfil.
 * La tarjeta es un crédito TARJETA_CREDITO (MOD-08): aquí solo se registra con
 * su banco y su cupo; sus compras y pagos siguen en gastos y créditos.
 */
class CuentaBancariaController extends Controller
{
    /** Registra una cuenta de débito o ahorro. */
    public function storeCuenta(Request $request): RedirectResponse
    {
        $this->normalizarBanco($request);
        $validated = $request->validateWithBag('cuenta', [
            'entidad_financiera_id' => ['required_without:banco_otro', 'nullable', 'uuid', Rule::exists('entidad_financiera', 'id')],
            'banco_otro' => ['required_without:entidad_financiera_id', 'nullable', 'string', 'max:100'],
            'alias' => ['nullable', 'string', 'max:80'],
            'saldo_inicial' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'es_exenta_gmf' => ['nullable', 'boolean'],
        ], [
            'entidad_financiera_id.required_without' => 'Elige el banco de la cuenta o escribe el nombre en "Otro banco".',
            'banco_otro.required_without' => 'Escribe el nombre del banco.',
            'banco_otro.max' => 'El nombre del banco no puede superar los 100 caracteres.',
            'entidad_financiera_id.exists' => 'Elige un banco de la lista.',
            'saldo_inicial.required' => 'Indica el monto o saldo inicial de la cuenta.',
            'saldo_inicial.numeric' => 'El saldo inicial debe ser un número.',
            'saldo_inicial.min' => 'El saldo inicial no puede ser negativo.',
            'saldo_inicial.regex' => 'El saldo inicial admite hasta 13 dígitos y 2 decimales.',
        ]);

        $cuenta = CuentaBancaria::create([
            'usuario_id' => Auth::id(),
            'entidad_financiera_id' => $validated['entidad_financiera_id'] ?? null,
            'banco_otro' => $validated['banco_otro'] ?? null,
            'alias' => isset($validated['alias']) && trim($validated['alias']) !== '' ? trim($validated['alias']) : null,
            'saldo_inicial' => $validated['saldo_inicial'],
            'es_exenta_gmf' => (bool) ($validated['es_exenta_gmf'] ?? false),
        ]);

        BitacoraService::registrar(
            accion: 'REGISTRAR_CUENTA_BANCARIA',
            entidadAfectada: 'cuentas_bancarias',
            entidadId: $cuenta->id,
            valorNuevo: $cuenta->fresh()->toArray(),
            justificacion: 'Registro de una cuenta de débito/ahorro desde Mi perfil.',
            usuarioId: Auth::id()
        );

        return redirect()->route('profile.show')->with('status', 'Cuenta registrada.');
    }

    /** Eliminación lógica de una cuenta; sus movimientos conservan el vínculo. */
    public function destroyCuenta(string $id): RedirectResponse
    {
        $cuenta = CuentaBancaria::findOrFail($id);
        $anterior = $cuenta->toArray();
        $cuenta->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_CUENTA_BANCARIA',
            entidadAfectada: 'cuentas_bancarias',
            entidadId: $cuenta->id,
            valorAnterior: $anterior,
            justificacion: 'Eliminación lógica de una cuenta de débito/ahorro desde Mi perfil.',
            usuarioId: Auth::id()
        );

        return redirect()->route('profile.show')->with('status', 'Cuenta eliminada.');
    }

    /**
     * Registra una tarjeta de crédito (banco y cupo aprobado) sin compras: nace con
     * capital 0 y una cuota. La tasa E.A. es opcional (0 por defecto) y se corrige
     * desde Créditos; el cupo disponible es cupo menos el saldo de capital.
     */
    public function storeTarjeta(Request $request): RedirectResponse
    {
        $this->normalizarBanco($request);
        $validated = $request->validateWithBag('tarjeta', [
            'entidad_financiera_id' => ['required_without:banco_otro', 'nullable', 'uuid', Rule::exists('entidad_financiera', 'id')],
            // creditos.banco admite 80 caracteres.
            'banco_otro' => ['required_without:entidad_financiera_id', 'nullable', 'string', 'max:80'],
            'cupo' => ['required', 'regex:/^\d{1,12}$/', 'not_regex:/^0+$/'],
            'tasa_interes_anual' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,4}(\.\d{1,6})?$/'],
        ], [
            'entidad_financiera_id.required_without' => 'Elige el banco de la tarjeta o escribe el nombre en "Otro banco".',
            'banco_otro.required_without' => 'Escribe el nombre del banco.',
            'banco_otro.max' => 'El nombre del banco no puede superar los 80 caracteres.',
            'entidad_financiera_id.exists' => 'Elige un banco de la lista.',
            'cupo.required' => 'Indica el cupo aprobado de la tarjeta.',
            'cupo.regex' => 'El cupo va en pesos enteros, con hasta 12 dígitos.',
            'cupo.not_regex' => 'El cupo debe ser mayor que $ 0.',
            'tasa_interes_anual.numeric' => 'La tasa E.A. debe ser un número.',
            'tasa_interes_anual.regex' => 'La tasa E.A. admite hasta 4 enteros y 6 decimales.',
        ]);

        $banco = isset($validated['entidad_financiera_id'])
            ? EntidadFinanciera::findOrFail($validated['entidad_financiera_id'])->nombre
            : $validated['banco_otro'];
        $creditos = app(CreditoController::class);
        $datos = $creditos->validarDatos([
            'tipo_credito' => 'TARJETA_CREDITO',
            'banco' => $banco,
            'proposito' => 'Tarjeta '.$banco,
            'tipo_tasa' => 'EA',
            'tasa' => (string) ($validated['tasa_interes_anual'] ?? '0'),
            'numero_cuotas' => 1,
            'periodicidad' => 'MENSUAL',
            'fecha_inicio' => CarbonImmutable::today()->toDateString(),
            'cupo' => $validated['cupo'],
        ], true, false);
        $datos['monto'] = '0';

        $creditos->registrar($datos, Auth::id(), [], 'Tarjeta de crédito registrada desde Mi perfil.');

        return redirect()->route('profile.show')->with('status', 'Tarjeta registrada.');
    }

    /**
     * "Otro banco": el select envía OTRO en lugar de un id. Exactamente uno de los dos
     * identifica el banco: con un banco de la lista se descarta el texto libre.
     */
    private function normalizarBanco(Request $request): void
    {
        $id = $request->input('entidad_financiera_id');
        $otro = trim((string) $request->input('banco_otro', ''));
        if ($id === 'OTRO' || $id === null || $id === '') {
            $request->merge(['entidad_financiera_id' => null, 'banco_otro' => $otro === '' ? null : $otro]);
        } else {
            $request->merge(['banco_otro' => null]);
        }
    }

    /** Datos para la sección "Cuentas y tarjetas" del perfil. */
    public static function paraPerfil(): array
    {
        $cuentas = CuentaBancaria::with('entidad:id,nombre')->orderBy('creado_en')->get();
        $saldos = SaldoCuentaService::saldos($cuentas->pluck('id')->all());
        $tarjetas = ConsumoTarjetaService::tarjetas(Auth::id())->orderBy('banco')->get(['id', 'banco', 'proposito', 'cupo']);

        return [
            'cuentas' => $cuentas->map(fn (CuentaBancaria $c) => [
                'id' => $c->id,
                'banco' => $c->nombre_banco,
                'alias' => $c->alias,
                'exenta' => $c->es_exenta_gmf,
                'saldo' => $saldos[$c->id] ?? '0.00',
            ]),
            'tarjetas' => $tarjetas,
            'bancos' => EntidadFinanciera::where('tipo', 'BANCO')->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
