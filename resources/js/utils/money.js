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

export function parseMoneyInput(value) {
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
    return `${value.isNegative() && !value.isZero() ? '-' : ''}$ ${grouped}${fraction}`;
}
