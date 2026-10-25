import { decimal, validateAmount, MAX_AMOUNT, subtractMoney } from '../utils/money.js';
import { todayBogota } from '../utils/date.js';

// RN-020.2: único mapeo de sugerencias; los tipos siguen viniendo del catálogo.
const suggested = {
    salario: 'RENTAS_TRABAJO', honorarios: 'RENTAS_TRABAJO',
    'honorarios y servicios': 'RENTAS_TRABAJO', 'honorarios/servicios': 'RENTAS_TRABAJO', 'honorarios profesionales': 'RENTAS_TRABAJO',
    arrendamientos: 'RENTAS_CAPITAL', 'rendimientos financieros': 'RENTAS_CAPITAL',
    pension: 'PENSIONES', dividendos: 'DIVIDENDOS',
};
export function suggestClassification(name) {
    return suggested[name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLocaleLowerCase('es-CO')] ?? '';
}
export function validateSource(values, existing = [], currentId = null) {
    const errors = {};
    const name = (values.nombre ?? '').trim();
    if (name.length < 3 || name.length > 60) errors.nombre = ['El nombre debe tener entre 3 y 60 caracteres.'];
    else if (existing.some((source) => source.id !== currentId && source.nombre.trim().toLocaleLowerCase('es-CO') === name.toLocaleLowerCase('es-CO'))) errors.nombre = ['Ya existe una fuente con ese nombre'];
    if (!values.tipo_fuente_id) errors.tipo_fuente_id = ['Selecciona el tipo de fuente de ingreso.'];
    if (!values.clasificacion_tributaria) errors.clasificacion_tributaria = ['Selecciona la clasificación tributaria.'];
    if ((values.pagador_entidad ?? '').length > 80) errors.pagador_entidad = ['El pagador o entidad no puede superar los 80 caracteres.'];
    return errors;
}
export function incomeSummary(values) {
    const gross = values.monto_original || '0';
    const retention = values.retencion_fuente || '0';
    const contributions = values.aportes_descontados || '0';
    return { gross, retention, contributions, net: subtractMoney(subtractMoney(gross, retention), contributions) };
}
export function validateIncome(values, recurrent = false) {
    const errors = {};
    if (!values.fuente_id) errors.fuente_id = ['Selecciona una fuente de ingreso activa.'];
    if (!recurrent) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(values.fecha_recepcion ?? '')) errors.fecha_recepcion = ['Indica la fecha de recepción.'];
        else if (values.fecha_recepcion > todayBogota()) errors.fecha_recepcion = ['La fecha de recepción no puede ser futura. Crea un ingreso recurrente o programado.'];
    }
    if (!validateAmount(values.monto_original)) errors.monto_original = ['El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99.'];
    for (const name of ['retencion_fuente', 'aportes_descontados']) {
        if (!values[name]) continue;
        try { const value = decimal(values[name]); if (value.lt('0') || value.gt(MAX_AMOUNT) || value.decimalPlaces() > 2) throw new Error(); }
        catch { errors[name] = ['El valor debe ser mayor o igual a $ 0 y tener máximo dos decimales.']; }
    }
    if (!errors.monto_original && !errors.retencion_fuente && !errors.aportes_descontados) {
        const { gross, retention, net } = incomeSummary(values);
        if (decimal(retention).gt(decimal(gross))) errors.retencion_fuente = ['La retención no puede superar el monto bruto'];
        else if (decimal(net).lt('0')) errors.aportes_descontados = ['La suma de la retención y los aportes no puede superar el monto bruto'];
    }
    if ((values.descripcion ?? '').length > 255) errors.descripcion = ['La descripción no puede superar los 255 caracteres.'];
    return errors;
}
export function validateRecurrence(values, limit = 'none') {
    const errors = {};
    if (!values.periodicidad) errors.periodicidad = ['Selecciona la periodicidad.'];
    if (!values.fecha_inicio) errors.fecha_inicio = ['Indica la fecha de inicio.'];
    if (limit === 'date' && !values.fecha_fin) errors.fecha_fin = ['Indica la fecha de fin.'];
    if (limit === 'count' && !values.max_ocurrencias) errors.max_ocurrencias = ['Indica el número de ocurrencias, entre 1 y 360.'];
    if (values.fecha_fin && values.max_ocurrencias) errors.fecha_fin = ['Elige fecha de fin o número de ocurrencias, no ambos.'];
    if (values.fecha_fin && values.fecha_fin < values.fecha_inicio) errors.fecha_fin = ['La fecha de fin no puede ser anterior a la fecha de inicio.'];
    if (values.max_ocurrencias && (!/^\d+$/.test(values.max_ocurrencias) || +values.max_ocurrencias < 1 || +values.max_ocurrencias > 360)) errors.max_ocurrencias = ['El número de ocurrencias debe estar entre 1 y 360.'];
    if (!values.modo_confirmacion) errors.modo_confirmacion = ['Selecciona el modo de confirmación.'];
    return errors;
}

export function validateIncomeFilters(values) {
    const errors = {};
    if (values.fecha_desde && values.fecha_hasta && values.fecha_desde > values.fecha_hasta) errors.fecha_hasta = ['La fecha final no puede ser anterior a la inicial.'];
    try {
        if (values.monto_min && decimal(values.monto_min).lt('0')) errors.monto_min = ['El monto mínimo no puede ser negativo.'];
        if (values.monto_max && decimal(values.monto_max).lt('0')) errors.monto_max = ['El monto máximo no puede ser negativo.'];
        if (values.monto_min && values.monto_max && decimal(values.monto_min).gt(decimal(values.monto_max))) errors.monto_max = ['El monto máximo no puede ser menor que el mínimo.'];
    } catch { errors.monto_min = ['Revisa el formato de los montos.']; }
    return errors;
}
