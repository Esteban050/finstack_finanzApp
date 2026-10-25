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
export const percentageOf = (amount, percent) => decimal(amount).times(decimal(percent)).div('100').toFixed(2);
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
