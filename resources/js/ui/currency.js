import { request } from '../api/index.js';
import { formatDate } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

export function mountCurrencySelector() {
    document.querySelectorAll('form').forEach((form) => {
        const radios = form.querySelectorAll('[data-currency-radio]');
        if (!radios.length) return;

        const rateFields = form.querySelectorAll('[data-exchange-rate-field]');
        const trmBoxes = form.querySelectorAll('[data-trm-suggestion]');
        const trmHints = form.querySelectorAll('[data-trm-hint]');
        const unitSpans = form.querySelectorAll('[data-currency-unit]');
        const summaryUnits = form.querySelectorAll('[data-currency-summary-unit]');
        const dateInput = form.querySelector('[data-income-date], [data-expense-date], [name=fecha_apertura], [name=fecha_inicio]');
        const rateInput = form.querySelector('[name=tasa_cambio_usada]');

        let lastFetchedDate = null, version = 0, controller;

        const fetchTrm = async (date) => {
            if (!date || date === lastFetchedDate) return;
            const current = ++version; controller?.abort(); controller = new AbortController();
            try {
                const url = new URL('/tasas-cambio/trm', window.location.origin);
                url.searchParams.set('fecha', date);
                const response = await request(url, { method: 'GET', signal: controller.signal });
                const trmData = response?.data;
                if (current !== version || dateInput?.value !== date || form.querySelector('[data-currency-radio]:checked')?.value !== 'USD') return;
                if (!trmData?.valor) throw new Error();

                lastFetchedDate = date;
                const formatted = formatMoney(String(trmData.valor));
                const isPrev = Boolean(trmData.es_tasa_anterior);
                const hintText = `TRM oficial de referencia (${formatDate(trmData.fecha_consultada)}): ${formatted} COP por 1 USD${isPrev ? ' (tasa hábil anterior)' : ''}. Si no indicas una tasa personalizada, se usará automáticamente esta TRM.`;

                trmHints.forEach((hint) => {
                    hint.textContent = hintText;
                });
                if (rateInput && !rateInput.value) {
                    rateInput.placeholder = trmData.valor;
                }
            } catch (error) {
                if (current === version && error.name !== 'AbortError') trmHints.forEach(hint => { hint.textContent = 'No fue posible consultar la TRM de referencia. Reintenta al seleccionar la fecha; el servidor validará la tasa al guardar.'; });
            }
        };

        const updateCurrency = () => {
            const selected = form.querySelector('[data-currency-radio]:checked')?.value || 'COP';
            const isUsd = selected === 'USD';
            if (!isUsd) { version++; controller?.abort(); lastFetchedDate = null; }
            if (rateInput) rateInput.disabled = !isUsd;

            rateFields.forEach((field) => {
                field.hidden = !isUsd;
            });
            trmBoxes.forEach((box) => {
                box.hidden = !isUsd;
            });
            unitSpans.forEach((span) => {
                if (span.closest('[data-deduction="true"]')?.querySelector('[aria-pressed=true]')?.textContent === '%') return;
                span.textContent = isUsd ? 'USD' : 'COP';
            });
            summaryUnits.forEach((span) => {
                span.textContent = isUsd ? 'USD (se convierte a COP según la TRM)' : 'COP';
            });

            if (isUsd && dateInput?.value) {
                fetchTrm(dateInput.value);
            }
        };

        radios.forEach((radio) => {
            radio.addEventListener('change', updateCurrency);
        });

        if (dateInput) {
            dateInput.addEventListener('change', () => {
                const selected = form.querySelector('[data-currency-radio]:checked')?.value;
                if (selected === 'USD' && dateInput.value) {
                    lastFetchedDate = null;
                    fetchTrm(dateInput.value);
                }
            });
        }

        updateCurrency();
    });
}

