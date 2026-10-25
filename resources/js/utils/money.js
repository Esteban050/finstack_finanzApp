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
export const estimateCOP = (amount, trm) => decimal(amount).times(decimal(trm)).toFixed(2);
export const percentageOf = (amount, percent) => decimal(amount).times(decimal(percent)).div('100').toFixed(2);
export const percentageFrom = (amount, total) => decimal(total).isZero() ? '0' : decimal(amount).div(decimal(total)).times('100').toFixed(24);

export function parseMoneyInput(value) {
    if (typeof value !== 'string' || !/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/.test(value)) {
        throw new TypeError('RNG-02: usa miles con punto y decimales con coma.');
    }
    return decimal(value.replaceAll('.', '').replace(',', '.')).toFixed(2);
}

export function formatMoney(amount, currency = 'COP') {
    if (!['COP', 'USD'].includes(currency)) throw new RangeError('Moneda no soportada.');
    const value = decimal(amount);
    const [whole, cents] = value.abs().toFixed(2).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const fraction = currency === 'USD' || cents !== '00' ? ',' + cents : '';
    return `${value.isNegative() && !value.isZero() ? '-' : ''}${currency === 'USD' ? 'US$' : '$'} ${grouped}${fraction}`;
}

export function formatTRM(rate) {
    const value = decimal(rate);
    if (!value.gt('0')) throw new RangeError('La TRM debe ser mayor que 0.');
    const [whole, fraction] = value.toFixed().split('.');
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction ? ',' + fraction : '') + ' COP/USD';
}
