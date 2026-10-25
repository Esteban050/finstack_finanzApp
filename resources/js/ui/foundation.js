import { formatMoney, parseMoneyInput, validateAmount, decimal, MAX_AMOUNT, percentageOf, percentageFrom, subtractMoney, parsePercentage, deductionMode } from '../utils/money.js';
import { formatDate, todayBogota } from '../utils/date.js';
import { safeMessage } from '../api/index.js';
import { renderIllustration } from './illustrations/index.js';
import { renderIcon } from './icons/Icon.js';
import { getCategoryVisual } from './icons/categoryIcons.js';

export function mapFieldErrors(form, errors, { focus = true, onlyErrored = false } = {}) {
    for (const field of form.querySelectorAll('[data-field]')) {
        const raw = errors[field.dataset.field] ?? [];
        const messages = Array.isArray(raw) ? raw : [raw];
        const error = field.querySelector('[data-field-error]');
        if (!error || (onlyErrored && error.hidden)) continue;
        error.textContent = messages.map((message) => safeMessage(message, 'Revisa la regla de validación de este campo.')).join(' ');
        error.hidden = messages.length === 0;
        const input = [...field.querySelectorAll('input, select, textarea')].find((input) => input.id === field.dataset.field) ?? field.querySelector('input:not([type=hidden]), select, textarea');
        input?.setAttribute('aria-invalid', String(messages.length > 0));
    }
    if (focus) form.querySelector('[aria-invalid=true]:not([disabled])')?.focus();
}

export function bindErrorRevalidation(form, validate) {
    const update = () => {
        if (form.querySelector('[aria-invalid=true]')) mapFieldErrors(form, validate(), {focus: false, onlyErrored: true});
    };
    form.addEventListener('input', update); form.addEventListener('change', update);
}

export function registerFoundation(Alpine) {
    Alpine.data('moneyInput', () => ({
        display: '', canonical: '', invalid: false, mode: 'money', preview: '—',
        init() {
            this.canonical = this.$root.dataset.value;
            if (this.$root.dataset.deduction === 'true') {
                queueMicrotask(() => {
                    const state = this.$root.dataset.editing === 'true' ? deductionMode(this.canonical || '0', this.gross()) : {mode:'percent',display:'0'};
                    this.mode = state.mode; this.display = state.mode === 'money' ? formatMoney(this.canonical || '0').replace(/^\$ /, '') : state.display;
                    this.validate();
                });
                this.$root.closest('form')?.addEventListener('money-change', (event) => { if (event.detail.name === 'monto_original' && this.mode === 'percent') this.validate(); });
            }
            if (this.canonical) {
                try { this.display = formatMoney(this.canonical).replace(/^\$ /, ''); }
                catch { this.display = this.canonical; this.validate(); }
            }
        },
        gross() { return this.$root.closest('form')?.querySelector('[name=monto_original]')?.value || ''; },
        setMode(mode) {
            const amount = this.canonical;
            this.mode = mode;
            if (mode === 'percent') this.display = deductionMode(amount || '0', this.gross()).mode === 'percent' ? deductionMode(amount || '0', this.gross()).display : '0';
            else this.display = amount ? formatMoney(amount).replace(/^\$ /, '') : '';
            this.validate();
        },
        validate() {
            const field = this.$root.closest('[data-field]');
            const input = field.querySelector('input[type=text]');
            let message = '';
            try {
                if (this.mode === 'percent') {
                    const percent = parsePercentage(this.display);
                    this.canonical = this.gross() ? percentageOf(this.gross(), percent) : '';
                } else this.canonical = this.display === '' ? '' : parseMoneyInput(this.display);
                const allowedZero = this.$root.dataset.allowZero === 'true';
                const inRange = this.canonical && (allowedZero ? decimal(this.canonical).gte('0') && decimal(this.canonical).lte(MAX_AMOUNT) : validateAmount(this.canonical));
                if (this.canonical && !inRange) message = allowedZero ? 'El valor debe ser mayor o igual a $ 0.' : 'El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99.';
                if (!this.canonical && input.required) message = 'Este campo es obligatorio.';
            } catch (error) { this.canonical = ''; message = error.message; }
            const hidden = this.$root.querySelector('input[type=hidden][name]');
            if (hidden) hidden.value = this.canonical;
            this.preview = this.mode === 'percent' && this.canonical && !message ? '= ' + formatMoney(this.canonical) : '—';
            this.invalid = Boolean(message);
            input.setAttribute('aria-invalid', String(this.invalid));
            input.setCustomValidity(message);
            const error = field.querySelector('[data-field-error]');
            error.textContent = message;
            error.hidden = !message;
            this.$dispatch('money-change', { name: field.dataset.field, amount: this.canonical, valid: !message });
        },
    }));
    Alpine.data('tagInput', () => ({
        tags: [], draft: '',
        init() { const tags = JSON.parse(this.$root.dataset.tags || '[]'); this.tags = Array.isArray(tags) ? tags : []; },
        add() {
            const tag = this.draft.trim().toLocaleLowerCase('es-CO');
            const error = this.$root.closest('[data-field]').querySelector('[data-field-error]');
            const message = [...tag].length < 2 || [...tag].length > 30 ? 'Cada etiqueta debe tener entre 2 y 30 caracteres.' : this.tags.length >= 10 && !this.tags.includes(tag) ? 'Un gasto admite como máximo 10 etiquetas.' : '';
            error.textContent = message; error.hidden = !message;
            if (!message) { if (!this.tags.includes(tag)) this.tags.push(tag); this.draft = ''; }
        },
    }));
}

