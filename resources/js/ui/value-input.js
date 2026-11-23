import { decimal, formatCanonicalValue, parseLocalizedValue, MAX_AMOUNT } from '../utils/money.js';
import { bindMoneyGrouping } from './money-format.js';

// Adaptador de controles existentes. El control original mantiene ID, nombre y
// decimal canónico para sus manejadores; el visible solo representa ese valor.
// No se infieren montos a partir de su texto: las vistas los marcan explícitamente.
export function mountValueInputs(root = document) {
    for (const canonical of root.querySelectorAll('input[data-value-input]:not([data-value-bound])')) {
        canonical.dataset.valueBound = 'true';
        const visible = canonical.cloneNode(false);
        const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        const precision = canonical.dataset.precision || '2';
        const id = canonical.id || `value-${canonical.name}`;
        canonical.id = id;
        visible.id = id + '-display';
        visible.type = 'text';
        visible.inputMode = 'decimal';
        visible.removeAttribute('name');
        visible.removeAttribute('min'); visible.removeAttribute('max'); visible.removeAttribute('step');
        visible.removeAttribute('data-value-input'); visible.removeAttribute('data-value-bound');
        visible.dataset.valueDisplay = id;
        visible.classList.add('ui-value-input');
        for (const attribute of [...visible.attributes]) if (/^on/i.test(attribute.name)) visible.removeAttribute(attribute.name);
        canonical.before(visible);
        for (const label of document.querySelectorAll('label[for]')) if (label.htmlFor === id) label.htmlFor = visible.id;
        canonical.type = 'hidden';
        let editing = false, initialized = false, validate = () => true;
        const sync = () => {
            if (editing) return;
            const raw = descriptor.get.call(canonical);
            try { visible.value = raw === '' ? '' : formatCanonicalValue(raw); }
            catch { visible.value = raw; }
            if (initialized) validate(false);
        };
        Object.defineProperty(canonical, 'value', {
            configurable: true,
            get() { return descriptor.get.call(this); },
            set(value) { descriptor.set.call(this, String(value ?? '')); sync(); },
        });
        const errorHost = canonical.closest('[data-field]')?.querySelector('[data-field-error]');
        validate = (notify = true) => {
            let error = '';
            try {
                const value = visible.value === '' ? '' : parseLocalizedValue(visible.value, {
                    precision, min: canonical.getAttribute('min') || '0', max: canonical.getAttribute('max') || MAX_AMOUNT,
                });
                if (!value && visible.required) error = 'Este campo es obligatorio.';
                if (value && canonical.dataset.positive === 'true' && decimal(value).lte('0')) error = 'El valor debe ser mayor que cero.';
                descriptor.set.call(canonical, value);
            } catch (failure) { descriptor.set.call(canonical, ''); error = failure.message; }
            visible.setCustomValidity(error);
            visible.setAttribute('aria-invalid', String(Boolean(error)));
            if (errorHost) { errorHost.textContent = error; errorHost.hidden = !error; }
            if (notify) {
                editing = true;
                try { canonical.dispatchEvent(new Event('input', { bubbles: true })); }
                finally { editing = false; }
            }
            return !error;
        };
        sync();
        // Respeta la precisión del campo (tasas y precios usan 4 u 8 decimales) y su mínimo negativo.
        bindMoneyGrouping(visible, { maxDecimals: Number(precision), allowNegative: () => Number(canonical.getAttribute('min') || 0) < 0 });
        visible.addEventListener('input', () => validate());
        visible.addEventListener('change', () => { validate(); canonical.dispatchEvent(new Event('change', { bubbles: true })); });
        visible.addEventListener('blur', () => validate(false));
        canonical.addEventListener('input', sync);
        canonical.addEventListener('change', sync);
        const syncAttributes = () => {
            visible.disabled = canonical.disabled;
            visible.required = canonical.required;
            visible.readOnly = canonical.readOnly;
        };
        new MutationObserver(() => { syncAttributes(); if (initialized && !visible.matches(':disabled')) validate(false); }).observe(canonical, { attributes: true, attributeFilter: ['disabled', 'required', 'readonly', 'min', 'max'] });
        syncAttributes();
        initialized = true;
        canonical.form?.addEventListener('reset', () => queueMicrotask(() => { sync(); visible.setCustomValidity(''); visible.setAttribute('aria-invalid', 'false'); }));
        canonical.form?.addEventListener('submit', event => {
            if (!visible.matches(':disabled') && !validate(false)) { event.preventDefault(); visible.reportValidity(); }
        }, true);
    }
}
