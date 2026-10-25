import { mapFieldErrors, bindErrorRevalidation } from '../ui/foundation.js';
import { request, safeMessage } from '../api/index.js';
import { formatMoney } from '../utils/money.js';
import { todayBogota } from '../utils/date.js';
import { catalogVisual, categoryPalette, legacyIcons } from './catalog.js';
import { ENABLE_FOREIGN_CURRENCY } from '../config/currency.js';
import { renderIcon } from '../ui/icons/Icon.js';
import { incomeSummary, validateIncome, validateSource, validateRecurrence, validateIncomeFilters, suggestClassification } from './rules.js';

const valuesOf = (form) => Object.fromEntries(new FormData(form));
export function moneyInputErrors(form) {
    const errors = {};
    for (const root of form.querySelectorAll('.ui-money')) {
        const input = root.querySelector('input[type=text]');
        if (!input.disabled && input.validity.customError) errors[root.closest('[data-field]').dataset.field] = [input.validationMessage];
    }
    return errors;
}
function normalizedErrors(errors) {
    const result = {};
    for (const [field, messages] of Object.entries(errors)) result[field] = messages.map((message) => {
        if (message.startsWith('Ya existe una fuente con ese nombre')) return 'Ya existe una fuente con ese nombre';
        if (message.startsWith('La retención no puede superar el monto bruto')) return 'La retención no puede superar el monto bruto';
        return safeMessage(message).replace(/\s*\((?:RF|RN|RNG|CA)-\d+(?:\.\d+)?\)/g, '');
    });
    return result;
}
function incomeFormErrors(form, values = valuesOf(form)) {
    let errors = {};
    const editor = form.querySelector('[data-income-editor]');
    if (editor) {
        const recurrence = form.querySelector('[data-recurrence-fields]');
        errors = validateIncome(values, Boolean(recurrence));
        if (recurrence) errors = { ...errors, ...validateRecurrence({ ...values, periodicidad: values.periodicidad || recurrence.querySelector('[name=periodicidad]').value, fecha_inicio: values.fecha_inicio || recurrence.querySelector('[name=fecha_inicio]').value }, recurrence.dataset.limit) };
    }
    if (form.hasAttribute('data-source-form')) {
        const catalog = JSON.parse(document.querySelector('[data-source-catalog]')?.dataset.sourceCatalog || '[]');
        errors = validateSource(values, catalog, form.dataset.sourceId || null);
    }
    errors = { ...errors, ...moneyInputErrors(form) };
    return errors;
}
export function mountIncome() {
    document.querySelectorAll('[data-income-form], [data-source-form]').forEach((form) => bindErrorRevalidation(form, () => incomeFormErrors(form)));

    document.querySelectorAll('[data-icon-picker]').forEach((select) => {
        const group = document.createElement('div'); group.className = 'ui-icon-picker';
        const search = document.createElement('input'); search.type = 'search'; search.placeholder = 'Buscar ícono por nombre'; search.setAttribute('aria-label', 'Buscar ícono por nombre');
        group.append(search);
        for (const option of select.options) {
            const label = option.value ? (legacyIcons[option.value]?.[1] ?? option.textContent) : 'Ninguno';
            const theme = document.createElement('fieldset'), legend = document.createElement('legend'); legend.textContent = label; theme.append(legend);
            const button = document.createElement('button'); button.type = 'button'; button.className = 'ui-button ui-button--secondary';
            button.setAttribute('aria-label', label); button.title = label; button.setAttribute('aria-pressed', String(option.selected));
            button.append(renderIcon(option.value ? catalogVisual({icono: option.value}, {}).iconName : 'ui-cerrar', {size: 22}));
            const text = document.createElement('span'); text.textContent = label; button.append(text);
            button.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', {bubbles:true})); for (const item of group.querySelectorAll('button')) item.setAttribute('aria-pressed', String(item === button)); });
            theme.append(button); group.append(theme);
        }
        search.addEventListener('input', () => { const term = search.value.toLocaleLowerCase('es-CO'); for (const item of group.querySelectorAll('fieldset')) item.hidden = !item.textContent.toLocaleLowerCase('es-CO').includes(term); });
        select.hidden = true; select.after(group);
    });
    const palette = categoryPalette(getComputedStyle(document.documentElement));
    const paint = (host, category) => {
        const visual = catalogVisual(category, palette);
        host.style.color = visual.colorVar;
        host.replaceChildren(renderIcon(visual.iconName, { size: 20 }));
    };
    document.querySelectorAll('[data-source-visual]').forEach((host) => paint(host, host.dataset));
    document.querySelectorAll('[data-source-type]').forEach((select) => {
        const host = select.closest('[data-field]').querySelector('[data-type-visual]');
        const update = () => { const option = select.selectedOptions[0]; host.hidden = !select.value; if (select.value) paint(host, option.dataset); };
        select.addEventListener('change', update); update();
    });
    document.querySelectorAll('[data-income-filters]').forEach((form) => form.addEventListener('submit', (event) => {
        const errors = { ...validateIncomeFilters(valuesOf(form)), ...moneyInputErrors(form) };
        mapFieldErrors(form, errors);
        if (Object.keys(errors).length) event.preventDefault();
    }));
    document.querySelectorAll('[data-income-editor]').forEach((editor) => {
        const form = editor.closest('form');
        const date = editor.querySelector('[data-income-date]');
        if (date && !date.value) date.value = todayBogota();
        const summary = (override = {}) => {
            try { const result = incomeSummary({ ...valuesOf(form), ...override }); for (const output of editor.querySelectorAll('[data-income-summary]')) output.textContent = formatMoney(result[output.dataset.incomeSummary]); }
            catch { for (const output of editor.querySelectorAll('[data-income-summary]')) output.textContent = '—'; }
        };
        editor.addEventListener('money-change', (event) => {
            if (event.detail.valid) summary({ [event.detail.name]: event.detail.amount });
            else for (const output of editor.querySelectorAll('[data-income-summary]')) output.textContent = '—';
        });
        const source = editor.querySelector('[data-income-source]');
        source.addEventListener('change', () => { editor.querySelector('[data-classification]').value = source.selectedOptions[0]?.dataset.sourceClassification || ''; });
        if (!editor.querySelector('[data-classification]').value) source.dispatchEvent(new Event('change'));
        queueMicrotask(() => summary());
    });
    document.querySelectorAll('[data-source-type]').forEach((select) => select.addEventListener('change', () => {
        const value = suggestClassification(select.selectedOptions[0]?.dataset.typeName || '');
        if (value) select.closest('form').querySelector('[data-classification]').value = value;
    }));
    document.querySelectorAll('[data-recurrence-fields]').forEach((section) => {
        const apply = (mode) => {
            section.dataset.limit = mode;
            for (const panel of section.querySelectorAll('[data-limit-panel]')) { const selected = panel.dataset.limitPanel === mode; panel.hidden = !selected; panel.querySelector('input').disabled = !selected; panel.querySelector('input').required = selected; }
            for (const button of section.querySelectorAll('[data-limit-choice]')) button.setAttribute('aria-pressed', String(button.dataset.limitChoice === mode));
        };
        apply(section.dataset.limit);
        for (const button of section.querySelectorAll('[data-limit-choice]')) button.addEventListener('click', () => apply(button.dataset.limitChoice));
        const start = section.querySelector('[name=fecha_inicio]'), end = section.querySelector('[name=fecha_fin]');
        const updateDate = () => { end.min = start.value; }; start.addEventListener('change', updateDate); updateDate();
    });
    document.querySelectorAll('[data-income-form], [data-source-form], [data-income-action]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.getAttribute('aria-busy') === 'true') return;
        const values = valuesOf(form);
        if (!ENABLE_FOREIGN_CURRENCY && Object.hasOwn(values, 'moneda')) values.moneda = 'COP';
        const errors = incomeFormErrors(form, values);
        mapFieldErrors(form, errors);
        if (Object.keys(errors).length) return;
        const status = form.querySelector('[data-form-status]');
        const submit = event.submitter;
        if (submit?.disabled) return;
        submit?.setAttribute('disabled', ''); submit?.setAttribute('aria-busy', 'true'); form.setAttribute('aria-busy', 'true');
        form.querySelector('[data-form-loading]')?.removeAttribute('hidden');
        let succeeded = false;
        try {
            const method = values._method || 'POST'; delete values._method;
            await request(form.action, { method, data: values });
            succeeded = true;
            // Relectura del listado y sus totales autoritativos, sin sumas locales.
            window.location.assign(form.dataset.returnUrl || window.location.href);
        } catch (error) {
            mapFieldErrors(form, normalizedErrors(error.errors || {}));
            if (status) { status.hidden = false; status.textContent = safeMessage(error.message); status.setAttribute('role', 'alert'); }
        } finally {
            if (succeeded) return;
            submit?.removeAttribute('disabled'); submit?.removeAttribute('aria-busy'); form.setAttribute('aria-busy', 'false');
            const loading = form.querySelector('[data-form-loading]'); if (loading) loading.hidden = true;
            const pageLoading = document.querySelector('[data-page-loading]'); if (pageLoading) pageLoading.hidden = true;
            document.querySelector('#main-content')?.setAttribute('aria-busy', 'false');
        }
    }));
}
