// Único límite de acceso HTTP del nuevo frontend. Las URLs se entregan desde Blade mediante route().
// TODO(contrato: acceso raíz: '/' sigue siendo una vista pública. Redirigir según sesión requiere modificar rutas/middleware, fuera del alcance autorizado; no sustituirlo por una redirección de cliente.)
// TODO(contrato: autenticación: no existe ruta/documento de términos de uso ni aceptación/versionado separado. La casilla envía únicamente acepta_politica del contrato actual.)
// TODO(contrato: RN-006.2: no existe endpoint de renovación ni metadatos de expiración; no simular Continuar sesión con una petición arbitraria.)
// TODO(contrato: RN-002.2/RF-012: reenvíos sin metadatos de espera/cupos. La cuenta regresiva local ayuda a la UX; la autoridad de los límites sigue siendo el servidor.)
// TODO(contrato: RN-005: reset solo recibe token, no expone correo/documento. La exclusión de datos personales se valida en servidor, sin inventar campos para el formulario.)
// TODO(contrato: mensajes auth: login bloqueado no es genérico; registro duplicado, reutilización y mensajes MFA difieren en puntuación. Mantener los mensajes recibidos literalmente.)
// TODO(contrato: registro: apellidos no tiene validación de letras/espacios en servidor; PasswordPolicyRule también prohíbe la parte local del correo de longitud >= 3.)
// TODO(contrato: auth.verify-email y auth.confirm-password no tienen ruta de vista activa: la verificación redirige al login y la confirmación no está registrada.)
// TODO(contrato: RN-027.4: CategoriaGastoService todavía valida 16 colores antiguos y claves legacy. Actualizar su contrato a las 87 claves del kit y los 12 hex --cat-*; el front no traduce silenciosamente los valores elegidos al enviar.)
// TODO(contrato: RN-027: no existe operación de reorganización/reparentado ni campo de orden; no ofrecer arrastre que no se guarde.)
// TODO(contrato: RN-025: subcategoria_id sigue nullable en registro/recurrencia del backend; el front exige subcategoría activa y bloquea categorías sin hijas.)
// TODO(contrato: RN-025: no hay cotización por fecha; ENABLE_FOREIGN_CURRENCY permanece false sin tasas simuladas.)
// TODO(contrato: RN-028: solo activa y eliminación de plantilla, sin estado terminal separado; falta paginación de plantillas y recordatorios de RF-058.)
// TODO(contrato: un historial cronológico combinado necesita consulta, filtros, paginación y totales conjuntos; por ahora la página presenta los listados reales en pestañas.)
// TODO(contrato: derivar también en servidor ultimos_digitos_nit desde numero_documento; la vista ya lo envía automáticamente sin campo editable.)
export class ApiError extends Error {
    constructor(status, message, errors = {}) { super(message); this.status = status; this.errors = errors; }
}

export function safeMessage(value, fallback = 'No fue posible completar la operación. Intenta nuevamente.') {
    if (typeof value === 'string' && (/^validation\./i.test(value) || /^[a-z][a-z_-]*(?:\.[a-z_\d-]+)+$/.test(value))) return 'Revisa este campo';
    return typeof value === 'string' && value.length <= 400 && !/(SQL|SQLSTATE|trace|exception|stack|server|servidor|\.php\b|\bat\s+\w+\()/i.test(value) ? value : fallback;
}

export async function request(url, { method = 'GET', data, signal } = {}) {
    const target = new URL(url, window.location.origin);
    if (target.origin !== window.location.origin) throw new ApiError(0, 'No fue posible completar la operación.');
    let response;
    try {
        response = await fetch(target, {
            method, signal, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', ...(data ? { 'Content-Type': 'application/json' } : {}) },
            ...(data ? { body: JSON.stringify(data) } : {}),
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new ApiError(0, 'No fue posible conectar. Revisa tu conexión e intenta nuevamente.');
    }
    if (response.status === 401 || (response.redirected && new URL(response.url).pathname === new URL(document.body.dataset.loginUrl, window.location.origin).pathname)) {
        const login = new URL(document.body.dataset.loginUrl, window.location.origin);
        login.searchParams.set('sesion', 'expirada');
        window.location.assign(login);
        throw new ApiError(401, 'Tu sesión expiró por inactividad');
    }
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const messages = { 403: 'Acceso denegado', 404: 'Recurso no encontrado', 419: 'Tu sesión expiró por inactividad' };
        const errors = {};
        if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
            for (const [field, values] of Object.entries(payload.errors)) errors[field] = (Array.isArray(values) ? values : [values]).map((value) => safeMessage(value, 'Revisa la regla de validación de este campo.'));
        }
        throw new ApiError(response.status, messages[response.status] ?? (response.status === 422 ? 'Revisa los campos indicados.' : safeMessage(null)), errors);
    }
    return payload;
}


// TODO(contrato: RNG-10: fuentes y categorías se entregan como colecciones sin paginación; confirmar meta y filtros antes de conectarlas a Pagination.)
// TODO(contrato: resumen financiero: falta endpoint o variables Blade de indicadores; no calcular totales ni insertar valores simulados.)
// TODO(contrato: RN-027.4: extender a las pantallas de categorías la adaptación visual del catálogo usada por fuentes, sin modificar el kit.)
// TODO(contrato: el backend debe confirmar el alcance exclusivo en COP y la política para registros históricos en otra moneda; el front ya envía COP en los formularios que lo exigen.)
// TODO(contrato: RN-021: falta ranking de fuentes más usadas; no presentar el orden alfabético como popularidad ni fabricar frecuencias.)
// TODO(contrato: RN-022: falta operación de terminación distinta de pausar; solo existe activa y DELETE elimina la plantilla conservando ocurrencias. La UI ofrece Pausar/Reanudar y Eliminar plantilla, sin estado terminado. El ancla de las plantillas con ocurrencias es inmutable en el servidor.)
// TODO(contrato: RNG-10: recurrentes se reciben como colección sin metadatos de paginación. Las fuentes usan tieneIngresos() existente en Blade; para clientes JSON falta exponer esa capacidad.)
