import { request, safeMessage } from '../api/index.js';
import { mapFieldErrors, bindErrorRevalidation } from '../ui/foundation.js';
import { renderIcon } from '../ui/icons/Icon.js';
import { catalogVisual, categoryPalette } from '../income/catalog.js';
import { moneyInputErrors } from '../income/index.js';
import { formatMoney, validateAmount } from '../utils/money.js';
import { todayBogota } from '../utils/date.js';
import { ENABLE_FOREIGN_CURRENCY } from '../config/currency.js';
import { validateExpense, validateExpenseFilters, validateBulk, validateCategory, selectionWith, validateTagDraft, categoryPayload } from './rules.js';

const valuesOf = (form) => {
    const data = new FormData(form), values = Object.fromEntries(data);
    if (form.querySelector('.ui-tags')) values.etiquetas = data.getAll('etiquetas[]');
    delete values['etiquetas[]'];
    return values;
};
const catalogOf = (form) => JSON.parse(form.querySelector('[data-catalog]')?.dataset.catalog ?? document.querySelector('[data-category-catalog]')?.dataset.categoryCatalog ?? '[]');
function errorMessages(errors) {
    return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key.replace(/^etiquetas\.\d+$/, 'etiquetas'), (Array.isArray(messages) ? messages : [messages]).map((value) => String(value).replace(/\s*\((?:RF|RN|RNG|CA)-\d+(?:\.\d+)?\)/g, '').replace(/^Alcanzaste el máximo de 50 categorías\.$/, 'Alcanzaste el máximo de 50 categorías'))]));
}
export function mountExpenses() {
    document.querySelectorAll('[data-expense-form]').forEach((form) => bindErrorRevalidation(form, () => {
        const recurrence = form.querySelector('[data-recurrence-fields]');
        return {...validateExpense(valuesOf(form), catalogOf(form), {recurrent: Boolean(recurrence), limit: recurrence?.dataset.limit, frozen: recurrence ? {fecha_inicio: recurrence.querySelector('[name=fecha_inicio]').value, periodicidad: recurrence.querySelector('[name=periodicidad]').value} : {}}), ...moneyInputErrors(form)};
    }));
    const palette = categoryPalette(getComputedStyle(document.documentElement));
    document.querySelectorAll('[data-expense-categories]').forEach((root) => {
        const categories = JSON.parse(root.dataset.catalog), category = root.querySelector('[data-expense-category]'), sub = root.querySelector('[data-expense-subcategory]'), form = root.closest('form');
        const editor = root.closest('[data-expense-editor]'), onlyActive = root.dataset.activeOnly === 'true';
        const suggest = () => {
            const chosen = categories.find((item) => item.id === category.value)?.subcategorias?.find((item) => item.id === sub.value);
            if (!editor || !chosen) return;
            editor.querySelector('[name=naturaleza]').value = chosen.naturaleza_sugerida ?? '';
            editor.querySelector('[name=necesidad]').value = chosen.necesidad_sugerida ?? '';
            form.dispatchEvent(new Event('change'));
        };
        const update = (initial = false) => {
            const chosen = categories.find((item) => item.id === category.value);
            const children = chosen?.subcategorias?.filter((item) => !onlyActive || item.activa) ?? [];
            const selected = initial ? sub.dataset.selected : '';
            sub.replaceChildren(new Option(category.value ? (sub.required ? 'Selecciona la subcategoría' : 'Todas las subcategorías') : 'Selecciona primero una categoría', ''));
            for (const item of children) sub.add(new Option(item.nombre, item.id));
            sub.value = selected || ''; sub.disabled = !category.value || !children.length;
            const help = root.querySelector('[data-subcategory-help]');
            const unavailable = onlyActive && category.value && !children.length;
            help.textContent = unavailable ? 'Esta categoría no tiene subcategorías activas. Crea o activa una subcategoría para continuar.' : '';
            const field = sub.closest('[data-field]'), error = field.querySelector('[data-field-error]');
            error.textContent = help.textContent; error.hidden = !unavailable; sub.setAttribute('aria-invalid', String(Boolean(unavailable)));
            if (editor) for (const button of form.querySelectorAll('[type=submit]')) button.disabled = Boolean(unavailable);
            const host = root.querySelector('[data-expense-category-icon]'); host.hidden = !chosen;
            if (chosen) { const visual = catalogVisual(chosen, palette); host.style.color = visual.colorVar; host.replaceChildren(renderIcon(visual.iconName, {size: 20})); }
            if (!initial) { if (editor) { editor.querySelector('[name=naturaleza]').value = ''; editor.querySelector('[name=necesidad]').value = ''; } form.dispatchEvent(new Event('change')); }
        };
        category.addEventListener('change', () => update()); sub.addEventListener('change', suggest); update(true);
    });
    document.querySelectorAll('[data-expense-editor]').forEach((editor) => {
        const date = editor.querySelector('[data-expense-date]'); if (date && !date.value) date.value = todayBogota();
        const relevance = editor.querySelector('[name=relevancia_tributaria]'), panel = editor.querySelector('[data-deduction-panel]'), subtype = panel.querySelector('select');
        const deduction = () => { const enabled = relevance.value === 'POTENCIALMENTE_DEDUCIBLE'; panel.hidden = !enabled; subtype.disabled = !enabled; subtype.required = enabled; };
        relevance.addEventListener('change', deduction); deduction();
        const summary = () => {
            for (const output of editor.querySelectorAll('[data-expense-summary]')) { const select = editor.querySelector(`[name="${output.dataset.expenseSummary}"]`); output.textContent = select.value ? select.selectedOptions[0]?.textContent ?? '—' : '—'; }
            try { const amount = editor.querySelector('[name=monto_original]').value; editor.querySelector('[data-expense-amount]').textContent = validateAmount(amount) ? formatMoney(amount) : '—'; } catch { editor.querySelector('[data-expense-amount]').textContent = '—'; }
        };
        editor.closest('form').addEventListener('change', summary);
        editor.addEventListener('money-change', (event) => { if (event.detail.valid) queueMicrotask(summary); else editor.querySelector('[data-expense-amount]').textContent = '—'; });
        queueMicrotask(summary);
    });
    document.querySelectorAll('[data-tags-url]').forEach((root) => {
        const input = root.querySelector('input:not([type=hidden])'); if (!input) return;
        let loaded = false;
        input.addEventListener('focus', async () => {
            if (loaded) return; loaded = true;
            let note = root.querySelector('[data-tags-status]');
            if (!note) { note = document.createElement('p'); note.className = 'ui-help'; note.dataset.tagsStatus = ''; note.setAttribute('role', 'status'); root.append(note); }
            note.textContent = 'Cargando etiquetas usadas…';
            try {
                const response = await request(root.dataset.tagsUrl); if (!Array.isArray(response.data)) throw new Error();
                const list = document.createElement('datalist'); list.id = `${input.id}-suggestions`;
                for (const tag of response.data) { if (typeof tag === 'string') list.append(new Option(tag, tag)); }
                root.append(list); input.setAttribute('list', list.id); note.textContent = '';
            } catch { loaded = false; note.textContent = 'No fue posible cargar las etiquetas usadas. Puedes escribir una etiqueta.'; }
        });
    });
    const bulk = document.querySelector('[data-expense-bulk]');
    if (bulk) {
        let selected = new Set();
        const count = document.querySelector('[data-selected-count]'), message = document.querySelector('[data-selection-error]');
        const update = () => { bulk.dataset.selected = JSON.stringify([...selected]); count.textContent = `${selected.size} de 100 gastos seleccionados`; document.querySelector('[data-bulk-panel]').hidden = !selected.size; };
        for (const checkbox of document.querySelectorAll('[data-expense-select]')) checkbox.addEventListener('change', () => {
            const next = selectionWith(selected, checkbox.value, checkbox.checked); selected = next.ids;
            if (next.blocked) checkbox.checked = false;
            message.textContent = next.blocked ? 'Puedes seleccionar como máximo 100 gastos por operación.' : ''; update();
        });
        document.querySelector('[data-select-page]')?.addEventListener('change', (event) => {
            for (const checkbox of document.querySelectorAll('[data-expense-select]')) { const next = selectionWith(selected, checkbox.value, event.target.checked); selected = next.ids; checkbox.checked = selected.has(checkbox.value); if (next.blocked) message.textContent = 'Puedes seleccionar como máximo 100 gastos por operación.'; } update();
        });
        bulk.querySelector('[data-change-tags]')?.addEventListener('change', (event) => { for (const input of bulk.querySelectorAll('.ui-tags input, .ui-tags button')) input.disabled = !event.target.checked; });
        for (const input of bulk.querySelectorAll('.ui-tags input, .ui-tags button')) input.disabled = true;
        update();
    }
    document.querySelectorAll('[data-expense-filters]').forEach((form) => form.addEventListener('submit', (event) => {
        const errors = {...validateExpenseFilters(valuesOf(form)), ...moneyInputErrors(form)}; mapFieldErrors(form, errors); if (Object.keys(errors).length) event.preventDefault();
    }));
    document.querySelectorAll('[data-expense-form], [data-expense-action], [data-expense-confirm], [data-expense-bulk], [data-expense-category-form], [data-expense-merge]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (form.getAttribute('aria-busy') === 'true') return;
        let values = valuesOf(form); const catalog = catalogOf(form); let errors = {};
        if ('moneda' in values && !ENABLE_FOREIGN_CURRENCY) values.moneda = 'COP';
        if (form.hasAttribute('data-expense-form')) {
            const recurrence = form.querySelector('[data-recurrence-fields]');
            const frozen = recurrence ? {fecha_inicio: recurrence.querySelector('[name=fecha_inicio]').value, periodicidad: recurrence.querySelector('[name=periodicidad]').value} : {};
            errors = validateExpense(values, catalog, {recurrent: Boolean(recurrence), limit: recurrence?.dataset.limit, frozen});
            if (recurrence) values.es_monto_estimado = Boolean(form.querySelector('[name=es_monto_estimado]:checked'));
        }
        if (form.hasAttribute('data-expense-confirm') && !validateAmount(values.monto_original)) errors.monto_original = ['Indica el monto real, mayor que $ 0 y dentro del límite permitido.'];
        if (form.hasAttribute('data-expense-bulk')) {
            values.ids = JSON.parse(form.dataset.selected || '[]');
            if (!form.querySelector('[data-change-tags]').checked) delete values.etiquetas;
            errors = validateBulk(values, catalog);
            for (const key of ['categoria_id','subcategoria_id','naturaleza','necesidad']) if (!values[key]) delete values[key];
        }
        if (form.hasAttribute('data-expense-category-form')) {
            errors = validateCategory(values, catalog, {id: form.dataset.categoryId || null, parentId: values.categoria_padre_id || form.dataset.parentId || null});
            const prepared = categoryPayload(values, JSON.parse(form.dataset.allowedIcons), JSON.parse(form.dataset.allowedColors), {parentId: values.categoria_padre_id || form.dataset.parentId || null, originalIcon: form.dataset.originalIcon, originalColor: form.dataset.originalColor, editing: Boolean(form.dataset.categoryId)});
            values = {...prepared.data, _method: values._method};
            const notice = form.querySelector('[data-expense-status]');
            if (notice && prepared.warnings.length) { notice.hidden = false; notice.textContent = prepared.warnings.join(' '); notice.setAttribute('role','status'); }

        }
        if (form.hasAttribute('data-expense-merge') && !values.destino_id) errors.destino_id = ['Selecciona el destino de la fusión.'];
        const tagDraft = form.querySelector('.ui-tags input:not([type=hidden])');
        if (tagDraft && !tagDraft.disabled) { const message = validateTagDraft(tagDraft.value, values.etiquetas ?? []); if (message) errors.etiquetas = [message]; }
        errors = {...errors, ...moneyInputErrors(form)}; mapFieldErrors(form, errors);
        const status = form.querySelector('[data-expense-status]'); if (status) status.hidden = true;
        if (Object.keys(errors).length) return;
        // Solo el envío válido convierte las etiquetas en la lista del contrato.
        if (values.relevancia_tributaria === 'NO_DEDUCIBLE') delete values.subtipo_deduccion;
        const submit = event.submitter; submit?.setAttribute('disabled', ''); form.setAttribute('aria-busy', 'true');
        const loading = form.querySelector('[data-expense-loading]'); if (loading) loading.hidden = false;
        let success = false;
        try {
            const method = values._method || 'POST'; delete values._method;
            await request(form.action, {method, data: values}); success = true;
            window.location.assign(form.dataset.returnUrl || window.location.href);
        } catch (error) {
            mapFieldErrors(form, errorMessages(error.errors || {}));
            if (status) { status.hidden = false; status.textContent = safeMessage(error.message); }
        } finally {
            if (!success) { submit?.removeAttribute('disabled'); form.setAttribute('aria-busy', 'false'); if (loading) loading.hidden = true; }
        }
    }));
    document.querySelectorAll('[data-open-expense-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openExpenseDialog).showModal()));
    document.querySelectorAll('[data-close-expense-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
    document.querySelectorAll('[data-expense-category-form]').forEach((form) => {
        bindErrorRevalidation(form, () => validateCategory(valuesOf(form), catalogOf(form), {id: form.dataset.categoryId || null, parentId: form.querySelector('[name=categoria_padre_id]')?.value || form.dataset.parentId || null}));
        const parent = form.querySelector('[data-category-parent]'), panel = form.querySelector('[data-category-suggestions]');
        const update = () => { const hasParent = Boolean(parent?.value || form.dataset.parentId); panel.hidden = !hasParent; for (const input of panel.querySelectorAll('select')) { input.disabled = !hasParent; input.required = hasParent; } };
        parent?.addEventListener('change', update); update();
    });
}