export function mountFoundation() {
    // Adaptador para las vistas existentes: conserva sus nombres de campo y contrato.
    window.scfpPreviewIncome = (prefix = '') => {
        const get = (name) => document.getElementById(prefix + name);
        try {
            const amount = get('monto_original').value || '0';
            const retention = percentageOf(amount, get('porcentaje_retencion').value || '0');
            const contributions = percentageOf(amount, get('porcentaje_aportes').value || '0');
            get('retencion_fuente').value = retention;
            get('aportes_descontados').value = contributions;
            const preview = document.getElementById('neto-preview');
            if (preview && !prefix) preview.textContent = formatMoney(subtractMoney(subtractMoney(amount, retention), contributions));
        } catch { /* Durante una entrada incompleta se conservan los valores anteriores. */ }
    };
    document.querySelectorAll('[data-percentage-amount]').forEach((input) => {
        input.step = 'any';
        if (!input.value) {
            try { input.value = percentageFrom(input.dataset.percentageAmount, document.getElementById('monto_original').value || '0'); } catch { input.value = ''; }
        }
    });
    document.querySelectorAll('[data-illustration]').forEach((host) => host.replaceChildren(renderIllustration(host.dataset.illustration)));
    document.querySelectorAll('[data-money]').forEach((host) => {
        try { host.textContent = formatMoney(host.dataset.money); }
        catch { host.textContent = '—'; }
    });
    document.querySelectorAll('[data-date]').forEach((host) => {
        try { host.textContent = formatDate(host.dataset.date); } catch { host.textContent = '—'; }
    });
    document.querySelectorAll('[data-date-no-future]').forEach((input) => { input.max = todayBogota(); });
    document.querySelectorAll('input[name=fecha], input[name=fecha_recepcion]').forEach((input) => { input.max = todayBogota(); });
    document.querySelectorAll('input[name=monto_original]').forEach((input) => { input.max = '999999999999.99'; });
    document.querySelectorAll('[data-category-name]').forEach((host) => {
        const visual = getCategoryVisual({ nombre: host.dataset.categoryName, icono: host.dataset.categoryIcon || undefined, color: host.dataset.categoryColor || undefined });
        host.style.color = /^var\(--cat-[a-z]+\)$/.test(visual.colorVar) ? visual.colorVar : 'var(--cat-slate)';
        host.prepend(renderIcon(visual.iconName, { size: 20 }));
    });
    // Mejora tablas existentes con etiquetas de columna para su presentación como tarjetas.
    document.querySelectorAll('.ui-content table').forEach((table) => {
        table.classList.add('ui-table');
        if (!table.parentElement.classList.contains('ui-table-scroll')) {
            const region = document.createElement('div'); region.className = 'ui-table-scroll'; region.tabIndex = 0;
            region.setAttribute('role', 'region'); region.setAttribute('aria-label', table.caption?.textContent || 'Tabla de registros');
            table.before(region); region.append(table);
        }
        table.setAttribute('role', 'table');
        table.querySelectorAll('thead, tbody').forEach((group) => group.setAttribute('role', 'rowgroup'));
        table.querySelectorAll('tr').forEach((row) => row.setAttribute('role', 'row'));
        table.querySelectorAll('th').forEach((cell) => { cell.scope = 'col'; cell.setAttribute('role', 'columnheader'); });
        table.querySelectorAll('td').forEach((cell) => cell.setAttribute('role', 'cell'));
        const headers = [...table.querySelectorAll('thead th')].map((header) => header.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((row) => [...row.children].forEach((cell, index) => { cell.dataset.label ||= headers[index] || ''; }));
    });
    // Vincula también los errores iniciales de Blade y los campos de las vistas heredadas.
    document.querySelectorAll('[data-field]').forEach((field) => {
        const input = [...field.querySelectorAll('input, select, textarea')].find((input) => input.id === field.dataset.field) ?? field.querySelector('input:not([type=hidden]), select, textarea');
        const error = field.querySelector('[data-field-error]');
        if (input && error) {
            input.id ||= field.querySelector('label')?.htmlFor || field.dataset.field;
            const described = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean)); described.add(error.id);
            input.setAttribute('aria-describedby', [...described].join(' '));
            input.setAttribute('aria-invalid', String(!error.hidden));
        }
    });
    document.querySelector('[aria-invalid=true]:not([disabled])')?.focus();
    document.querySelectorAll('label:not([for])').forEach((label, index) => {
        // Las etiquetas envolventes pertenecen a su propio control, nunca al primero del grupo.
        const ownInput = label.querySelector('input:not([type=hidden]), select, textarea');
        const siblings = label.parentElement.querySelectorAll('input:not([type=hidden]), select, textarea');
        const input = ownInput ?? (siblings.length === 1 ? siblings[0] : null);
        if (input) { input.id ||= `legacy-field-${index}`; label.htmlFor = input.id; }
    });
    const drawer = document.querySelector('#navigation-drawer');
    const openButton = document.querySelector('[data-open-drawer]');
    openButton?.addEventListener('click', () => { drawer.showModal(); openButton.setAttribute('aria-expanded', 'true'); });
    drawer?.addEventListener('close', () => { openButton.setAttribute('aria-expanded', 'false'); openButton.focus(); });
    drawer?.addEventListener('click', (event) => { if (event.target === drawer && event.clientX > drawer.getBoundingClientRect().right) drawer.close(); });
    matchMedia('(min-width: 768px)').addEventListener('change', (event) => { if (event.matches && drawer?.open) drawer.close(); });
    document.querySelector('[data-dismiss-toast]')?.addEventListener('click', () => { document.querySelector('#ui-toast').hidden = true; });
    installConfirmation();
    document.querySelectorAll('[data-page-size]').forEach((select) => select.addEventListener('change', () => select.form.requestSubmit()));
    window.addEventListener('pageshow', resetLoading);
    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || event.target.method === 'dialog' || event.target.target === '_blank') return;
        const loading = document.querySelector('[data-page-loading]');
        if (loading) { loading.hidden = false; document.querySelector('#main-content')?.setAttribute('aria-busy', 'true'); }
    });
}

