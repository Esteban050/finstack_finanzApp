import { decimal, formatMoney, formatQuantity, MAX_AMOUNT } from '../utils/money.js';
import { request } from '../api/index.js';
import { mapFieldErrors } from '../ui/foundation.js';
import { moneyInputErrors } from '../income/index.js';
import { simulateBenefits } from './benefits.js';

export function rentaPayload(form) {
    const values = Object.fromEntries(new FormData(form));
    return Object.fromEntries(['patrimonio_bruto_cop', 'ingresos_brutos_cop', 'consumos_tarjeta_cop', 'compras_totales_cop', 'consignaciones_cop'].map(key => [key, values[key] || '0']));
}

export function validateRentaAmounts(values) {
    const errors = {};
    for (const [name, raw] of Object.entries(values)) {
        try { const value = decimal(raw); if (value.lt('0') || value.gt(MAX_AMOUNT) || value.decimalPlaces() > 2) throw new Error(); }
        catch { errors[name] = ['Indica un valor entre $ 0 y $ 999.999.999.999,99, con máximo dos decimales.']; }
    }
    return errors;
}

export function complementaryTotal(base, adjustment) {
    return decimal(base).plus(adjustment || '0').toFixed(2);
}

export function registerRenta(Alpine) {
    window.simulateRentaBenefits = simulateBenefits;
    Alpine.data('rentaCalculator', () => ({
        result: null, calculating: false, message: '', _version: 0, _controller: null,
        invalidate() { this._version++; this._controller?.abort(); this.result = null; this.calculating = false; this.message = ''; },
        money(value) { try { return formatMoney(String(value)); } catch { return '—'; } },
        quantity(value) { try { return formatQuantity(String(value)); } catch { return '—'; } },
        get criteria() { return Object.values(this.result?.obligacion?.criterios || {}); },
        async calculate() {
            if (this.calculating) return;
            const form = this.$root.querySelector('form');
            const payload = rentaPayload(form);
            const errors = { ...validateRentaAmounts(payload), ...moneyInputErrors(form) };
            mapFieldErrors(form, errors);
            if (Object.keys(errors).length) return;
            const version = ++this._version;
            this._controller?.abort(); this._controller = new AbortController();
            this.calculating = true; this.message = ''; this.result = null;
            try {
                const result = await request(this.$root.dataset.calculateUrl, { method: 'POST', data: payload, signal: this._controller.signal });
                if (version !== this._version) return;
                if (!result.obligacion || !result.liquidacion) throw new Error('No fue posible interpretar el resultado. Intenta nuevamente.');
                this.result = result;
                this.$nextTick(() => this.$root.querySelector('[data-renta-result]')?.focus());
            } catch (error) {
                if (version !== this._version || error.name === 'AbortError') return;
                this.message = error.message; mapFieldErrors(form, error.errors || {});
            } finally { if (version === this._version) this.calculating = false; }
        },
        destroy() { this._controller?.abort(); },
    }));
    Alpine.data('rentaComplementary', () => ({
        patrimonioConsolidado: '0', ingresosConsolidados: '0', pisoInversiones: '0',
        ajustePatrimonio: '0', ajusteIngresos: '0', consumosTarjeta: '0', comprasTotales: '0', consignaciones: '0',
        invalidFields: {},
        init() { Object.assign(this, JSON.parse(this.$root.dataset.rentaInitial)); },
        update(detail) {
            const keys = { c1_ajuste_manual_cop: 'ajustePatrimonio', c2_ajuste_manual_cop: 'ajusteIngresos', c3_consumos_tarjeta_cop: 'consumosTarjeta', c4_compras_totales_cop: 'comprasTotales', c5_consignaciones_declaradas_cop: 'consignaciones' };
            if (!keys[detail.name]) return;
            this[keys[detail.name]] = detail.amount;
            this.invalidFields[detail.name] = !detail.valid;
        },
        get patrimonioFinal() { return complementaryTotal(this.patrimonioConsolidado, this.ajustePatrimonio); },
        get ingresosFinales() { return complementaryTotal(this.ingresosConsolidados, this.ajusteIngresos); },
        get patrimonioInvalido() { return decimal(this.patrimonioFinal).lt('0'); },
        get ingresosInvalidos() { return decimal(this.ingresosFinales).lt('0'); },
        get hasAdjustments() { return !decimal(this.ajustePatrimonio || '0').isZero() || !decimal(this.ajusteIngresos || '0').isZero(); },
        get c5Evaluado() { return decimal(this.pisoInversiones).gte(this.consignaciones || '0') ? this.pisoInversiones : this.consignaciones; },
        money(value) { try { return formatMoney(String(value)); } catch { return '—'; } },
        validate(event) {
            const form = event.target, errors = moneyInputErrors(form);
            if (this.patrimonioInvalido) errors.c1_ajuste_manual_cop = ['El ajuste no puede dejar el patrimonio final por debajo de cero.'];
            if (this.ingresosInvalidos) errors.c2_ajuste_manual_cop = ['El ajuste no puede dejar los ingresos finales por debajo de cero.'];
            const note = form.elements.justificacion_ajuste.value.trim();
            if (this.hasAdjustments && (note.length < 10 || note.length > 500)) errors.justificacion_ajuste = ['Explica el ajuste con entre 10 y 500 caracteres.'];
            mapFieldErrors(form, errors);
            if (Object.keys(errors).length) event.preventDefault();
        },
    }));
}
