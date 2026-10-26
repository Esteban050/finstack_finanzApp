export function passwordChecks(password, {correo = '', documento = ''} = {}) {
    const email = correo.trim().toLocaleLowerCase('es-CO'), doc = documento.trim().toLocaleLowerCase('es-CO'), lower = password.toLocaleLowerCase('es-CO');
    const local = email.split('@')[0];
    return {
        length: [...password].length >= 10 && [...password].length <= 64,
        upper: /[A-Z]/.test(password), lower: /[a-z]/.test(password), digit: /[0-9]/.test(password), special: /[^A-Za-z0-9]/.test(password),
        personal: email && doc ? !lower.includes(email) && !(local.length >= 3 && lower.includes(local)) && !lower.includes(doc) : null,
    };
}
export function validateAuth(kind, values, context = {}) {
    const errors = {}, passwords = ['register', 'reset', 'change'].includes(kind);
    if (['login', 'register', 'recovery', 'verification-resend'].includes(kind)) {
        if (!values.correo || values.correo.length > 254 || !/^[^\s@]+@[^\s@]+$/.test(values.correo)) errors.correo = 'Indica un correo electrónico válido, de máximo 254 caracteres.';
    }
    if (kind === 'register') {
        for (const name of ['nombres', 'apellidos']) if (!/^[\p{L} ]{2,60}$/u.test((values[name] ?? '').trim())) errors[name] = 'Debe tener entre 2 y 60 caracteres, solo letras y espacios.';
        if (!['CC', 'CE', 'PA', 'PPT'].includes(values.tipo_documento)) errors.tipo_documento = 'Selecciona el tipo de documento.';
        if (!/^[\p{L}\p{N}]{5,15}$/u.test(values.numero_documento ?? '')) errors.numero_documento = 'El documento debe tener de 5 a 15 caracteres alfanuméricos, sin puntos.';
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
    return errors;
}
