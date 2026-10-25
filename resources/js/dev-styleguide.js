import '../css/app.css';
import '../css/styleguide.css';
import './app.js';
import { mapFieldErrors } from './ui/foundation.js';
import { validateAmount } from './utils/money.js';
import { todayBogota } from './utils/date.js';
import { contrastCases, maximumKitBackdrop } from './utils/contrast.js';
import glowSource from '../assets/backgrounds/bg-glow.svg?raw';
import gridSource from '../assets/backgrounds/bg-grid.svg?raw';
import { validateIncome, validateRecurrence, validateSource } from './income/rules.js';
import { moneyInputErrors } from './income/index.js';

// Esta entrada solo es referenciada por el middleware de Vite en desarrollo.
if (!import.meta.env.DEV) throw new Error('La guía de estilo es exclusiva de desarrollo.');

const rootStyle = getComputedStyle(document.documentElement);
const token = (name) => rootStyle.getPropertyValue('--' + name).trim();
const swatches = document.querySelector('[data-token-swatches]');
for (const name of ['ui-bg', 'ui-surface', 'ui-raised', 'ui-text', 'ui-muted', 'accent', 'ui-border', 'ui-border-strong', 'ui-danger']) {
    const item = document.createElement('div'); item.className = 'sg-swatch';
    const color = document.createElement('span'); color.style.background = `var(--${name})`;
    const label = document.createElement('code'); label.textContent = '--' + name;
    const value = document.createElement('small'); value.textContent = token(name);
    item.append(color, label, value); swatches.append(item);
}
for (const size of [1, 2, 3, 4, 6, 8, 12, 16]) {
    const item = document.createElement('div'); const bar = document.createElement('span');
    bar.style.width = `var(--ui-space-${size})`;
    const label = document.createElement('span'); label.textContent = `${size * 4} px`; label.style.background = 'none'; label.style.height = 'auto';
    item.append(bar, label); document.querySelector('[data-spacing-swatches]').append(item);
}

const backdrop = document.querySelector('.ui-shell-backdrop');
const backdropColor = maximumKitBackdrop(token('ui-bg'), glowSource, gridSource, Number(getComputedStyle(backdrop, '::before').opacity), Number(getComputedStyle(backdrop, '::after').opacity));
const report = contrastCases(token, backdropColor);
const reportTable = document.createElement('table'); reportTable.className = 'ui-table';
const caption = document.createElement('caption'); caption.textContent = 'Contraste de los tokens reales. Texto: ≥4,5:1. Controles y foco: ≥3:1.'; reportTable.append(caption);
const head = document.createElement('thead'); const headRow = document.createElement('tr');
for (const text of ['Primer plano / fondo', 'Relación', 'Resultado']) { const th = document.createElement('th'); th.scope = 'col'; th.textContent = text; headRow.append(th); }
head.append(headRow); reportTable.append(head);
const body = document.createElement('tbody');
for (const item of report) {
    const row = document.createElement('tr');
    const values = [`${item.foreground} / ${item.background}${item.usage ? ' · ' + item.usage : ''}`, item.ratio.toFixed(2) + ':1', item.ratio >= item.minimum ? 'AA · Cumple' : 'No cumple'];
    values.forEach((value, index) => { const cell = document.createElement('td'); cell.dataset.label = ['Combinación', 'Relación', 'Resultado'][index]; cell.textContent = value; row.append(cell); });
    body.append(row);
}
reportTable.append(body); document.querySelector('[data-contrast-report]').append(reportTable);

function toast(message) {
    document.querySelector('[data-toast-message]').textContent = message;
    document.querySelector('#ui-toast').hidden = false;
}
document.querySelector('[data-demo-toast]').addEventListener('click', () => toast('Mensaje de ejemplo. No se guardó ningún registro.'));
document.querySelector('[data-demo-retry]').addEventListener('click', () => toast('Reintento de ejemplo. No se realizó ninguna consulta.'));
document.querySelector('[data-show-field-errors]').addEventListener('click', (event) => {
    mapFieldErrors(event.target.closest('details'), {
        'sg-invalid-date': ['Este campo es obligatorio.'],
        'sg-invalid-payment': ['Este campo es obligatorio.'],
    });
});
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href^="/"]');
    if (!link) return;
    event.preventDefault();
    toast('Enlace de ejemplo del AppShell. Estás en la guía de desarrollo.');
});
document.addEventListener('submit', (event) => {
    if (event.target.method === 'dialog') return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (event.target.id === 'sg-delete') {
        document.querySelector('#sg-delete-result').textContent = 'Confirmación recibida. No se eliminó ningún registro: es un ejemplo.';
    } else if (event.target.id === 'sg-form') {
        const form = event.target; const values = new FormData(form); const errors = {};
        if (!validateAmount(values.get('sg-amount'))) errors['sg-amount'] = ['El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99.'];
        if (!values.get('sg-date')) errors['sg-date'] = ['Este campo es obligatorio.'];
        else if (values.get('sg-date') > todayBogota()) errors['sg-date'] = ['La fecha no puede ser posterior a hoy.'];
        if (!values.get('sg-payment')) errors['sg-payment'] = ['Este campo es obligatorio.'];
        mapFieldErrors(form, errors);
        const result = document.querySelector('#sg-form-result');
        result.hidden = false; result.textContent = Object.keys(errors).length ? 'Revisa los campos indicados.' : 'Ejemplo validado correctamente. No se guardó ningún registro.';
    } else if (event.target.id === 'sg-income-example') {
        const form = event.target, values = Object.fromEntries(new FormData(form));
        const errors = { ...validateIncome(values), ...validateRecurrence(values, form.querySelector('[data-recurrence-fields]').dataset.limit), ...moneyInputErrors(form) };
        mapFieldErrors(form, errors);
        document.querySelector('#sg-income-result').textContent = Object.keys(errors).length ? 'Revisa los campos indicados.' : 'Ejemplo validado. No se guardó ningún ingreso.';
    } else if (event.target.id === 'sg-source-example') {
        const errors = validateSource(Object.fromEntries(new FormData(event.target)));
        mapFieldErrors(event.target, errors);
        document.querySelector('#sg-source-result').textContent = Object.keys(errors).length ? 'Revisa los campos indicados.' : 'Fuente validada. No se guardó ningún registro.';
    } else toast('Control de paginación de ejemplo. No se realizó ninguna consulta.');
}, true);
