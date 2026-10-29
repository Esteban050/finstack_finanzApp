<?php

use App\Http\Controllers\Admin\AdminUsuariosController;
use App\Http\Controllers\Admin\CalendarioTributarioController;
use App\Http\Controllers\Admin\CategoriaCatalogoController;
use App\Http\Controllers\Admin\ParametroNormativoController;
use App\Http\Controllers\Admin\TarifaEscalonadaController;
use App\Http\Controllers\Admin\TrmController;
use App\Http\Controllers\FuenteIngresoController;
use App\Http\Controllers\GastoCategoriaController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\GastoRecurrenteController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\IngresoRecurrenteController;
use App\Http\Controllers\InversionController;
use App\Http\Controllers\MovimientoInversionController;
use App\Http\Controllers\PagoSeguridadSocialController;
use App\Http\Controllers\PoliticaTratamientoController;
use App\Http\Controllers\RentabilidadController;
use App\Http\Controllers\SeguridadSocialController;
use App\Http\Controllers\SimulacionInversionController;
use App\Http\Controllers\TasaCambioController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ValoracionInversionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas de Usuario Autenticado
Route::middleware(['auth', 'politica.check'])->group(function () {
    // RF-003 RN-003.5: Dashboard de Usuario
    Route::get('/dashboard', function () {
        $usuario = auth()->user();
        $perfil = $usuario->perfilTributarioActivo;
        return view('dashboard', compact('usuario', 'perfil'));
    })->name('dashboard');

    // RF-008: Perfil tributario y personal
    Route::get('/perfil', [UserProfileController::class, 'show'])->name('profile.show');
    Route::patch('/perfil', [UserProfileController::class, 'update'])->name('profile.update');

    // RF-012: MFA (Gestión desde el perfil)
    Route::post('/perfil/mfa/solicitar-codigo', [UserProfileController::class, 'requestMfaActivationCode'])->name('profile.mfa.code');
    Route::post('/perfil/mfa/toggle', [UserProfileController::class, 'toggleMfa'])->name('profile.mfa.toggle');

    // RF-011: Exportación de datos personales
    Route::get('/perfil/exportar', [UserProfileController::class, 'exportarDatos'])->name('profile.export');

    // RF-010: Solicitud de supresión de cuenta
    Route::post('/perfil/solicitar-supresion', [UserProfileController::class, 'solicitarSupresion'])->name('profile.delete.account');

    /*
    | MOD-03 — Gestión de ingresos (característica A)
    |
    | Las rutas con segmento fijo van antes de las paramétricas para que
    | /ingresos/fuentes y /ingresos/recurrentes no se confundan con un id.
    */

    // RF-020: Fuentes de ingreso
    Route::get('/ingresos/fuentes', [FuenteIngresoController::class, 'index'])->name('ingresos.fuentes.index');
    Route::post('/ingresos/fuentes', [FuenteIngresoController::class, 'store'])->name('ingresos.fuentes.store');
    Route::put('/ingresos/fuentes/{id}', [FuenteIngresoController::class, 'update'])->name('ingresos.fuentes.update');
    Route::patch('/ingresos/fuentes/{id}/toggle', [FuenteIngresoController::class, 'toggleActiva'])->name('ingresos.fuentes.toggle');
    Route::delete('/ingresos/fuentes/{id}', [FuenteIngresoController::class, 'destroy'])->name('ingresos.fuentes.destroy');

    // RF-022: Ingresos recurrentes
    Route::get('/ingresos/recurrentes', [IngresoRecurrenteController::class, 'index'])->name('ingresos.recurrentes.index');
    Route::post('/ingresos/recurrentes', [IngresoRecurrenteController::class, 'store'])->name('ingresos.recurrentes.store');
    Route::put('/ingresos/recurrentes/{id}', [IngresoRecurrenteController::class, 'update'])->name('ingresos.recurrentes.update');
    Route::patch('/ingresos/recurrentes/{id}/toggle', [IngresoRecurrenteController::class, 'toggleActiva'])->name('ingresos.recurrentes.toggle');
    Route::delete('/ingresos/recurrentes/{id}', [IngresoRecurrenteController::class, 'destroy'])->name('ingresos.recurrentes.destroy');

    // RF-024: Consulta y filtrado · RF-021: Registro · RF-023: Edición y eliminación
    Route::get('/ingresos', [IngresoController::class, 'index'])->name('ingresos.index');
    Route::get('/ingresos/crear', [IngresoController::class, 'create'])->name('ingresos.create');
    Route::post('/ingresos', [IngresoController::class, 'store'])->name('ingresos.store');
    Route::get('/ingresos/{id}/editar', [IngresoController::class, 'edit'])->name('ingresos.edit');
    Route::put('/ingresos/{id}', [IngresoController::class, 'update'])->name('ingresos.update');
    Route::delete('/ingresos/{id}', [IngresoController::class, 'destroy'])->name('ingresos.destroy');

    // RN-022.3: Confirmar una ocurrencia generada en modo "Requiere confirmación"
    Route::patch('/ingresos/{id}/confirmar', [IngresoController::class, 'confirmar'])->name('ingresos.confirmar');

    /*
    | MOD-04 — Gestión de gastos (característica B)
    |
    | Las rutas con segmento fijo van antes de las paramétricas para que
    | /gastos/categorias y /gastos/recurrentes no se confundan con un id.
    */

    // RF-027: Categorías y subcategorías personalizadas
    Route::get('/gastos/categorias', [GastoCategoriaController::class, 'index'])->name('gastos.categorias.index');
    Route::post('/gastos/categorias', [GastoCategoriaController::class, 'store'])->name('gastos.categorias.store');
    Route::put('/gastos/categorias/{id}', [GastoCategoriaController::class, 'update'])->name('gastos.categorias.update');
    Route::patch('/gastos/categorias/{id}/toggle', [GastoCategoriaController::class, 'toggleActiva'])->name('gastos.categorias.toggle');
    // RN-027.2 / CA-027.1: fusionar una categoría propia en otra
    Route::post('/gastos/categorias/{id}/fusionar', [GastoCategoriaController::class, 'fusionar'])->name('gastos.categorias.fusionar');
    Route::delete('/gastos/categorias/{id}', [GastoCategoriaController::class, 'destroy'])->name('gastos.categorias.destroy');

    // RF-028: Gastos recurrentes
    Route::get('/gastos/recurrentes', [GastoRecurrenteController::class, 'index'])->name('gastos.recurrentes.index');
    Route::post('/gastos/recurrentes', [GastoRecurrenteController::class, 'store'])->name('gastos.recurrentes.store');
    Route::put('/gastos/recurrentes/{id}', [GastoRecurrenteController::class, 'update'])->name('gastos.recurrentes.update');
    Route::patch('/gastos/recurrentes/{id}/toggle', [GastoRecurrenteController::class, 'toggleActiva'])->name('gastos.recurrentes.toggle');
    Route::delete('/gastos/recurrentes/{id}', [GastoRecurrenteController::class, 'destroy'])->name('gastos.recurrentes.destroy');

    // RN-025.2: acumulados anuales de consumos con tarjeta y de compras y consumos
    Route::get('/gastos/indicadores', [GastoController::class, 'indicadores'])->name('gastos.indicadores');
    // RN-026.1 (6): etiquetas ya usadas, para reutilizarlas
    Route::get('/gastos/etiquetas', [GastoController::class, 'etiquetas'])->name('gastos.etiquetas');
    // RN-029.3: edición masiva de hasta 100 gastos
    Route::patch('/gastos/edicion-masiva', [GastoController::class, 'edicionMasiva'])->name('gastos.edicion-masiva');

    // RF-029: Consulta y filtrado · RF-025: Registro · RF-026: Clasificación
    Route::get('/gastos', [GastoController::class, 'index'])->name('gastos.index');
    Route::get('/gastos/crear', [GastoController::class, 'create'])->name('gastos.create');
    Route::post('/gastos', [GastoController::class, 'store'])->name('gastos.store');
    Route::get('/gastos/{id}/editar', [GastoController::class, 'edit'])->name('gastos.edit');
    Route::put('/gastos/{id}', [GastoController::class, 'update'])->name('gastos.update');
    Route::delete('/gastos/{id}', [GastoController::class, 'destroy'])->name('gastos.destroy');

    // RN-028.2: Confirmar el monto real de una ocurrencia con monto estimado
    Route::patch('/gastos/{id}/confirmar', [GastoController::class, 'confirmar'])->name('gastos.confirmar');

    // RF-017: TRM vigente en una fecha y conversión COP ⇄ USD
    Route::get('/tasas-cambio/trm', [TasaCambioController::class, 'trm'])->name('tasas-cambio.trm');
    Route::get('/tasas-cambio/convertir', [TasaCambioController::class, 'convertir'])->name('tasas-cambio.convertir');

    /*
    | MOD-05 — Seguridad social (característica C)
    |
    | CA-008.1: sin el perfil de ocupación completo no se puede usar el módulo.
    */
    Route::middleware('perfil.completo')->prefix('seguridad-social')->name('seguridad-social.')->group(function () {
        // RF-033: liquidación del independiente o contratista (RN-033.7)
        Route::get('/liquidaciones', [SeguridadSocialController::class, 'liquidaciones'])->name('liquidaciones.index');
        Route::post('/liquidaciones', [SeguridadSocialController::class, 'liquidar'])->name('liquidaciones.store');
        // RN-033.1: simulación con un ingreso base ingresado a mano, sin guardar
        Route::post('/liquidaciones/simular', [SeguridadSocialController::class, 'simular'])->name('liquidaciones.simular');
        Route::get('/liquidaciones/{periodo}', [SeguridadSocialController::class, 'liquidacion'])->name('liquidaciones.show');

        // RF-034: aportes del asalariado y aplicación al ingreso (RN-034.5)
        Route::post('/asalariado/calcular', [SeguridadSocialController::class, 'calcularAsalariado'])->name('asalariado.calcular');
        Route::post('/asalariado/aplicar', [SeguridadSocialController::class, 'aplicarAsalariado'])->name('asalariado.aplicar');

        // RF-035: pagos por PILA, cada uno con su gasto (RN-035.3)
        Route::get('/pagos', [PagoSeguridadSocialController::class, 'index'])->name('pagos.index');
        Route::post('/pagos', [PagoSeguridadSocialController::class, 'store'])->name('pagos.store');
        Route::get('/pagos/{id}', [PagoSeguridadSocialController::class, 'show'])->name('pagos.show');
        Route::put('/pagos/{id}', [PagoSeguridadSocialController::class, 'update'])->name('pagos.update');
        Route::delete('/pagos/{id}', [PagoSeguridadSocialController::class, 'destroy'])->name('pagos.destroy');
        // RN-035.1: soporte del pago, con las reglas de RF-030
        Route::post('/pagos/{id}/soporte', [PagoSeguridadSocialController::class, 'subirSoporte'])->name('pagos.soporte.store');
        Route::delete('/pagos/{id}/soporte', [PagoSeguridadSocialController::class, 'eliminarSoporte'])->name('pagos.soporte.destroy');
        // RN-030.3: descarga solo con enlace firmado de 5 minutos
        Route::get('/pagos/{id}/soporte/descarga', [PagoSeguridadSocialController::class, 'descargarSoporte'])->middleware('signed')->name('pagos.soporte.descarga');
        // RN-035.4: aportes de salud y pensión del año (ingreso no constitutivo)
        Route::get('/resumen-anual', [PagoSeguridadSocialController::class, 'resumenAnual'])->name('resumen-anual');

        // RF-036: fechas límite de pago y alertas activas
        Route::get('/alertas', [SeguridadSocialController::class, 'alertas'])->name('alertas');
    });

    /*
    | MOD-06 — Inversiones y rentabilidad (característica D)
    |
    | Los {id} se restringen a UUID para que las rutas fijas del módulo
    | (portafolio, simulaciones) no se confundan con una inversión.
    */
    Route::prefix('inversiones')->name('inversiones.')->group(function () {
        // RF-041: consolidado del portafolio a una fecha de corte
        Route::get('/portafolio', [RentabilidadController::class, 'portafolio'])->name('portafolio');

        // RF-042: simulación de hasta 4 alternativas; se guardan hasta 20 (RN-042.4)
        Route::post('/simulaciones/calcular', [SimulacionInversionController::class, 'calcular'])->name('simulaciones.calcular');
        Route::get('/simulaciones', [SimulacionInversionController::class, 'index'])->name('simulaciones.index');
        Route::post('/simulaciones', [SimulacionInversionController::class, 'store'])->name('simulaciones.store');
        Route::get('/simulaciones/{id}', [SimulacionInversionController::class, 'show'])->whereUuid('id')->name('simulaciones.show');
        Route::put('/simulaciones/{id}', [SimulacionInversionController::class, 'update'])->whereUuid('id')->name('simulaciones.update');
        Route::delete('/simulaciones/{id}', [SimulacionInversionController::class, 'destroy'])->whereUuid('id')->name('simulaciones.destroy');

        // RF-037: registro de inversiones
        Route::get('/', [InversionController::class, 'index'])->name('index');
        Route::post('/', [InversionController::class, 'store'])->name('store');
        Route::get('/{id}', [InversionController::class, 'show'])->whereUuid('id')->name('show');
        Route::put('/{id}', [InversionController::class, 'update'])->whereUuid('id')->name('update');
        Route::delete('/{id}', [InversionController::class, 'destroy'])->whereUuid('id')->name('destroy');

        // RF-038: movimientos; un rendimiento crea su ingreso (RN-038.4)
        Route::get('/{id}/movimientos', [MovimientoInversionController::class, 'index'])->whereUuid('id')->name('movimientos.index');
        Route::post('/{id}/movimientos', [MovimientoInversionController::class, 'store'])->whereUuid('id')->name('movimientos.store');
        Route::delete('/{id}/movimientos/{movimientoId}', [MovimientoInversionController::class, 'destroy'])->whereUuid(['id', 'movimientoId'])->name('movimientos.destroy');

        // RF-039: valor de mercado e histórico; valor causado de CDT y bonos (RN-039.2)
        Route::get('/{id}/valoraciones', [ValoracionInversionController::class, 'index'])->whereUuid('id')->name('valoraciones.index');
        Route::post('/{id}/valoraciones', [ValoracionInversionController::class, 'store'])->whereUuid('id')->name('valoraciones.store');
        Route::delete('/{id}/valoraciones/{valoracionId}', [ValoracionInversionController::class, 'destroy'])->whereUuid(['id', 'valoracionId'])->name('valoraciones.destroy');
        Route::get('/{id}/valor-causado', [ValoracionInversionController::class, 'valorCausado'])->whereUuid('id')->name('valor-causado');

        // RF-040: rentabilidad a una fecha de corte
        Route::get('/{id}/rentabilidad', [RentabilidadController::class, 'inversion'])->whereUuid('id')->name('rentabilidad');
    });
});

