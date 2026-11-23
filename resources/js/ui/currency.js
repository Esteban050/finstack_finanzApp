import { request } from '../api/index.js';
import { formatDate } from '../utils/date.js';
import { formatQuantity } from '../utils/money.js';

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
        const usd = () => form.querySelector('[data-currency-radio]:checked')?.value === 'USD';
        const describe = (message) => trmHints.forEach(hint => { hint.textContent = message; });
        let active;

        const updateReference = async () => {
            active?.abort();
            active = undefined;
            if (!usd() || !trmHints.length) {
                describe('');
                return;
            }

            const date = dateInput?.value;
            if (!date) {
                describe('Selecciona la fecha para consultar la TRM.');
                return;
            }

            const lookup = new AbortController();
            active = lookup;
            describe('Consultando la TRM para la fecha seleccionada…');
            try {
                const url = new URL('/tasas-cambio/trm', window.location.origin);
                url.searchParams.set('fecha', date);
                const { data } = await request(url, { signal: lookup.signal });
                if (active !== lookup || dateInput.value !== date || !usd()) return;
                if (!data?.valor) throw new Error('Falta el valor de la TRM');

                const rate = '$ ' + formatQuantity(String(data.valor));
                const source = data.es_tasa_anterior ? ' (tasa anterior disponible)' : '';
                describe('TRM de referencia para ' + formatDate(date) + ': ' + rate + ' COP por 1 USD' + source + '. Se aplicará si dejas la tasa manual vacía.');
            } catch (error) {
                if (active !== lookup || dateInput.value !== date || !usd() || error.name === 'AbortError') return;
                describe(error.status === 503
                    ? 'No hay TRM disponible para esta fecha. Ingresa la tasa de cambio manualmente.'
                    : 'No se pudo consultar la TRM. Puedes indicar la tasa manualmente o intentar con otra fecha.');
            }
        };

        const updateCurrency = () => {
            const isUsd = usd();
            if (rateInput) rateInput.disabled = !isUsd;
            rateFields.forEach(field => { field.hidden = !isUsd; });
            trmBoxes.forEach(box => { box.hidden = !isUsd; });
            unitSpans.forEach((span) => {
                if (span.closest('[data-deduction="true"]')?.querySelector('[aria-pressed=true]')?.textContent === '%') return;
                span.textContent = isUsd ? 'USD' : 'COP';
            });
            summaryUnits.forEach((span) => {
                span.textContent = isUsd ? 'USD (se convierte a COP según la TRM)' : 'COP';
            });
            updateReference();
        };

        radios.forEach(radio => radio.addEventListener('change', updateCurrency));
        dateInput?.addEventListener('change', updateReference);
        updateCurrency();
    });
}
