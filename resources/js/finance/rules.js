import { decimal, MAX_AMOUNT, formatMoney } from '../utils/money.js';
import { todayBogota } from '../utils/date.js';

export function canonicalDecimal(value, { min = '0', max, precision = 2 } = {}) {
    if (typeof value !== 'string' || !new RegExp(`^-?\\d+(?:,\\d{1,${precision}})?$`).test(value)) throw new Error(`Usa coma decimal y máximo ${precision} decimales, sin separadores de miles.`);
    const result = value.replace(',', '.');
    if (decimal(result).lt(min) || (max !== undefined && decimal(result).gt(max))) throw new Error(max === undefined ? `El valor debe ser mayor o igual a ${min}.` : `El valor debe estar entre ${min.replace('.', ',')} y ${max.replace('.', ',')}.`);
    return result;
}

export function validateFinance(values, kind) {
    const errors = {}, fail = (field, message) => { errors[field] = [message]; };
    if (kind === 'investment') {
        if ([...String(values.nombre || '').trim()].length < 3 || [...String(values.nombre || '')].length > 60) fail('nombre', 'El nombre debe tener entre 3 y 60 caracteres.');
        if (values.fecha_apertura > todayBogota()) fail('fecha_apertura', 'La fecha de apertura no puede ser futura.');
        if (values.fecha_vencimiento && values.fecha_vencimiento <= values.fecha_apertura) fail('fecha_vencimiento', 'El vencimiento debe ser posterior a la apertura.');
    }
    if (kind === 'simulation') {
        if (!values.alternativas?.length || values.alternativas.length > 4) fail('alternativas', 'Compara entre una y cuatro alternativas.');
        values.alternativas?.forEach((alternative, index) => {
            try {
                if (decimal(alternative.monto_inicial || '0').isZero() && decimal(alternative.aporte_periodico || '0').isZero()) fail(`alternativas.${index}.monto_inicial`, 'Indica un monto inicial o un aporte periódico mayor que cero.');
            } catch { fail(`alternativas.${index}.monto_inicial`, 'Revisa el monto de la alternativa.'); }
        });
    }
    for (const [key, value] of Object.entries(values)) {
        if (['salario','ingreso_base','salud','pension','fsp','arl','intereses_mora','retencion_fuente','monto','monto_inicial','valor','retencion'].includes(key) && value !== '') {
            try { if (decimal(value).lt('0') || decimal(value).gt(MAX_AMOUNT)) fail(key, 'El valor debe estar entre $ 0 y $ 999.999.999.999,99.'); }
            catch { fail(key, 'Revisa el monto.'); }
        }
    }
    return errors;
}

export function nestedFinanceValues(entries) {
    const result = {}, alternatives = new Map();
    for (const [key, value] of entries) {
        const match = /^alternativas\[(\d+)\]\[([a-z_]+)\]$/.exec(key);
        if (match) { if (!alternatives.has(match[1])) alternatives.set(match[1], {}); alternatives.get(match[1])[match[2]] = value; }
        else result[key] = value;
    }
    if (alternatives.size) result.alternativas = [...alternatives.values()];
    return result;
}

export function parameterText(key, value) {
    if (value === null) return 'No aplica';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (key === 'valor_smmlv') return formatMoney(String(value));
    if (/^(tarifa_|porc_|ibc_)/.test(key)) return decimal(String(value)).toFixed(2).replace('.', ',');
    return String(value);
}
