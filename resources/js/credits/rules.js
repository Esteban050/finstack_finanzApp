import { decimal, formatMoney } from '../utils/money.js';
import { canonicalDecimal } from '../finance/rules.js';

export const creditLabels = {LIBRE_INVERSION:'Libre inversión',LIBRANZA:'Libranza',VEHICULO:'Vehículo',VIVIENDA:'Vivienda',TARJETA_CREDITO:'Tarjeta de crédito',OTRO:'Otro',FRANCES:'Cuota fija',ALEMAN:'Abono constante a capital',AL_DIA:'Al día',EN_MORA:'En mora',PAGADO:'Pagado',PAGADA:'Pagada',PAGADA_PARCIAL:'Pagada parcialmente',VENCIDA:'Vencida',PENDIENTE:'Pendiente',CAPITAL:'Capital',ABONO_EXTRAORDINARIO:'Abono extraordinario',INTERES_CORRIENTE:'Interés corriente',INTERES_MORA:'Intereses de mora',SEGURO:'Seguro',CARGO:'Cargos',TOTAL:'Total'};
export const ratePercent = value => decimal(String(value)).times('100').toFixed(2).replace('.',',')+' %';
const integerPeso=value=>/^\d+(?:\.0{1,2})?$/.test(String(value))?String(value).split('.')[0]:value;
export function debtClassification(value) {
    if(value===null || value===undefined)return {label:'No calculable: registre sus ingresos',tone:'neutral'};
    const rate=decimal(String(value));
    return rate.lte('30')?{label:'Verde · bajo',tone:'success'}:rate.lte('40')?{label:'Amarillo · moderado',tone:'warning'}:{label:'Rojo · alto',tone:'danger'};
}
export function paymentPayload(values) {
    const result=Object.fromEntries(Object.entries(values).filter(([key,value])=>['fecha','valor','medio','referencia','aplicacion_excedente','reducir'].includes(key) && value!==''));
    if(result.aplicacion_excedente!=='ABONO_CAPITAL')delete result.reducir;
    if(result.valor!==undefined)result.valor=integerPeso(result.valor);
    return result;
}
export function creditPayload(values) {
    const payload={...values},errors={};
    for(const key of ['tasa','tasa_mora','seguro_porc_mensual']) {
        if(payload[key]===undefined || payload[key]===''){delete payload[key];continue;}
        try {payload[key]=canonicalDecimal(payload[key],{min:'0',precision:6});}catch(error){errors[key]=[error.message];}
    }
    for(const key of ['cargo_fijo','valor_inmueble'])if(payload[key]){payload[key]=integerPeso(payload[key]);if(!/^\d+$/.test(payload[key]))errors[key]=['Indica un valor en pesos enteros, sin centavos.'];}
    if(payload.tipo_tasa!=='NOMINAL')delete payload.capitalizacion;
    if(payload.tipo_credito!=='VIVIENDA'){delete payload.valor_inmueble;delete payload.es_vis;}
    if(payload.plazo_meses!==undefined)delete payload.numero_cuotas;
    return {payload,errors};
}
export function recordPayload(record) {
    const keys=['banco','proposito','tipo_credito','monto','tipo_tasa','capitalizacion','periodicidad','sistema_amortizacion','plazo_meses','numero_cuotas','fecha_inicio','fecha_primer_pago','seguro_porc_mensual','cargo_fijo','valor_inmueble','es_vis'];
    const payload=Object.fromEntries(keys.filter(key=>record[key]!==null && record[key]!==undefined).map(key=>[key,key.startsWith('fecha_')?record[key].slice(0,10):record[key]]));
    payload.tasa=record.tasa_ingresada;
    if(record.tasa_mora_ea!==null && record.tasa_mora_ea!==undefined)payload.tasa_mora=decimal(String(record.tasa_mora_ea)).times('100').toFixed(6);
    if(payload.plazo_meses!==undefined)delete payload.numero_cuotas;
    for(const key of ['cargo_fijo','valor_inmueble'])if(payload[key]!==undefined)payload[key]=integerPeso(payload[key]);
    return payload;
}
export function planSummary(plan) {
    return [['Primera cuota',plan.cuota],['Última cuota',plan.cuota_ultima],['Total de intereses',plan.total_intereses],['Capital',plan.total_capital],['Seguros y cargos',plan.total_seguros_cargos],['Total a pagar',plan.total_a_pagar]].filter(([,value])=>value!==undefined).map(([label,value])=>[label,formatMoney(String(value))]);
}

export function abonoComparison(plan, simulation) {
    const future = plan.filter(row => row.fecha.slice(0,10) > simulation.fecha);
    const replacement = simulation.plan_nuevo;
    const sum = (rows, field) => rows.reduce((total,row) => total.plus(String(row[field] ?? '0')), decimal('0')).toFixed(0);
    return {
        cuotaAntes: future[0]?.cuota ?? null,
        cuotaDespues: replacement?.[0]?.cuota ?? (simulation.cancela_credito ? '0' : null),
        cuotasAntes: future.length,
        cuotasDespues: replacement?.length ?? null,
        interesesAntes: sum(future, 'interes_pendiente'),
        interesesDespues: replacement === null ? null : sum(replacement, 'interes'),
    };
}
