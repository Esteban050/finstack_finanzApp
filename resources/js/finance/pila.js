import { request, safeMessage } from '../api/index.js';
import { formatMoney } from '../utils/money.js';

export function mountPilaAutofill() {
    document.querySelectorAll('[data-pila-autofill-btn]').forEach((button) => {
        button.addEventListener('click', async () => {
            const container = button.closest('[data-pila-autofill-container]');
            if (!container) return;

            const targetPrefix = button.dataset.targetPrefix || 'pila';
            const periodInput = document.getElementById(`${targetPrefix}-periodo`);
            const statusEl = container.querySelector('[data-pila-autofill-status]');
            const msgEl = container.querySelector('[data-pila-autofill-msg]');
            const actionEl = container.querySelector('[data-pila-autofill-action]');
            const detailsEl = container.querySelector('[data-pila-autofill-details]');
            const itemsEl = container.querySelector('[data-pila-autofill-items]');
            const metricIngresos = container.querySelector('[data-pila-metric-ingresos]');
            const metricIbc = container.querySelector('[data-pila-metric-ibc]');
            const metricTotal = container.querySelector('[data-pila-metric-total]');
            const loadingSpan = button.querySelector('[data-pila-autofill-btn-loading]');
            const textSpan = button.querySelector('[data-pila-autofill-btn-text]');

            const periodo = periodInput?.value?.trim();
            if (!periodo) {
                if (statusEl && msgEl) {
                    statusEl.hidden = false;
                    statusEl.dataset.state = 'warning';
                    msgEl.textContent = 'Selecciona primero un periodo cotizado (año y mes) para consultar los ingresos asociados.';
                    if (actionEl) actionEl.hidden = true;
                    if (detailsEl) detailsEl.hidden = true;
                }
                periodInput?.focus();
                return;
            }

            button.disabled = true;
            if (loadingSpan) loadingSpan.hidden = false;
            if (textSpan) textSpan.hidden = true;

            try {
                const targetUrl = new URL(button.dataset.url, window.location.origin);
                targetUrl.searchParams.set('periodo', periodo);

                const response = await request(targetUrl, { method: 'GET' });
                const result = response.data;

                if (!statusEl || !msgEl) return;
                statusEl.hidden = false;

                if (!result.tiene_ingresos) {
                    statusEl.dataset.state = 'warning';
                    msgEl.textContent = result.mensaje || `No se encontraron ingresos por honorarios o servicios en el periodo ${periodo}. Puedes registrarlos en el módulo de ingresos o diligenciar el formulario manualmente.`;
                    if (actionEl) actionEl.hidden = false;
                    if (detailsEl) detailsEl.hidden = true;
                } else {
                    statusEl.dataset.state = 'success';
                    msgEl.textContent = result.mensaje;
                    if (actionEl) actionEl.hidden = true;

                    if (metricIngresos) metricIngresos.textContent = formatMoney(result.total_ingresos || result.total_ingresos_brutos || '0');
                    if (metricIbc && result.calculo?.ibc) metricIbc.textContent = formatMoney(result.calculo.ibc);
                    if (metricTotal && result.calculo?.total) metricTotal.textContent = formatMoney(result.calculo.total);

                    if (itemsEl && Array.isArray(result.ingresos)) {
                        itemsEl.replaceChildren();
                        for (const item of result.ingresos) {
                            const li = document.createElement('li');
                            li.className = 'pila-autofill-item';

                            const info = document.createElement('div');
                            const sourceSpan = document.createElement('span');
                            sourceSpan.className = 'pila-autofill-item-source';
                            sourceSpan.textContent = item.fuente || 'Honorarios / Servicios';

                            const dateSpan = document.createElement('span');
                            dateSpan.className = 'pila-autofill-item-date';
                            dateSpan.textContent = ` (${item.fecha || periodo})`;

                            info.append(sourceSpan, dateSpan);

                            const amountEl = document.createElement('strong');
                            amountEl.className = 'pila-autofill-item-amount';
                            amountEl.textContent = formatMoney(item.monto);

                            li.append(info, amountEl);
                            itemsEl.append(li);
                        }
                    }
                    if (detailsEl) detailsEl.hidden = false;

                    if (result.calculo) {
                        const concepts = ['salud', 'pension', 'fsp', 'arl', 'intereses_mora'];
                        for (const concept of concepts) {
                            const rawValue = result.calculo[concept] ?? '0';
                            const input = document.getElementById(`${targetPrefix}-${concept}`);
                            if (!input) continue;

                            const wrapper = input.closest('[x-data]');
                            const formatted = formatMoney(rawValue).replace(/^\$\s*/, '');

                            if (window.Alpine && wrapper) {
                                const alpineData = window.Alpine.$data(wrapper);
                                if (alpineData && typeof alpineData.validate === 'function') {
                                    alpineData.canonical = rawValue;
                                    alpineData.display = formatted;
                                    alpineData.validate();
                                }
                            }

                            input.value = formatted;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            input.dispatchEvent(new Event('blur', { bubbles: true }));

                            const hidden = wrapper?.querySelector(`input[type=hidden][name="${concept}"]`);
                            if (hidden) hidden.value = rawValue;
                        }
                    }
                }
            } catch (error) {
                if (statusEl && msgEl) {
                    statusEl.hidden = false;
                    statusEl.dataset.state = 'error';
                    msgEl.textContent = safeMessage(error.message, 'No fue posible consultar los ingresos del periodo.');
                    if (actionEl) actionEl.hidden = true;
                    if (detailsEl) detailsEl.hidden = true;
                }
            } finally {
                button.disabled = false;
                if (loadingSpan) loadingSpan.hidden = true;
                if (textSpan) textSpan.hidden = false;
            }
        });
    });
}
