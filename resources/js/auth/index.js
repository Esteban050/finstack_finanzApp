import { passwordChecks, validateAuth, secondsRemaining, nameFieldError, documentFieldError } from './rules.js';

const cookie = (name) => document.cookie.split('; ').find((item) => item.startsWith(name + '='))?.slice(name.length + 1);
const setCookie = (name, value, age = 86400) => { document.cookie = `${name}=${value}; Path=/; Max-Age=${age}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`; };
function showClientErrors(form, errors) {
    // Los errores del servidor se conservan literalmente en su propio elemento.
    for (const message of form.querySelectorAll('[data-auth-client-error]')) {
        const name = message.dataset.authClientError;
        message.textContent = errors[name] || ''; message.hidden = !errors[name];
        if (errors[name]) form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true');
        else if (!form.querySelector(`[data-field="${name}"] [data-field-error]:not([hidden])`)) form.elements.namedItem(name)?.removeAttribute('aria-invalid');
    }
}
export function mountAuth() {
    const themeButtons = document.querySelectorAll('[data-theme-choice]');
    const syncTheme = () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        for (const button of themeButtons) {
            button.setAttribute('aria-pressed', String(dark));
            button.title = dark ? 'Activar modo claro' : 'Activar modo oscuro';
            button.hidden = false;
        }
    };
    syncTheme();
    for (const button of themeButtons) {
        button.addEventListener('click', () => {
            const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = theme;
            setCookie('finanzapp_theme', theme, 31536000);
            syncTheme();
        });
    }
    document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls')), show = input.type === 'password';
        input.type = show ? 'text' : 'password'; button.setAttribute('aria-pressed', String(show)); button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.querySelector('[data-password-show]').hidden = show; button.querySelector('[data-password-hide]').hidden = !show;
    }));
    document.querySelectorAll('[data-auth-form]').forEach((form) => {
        const kind = form.dataset.authForm, submit = form.querySelector('[data-auth-submit]');
        const values = () => Object.fromEntries(new FormData(form));
        const update = () => {
            if (!['register', 'reset', 'change', 'policy'].includes(kind) || form.dataset.submitting) return;
            const data = values();
            if (kind === 'policy') { submit.disabled = data.acepto !== '1'; return; }
            const checks = passwordChecks(data.password ?? '', {correo: data.correo ?? form.dataset.passwordEmail ?? '', documento: data.numero_documento ?? form.dataset.passwordDocument ?? ''});
            for (const row of form.querySelectorAll('[data-password-rule]')) {
                const value = checks[row.dataset.passwordRule]; row.dataset.valid = String(value === true);
                row.querySelector('[data-rule-pending]').hidden = value === true; row.querySelector('[data-rule-pass]').hidden = value !== true;
                row.querySelector('[data-rule-state]').textContent = value === null ? (kind === 'register' ? 'Pendiente' : 'Al guardar') : value ? 'Cumple' : 'Pendiente';
            }
            const confirmation = form.querySelector('[name=password_confirmation]');
            const mismatch = confirmation.value && confirmation.value !== data.password ? {password_confirmation: 'Las contraseñas no coinciden'} : {};
            const error = form.querySelector('[data-auth-client-error=password_confirmation]');
            error.textContent = mismatch.password_confirmation || ''; error.hidden = !mismatch.password_confirmation;
            submit.disabled = kind === 'register' && (data.acepta_politica !== '1' || Object.values(checks).some((value) => value !== true) || !confirmation.value || Boolean(mismatch.password_confirmation));
        };
        form.addEventListener('input', update); form.addEventListener('change', update); update();
        if (kind === 'register') {
            // RN-001.1: el error de nombres, apellidos y documento aparece al salir del campo y se actualiza mientras se corrige.
            for (const [name, rule] of [['nombres', nameFieldError], ['apellidos', nameFieldError], ['numero_documento', documentFieldError]]) {
                const input = form.elements.namedItem(name), message = form.querySelector(`[data-auth-client-error="${name}"]`);
                if (!input || !message) continue;
                const check = () => {
                    const error = rule(input.value);
                    message.textContent = error; message.hidden = !error;
                    if (error) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
                };
                input.addEventListener('blur', () => { input.dataset.touched = 'true'; check(); });
                input.addEventListener('input', () => { if (input.dataset.touched) check(); });
                if (input.value) { input.dataset.touched = 'true'; check(); }
            }
        }
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            const errors = validateAuth(kind, values(), {correo: form.dataset.passwordEmail, documento: form.dataset.passwordDocument});
            showClientErrors(form, errors);
            if (Object.keys(errors).length) { event.preventDefault(); form.elements.namedItem(Object.keys(errors)[0])?.focus(); return; }
            if (form.dataset.resendDeadline && secondsRemaining(Number(form.dataset.resendDeadline))) { event.preventDefault(); return; }
            form.dataset.submitting = 'true'; submit.disabled = true; submit.setAttribute('aria-busy', 'true');
            submit.querySelector('[data-auth-submit-label]').textContent = submit.dataset.loadingLabel || 'Enviando…'; submit.querySelector('[data-auth-submit-spinner]').hidden = false;
            if (form.dataset.resendKey) setCookie('fa_resend_' + form.dataset.resendKey, Date.now() + 60000);
            if (kind === 'register') setCookie('fa_resend_verification', Date.now() + 60000);
            if (kind === 'login') setCookie('fa_resend_mfa', Date.now() + 60000);
        });
    });
    document.querySelectorAll('[data-resend-key]').forEach((form) => {
        const key = 'fa_resend_' + form.dataset.resendKey;
        let deadline = Number(cookie(key) || 0);
        if (!deadline && form.dataset.resendInitial === 'true') { deadline = Date.now() + 60000; setCookie(key, deadline); }
        // El servidor manda la espera real cuando rechaza un reenvío por los 60 s.
        if (form.dataset.resendWait) { deadline = Date.now() + Number(form.dataset.resendWait) * 1000; setCookie(key, deadline); }
        const button = form.querySelector('[data-auth-submit]'), output = form.querySelector('[data-resend-countdown]');
        form.dataset.resendDeadline = String(deadline);
        if (form.dataset.resendBlocked === 'true') {
            // RN-002.2 / RN-012.4: con el límite alcanzado no se ofrece más reenvío.
            button.disabled = true; output.textContent = 'Ya no hay más reenvíos permitidos por ahora.';
            return;
        }
        const tick = () => { const remaining = secondsRemaining(deadline); output.textContent = remaining ? `Puedes reenviar en ${remaining} s.` : 'Ya puedes solicitar un reenvío.'; if (!form.dataset.submitting) button.disabled = remaining > 0; return remaining; };
        if (tick()) { const interval = setInterval(() => { if (!tick()) clearInterval(interval); }, 1000); }
    });
    const firstError = document.querySelector('[data-auth-general-error]') || [...document.querySelectorAll('[data-auth-form] [data-field-error]:not([hidden])')].find((error) => error.textContent.trim());
    if (firstError) { const input = firstError.closest('[data-field]')?.querySelector('input:not([type=hidden]), select'); if (input) input.focus(); else firstError.focus(); }
    window.addEventListener('pageshow', (event) => { if (event.persisted && document.querySelector('[data-auth-form][data-submitting]')) window.location.reload(); });
}
