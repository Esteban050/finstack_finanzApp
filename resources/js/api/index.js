// Único límite de acceso HTTP del nuevo frontend. Las URLs se entregan desde Blade mediante route().
export class ApiError extends Error {
    constructor(status, message, errors = {}) { super(message); this.status = status; this.errors = errors; }
}

export function safeMessage(value, fallback = 'No fue posible completar la operación. Intenta nuevamente.') {
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

export async function getTRMByDate() {
    // TODO(contrato: RF-017 / RN-017.1–RN-017.4: falta endpoint de TRM por fecha, formato decimal y fecha de vigencia. El servidor resuelve la fecha anterior y guarda la conversión autoritativa.)
    throw new ApiError(0, 'No fue posible consultar la TRM.');
}

// TODO(contrato: RNG-10: fuentes y categorías se entregan como colecciones sin paginación; confirmar meta y filtros antes de conectarlas a Pagination.)
// TODO(contrato: resumen financiero: falta endpoint o variables Blade de indicadores; no calcular totales ni insertar valores simulados.)
// TODO(contrato: RN-027.4: el backend usa color_hex e iconos de un catálogo diferente del kit; acordar claves del registro y nombres --cat-* antes de reemplazar los selectores de categorías.)
// TODO(contrato: RN-021.3 / RF-017: formularios heredados envían tasa_cambio_usada manual; el backend debe consultar la TRM y devolver monto original, tasa y monto COP como strings decimales. No conectar conversión autoritativa desde el navegador.)
