import { request, safeMessage } from '../api/index.js';
import { formatMoney } from '../utils/money.js';

export function mountPilaAutofill() {
    // Escuchar cambios en el selector de tipo de servicio / cotizante
    document.querySelectorAll('[data-pila-service-select]').forEach((select) => {
        const handler = () => {
            const prefix = select.dataset.targetPrefix || 'pila';
            const container = select.closest('[data-pila-autofill-container]');
            const rentistaBox = document.getElementById(`${prefix}-rentista-gastos-box`) || container?.querySelector('[data-pila-rentista-box]');
            const titleEl = container?.querySelector('[data-pila-autofill-title]');
            const descEl = container?.querySelector('[data-pila-autofill-desc]');
            const isRentista = select.value === 'RENTISTA_CAPITAL';

            if (rentistaBox) {
                if (isRentista) {
                    rentistaBox.hidden = false;
                    rentistaBox.style.display = 'block';
                } else {
                    rentistaBox.hidden = true;
                    rentistaBox.style.display = 'none';
                }
            }

            if (titleEl) {
                titleEl.textContent = isRentista
                    ? 'Autocompletar aportes para Rentista de Capital'
                    : 'Autocompletar desde ingresos de rentas de trabajo';
            }

            if (descEl) {
                descEl.textContent = isRentista
                    ? 'Consulta tus ingresos, descuenta los gastos asociados registrados y calcula automáticamente el IBC (40% sobre la base neta) y los aportes sugeridos.'
                    : 'Consulta tus honorarios o servicios del mes cotizado y calcula automáticamente los aportes sugeridos.';
            }
        };

        select.addEventListener('change', handler);
        // Ejecutar estado inicial
        handler();
    });

    document.querySelectorAll('[data-pila-autofill-btn]').forEach((button) => {
        const targetPrefix = button.dataset.targetPrefix || 'pila';
        const gastosInput = document.getElementById(`${targetPrefix}-gastos-asociados`);
        if (gastosInput && !gastosInput.dataset.pilaBound) {
            gastosInput.dataset.pilaBound = 'true';
            gastosInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    button.click();
                }
            });
        }

        button.addEventListener('click', async () => {
            const container = button.closest('[data-pila-autofill-container]');
            if (!container) return;

            const periodInput = document.getElementById(`${targetPrefix}-periodo`);
            const serviceSelect = document.getElementById(`${targetPrefix}-tipo-servicio`);
            const statusEl = container.querySelector('[data-pila-autofill-status]');
            const msgEl = container.querySelector('[data-pila-autofill-msg]');
            const actionEl = container.querySelector('[data-pila-autofill-action]');
            const detailsEl = container.querySelector('[data-pila-autofill-details]');
            const itemsEl = container.querySelector('[data-pila-autofill-items]');
            const metricIngresos = container.querySelector('[data-pila-metric-ingresos]');
            const metricGastosWrapper = container.querySelector('[data-pila-metric-gastos-wrapper]');
            const metricGastos = container.querySelector('[data-pila-metric-gastos]');
            const metricNetoWrapper = container.querySelector('[data-pila-metric-neto-wrapper]');
            const metricNeto = container.querySelector('[data-pila-metric-neto]');
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

            const isRentista = serviceSelect?.value === 'RENTISTA_CAPITAL';
            let gastosValue = '0';
            if (isRentista && gastosInput) {
                const wrapper = gastosInput.closest('[x-data]');
                const hiddenInput = wrapper?.querySelector('input[type=hidden][name="gastos_asociados"]');
                gastosValue = hiddenInput?.value || gastosInput.value || '0';
            }

            button.disabled = true;
            if (loadingSpan) loadingSpan.hidden = false;
            if (textSpan) textSpan.hidden = true;

            try {
                const targetUrl = new URL(button.dataset.url, window.location.origin);
                targetUrl.searchParams.set('periodo', periodo);
                targetUrl.searchParams.set('tipo_servicio', isRentista ? 'RENTISTA_CAPITAL' : 'PRESTACION_SERVICIOS');
                targetUrl.searchParams.set('es_rentista', isRentista ? 'true' : 'false');
                if (isRentista) {
                    targetUrl.searchParams.set('gastos', gastosValue);
                }

                const response = await request(targetUrl, { method: 'GET' });
                const result = response.data;

                if (!statusEl || !msgEl) return;
                statusEl.hidden = false;

                if (!result.tiene_ingresos) {
                    statusEl.dataset.state = 'warning';
                    msgEl.textContent = result.mensaje || (isRentista
                        ? `No se encontraron ingresos de rentas de capital o trabajo en el periodo ${periodo}. Puedes registrarlos en el módulo de ingresos o diligenciar el formulario manualmente.`
                        : `No se encontraron ingresos por honorarios o servicios en el periodo ${periodo}. Puedes registrarlos en el módulo de ingresos o diligenciar el formulario manualmente.`);
                    if (actionEl) actionEl.hidden = false;
                    if (detailsEl) detailsEl.hidden = true;
                } else {
                    statusEl.dataset.state = 'success';
                    msgEl.textContent = result.mensaje;
                    if (actionEl) actionEl.hidden = true;

                    if (metricIngresos) {
                        metricIngresos.textContent = formatMoney(result.total_ingresos_brutos || result.total_ingresos || '0');
                    }

                    if (result.tipo_cotizante === 'RENTISTA_CAPITAL' || isRentista) {
                        if (metricGastosWrapper) metricGastosWrapper.hidden = false;
                        if (metricGastos) metricGastos.textContent = `-${formatMoney(result.gastos_asociados || gastosValue || '0')}`;
                        if (metricNetoWrapper) metricNetoWrapper.hidden = false;
                        if (metricNeto) metricNeto.textContent = formatMoney(result.ingreso_neto || result.calculo?.ingreso_base || '0');
                    } else {
                        if (metricGastosWrapper) metricGastosWrapper.hidden = true;
                        if (metricNetoWrapper) metricNetoWrapper.hidden = true;
                    }

                    if (metricIbc && result.calculo?.ibc) {
                        metricIbc.textContent = formatMoney(result.calculo.ibc);
                    }
                    if (metricTotal && result.calculo?.total) {
                        metricTotal.textContent = formatMoney(result.calculo.total);
                    }

                    if (itemsEl && Array.isArray(result.ingresos)) {
                        itemsEl.replaceChildren();
                        for (const item of result.ingresos) {
                            const li = document.createElement('li');
                            li.className = 'pila-autofill-item';

                            const info = document.createElement('div');
                            const sourceSpan = document.createElement('span');
                            sourceSpan.className = 'pila-autofill-item-source';
                            sourceSpan.textContent = item.fuente || (isRentista ? 'Renta de capital' : 'Honorarios / Servicios');

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
