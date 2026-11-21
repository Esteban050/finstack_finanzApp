import { decimal } from '../utils/money.js';

// Conserva la simulación informativa existente; todos los importes son decimales.
// TODO(contrato): esta simulación adicional necesita un endpoint autoritativo propio.
export function simulateBenefits(base, selection) {
    const uvt = decimal(base.uvt);
    const dependent = selection.dependientes ? uvt.times('72').times(selection.personas || '1') : decimal('0');
    const invoice = selection.factura ? decimal(selection.compras || '0').times('0.01').clamp('0', uvt.times('240')) : decimal('0');
    const donation = selection.donaciones ? decimal(selection.donacion || '0').times('0.25').toDecimalPlaces(0) : decimal('0');
    const taxable = decimal(base.rlg).minus(dependent).minus(invoice).clamp('0', base.rlg).div(uvt);
    const brackets = [['1090','0','0','0'],['1700','1090','0.19','0'],['4100','1700','0.28','116'],['8670','4100','0.33','788'],['18970','8670','0.35','2296'],['31000','18970','0.37','5901'],[null,'31000','0.39','10352']];
    const [,floor,rate,fixed] = brackets.find(([ceiling]) => ceiling === null || taxable.lte(ceiling));
    const nonNegative = value => value.lt('0') ? decimal('0') : value;
    const tax = nonNegative(taxable.minus(floor).times(rate).plus(fixed).times(uvt).div('1000').toDecimalPlaces(0).times('1000').minus(donation));
    const balance = tax.minus(base.retenciones);
    return { dependientes: dependent.toFixed(2), factura: invoice.toFixed(2), donaciones: donation.toFixed(2), impuesto: tax.toFixed(2), saldo: balance.toFixed(2), ahorro: nonNegative(decimal(base.impuesto).minus(tax)).toFixed(2) };
}