// RF-009: Política de tratamiento de datos personales (Pública sin autenticación)
Route::get('/politica-datos', [PoliticaTratamientoController::class, 'mostrarPolitica'])->name('politica.mostrar');
Route::post('/politica-datos/aceptar', [PoliticaTratamientoController::class, 'aceptarPolitica'])->middleware('auth')->name('politica.aceptar');

// MOD-02: Rutas exclusivas de Administrador (CA-003.2, CA-013.3)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard de Administrador
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // RF-013: Parámetros normativos
    Route::get('/parametros', [ParametroNormativoController::class, 'index'])->name('parametros.index');
    Route::get('/parametros/crear', [ParametroNormativoController::class, 'create'])->name('parametros.create');
    Route::post('/parametros', [ParametroNormativoController::class, 'store'])->name('parametros.store');
    Route::get('/parametros/{id}/editar', [ParametroNormativoController::class, 'edit'])->name('parametros.edit');
    Route::put('/parametros/{id}', [ParametroNormativoController::class, 'update'])->name('parametros.update');

    // RF-014: Tablas de tarifas escalonadas
    Route::get('/parametros/{id}/tarifas', [TarifaEscalonadaController::class, 'index'])->name('tarifas.index');
    Route::post('/parametros/{id}/tarifas', [TarifaEscalonadaController::class, 'guardarTabla'])->name('tarifas.guardar');
    Route::post('/parametros/{id}/tarifas/probar', [TarifaEscalonadaController::class, 'probarCalculoRenta'])->name('tarifas.probar');

    // RF-015: Catálogo de categorías predeterminadas
    Route::get('/categorias', [CategoriaCatalogoController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaCatalogoController::class, 'store'])->name('categorias.store');
    Route::patch('/categorias/{id}/toggle', [CategoriaCatalogoController::class, 'toggleActiva'])->name('categorias.toggle');

    // RF-016: Calendario tributario y de aportes
    Route::get('/calendario', [CalendarioTributarioController::class, 'index'])->name('calendario.index');
    Route::post('/calendario/importar', [CalendarioTributarioController::class, 'importarCsv'])->name('calendario.importar');
    Route::get('/calendario/consultar-nit', [CalendarioTributarioController::class, 'consultarPorNit'])->name('calendario.consultar');

    // RF-017: TRM — histórico, registro o corrección manual (RN-017.3) y sincronización (RN-017.1)
    Route::get('/trm', [TrmController::class, 'index'])->name('trm.index');
    Route::post('/trm', [TrmController::class, 'store'])->name('trm.store');
    Route::post('/trm/sincronizar', [TrmController::class, 'sincronizar'])->name('trm.sincronizar');

    // RF-018: Gestión de cuentas de usuario
    Route::get('/usuarios', [AdminUsuariosController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios/crear-admin', [AdminUsuariosController::class, 'storeAdmin'])->name('usuarios.store-admin');
    Route::patch('/usuarios/{id}/estado', [AdminUsuariosController::class, 'cambiarEstado'])->name('usuarios.cambiar-estado');
    Route::post('/usuarios/{id}/desbloquear', [AdminUsuariosController::class, 'desbloquear'])->name('usuarios.desbloquear');
});

require __DIR__.'/auth.php';
