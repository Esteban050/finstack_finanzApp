import { passwordChecks, validateAuth, secondsRemaining } from './rules.js';

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
    for (const select of document.querySelectorAll('[data-theme-choice]')) {
        select.value = document.documentElement.dataset.theme || 'dark';
        select.addEventListener('change', () => { document.documentElement.dataset.theme = select.value; setCookie('finanzapp_theme', select.value, 31536000); for (const other of document.querySelectorAll('[data-theme-choice]')) other.value = select.value; });
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
        const button = form.querySelector('[data-auth-submit]'), output = form.querySelector('[data-resend-countdown]');
        form.dataset.resendDeadline = String(deadline);
        const tick = () => { const remaining = secondsRemaining(deadline); output.textContent = remaining ? `Puedes reenviar en ${remaining} s.` : 'Ya puedes solicitar un reenvío.'; if (!form.dataset.submitting) button.disabled = remaining > 0; return remaining; };
        if (tick()) { const interval = setInterval(() => { if (!tick()) clearInterval(interval); }, 1000); }
    });
    const firstError = document.querySelector('[data-auth-general-error]') || [...document.querySelectorAll('[data-auth-form] [data-field-error]:not([hidden])')].find((error) => error.textContent.trim());
    if (firstError) { const input = firstError.closest('[data-field]')?.querySelector('input:not([type=hidden]), select'); if (input) input.focus(); else firstError.focus(); }
    window.addEventListener('pageshow', (event) => { if (event.persisted && document.querySelector('[data-auth-form][data-submitting]')) window.location.reload(); });
}