function resetLoading() {
    const loading = document.querySelector('[data-page-loading]');
    if (loading) loading.hidden = true;
    document.querySelector('#main-content')?.setAttribute('aria-busy', 'false');
}

function installConfirmation() {
    const dialog = document.querySelector('#confirm-dialog');
    if (!dialog) return;
    const approved = new WeakSet();
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const destructive = form.querySelector('input[name=_method]')?.value.toUpperCase() === 'DELETE' || form.hasAttribute('data-confirm-delete') || event.submitter?.hasAttribute('data-confirm-delete');
        if (!destructive || approved.has(form)) { approved.delete(form); return; }
        event.preventDefault(); event.stopImmediatePropagation();
        const trigger = event.submitter;
        dialog.querySelector('#confirm-title').textContent = form.dataset.confirmTitle || 'Confirmar eliminación';
        dialog.querySelector('[value=confirm]').textContent = form.dataset.confirmLabel || 'Eliminar';
        let description = form.dataset.confirmDescription || '¿Deseas eliminar este registro?';
        if (form.dataset.confirmDate && form.dataset.confirmAmount) description = `${formatDate(form.dataset.confirmDate)} · ${form.dataset.confirmSource || ''} · ${formatMoney(form.dataset.confirmAmount)}. ${description}`;
        dialog.querySelector('#confirm-description').textContent = description;
        dialog.returnValue = '';
        dialog.showModal();
        dialog.addEventListener('close', () => {
            trigger?.focus();
            if (dialog.returnValue === 'confirm') { approved.add(form); form.requestSubmit(trigger || undefined); }
        }, { once: true });
    }, true);
}
