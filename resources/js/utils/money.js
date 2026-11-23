import Decimal from 'decimal.js';

const Money = Decimal.clone({ precision: 40, rounding: Decimal.ROUND_HALF_UP });
export const MAX_AMOUNT = '999999999999.99';

export function decimal(value) {
    if (typeof value !== 'string' || !/^-?\d+(?:\.\d+)?$/.test(value)) {
        throw new TypeError('El monto debe ser un string decimal con punto, sin separadores de miles.');
    }
    return new Money(value);
}

export function validateAmount(value) {
    try {
        const amount = decimal(value);
        return amount.gt('0') && amount.lte(MAX_AMOUNT) && amount.decimalPlaces() <= 2;
    } catch { return false; }
}

export const addMoney = (a, b) => decimal(a).plus(decimal(b)).toFixed(2);
export const subtractMoney = (a, b) => decimal(a).minus(decimal(b)).toFixed(2);
export function moneyCents(value) {
    if (typeof value !== 'string' || !/^-?\d+(?:\.\d{1,2})?$/.test(value)) throw new TypeError('Indica un monto con máximo dos decimales.');
    const negative = value.startsWith('-');
    const [whole, fraction = ''] = value.replace(/^-/, '').split('.');
    const cents = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    return negative ? -cents : cents;
}
export function centsMoney(cents) {
    const absolute = cents < 0n ? -cents : cents;
    return (cents < 0n ? '-' : '') + (absolute / 100n) + '.' + String(absolute % 100n).padStart(2, '0');
}
export function parsePercentage(value) {
    if (typeof value !== 'string' || !/^\d{1,3}(?:,\d{1,2})?$/.test(value) || moneyCents(value.replace(',', '.')) > 10000n) throw new TypeError('Indica un porcentaje entre 0 y 100, con máximo dos decimales.');
    return value.replace(',', '.');
}
export function percentageOf(amount, percent) {
    const cents = moneyCents(amount), basis = moneyCents(percent);
    if (cents < 0n || basis < 0n || basis > 10000n) throw new RangeError('El porcentaje debe estar entre 0 y 100.');
    return centsMoney((cents * basis + 5000n) / 10000n);
}
export function deductionMode(amount, gross) {
    try {
        const total = moneyCents(gross), value = moneyCents(amount);
        if (total <= 0n || value < 0n || value > total) return {mode: 'money', display: amount};
        const basis = (value * 10000n + total / 2n) / total;
        const percent = centsMoney(basis);
        return percentageOf(gross, percent) === centsMoney(value) ? {mode: 'percent', display: percent.replace('.', ',')} : {mode: 'money', display: amount};
    } catch { return {mode: 'money', display: amount}; }
}
export const percentageFrom = (amount, total) => decimal(total).isZero() ? '0' : decimal(amount).div(decimal(total)).times('100').toFixed(24);

export function formatQuantity(amount) {
    const [whole, fraction] = decimal(amount).toFixed(8).split('.');
    const tail = fraction.replace(/0+$/, '');
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (tail ? ',' + tail : '');
}

export function normalizeMoneyInput(value) {
    return typeof value === 'string' ? value.replace(/[$\s\u00a0\u202f]/g, '').replace(/[’']/g, '.') : value;
}
// RNG-09: el máximo es $ 999.999.999.999,99, es decir, 12 dígitos enteros y 2 decimales.
// Con ese límite de dígitos el valor nunca puede pasarse del tope.
export const MAX_INTEGER_DIGITS = 12;
export const MAX_DECIMAL_DIGITS = 2;

/** true si el texto que quedaría en el campo supera los dígitos permitidos. */
export function exceedsMoneyDigits(value, maxDecimals = MAX_DECIMAL_DIGITS) {
    const raw = normalizeMoneyInput(String(value ?? ''));
    const [whole, ...rest] = raw.split(',');
    const enteros = whole.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const decimales = rest.join('').replace(/\D/g, '');
    return enteros.length > MAX_INTEGER_DIGITS || decimales.length > maxDecimals;
}

/**
 * Texto que se puede insertar en un campo de dinero: solo dígitos, puntos y comas
 * (y el signo menos si el campo admite negativos). Al pegar se quitan el símbolo $
 * y los espacios de un valor copiado como "$ 1.000"; cualquier otro carácter
 * (letras, símbolos) hace que se rechace. Devuelve el texto limpio o null.
 */
export function cleanMoneyInsert(text, { allowNegative = false, pasted = false } = {}) {
    let limpio = String(text ?? '');
    if (pasted) limpio = normalizeMoneyInput(limpio);
    const permitido = allowNegative ? /^[\d.,-]*$/ : /^[\d.,]*$/;
    return permitido.test(limpio) ? limpio : null;
}

export function parseMoneyInput(value) {
    value = normalizeMoneyInput(value);
    if (typeof value !== 'string' || !/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/.test(value)) {
        throw new TypeError('Usa puntos para los miles y coma para los decimales.');
    }
    return decimal(value.replaceAll('.', '').replace(',', '.')).toFixed(2);
}

export function formatMoney(amount) {
    const value = decimal(amount);
    const [whole, cents] = value.abs().toFixed(2).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const fraction = cents !== '00' ? ',' + cents : '';
    return `${value.isNegative() && !value.isZero() ? '-' : ''}$\u00a0${grouped}${fraction}`;
}

// Separadores únicamente de presentación; el parser conserva el decimal canónico.
export function formatMoneyDraft(value, { regroup = false } = {}) {
    const raw = normalizeMoneyInput(String(value));
    if (raw === '' || raw === '-') return raw;
    if (!/^-?[\d.]+(?:,\d*)?$/.test(raw)) return String(value);
    const [whole, fraction] = raw.split(',');
    const sign = whole.startsWith('-') ? '-' : '';
    if (!regroup && whole.includes('.') && !/^-?\d{1,3}(?:\.\d{3})+$/.test(whole)) return String(value);
    const digits = whole.replace(/[-.]/g, '');
    if (!digits) return String(value);
    return sign + digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction === undefined ? '' : ',' + fraction);
}

// Tasas, precios y cantidades conservan su precisión contractual, sin pasar por Number.
export function parseLocalizedValue(value, { precision = 2, min, max } = {}) {
    const raw = normalizeMoneyInput(value);
    const fraction = String(precision) === '0' ? '' : `(?:,\\d{1,${precision}})?`;
    if (typeof raw !== 'string' || !new RegExp(`^-?(?:\\d+|\\d{1,3}(?:\\.\\d{3})+)${fraction}$`).test(raw)) {
        throw new TypeError(`Usa puntos para los miles y coma para los decimales; máximo ${precision} decimales.`);
    }
    const canonical = raw.replaceAll('.', '').replace(',', '.');
    const amount = decimal(canonical);
    if (min !== undefined && amount.lt(String(min))) throw new RangeError(`El valor debe ser mayor o igual a ${formatQuantity(String(min))}.`);
    if (max !== undefined && amount.gt(String(max))) throw new RangeError(`El valor no puede superar ${formatQuantity(String(max))}.`);
    return canonical;
}

export function formatCanonicalValue(value) {
    const raw = String(value);
    decimal(raw);
    const [whole, fraction] = raw.split('.');
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction ? ',' + fraction : '');
}
