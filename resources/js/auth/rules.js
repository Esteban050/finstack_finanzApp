import { formatDate, todayBogota } from '../utils/date.js';

export function passwordChecks(password, {correo = '', documento = ''} = {}) {
    const email = correo.trim().toLocaleLowerCase('es-CO'), doc = documento.trim().toLocaleLowerCase('es-CO'), lower = password.toLocaleLowerCase('es-CO');
    const local = email.split('@')[0];
    return {
        length: [...password].length >= 10 && [...password].length <= 64,
        upper: /[A-Z]/.test(password), lower: /[a-z]/.test(password), digit: /[0-9]/.test(password), special: /[^A-Za-z0-9]/.test(password),
        personal: email && doc ? !lower.includes(email) && !(local.length >= 3 && lower.includes(local)) && !lower.includes(doc) : null,
    };
}
// RN-001.1: nombres y apellidos obligatorios, de 2 a 60 caracteres, solo letras y espacios.
export function nameFieldError(value) {
    const text = String(value ?? '').trim(), length = [...text].length;
    if (!length) return 'Este campo es obligatorio.';
    if (length < 2) return 'Debe tener mínimo 2 caracteres.';
    if (length > 60) return 'Debe tener máximo 60 caracteres.';
    if (!/^[\p{L} ]+$/u.test(text)) return 'Solo se permiten letras y espacios.';
    return '';
}
// RN-001.1: número de documento obligatorio, de 5 a 15 caracteres alfanuméricos, sin puntos.
export function documentFieldError(value) {
    const text = String(value ?? '').trim(), length = [...text].length;
    if (!length) return 'Este campo es obligatorio.';
    if (!/^[\p{L}\p{N}]+$/u.test(text)) return 'Solo letras y números, sin puntos, guiones ni espacios.';
    if (length < 5) return 'Debe tener mínimo 5 caracteres.';
    if (length > 15) return 'Debe tener máximo 15 caracteres.';
    return '';
}
export function validateAuth(kind, values, context = {}) {
    const errors = {}, passwords = ['register', 'reset', 'change'].includes(kind);
    if (['login', 'register', 'recovery', 'verification-resend'].includes(kind)) {
        if (!values.correo || values.correo.length > 254 || !/^[^\s@]+@[^\s@]+$/.test(values.correo)) errors.correo = 'Indica un correo electrónico válido, de máximo 254 caracteres.';
    }
    if (kind === 'register') {
        for (const name of ['nombres', 'apellidos']) { const message = nameFieldError(values[name]); if (message) errors[name] = message; }
        if (!['CC', 'CE', 'PA', 'PPT'].includes(values.tipo_documento)) errors.tipo_documento = 'Selecciona el tipo de documento.';
        { const message = documentFieldError(values.numero_documento); if (message) errors.numero_documento = message; }
        if (values.acepta_politica !== '1') errors.acepta_politica = 'Debes aceptar la política de datos y los términos de uso.';
    }
    if (passwords) {
        const checks = passwordChecks(values.password ?? '', {correo: values.correo ?? context.correo ?? '', documento: values.numero_documento ?? context.documento ?? ''});
        if (Object.values(checks).some((value) => value === false)) errors.password = 'Revisa las condiciones de la contraseña.';
        if (!values.password_confirmation || values.password !== values.password_confirmation) errors.password_confirmation = 'Las contraseñas no coinciden';
    }
    if (['login', 'confirm'].includes(kind) && !values.password) errors.password = 'Ingresa tu contraseña.';
    if (kind === 'change' && !values.current_password) errors.current_password = 'Ingresa tu contraseña actual.';
    if (kind === 'mfa' && !/^[0-9]{6}$/.test(values.codigo ?? '')) errors.codigo = 'Ingresa el código de 6 dígitos.';
    if (kind === 'policy' && values.acepto !== '1') errors.acepto = 'Debes aceptar la política para continuar.';
    return errors;
}
export function secondsRemaining(deadline, now = Date.now()) { return Number.isFinite(deadline) && Number.isFinite(now) ? Math.max(0, Math.ceil((deadline - now) / 1000)) : 0; }

export function validateProfilePresentation(values) {
    const errors = {};
    for (const name of ['nombres','apellidos']) if (!/^[\p{L} ]{2,60}$/u.test((values[name] ?? '').trim())) errors[name] = ['Debe tener entre 2 y 60 caracteres, solo letras y espacios.'];
    if (values.telefono && !/^\d{10}$/.test(values.telefono)) errors.telefono = ['El teléfono debe tener exactamente 10 dígitos.'];
    if (Object.hasOwn(values,'fecha_nacimiento')) {
        const today = todayBogota(), cutoff = String(Number(today.slice(0,4)) - 18) + today.slice(4);
        try { formatDate(values.fecha_nacimiento); if (values.fecha_nacimiento > cutoff) errors.fecha_nacimiento = ['Debes ser mayor de edad.']; }
        catch { errors.fecha_nacimiento = ['Indica una fecha de nacimiento válida.']; }
    }
    if (Object.hasOwn(values,'ocupaciones') && (!Array.isArray(values.ocupaciones) || !values.ocupaciones.length)) errors.ocupaciones = ['Selecciona al menos una ocupación.'];
    if (Object.hasOwn(values,'ciudad_residencia') && (!values.ciudad_residencia.trim() || values.ciudad_residencia.trim().length > 80)) errors.ciudad_residencia = ['Indica tu ciudad, con máximo 80 caracteres.'];
    return errors;
}
